<?php
/**
 * Core plugin loader.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Core {

    protected static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct() {
        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );

        // Register monthly cron schedule (not built into WordPress).
        add_filter( 'cron_schedules', array( __CLASS__, 'register_cron_schedules' ) );

        // AJAX consent save (public + private).
        add_action( 'wp_ajax_wpog_save_consent', array( 'WPOG_Consent', 'ajax_save' ) );
        add_action( 'wp_ajax_nopriv_wpog_save_consent', array( 'WPOG_Consent', 'ajax_save' ) );

        // REST endpoints (tracking, future).
        WPOG_REST::init();

        // Legal Document Prompt Generator REST endpoints.
        WPOG_Legal_REST::init();

        // Form privacy consent — registered on every request: rendering happens
        // on the front-end while validation/logging happen during the CF7 AJAX
        // submission (which runs in an admin context).
        WPOG_Form_Consent::init();

        // Terms & Conditions module (e-commerce) — registered on every request:
        // rendering happens on the front-end checkout while validation/logging
        // and the order-list column run in the admin context.
        WPOG_Terms_Consent::init();

        // Frontend.
        if ( ! is_admin() ) {
            WPOG_Script_Blocker::init();
            WPOG_Domain_Blocker::init();
            WPOG_Public::init();
        }

        // Admin.
        if ( is_admin() && class_exists( 'WPOG_Admin' ) ) {
            WPOG_Admin::init();
        }

        // Daily cleanup of old logs.
        add_action( 'wpog_daily_event', array( 'WPOG_Logger', 'purge_old' ) );
        add_action( 'wpog_daily_event', array( 'WPOG_Form_Consent_Logger', 'purge_old' ) );
        add_action( 'wpog_daily_event', array( 'WPOG_Terms_Consent_Logger', 'purge_old' ) );

        // Monthly cookie database refresh.
        add_action( 'wpog_monthly_cookie_db_refresh', array( 'WPOG_Cookie_Database', 'cron_refresh' ) );
    }

    /**
     * Register custom cron schedules used by the plugin.
     */
    public static function register_cron_schedules( $schedules ) {
        $schedules['monthly'] = array(
            'interval' => MONTH_IN_SECONDS,
            'display'  => __( 'Once Monthly', 'wp-opengdpr' ),
        );
        return $schedules;
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'wp-opengdpr', false, dirname( WPOG_PLUGIN_BASENAME ) . '/languages' );
    }

    public static function activate() {
        WPOG_Logger::install_table();
        WPOG_Tracking::install_table();
        WPOG_Form_Consent_Logger::install_table();
        WPOG_Terms_Consent_Logger::install_table();
        WPOG_Legal_Generator::install_table();
        WPOG_Cookie_Database::install_table();
        update_option( 'wpog_db_version', WPOG_Settings::DB_VERSION );

        // Register the My Account privacy endpoint, then flush rewrite rules so
        // the /my-account/privacy-consent/ URL works without a manual permalink save.
        if ( class_exists( 'WPOG_Terms_My_Account' ) ) {
            WPOG_Terms_My_Account::add_endpoint();
        }
        flush_rewrite_rules();

        if ( ! wp_next_scheduled( 'wpog_daily_event' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wpog_daily_event' );
        }
        WPOG_Cookie_Database::schedule_cron();
    }

    /**
     * Run when the loaded plugin version is newer than the recorded db
     * version. This guarantees that sites upgrading from an older release
     * (where, e.g., the detections table did not exist) get their schema
     * created without having to deactivate / reactivate the plugin.
     */
    public static function maybe_upgrade() {
        $current = get_option( 'wpog_db_version' );
        if ( $current === WPOG_Settings::DB_VERSION ) {
            return;
        }
        WPOG_Logger::install_table();
        WPOG_Tracking::install_table();
        WPOG_Form_Consent_Logger::install_table();
        WPOG_Terms_Consent_Logger::install_table();
        WPOG_Legal_Generator::install_table();
        WPOG_Cookie_Database::install_table();

        // 1.5.1: migrate module text strings into the centralized Translations system.
        if ( version_compare( (string) $current, '1.5.1', '<' ) ) {
            self::migrate_module_texts_to_translations();
        }

        update_option( 'wpog_db_version', WPOG_Settings::DB_VERSION );

        // 1.5.0: flush rewrite rules so the My Account privacy endpoint
        // (/my-account/privacy-consent/) resolves without needing a manual
        // Settings → Permalinks save. Scheduled late on init so add_endpoint()
        // has already registered the endpoint before flush_rewrite_rules() runs.
        add_action( 'init', 'flush_rewrite_rules', 9999 );
    }

    /**
     * Migrate user-facing checkbox labels and error messages from the old
     * module-specific settings (wpog_form_consent, wpog_terms_consent) into
     * the centralized wpog_translations option. Only copies values that differ
     * from the English default — admin-customized texts are preserved.
     */
    private static function migrate_module_texts_to_translations() {
        $defaults     = WPOG_Settings::default_strings();
        $translations = get_option( 'wpog_translations', array() );
        if ( ! is_array( $translations ) ) {
            $translations = array();
        }

        $html_fields = array( 'form_privacy_checkbox_label', 'terms_terms_checkbox_label', 'terms_privacy_checkbox_label' );

        // Form Privacy Consent module.
        $default_fc = WPOG_Settings::defaults( 'form_consent' );
        $fc         = get_option( 'wpog_form_consent', array() );
        if ( is_array( $fc ) ) {
            $map = array(
                'checkbox_main_label'      => 'form_privacy_checkbox_label',
                'checkbox_main_error'      => 'form_privacy_error',
                'checkbox_marketing_label' => 'form_marketing_checkbox_label',
            );
            foreach ( $map as $old_key => $new_key ) {
                $val = isset( $fc[ $old_key ] ) ? (string) $fc[ $old_key ] : '';
                $def = isset( $default_fc[ $old_key ] ) ? (string) $default_fc[ $old_key ] : '';
                // Only migrate if the value was customized (differs from default)
                // and the Translations slot is still empty.
                if ( '' !== $val && $val !== $def && empty( $translations[ $new_key ] ) ) {
                    $translations[ $new_key ] = in_array( $new_key, $html_fields, true )
                        ? wp_kses_post( $val )
                        : sanitize_textarea_field( $val );
                }
            }
        }

        // Terms & Conditions module.
        $default_tc = WPOG_Settings::defaults( 'terms_consent' );
        $tc         = get_option( 'wpog_terms_consent', array() );
        if ( is_array( $tc ) ) {
            $map = array(
                'terms_checkbox_label'     => 'terms_terms_checkbox_label',
                'privacy_checkbox_label'   => 'terms_privacy_checkbox_label',
                'marketing_checkbox_label' => 'terms_marketing_checkbox_label',
                'age_checkbox_label'       => 'terms_age_checkbox_label',
                'terms_error_message'      => 'terms_terms_error_message',
                'privacy_error_message'    => 'terms_privacy_error_message',
                'age_error_message'        => 'terms_age_error_message',
            );
            foreach ( $map as $old_key => $new_key ) {
                $val = isset( $tc[ $old_key ] ) ? (string) $tc[ $old_key ] : '';
                $def = isset( $default_tc[ $old_key ] ) ? (string) $default_tc[ $old_key ] : '';
                if ( '' !== $val && $val !== $def && empty( $translations[ $new_key ] ) ) {
                    $translations[ $new_key ] = in_array( $new_key, $html_fields, true )
                        ? wp_kses_post( $val )
                        : sanitize_textarea_field( $val );
                }
            }
        }

        if ( ! empty( $translations ) ) {
            update_option( 'wpog_translations', $translations );
        }
    }

    public static function deactivate() {
        $ts = wp_next_scheduled( 'wpog_daily_event' );
        if ( $ts ) {
            wp_unschedule_event( $ts, 'wpog_daily_event' );
        }
        WPOG_Cookie_Database::clear_cron();
        // Drop the rewrite endpoint registered for the My Account privacy tab.
        flush_rewrite_rules();
    }
}
