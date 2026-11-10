<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
delete_option( 'wem_ic_settings' );
// Individual visitor carts expire automatically through WordPress Transients.
