<?php
/**
 * Plugin Name: WEM IC Example Form (Testing Only)
 * Description: Standalone test form demonstrating WEM Inquiry Cart server-side integration. Disabled email by default.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: wem-ic-example-form
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** TEST ONLY: Add define( 'WEM_IC_EXAMPLE_ALLOW_EMAIL', true ); to wp-config.php to enable real test emails. */
function wem_ic_example_mail_enabled() {
    return defined( 'WEM_IC_EXAMPLE_ALLOW_EMAIL' ) && true === WEM_IC_EXAMPLE_ALLOW_EMAIL;
}

add_action( 'wp_enqueue_scripts', 'wem_ic_example_styles' );
function wem_ic_example_styles() {
    wp_enqueue_style( 'wem-ic-example-form', plugin_dir_url( __FILE__ ) . 'assets/form.css', array(), '0.1.0' );
}

add_shortcode( 'wem_ic_example_form', 'wem_ic_example_form_shortcode' );
function wem_ic_example_form_shortcode() {
    if ( ! function_exists( 'wem_ic_get_cart_items' ) || ! function_exists( 'wem_ic_clear_cart' ) ) {
        return '<div class="wem-ic-example" role="status">请先启用 WEM Inquiry Cart 插件。</div>';
    }

    $items = wem_ic_get_cart_items();
    $status = isset( $_GET['wem_ic_example_status'] ) && is_scalar( $_GET['wem_ic_example_status'] )
        ? sanitize_key( wp_unslash( $_GET['wem_ic_example_status'] ) ) : '';
    $messages = array(
        'dry_run'      => '模拟测试通过：表单有效、已读取购物车；没有发送邮件，也没有清空购物车。',
        'sent'         => '测试邮件已由 WordPress 提交到发送服务；购物车已清空。请检查管理员邮箱是否实际收到。',
        'invalid'      => '请检查姓名、邮箱、留言及必填字段。',
        'empty'        => '购物车为空，请先添加产品。',
        'security'     => '表单安全验证失败，请刷新页面后重试。',
        'too_fast'     => '提交过于频繁，请稍后再试。',
        'mail_failed'  => '测试邮件未成功提交到发送服务，购物车已保留。请检查 WordPress 邮件配置。',
        'blocked'      => '表单未通过垃圾信息检查，请重试。',
    );

    ob_start(); ?>
    <section class="wem-ic-example" aria-label="购物车表单集成测试">
        <h2>Shopping Cart Form Integration Test</h2>
        <p class="wem-ic-example__note">独立的开发测试示例，不属于正式插件的联系表单。当前模式：<strong><?php echo wem_ic_example_mail_enabled() ? '真实测试邮件（发送到本站管理员邮箱）' : '安全模拟（默认不发送）'; ?></strong></p>
        <?php if ( isset( $messages[ $status ] ) ) : ?>
            <div role="status" class="wem-ic-example__notice <?php echo in_array( $status, array( 'sent', 'dry_run' ), true ) ? 'is-success' : 'is-error'; ?>"><?php echo esc_html( $messages[ $status ] ); ?></div>
        <?php endif; ?>
        <h3>Selected Products</h3>
        <?php if ( empty( $items ) ) : ?>
            <p>购物车为空，请在产品页面使用 <code>[add_to_cart]</code> 添加商品。</p>
        <?php else : ?>
            <ul class="wem-ic-example__items">
                <?php foreach ( $items as $item ) : ?>
                    <li><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a><span>× <?php echo esc_html( (string) $item['quantity'] ); ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <h3>Test Contact Information</h3>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wem-ic-example__form">
            <input type="hidden" name="action" value="wem_ic_example_submit">
            <input type="hidden" name="return_to" value="<?php echo esc_url( get_permalink( get_queried_object_id() ) ?: home_url( '/' ) ); ?>">
            <?php wp_nonce_field( 'wem_ic_example_submit', 'wem_ic_example_nonce' ); ?>
            <div class="wem-ic-example__honeypot" aria-hidden="true"><label for="wem-ic-example-website">Website</label><input type="text" id="wem-ic-example-website" name="website" tabindex="-1" autocomplete="off"></div>
            <div class="wem-ic-example__fields">
                <label>Name *<input type="text" name="contact_name" maxlength="100" required autocomplete="name"></label>
                <label>Email *<input type="email" name="contact_email" maxlength="150" required autocomplete="email"></label>
            </div>
            <label>Message *<textarea name="contact_message" rows="4" maxlength="2000" required></textarea></label>
            <button type="submit" <?php disabled( empty( $items ) ); ?>><?php echo wem_ic_example_mail_enabled() ? 'Send Test Email' : 'Run Dry Test'; ?></button>
        </form>
        <p class="wem-ic-example__note">模拟模式：只验证，不存储个人信息、不发送邮件、不清空购物车。邮件模式：仅发往 WordPress「设置 → 常规」中的管理员邮箱，发送成功后才清空。</p>
    </section>
    <?php return ob_get_clean();
}

add_action( 'admin_post_wem_ic_example_submit', 'wem_ic_example_submit' );
add_action( 'admin_post_nopriv_wem_ic_example_submit', 'wem_ic_example_submit' );
function wem_ic_example_submit() {
    nocache_headers();
    // Redirect only within this WordPress site. Never use arbitrary external destinations.
    $return = isset( $_POST['return_to'] ) && is_scalar( $_POST['return_to'] )
        ? esc_url_raw( wp_unslash( $_POST['return_to'] ) ) : home_url( '/' );
    $return = wp_validate_redirect( $return, home_url( '/' ) );
    $return = remove_query_arg( 'wem_ic_example_status', $return );
    $redirect = static function ( $status ) use ( $return ) {
        wp_safe_redirect( add_query_arg( 'wem_ic_example_status', $status, $return ), 303 );
        exit;
    };

    if ( ! function_exists( 'wem_ic_get_cart_items' ) || ! function_exists( 'wem_ic_clear_cart' ) ) {
        $redirect( 'security' );
    }
    $nonce = isset( $_POST['wem_ic_example_nonce'] ) && is_scalar( $_POST['wem_ic_example_nonce'] )
        ? sanitize_text_field( wp_unslash( $_POST['wem_ic_example_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'wem_ic_example_submit' ) ) {
        $redirect( 'security' );
    }
    $honeypot = isset( $_POST['website'] ) && is_scalar( $_POST['website'] ) ? trim( (string) wp_unslash( $_POST['website'] ) ) : '';
    if ( '' !== $honeypot ) { $redirect( 'blocked' ); }

    $name = isset( $_POST['contact_name'] ) && is_scalar( $_POST['contact_name'] )
        ? sanitize_text_field( wp_unslash( $_POST['contact_name'] ) ) : '';
    $email = isset( $_POST['contact_email'] ) && is_scalar( $_POST['contact_email'] )
        ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '';
    $message = isset( $_POST['contact_message'] ) && is_scalar( $_POST['contact_message'] )
        ? sanitize_textarea_field( wp_unslash( $_POST['contact_message'] ) ) : '';
    if ( '' === trim( $name ) || ! is_email( $email ) || '' === trim( $message ) ||
         strlen( $name ) > 300 || strlen( $email ) > 150 || strlen( $message ) > 6000 ) {
        $redirect( 'invalid' );
    }

    // Always read canonical product IDs/quantities from server-side cart, not hidden form fields.
    $items = wem_ic_get_cart_items();
    if ( empty( $items ) ) { $redirect( 'empty' ); }

    if ( ! wem_ic_example_mail_enabled() ) {
        $redirect( 'dry_run' ); // No email, no persistent personal data, cart preserved.
    }

    // Limit actual outgoing test mail (not just successful sends) by a salted hash of IP+UA.
    // 60-second minimum gap per identity, plus a 10-minute cap of 5 requests.
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
    $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
    $identity = hash_hmac( 'sha256', $ip . '|' . $ua, wp_salt( 'nonce' ) );
    $cooldown_key = 'wem_ic_ex_c_' . substr( $identity, 0, 40 );
    $budget_key = 'wem_ic_ex_b_' . substr( $identity, 0, 40 );
    if ( get_transient( $cooldown_key ) || (int) get_transient( $budget_key ) >= 5 ) {
        $redirect( 'too_fast' );
    }
    set_transient( $cooldown_key, 1, 60 );
    $attempts = (int) get_transient( $budget_key );
    set_transient( $budget_key, $attempts + 1, 600 );

    $recipient = get_option( 'admin_email' );
    if ( ! is_email( $recipient ) ) { $redirect( 'mail_failed' ); }
    $lines = array( 'Test Inquiry (WEM Inquiry Cart)', '', 'Contact name: ' . $name,
        'Contact email: ' . $email, 'Message:', $message, '', 'Selected products:' );
    foreach ( $items as $item ) {
        $lines[] = sprintf( '- [%d] %s | Qty: %d | %s',
            absint( $item['product_id'] ), wp_strip_all_tags( $item['name'] ),
            absint( $item['quantity'] ), esc_url_raw( $item['url'] ) );
    }
    $sent = wp_mail( $recipient, '[TEST] WEM Inquiry Cart Form', implode( "\n", $lines ),
        array( 'Content-Type: text/plain; charset=UTF-8' ) );
    if ( ! $sent ) { $redirect( 'mail_failed' ); }
    wem_ic_clear_cart();
    $redirect( 'sent' );
}
