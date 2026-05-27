<?php
/**
 * Plugin Name: WP OpenGDPR
 * Plugin URI: https://example.com/wp-opengdpr
 * Description: GDPR / ePrivacy compliant cookie consent manager with banner, granular categories, script blocker and consent logging.
 * Version: 1.0.0
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * Author: WP OpenGDPR
 * License: GPLv2 or later
 * Text Domain: wp-opengdpr
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPOG_VERSION', '1.0.0' );
define( 'WPOG_PLUGIN_FILE', __FILE__ );
define( 'WPOG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPOG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPOG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WPOG_PLUGIN_DIR . 'includes/class-wpog-settings.php';
require_once WPOG_PLUGIN_DIR . 'includes/class-wpog-logger.php';
require_once WPOG_PLUGIN_DIR . 'includes/class-wpog-consent.php';
require_once WPOG_PLUGIN_DIR . 'includes/class-wpog-script-blocker.php';
require_once WPOG_PLUGIN_DIR . 'includes/class-wpog-core.php';
require_once WPOG_PLUGIN_DIR . 'public/class-wpog-public.php';
if ( is_admin() ) {
    require_once WPOG_PLUGIN_DIR . 'admin/class-wpog-admin.php';
}

register_activation_hook( __FILE__, array( 'WPOG_Core', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPOG_Core', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WPOG_Core', 'instance' ) );
