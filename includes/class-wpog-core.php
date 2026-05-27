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

        // AJAX consent save (public + private).
        add_action( 'wp_ajax_wpog_save_consent', array( 'WPOG_Consent', 'ajax_save' ) );
        add_action( 'wp_ajax_nopriv_wpog_save_consent', array( 'WPOG_Consent', 'ajax_save' ) );

        // Frontend.
        if ( ! is_admin() ) {
            WPOG_Script_Blocker::init();
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
        if ( ! wp_next_scheduled( 'wpog_daily_event' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wpog_daily_event' );
        }
    }

    public static function deactivate() {
        $ts = wp_next_scheduled( 'wpog_daily_event' );
        if ( $ts ) {
            wp_unschedule_event( $ts, 'wpog_daily_event' );
        }
    }
}
