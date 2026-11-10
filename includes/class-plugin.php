<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WEM_IC_Plugin {
    const OPTION = 'wem_ic_settings';
    private static $shortcode_mini_rendered = false;

    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'wp_footer', array( __CLASS__, 'auto_floating' ), 30 );
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );

        add_shortcode( 'add_to_cart', array( __CLASS__, 'add_button' ) );
        add_shortcode( 'cart', array( __CLASS__, 'cart' ) );
        add_shortcode( 'mini_cart', array( __CLASS__, 'mini_cart' ) );
        add_shortcode( 'cart_summary', array( __CLASS__, 'cart_summary' ) );

        foreach ( array( 'state', 'add', 'update', 'remove', 'clear' ) as $action ) {
            add_action( 'wp_ajax_wem_ic_' . $action, array( __CLASS__, 'ajax_' . $action ) );
            add_action( 'wp_ajax_nopriv_wem_ic_' . $action, array( __CLASS__, 'ajax_' . $action ) );
        }
    }

    public static function defaults() {
        return array(
            'post_type' => 'products',
            'image_meta' => 'product_pic01',
            'cart_page' => 0,
            'auto_float' => 0,
        );
    }

    public static function option( $key ) {
        $options = wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
        return $options[ $key ] ?? null;
    }

    public static function assets() {
        // The complete cart interface may appear in header, templates, or a shortcode.
        wp_enqueue_style( 'wem-ic-cart', WEM_IC_URL . 'assets/css/cart.css', array(), WEM_IC_VERSION );
        wp_enqueue_script( 'wem-ic-cart', WEM_IC_URL . 'assets/js/cart.js', array(), WEM_IC_VERSION, true );
        wp_localize_script( 'wem-ic-cart', 'wemIC', array(
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'wem_ic_ajax' ),
            'iconAdd'    => WEM_IC_URL . 'assets/icons/cart-add.svg',
            'iconAdded'  => WEM_IC_URL . 'assets/icons/cart-added.svg',
            'messages'   => array(
                'error'   => __( 'Request failed. Please try again.', 'wem-inquiry-cart' ),
                'added'   => __( 'Added to Cart', 'wem-inquiry-cart' ),
                'invalid' => __( 'Please enter a quantity between 1 and 99.', 'wem-inquiry-cart' ),
                'updated' => __( 'Cart quantity updated successfully.', 'wem-inquiry-cart' ),
                'removed' => __( 'Product removed from your cart.', 'wem-inquiry-cart' ),
            ),
        ) );
    }

    public static function add_button( $atts ) {
        $atts = shortcode_atts( array( 'product_id' => 0, 'quantity' => 1 ), $atts, 'add_to_cart' );
        $id = absint( $atts['product_id'] );
        if ( ! $id ) { $id = get_the_ID(); }
        if ( ! $id || ! WEM_IC_Cart_Store::allowed_post( $id ) ) { return ''; }
        $qty = max( 1, min( 99, absint( $atts['quantity'] ) ) );
        ob_start(); ?>
        <div class="wic-cart-ui wic-add-wrap" data-wic-add-wrap>
            <button class="wic-add-button" type="button" data-wic-add="<?php echo esc_attr( $id ); ?>" data-wic-quantity="<?php echo esc_attr( $qty ); ?>">
                <span class="wic-add-button__icon" aria-hidden="true"><img src="<?php echo esc_url( WEM_IC_URL . 'assets/icons/cart-add.svg' ); ?>" width="17" height="17" alt=""></span>
                <span class="wic-add-button__text"><?php esc_html_e( 'Add to Cart', 'wem-inquiry-cart' ); ?></span>
            </button>
            <span class="wic-add-status" role="status" aria-live="polite"></span>
        </div>
        <?php return ob_get_clean();
    }

    public static function cart() {
        return '<div class="wic-cart-ui wic-cart-page" id="wic-cart-section"><h2 class="wic-cart-page__heading">' . esc_html__( 'Your Cart', 'wem-inquiry-cart' ) . '</h2><p class="wic-cart__notice" data-wic-notice role="status" aria-live="polite" aria-atomic="true"></p><div class="wic-cart" data-wic-cart><div class="wic-cart__items" data-wic-items><p class="wic-cart__loading">' . esc_html__( 'Loading cart…', 'wem-inquiry-cart' ) . '</p></div></div></div>';
    }

    public static function cart_summary() {
        // Render a human-friendly summary. Use wem_ic_get_cart_items() in form handlers instead.
        $items = WEM_IC_Cart_Store::items();
        if ( ! $items ) { return '<div class="wic-cart-ui"><p>' . esc_html__( 'Your cart is empty.', 'wem-inquiry-cart' ) . '</p></div>'; }
        $out = '<div class="wic-cart-ui"><ul class="wic-cart-summary">';
        foreach ( $items as $item ) {
            $out .= '<li><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a> &times; ' . esc_html( (string) $item['quantity'] ) . '</li>';
        }
        return $out . '</ul></div>';
    }

    private static function cart_url() {
        $id = absint( self::option( 'cart_page' ) );
        return $id && get_post_status( $id ) === 'publish' ? get_permalink( $id ) : '#wic-cart-section';
    }

    private static function floating_markup() {
        return '<div class="wic-cart-ui wic-floating-wrap"><div class="wic-floating-cart"><a href="' . esc_url( self::cart_url() ) . '" class="wic-floating-cart__button" aria-label="' . esc_attr__( 'View cart', 'wem-inquiry-cart' ) . '"><img class="wic-floating-cart__icon" src="' . esc_url( WEM_IC_URL . 'assets/icons/cart-floating.svg' ) . '" alt="" width="30" height="30"><span class="wic-floating-cart__count" data-wic-count hidden>0</span></a></div></div>';
    }

    public static function mini_cart() {
        self::$shortcode_mini_rendered = true;
        return self::floating_markup();
    }

    public static function auto_floating() {
        if ( ! self::option( 'auto_float' ) || self::$shortcode_mini_rendered ) { return; }
        echo self::floating_markup(); // Escaped in floating_markup().
    }

    private static function verify() {
        nocache_headers();
        if ( ! check_ajax_referer( 'wem_ic_ajax', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check expired. Refresh the page and retry.', 'wem-inquiry-cart' ) ), 403 );
        }
    }

    private static function product_id() {
        return isset( $_POST['product_id'] ) && is_scalar( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
    }

    private static function qty() {
        if ( ! isset( $_POST['quantity'] ) || ! is_scalar( $_POST['quantity'] ) ) { return 0; }
        $raw = wp_unslash( $_POST['quantity'] );
        if ( ! preg_match( '/^[1-9][0-9]{0,1}$/D', (string) $raw ) ) { return 0; }
        return (int) $raw;
    }

    private static function response() {
        wp_send_json_success( array(
            'count' => WEM_IC_Cart_Store::count(),
            'html'  => self::items_markup(),
        ) );
    }

    private static function mutation( $method ) {
        self::verify();
        $id = self::product_id();
        if ( ! $id ) { wp_send_json_error( array( 'message' => __( 'Invalid product ID.', 'wem-inquiry-cart' ) ), 400 ); }
        if ( 'remove' === $method ) {
            WEM_IC_Cart_Store::remove( $id );
        } else {
            $qty = self::qty();
            $result = 'add' === $method ? WEM_IC_Cart_Store::add( $id, $qty ) : WEM_IC_Cart_Store::update( $id, $qty );
            if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 ); }
        }
        self::response();
    }

    public static function ajax_add() { self::mutation( 'add' ); }
    public static function ajax_update() { self::mutation( 'update' ); }
    public static function ajax_remove() { self::mutation( 'remove' ); }
    public static function ajax_state() { self::verify(); self::response(); }
    public static function ajax_clear() { self::verify(); WEM_IC_Cart_Store::clear(); self::response(); }

    private static function items_markup() {
        $items = WEM_IC_Cart_Store::items();
        if ( ! $items ) {
            return '<div class="wic-cart__empty"><strong>' . esc_html__( 'Your cart is empty.', 'wem-inquiry-cart' ) . '</strong><p>' . esc_html__( 'Choose a product to add to your cart.', 'wem-inquiry-cart' ) . '</p></div>';
        }
        $html = '';
        foreach ( $items as $item ) {
            $id = $item['product_id'];
            $html .= '<div class="wic-cart__row" data-wic-row="' . esc_attr( $id ) . '">';
            if ( $item['image'] ) {
                $html .= '<a class="wic-cart__thumb-link" href="' . esc_url( $item['url'] ) . '"><img class="wic-cart__thumb" src="' . esc_url( $item['image'] ) . '" alt="" loading="lazy"></a>';
            } else {
                $html .= '<a class="wic-cart__thumb-link wic-cart__thumb--placeholder" href="' . esc_url( $item['url'] ) . '"><span aria-hidden="true">▦</span></a>';
            }
            $html .= '<div class="wic-cart__title"><a class="wic-cart__title-link" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a></div>';
            $html .= '<div class="wic-cart__qty"><label for="wic-qty-' . esc_attr( $id ) . '">' . esc_html__( 'Quantity', 'wem-inquiry-cart' ) . '</label><input id="wic-qty-' . esc_attr( $id ) . '" type="number" min="1" max="99" step="1" value="' . esc_attr( $item['quantity'] ) . '" data-wic-qty></div>';
            $html .= '<div class="wic-cart__actions"><button type="button" data-wic-update="' . esc_attr( $id ) . '">' . esc_html__( 'Update', 'wem-inquiry-cart' ) . '</button><button type="button" data-wic-remove="' . esc_attr( $id ) . '">' . esc_html__( 'Delete', 'wem-inquiry-cart' ) . '</button></div></div>';
        }
        return $html;
    }

    public static function admin_menu() {
        add_options_page( __( 'Inquiry Cart', 'wem-inquiry-cart' ), __( 'Inquiry Cart', 'wem-inquiry-cart' ), 'manage_options', 'wem-inquiry-cart', array( __CLASS__, 'settings_page' ) );
    }

    public static function admin_init() {
        register_setting( 'wem_ic_group', self::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_options' ), 'default' => self::defaults() ) );
    }

    public static function sanitize_options( $raw ) {
        $raw = is_array( $raw ) ? $raw : array();
        $types = get_post_types( array( 'public' => true ), 'names' );
        $type = isset( $raw['post_type'] ) ? sanitize_key( $raw['post_type'] ) : 'products';
        $meta = isset( $raw['image_meta'] ) ? sanitize_key( $raw['image_meta'] ) : 'product_pic01';
        return array(
            'post_type' => in_array( $type, $types, true ) ? $type : 'products',
            'image_meta' => $meta ?: 'product_pic01',
            'cart_page' => isset( $raw['cart_page'] ) ? absint( $raw['cart_page'] ) : 0,
            'auto_float' => ! empty( $raw['auto_float'] ) ? 1 : 0,
        );
    }

    public static function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $types = get_post_types( array( 'public' => true ), 'objects' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'WEM Inquiry Cart（产品询盘购物车）', 'wem-inquiry-cart' ); ?></h1>
            <p><?php esc_html_e( '本插件只负责将产品加入购物车、修改数量和显示产品清单。客户联系表单及邮件发送仍由原网站负责。', 'wem-inquiry-cart' ); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields( 'wem_ic_group' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wem-ic-type"><?php esc_html_e( '产品文章类型', 'wem-inquiry-cart' ); ?></label></th>
                        <td>
                            <select id="wem-ic-type" name="<?php echo esc_attr( self::OPTION ); ?>[post_type]">
                                <?php foreach ( $types as $type ) : ?>
                                    <option value="<?php echo esc_attr( $type->name ); ?>" <?php selected( self::option( 'post_type' ), $type->name ); ?>><?php echo esc_html( $type->label . ' (' . $type->name . ')' ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( '选择网站中用于发布产品的文章类型。例如 Products (products)。只有此类型的已发布产品才能加入购物车。', 'wem-inquiry-cart' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wem-ic-img"><?php esc_html_e( '产品图片字段', 'wem-inquiry-cart' ); ?></label></th>
                        <td>
                            <input id="wem-ic-img" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[image_meta]" value="<?php echo esc_attr( self::option( 'image_meta' ) ); ?>">
                            <p class="description"><?php esc_html_e( '用于显示购物车中的产品缩略图。默认 product_pic01；如果该字段没有图片，插件会尝试使用产品特色图片。不确定时可保持默认。', 'wem-inquiry-cart' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wem-ic-cart-page"><?php esc_html_e( '购物车页面', 'wem-inquiry-cart' ); ?></label></th>
                        <td>
                            <?php wp_dropdown_pages( array( 'id' => 'wem-ic-cart-page', 'name' => self::OPTION . '[cart_page]', 'selected' => self::option( 'cart_page' ), 'show_option_none' => __( '请选择包含 [cart] 的页面', 'wem-inquiry-cart' ), 'option_none_value' => 0 ) ); ?>
                            <p class="description"><?php esc_html_e( '先创建一个页面，在页面内容中加入 [cart]，再在这里选择它。点击悬浮购物车时会跳转到该页面；不选择时，悬浮按钮只会尝试定位当前页面的购物车区域。', 'wem-inquiry-cart' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( '悬浮购物车按钮', 'wem-inquiry-cart' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[auto_float]" value="1" <?php checked( self::option( 'auto_float' ), 1 ); ?>> <?php esc_html_e( '自动在网站前台页面显示悬浮购物车图标', 'wem-inquiry-cart' ); ?></label>
                            <p class="description"><?php esc_html_e( '勾选：插件自动显示右侧悬浮购物车。未勾选：可在指定位置使用 [mini_cart] 手动显示。通常选择一种方式即可，避免重复。', 'wem-inquiry-cart' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( '保存设置', 'wem-inquiry-cart' ) ); ?>
            </form>
            <h2><?php esc_html_e( '常用短代码', 'wem-inquiry-cart' ); ?></h2>
            <p class="description"><?php esc_html_e( '将对应短代码复制到 WordPress 页面或模板中使用：', 'wem-inquiry-cart' ); ?></p>
            <table class="widefat striped" style="max-width:780px;">
                <tbody>
                    <tr><td><code>[add_to_cart]</code></td><td><?php esc_html_e( '显示“加入购物车”按钮。放在产品页面或产品列表循环中使用；也可以指定产品 ID。', 'wem-inquiry-cart' ); ?></td></tr>
                    <tr><td><code>[cart]</code></td><td><?php esc_html_e( '显示购物车产品清单，可修改数量和删除商品。', 'wem-inquiry-cart' ); ?></td></tr>
                    <tr><td><code>[mini_cart]</code></td><td><?php esc_html_e( '手动显示右侧悬浮购物车；如果已经启用上面的自动显示，通常不需要再添加。', 'wem-inquiry-cart' ); ?></td></tr>
                    <tr><td><code>[cart_summary]</code></td><td><?php esc_html_e( '显示简易产品清单，仅供页面展示；表单提交请通过服务器端接口获取购物车数据。', 'wem-inquiry-cart' ); ?></td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }
}
