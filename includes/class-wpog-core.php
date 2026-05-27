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

        // AJAX consent save (public + private).
        add_action( 'wp_ajax_wpog_save_consent', array( 'WPOG_Consent', 'ajax_save' ) );
        add_action( 'wp_ajax_nopriv_wpog_save_consent', array( 'WPOG_Consent', 'ajax_save' ) );

        // REST endpoints (tracking, future).
        WPOG_REST::init();

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
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'wp-opengdpr', false, dirname( WPOG_PLUGIN_BASENAME ) . '/languages' );
    }

    public static function activate() {
        WPOG_Logger::install_table();
        WPOG_Tracking::install_table();
        update_option( 'wpog_db_version', WPOG_Settings::DB_VERSION );
        if ( ! wp_next_scheduled( 'wpog_daily_event' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wpog_daily_event' );
        }
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
        update_option( 'wpog_db_version', WPOG_Settings::DB_VERSION );
    }

    public static function deactivate() {
        $ts = wp_next_scheduled( 'wpog_daily_event' );
        if ( $ts ) {
            wp_unschedule_event( $ts, 'wpog_daily_event' );
        }
    }
}
