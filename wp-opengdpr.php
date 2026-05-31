<?php
/**
 * Plugin Name: WP OpenGDPR
 * Plugin URI: https://example.com/wp-opengdpr
 * Description: GDPR / ePrivacy compliant cookie consent manager with banner, granular categories, script blocker and consent logging.
 * Version: 1.0.3
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * Author: Luca Fontanot
 * License: GPLv2 or later
 * Text Domain: wp-opengdpr
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPOG_VERSION', '1.0.3' );
define( 'WPOG_PLUGIN_FILE', __FILE__ );
define( 'WPOG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPOG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPOG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WPOG_PLUGIN_DIR . 'includes/class-wpog-loader.php';
WPOG_Loader::load();

register_activation_hook( __FILE__, array( 'WPOG_Core', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPOG_Core', 'deactivate' ) );

// Declare WooCommerce HPOS (Custom Order Tables) compatibility. The Terms &
// Conditions module writes order meta exclusively through WC_Order methods.
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            WPOG_PLUGIN_FILE,
            true
        );
    }
} );

add_action( 'plugins_loaded', array( 'WPOG_Core', 'instance' ) );
