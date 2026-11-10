<?php
/**
 * Plugin Name: WEM Inquiry Cart
 * Description: Lightweight B2B product inquiry cart with reusable shortcodes. Does not handle contact forms or emails.
 * Version: 0.1.4
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: WEM
 * Text Domain: wem-inquiry-cart
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WEM_IC_VERSION', '0.1.4' );
define( 'WEM_IC_FILE', __FILE__ );
define( 'WEM_IC_PATH', plugin_dir_path( __FILE__ ) );
define( 'WEM_IC_URL', plugin_dir_url( __FILE__ ) );

require_once WEM_IC_PATH . 'includes/class-cart-store.php';
require_once WEM_IC_PATH . 'includes/class-plugin.php';

WEM_IC_Plugin::init();

/**
 * 在「已安装插件」列表增加“设置”快捷入口，直接进入插件原有设置页。
 * 不影响购物车短代码、AJAX、数据存储或前端样式。
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wem_ic_plugin_settings_link' );
function wem_ic_plugin_settings_link( $links ) {
    if ( current_user_can( 'manage_options' ) ) {
        $url = admin_url( 'options-general.php?page=wem-inquiry-cart' );
        array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( '设置', 'wem-inquiry-cart' ) . '</a>' );
    }
    return $links;
}
