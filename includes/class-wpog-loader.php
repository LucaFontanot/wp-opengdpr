<?php
/**
 * Loader — owns the list of plugin source files and is the single place
 * where new classes get hooked into the require chain. Keeping this here
 * (instead of in wp-opengdpr.php) makes it easy to grow the codebase into
 * sub-folders without touching the main plugin bootstrap.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Loader {

    public static function load() {
        // Foundations.
        require_once WPOG_PLUGIN_DIR . 'includes/class-wpog-settings.php';
        require_once WPOG_PLUGIN_DIR . 'includes/class-wpog-logger.php';
        require_once WPOG_PLUGIN_DIR . 'includes/class-wpog-consent.php';
        require_once WPOG_PLUGIN_DIR . 'includes/class-wpog-cookie-database.php';

        // Custom (admin-defined) script injector — legacy "blocker" name kept
        // for backward compatibility with the public class API.
        require_once WPOG_PLUGIN_DIR . 'includes/class-wpog-script-blocker.php';

        // New modular features.
        require_once WPOG_PLUGIN_DIR . 'includes/blocker/class-wpog-domain-blocker.php';
        require_once WPOG_PLUGIN_DIR . 'includes/tracking/class-wpog-tracking.php';
        require_once WPOG_PLUGIN_DIR . 'includes/rest/class-wpog-rest.php';

        // Legal Document Prompt Generator (admin wizard + REST).
        require_once WPOG_PLUGIN_DIR . 'includes/legal-generator/class-wpog-legal-generator.php';
        require_once WPOG_PLUGIN_DIR . 'includes/rest/class-wpog-legal-rest.php';

        // Form privacy consent module (independent from cookie consent).
        require_once WPOG_PLUGIN_DIR . 'includes/form-consent/class-wpog-form-consent-logger.php';
        require_once WPOG_PLUGIN_DIR . 'includes/form-consent/class-wpog-cf7-integration.php';
        require_once WPOG_PLUGIN_DIR . 'includes/form-consent/class-wpog-wpforms-integration.php';
        require_once WPOG_PLUGIN_DIR . 'includes/form-consent/class-wpog-form-consent.php';

        // Terms & Conditions module (e-commerce / WooCommerce).
        require_once WPOG_PLUGIN_DIR . 'includes/terms-consent/class-wpog-terms-consent-logger.php';
        require_once WPOG_PLUGIN_DIR . 'includes/terms-consent/class-wpog-woocommerce-integration.php';
        require_once WPOG_PLUGIN_DIR . 'includes/terms-consent/class-wpog-woocommerce-blocks-integration.php';
        require_once WPOG_PLUGIN_DIR . 'includes/terms-consent/class-wpog-terms-registration.php';
        require_once WPOG_PLUGIN_DIR . 'includes/terms-consent/class-wpog-terms-my-account.php';
        require_once WPOG_PLUGIN_DIR . 'includes/terms-consent/class-wpog-privacy-api.php';
        require_once WPOG_PLUGIN_DIR . 'includes/terms-consent/class-wpog-terms-consent.php';

        // Cross-domain consent bridge (serve identical consent UI to external sites).
        require_once WPOG_PLUGIN_DIR . 'includes/class-wpog-cross-domain.php';

        // Core wiring.
        require_once WPOG_PLUGIN_DIR . 'includes/class-wpog-core.php';

        // Frontend / Admin entry points.
        require_once WPOG_PLUGIN_DIR . 'public/class-wpog-public.php';
        if ( is_admin() ) {
            require_once WPOG_PLUGIN_DIR . 'admin/class-wpog-admin.php';
        }
    }
}
