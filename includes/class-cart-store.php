<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Stores only product IDs and quantities. Product names/images come from WordPress. */
final class WEM_IC_Cart_Store {
    const COOKIE = 'wem_ic_cart';
    const TTL    = 604800; // 7 days since latest cart mutation.
    const MAX_LINES = 50;
    const MAX_QTY   = 99;

    private static $issued_token = null;

    private static function valid_token( $token ) {
        return is_string( $token ) && (bool) preg_match( '/^[a-f0-9]{64}$/D', $token );
    }

    private static function token( $create = false ) {
        if ( self::$issued_token ) { return self::$issued_token; }
        $token = isset( $_COOKIE[ self::COOKIE ] ) ? wp_unslash( $_COOKIE[ self::COOKIE ] ) : '';
        if ( self::valid_token( $token ) ) { return $token; }
        if ( ! $create ) { return ''; }

        $token = bin2hex( random_bytes( 32 ) );
        self::$issued_token = $token;
        if ( ! headers_sent() ) {
            setcookie( self::COOKIE, $token, array(
                'expires'  => time() + self::TTL,
                'path'     => ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ) );
        }
        return $token;
    }

    private static function key( $token ) {
        return 'wem_ic_' . hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
    }

    public static function get() {
        $token = self::token();
        if ( ! $token ) { return array(); }
        $cart = get_transient( self::key( $token ) );
        return is_array( $cart ) ? $cart : array();
    }

    private static function save( $cart ) {
        $token = self::token( ! empty( $cart ) );
        if ( ! $token ) { return; }
        // Refresh the cookie lifetime whenever the cart changes, matching the transient TTL.
        if ( ! headers_sent() ) {
            setcookie( self::COOKIE, $token, array(
                'expires'  => time() + self::TTL,
                'path'     => ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ) );
        }
        if ( empty( $cart ) ) {
            delete_transient( self::key( $token ) );
        } else {
            set_transient( self::key( $token ), $cart, self::TTL );
        }
    }

    public static function allowed_post( $id ) {
        $post = get_post( $id );
        $type = WEM_IC_Plugin::option( 'post_type' );
        return $post && $post->post_status === 'publish' && $post->post_type === $type;
    }

    public static function add( $id, $qty ) {
        if ( ! self::allowed_post( $id ) ) {
            return new WP_Error( 'invalid_product', __( 'This product is not available.', 'wem-inquiry-cart' ) );
        }
        if ( $qty < 1 || $qty > self::MAX_QTY ) {
            return new WP_Error( 'invalid_quantity', __( 'Quantity must be between 1 and 99.', 'wem-inquiry-cart' ) );
        }
        $cart = self::get();
        if ( ! isset( $cart[ $id ] ) && count( $cart ) >= self::MAX_LINES ) {
            return new WP_Error( 'cart_limit', __( 'The cart has reached its item limit.', 'wem-inquiry-cart' ) );
        }
        $cart[ $id ] = min( self::MAX_QTY, (int) ( $cart[ $id ] ?? 0 ) + $qty );
        self::save( $cart );
        return true;
    }

    public static function update( $id, $qty ) {
        $cart = self::get();
        if ( ! isset( $cart[ $id ] ) ) {
            return new WP_Error( 'missing_product', __( 'This product is not in your cart.', 'wem-inquiry-cart' ) );
        }
        if ( $qty < 1 || $qty > self::MAX_QTY ) {
            return new WP_Error( 'invalid_quantity', __( 'Quantity must be between 1 and 99.', 'wem-inquiry-cart' ) );
        }
        if ( ! self::allowed_post( $id ) ) {
            return new WP_Error( 'invalid_product', __( 'This product is not available.', 'wem-inquiry-cart' ) );
        }
        $cart[ $id ] = $qty;
        self::save( $cart );
        return true;
    }

    public static function remove( $id ) {
        $cart = self::get();
        unset( $cart[ $id ] );
        self::save( $cart );
    }

    public static function clear() {
        self::save( array() );
    }

    public static function items() {
        $items = array();
        foreach ( self::get() as $id => $qty ) {
            $id  = absint( $id );
            $qty = absint( $qty );
            if ( ! $id || ! $qty || ! self::allowed_post( $id ) ) { continue; }
            $image = get_post_meta( $id, WEM_IC_Plugin::option( 'image_meta' ), true );
            $image = is_string( $image ) ? $image : '';
            if ( ! $image ) { $image = get_the_post_thumbnail_url( $id, 'thumbnail' ); }
            $items[] = array(
                'product_id' => $id,
                'name'       => get_the_title( $id ),
                'quantity'   => min( self::MAX_QTY, $qty ),
                'url'        => get_permalink( $id ),
                'image'      => $image ? esc_url_raw( $image ) : '',
            );
        }
        return $items;
    }

    public static function count() {
        return array_sum( array_column( self::items(), 'quantity' ) );
    }
}

/** Public integration API for existing contact-form handlers. */
function wem_ic_get_cart_items() {
    return WEM_IC_Cart_Store::items();
}

/** Call only AFTER the site's own form processing succeeds. */
function wem_ic_clear_cart() {
    WEM_IC_Cart_Store::clear();
}
