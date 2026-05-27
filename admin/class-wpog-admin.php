<?php
/**
 * Admin controller.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Admin {

    const CAP   = 'manage_options';
    const SLUG  = 'wpog';
    const NONCE = 'wpog_admin';

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_post' ) );
        add_action( 'admin_post_wpog_export_logs', array( __CLASS__, 'export_logs' ) );
        add_action( 'admin_post_wpog_export_translations', array( __CLASS__, 'export_translations' ) );
        add_action( 'admin_post_wpog_export_settings', array( __CLASS__, 'export_settings' ) );
    }

    public static function enqueue( $hook ) {
        if ( strpos( (string) $hook, self::SLUG ) === false ) {
            return;
        }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_media();
        wp_enqueue_style( 'wpog-admin', WPOG_PLUGIN_URL . 'admin/assets/admin.css', array(), WPOG_VERSION );
        wp_enqueue_script( 'wpog-admin', WPOG_PLUGIN_URL . 'admin/assets/admin.js', array( 'jquery', 'wp-color-picker' ), WPOG_VERSION, true );
    }

    public static function menu() {
        add_menu_page(
            __( 'Cookie Consent', 'wp-opengdpr' ),
            __( 'Cookie Consent', 'wp-opengdpr' ),
            self::CAP,
            self::SLUG,
            array( __CLASS__, 'route' ),
            'dashicons-shield-alt',
            80
        );
        $pages = array(
            'wpog'              => __( 'General Settings', 'wp-opengdpr' ),
            'wpog-banner'       => __( 'Banner Appearance', 'wp-opengdpr' ),
            'wpog-popup'        => __( 'Popup Appearance', 'wp-opengdpr' ),
            'wpog-categories'   => __( 'Categories & Cookies', 'wp-opengdpr' ),
            'wpog-scripts'      => __( 'Script Manager', 'wp-opengdpr' ),
            'wpog-blocker'      => __( 'Domain Blocker', 'wp-opengdpr' ),
            'wpog-tracking'     => __( 'Detections', 'wp-opengdpr' ),
            'wpog-logs'         => __( 'Consent Logs', 'wp-opengdpr' ),
            'wpog-translations' => __( 'Translations', 'wp-opengdpr' ),
            'wpog-settings-io'  => __( 'Export / Import', 'wp-opengdpr' ),
        );
        foreach ( $pages as $slug => $title ) {
            add_submenu_page( self::SLUG, $title, $title, self::CAP, $slug, array( __CLASS__, 'route' ) );
        }
    }

    public static function route() {
        $screen = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'wpog';
        $map = array(
            'wpog'              => 'page-general.php',
            'wpog-banner'       => 'page-banner.php',
            'wpog-popup'        => 'page-popup.php',
            'wpog-categories'   => 'page-cookies.php',
            'wpog-scripts'      => 'page-scripts.php',
            'wpog-blocker'      => 'page-blocker.php',
            'wpog-tracking'     => 'page-tracking.php',
            'wpog-logs'         => 'page-logs.php',
            'wpog-translations' => 'page-translations.php',
            'wpog-settings-io'  => 'page-settings-io.php',
        );
        $view = $map[ $screen ] ?? 'page-general.php';
        echo '<div class="wrap wpog-wrap">';
        include WPOG_PLUGIN_DIR . 'admin/views/' . $view;
        echo '</div>';
    }

    public static function handle_post() {
        if ( empty( $_POST['wpog_form'] ) ) {
            return;
        }
        if ( ! current_user_can( self::CAP ) ) {
            return;
        }
        check_admin_referer( self::NONCE );

        $form = sanitize_key( $_POST['wpog_form'] );

        switch ( $form ) {
            case 'general':
                $in = wp_unslash( (array) ( $_POST['wpog'] ?? array() ) );
                WPOG_Settings::replace( 'general', array(
                    'enabled'            => ! empty( $in['enabled'] ) ? 1 : 0,
                    'consent_duration'   => max( 1, (int) ( $in['consent_duration'] ?? 180 ) ),
                    'policy_version'     => sanitize_text_field( $in['policy_version'] ?? '1.0' ),
                    'privacy_url'        => esc_url_raw( $in['privacy_url'] ?? '' ),
                    'cookie_url'         => esc_url_raw( $in['cookie_url'] ?? '' ),
                    'eu_only'            => ! empty( $in['eu_only'] ) ? 1 : 0,
                    'log_enabled'        => ! empty( $in['log_enabled'] ) ? 1 : 0,
                    'anonymize_ip'       => ! empty( $in['anonymize_ip'] ) ? 1 : 0,
                    'log_retention_days' => max( 0, (int) ( $in['log_retention_days'] ?? 365 ) ),
                    'fab_enabled'        => ! empty( $in['fab_enabled'] ) ? 1 : 0,
                    'fab_position'       => in_array( $in['fab_position'] ?? 'bottom-right', array( 'bottom-right', 'bottom-left' ), true ) ? $in['fab_position'] : 'bottom-right',
                    'fab_label'          => sanitize_text_field( $in['fab_label'] ?? '🍪' ),
                    'fab_bg_color'       => sanitize_hex_color( $in['fab_bg_color'] ?? '' ) ?: '',
                    'fab_text_color'     => sanitize_hex_color( $in['fab_text_color'] ?? '' ) ?: '',
                    'reload_on_accept'   => ! empty( $in['reload_on_accept'] ) ? 1 : 0,
                    'autoblocker_enabled'=> ! empty( $in['autoblocker_enabled'] ) ? 1 : 0,
                    'tracking_enabled'   => ! empty( $in['tracking_enabled'] ) ? 1 : 0,
                ) );
                break;

            case 'banner':
                $in = wp_unslash( (array) ( $_POST['wpog'] ?? array() ) );
                WPOG_Settings::replace( 'banner', array(
                    'position'      => in_array( $in['position'] ?? 'bottom', array( 'top', 'bottom' ), true ) ? $in['position'] : 'bottom',
                    'bg_color'      => sanitize_hex_color( $in['bg_color'] ?? '#ffffff' ),
                    'text_color'    => sanitize_hex_color( $in['text_color'] ?? '#333333' ),
                    'primary_bg'    => sanitize_hex_color( $in['primary_bg'] ?? '#0073aa' ),
                    'primary_text'  => sanitize_hex_color( $in['primary_text'] ?? '#ffffff' ),
                    'secondary_bg'  => sanitize_hex_color( $in['secondary_bg'] ?? '#f0f0f0' ),
                    'secondary_text'=> sanitize_hex_color( $in['secondary_text'] ?? '#333333' ),
                    'border_radius' => max( 0, (int) ( $in['border_radius'] ?? 4 ) ),
                    'font_size'     => max( 8, (int) ( $in['font_size'] ?? 14 ) ),
                    'animation'     => in_array( $in['animation'] ?? 'slide', array( 'slide', 'fade', 'none' ), true ) ? $in['animation'] : 'slide',
                    'overlay'       => ! empty( $in['overlay'] ) ? 1 : 0,
                    'logo'          => esc_url_raw( $in['logo'] ?? '' ),
                ) );
                break;

            case 'popup':
                $in = wp_unslash( (array) ( $_POST['wpog'] ?? array() ) );
                WPOG_Settings::replace( 'popup', array(
                    'width'         => max( 280, (int) ( $in['width'] ?? 600 ) ),
                    'overlay_color' => sanitize_text_field( $in['overlay_color'] ?? 'rgba(0,0,0,0.5)' ),
                    'toggle_on'     => sanitize_hex_color( $in['toggle_on'] ?? '#0073aa' ),
                    'toggle_off'    => sanitize_hex_color( $in['toggle_off'] ?? '#cccccc' ),
                    'show_extended' => ! empty( $in['show_extended'] ) ? 1 : 0,
                    'bg_color'      => sanitize_hex_color( $in['bg_color'] ?? '#ffffff' ),
                    'text_color'    => sanitize_hex_color( $in['text_color'] ?? '#333333' ),
                    'primary_bg'    => sanitize_hex_color( $in['primary_bg'] ?? '#0073aa' ),
                    'primary_text'  => sanitize_hex_color( $in['primary_text'] ?? '#ffffff' ),
                    'secondary_bg'  => sanitize_hex_color( $in['secondary_bg'] ?? '#f0f0f0' ),
                    'secondary_text'=> sanitize_hex_color( $in['secondary_text'] ?? '#333333' ),
                    'border_radius' => max( 0, (int) ( $in['border_radius'] ?? 4 ) ),
                    'font_size'     => max( 8, (int) ( $in['font_size'] ?? 14 ) ),
                ) );
                break;

            case 'categories':
                $in   = wp_unslash( (array) ( $_POST['wpog'] ?? array() ) );
                $out  = WPOG_Settings::defaults( 'categories' );
                foreach ( array( 'necessary', 'functional', 'analytics', 'marketing' ) as $k ) {
                    $row = (array) ( $in[ $k ] ?? array() );
                    $cookies = array();
                    if ( ! empty( $row['cookies'] ) && is_array( $row['cookies'] ) ) {
                        foreach ( $row['cookies'] as $c ) {
                            if ( empty( $c['name'] ) ) { continue; }
                            $cookies[] = array(
                                'name'     => sanitize_text_field( $c['name'] ),
                                'provider' => sanitize_text_field( $c['provider'] ?? '' ),
                                'duration' => sanitize_text_field( $c['duration'] ?? '' ),
                                'purpose'  => sanitize_text_field( $c['purpose'] ?? '' ),
                                'privacy'  => esc_url_raw( $c['privacy'] ?? '' ),
                            );
                        }
                    }
                    $out[ $k ] = array(
                        'name'        => sanitize_text_field( $row['name'] ?? $out[ $k ]['name'] ),
                        'description' => sanitize_textarea_field( $row['description'] ?? $out[ $k ]['description'] ),
                        'extended'    => wp_kses_post( $row['extended'] ?? '' ),
                        'cookies'     => $cookies,
                    );
                }
                WPOG_Settings::replace( 'categories', $out );
                break;

            case 'scripts':
                $in  = wp_unslash( (array) ( $_POST['wpog']['scripts'] ?? array() ) );
                $out = array();
                foreach ( $in as $row ) {
                    if ( empty( $row['name'] ) && empty( $row['content'] ) ) { continue; }
                    $out[] = array(
                        'name'     => sanitize_text_field( $row['name'] ?? '' ),
                        'category' => in_array( $row['category'] ?? 'marketing', array( 'necessary','functional','analytics','marketing' ), true ) ? $row['category'] : 'marketing',
                        'type'     => in_array( $row['type'] ?? 'inline', array( 'inline','src','iframe' ), true ) ? $row['type'] : 'inline',
                        'content'  => 'inline' === ( $row['type'] ?? 'inline' )
                            ? wp_unslash( $row['content'] ?? '' )
                            : esc_url_raw( $row['content'] ?? '' ),
                        'position' => in_array( $row['position'] ?? 'head', array( 'head','body-top','body-bottom' ), true ) ? $row['position'] : 'head',
                        'active'   => ! empty( $row['active'] ) ? 1 : 0,
                    );
                }
                update_option( 'wpog_scripts', $out );
                break;

            case 'translations':
                $in   = wp_unslash( (array) ( $_POST['wpog']['translations'] ?? array() ) );
                $keys = array_keys( WPOG_Settings::default_strings() );
                $out  = array();
                foreach ( $keys as $k ) {
                    if ( isset( $in[ $k ] ) && '' !== trim( (string) $in[ $k ] ) ) {
                        // banner_message supports HTML (rich-text editor)
                        $out[ $k ] = ( 'banner_message' === $k )
                            ? wp_kses_post( $in[ $k ] )
                            : sanitize_textarea_field( $in[ $k ] );
                    }
                }
                update_option( 'wpog_translations', $out );

                if ( ! empty( $_POST['wpog_reset_all'] ) ) {
                    delete_option( 'wpog_translations' );
                }
                if ( ! empty( $_FILES['wpog_import']['tmp_name'] ) ) {
                    $json = file_get_contents( $_FILES['wpog_import']['tmp_name'] );
                    $data = json_decode( $json, true );
                    if ( is_array( $data ) ) {
                        $clean = array();
                        foreach ( $keys as $k ) {
                            if ( isset( $data[ $k ] ) ) {
                                $clean[ $k ] = sanitize_textarea_field( (string) $data[ $k ] );
                            }
                        }
                        update_option( 'wpog_translations', $clean );
                    }
                }
                break;

            case 'logs':
                if ( ! empty( $_POST['wpog_purge'] ) ) {
                    WPOG_Logger::purge_old();
                }
                break;

            case 'blocker':
                $in   = wp_unslash( (array) ( $_POST['wpog']['rules'] ?? array() ) );
                $rules = array();
                foreach ( $in as $row ) {
                    if ( empty( $row['domain'] ) ) {
                        continue;
                    }
                    $rules[] = array(
                        'domain'   => $row['domain'],
                        'category' => $row['category'] ?? 'marketing',
                        'note'     => $row['note'] ?? '',
                        'active'   => ! empty( $row['active'] ) ? 1 : 0,
                    );
                }
                WPOG_Domain_Blocker::save_all( $rules );
                break;

            case 'tracking':
                $action_kind = sanitize_key( $_POST['wpog_action'] ?? '' );
                $id          = (int) ( $_POST['wpog_id'] ?? 0 );
                $bulk_ids    = array_map( 'intval', (array) ( $_POST['wpog_ids'] ?? array() ) );

                if ( 'delete_all' === $action_kind ) {
                    global $wpdb;
                    $wpdb->query( 'DELETE FROM ' . WPOG_Tracking::table_name() );
                    break;
                }
                if ( $id ) { $bulk_ids = array( $id ); }

                foreach ( $bulk_ids as $iid ) {
                    if ( $iid <= 0 ) { continue; }
                    if ( 'ignore' === $action_kind ) {
                        WPOG_Tracking::set_status( $iid, WPOG_Tracking::STATUS_IGNORED );
                    } elseif ( 'allow' === $action_kind ) {
                        WPOG_Tracking::set_status( $iid, WPOG_Tracking::STATUS_ALLOWED );
                    } elseif ( 'delete' === $action_kind ) {
                        WPOG_Tracking::delete( $iid );
                    } elseif ( 'block' === $action_kind ) {
                        $det = WPOG_Tracking::get( $iid );
                        if ( $det && ! empty( $det->domain ) ) {
                            $cat = sanitize_key( $_POST['wpog_block_category'] ?? 'marketing' );
                            WPOG_Domain_Blocker::add_rule( $det->domain, $cat, $det->value );
                            WPOG_Tracking::set_status( $iid, WPOG_Tracking::STATUS_BLOCKED );
                        }
                    }
                }
                break;

            case 'settings_import':
                if ( ! empty( $_FILES['wpog_settings_file']['tmp_name'] ) ) {
                    $json = file_get_contents( $_FILES['wpog_settings_file']['tmp_name'] );
                    $data = json_decode( $json, true );
                    if ( is_array( $data ) ) {
                        foreach ( array( 'general', 'banner', 'popup', 'categories', 'scripts', 'translations' ) as $group ) {
                            if ( isset( $data[ $group ] ) && is_array( $data[ $group ] ) ) {
                                update_option( 'wpog_' . $group, $data[ $group ] );
                            }
                        }
                        if ( isset( $data['cookie_policy'] ) ) {
                            update_option( 'wpog_cookie_policy', wp_kses_post( (string) $data['cookie_policy'] ) );
                        }
                    }
                }
                wp_safe_redirect( add_query_arg( array( 'updated' => '1' ), admin_url( 'admin.php?page=wpog-settings-io' ) ) );
                exit;

            case 'cookie_policy':
                $policy = wp_kses_post( wp_unslash( $_POST['wpog_cookie_policy'] ?? '' ) );
                update_option( 'wpog_cookie_policy', $policy );
                break;
        }

        wp_safe_redirect( add_query_arg( 'updated', '1', wp_get_referer() ?: admin_url( 'admin.php?page=' . self::SLUG ) ) );
        exit;
    }

    public static function export_logs() {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( 'Forbidden' );
        }
        check_admin_referer( self::NONCE );

        $args = array(
            'from'     => sanitize_text_field( $_GET['from'] ?? '' ),
            'to'       => sanitize_text_field( $_GET['to'] ?? '' ),
            'action'   => sanitize_text_field( $_GET['filter_action'] ?? '' ),
            'per_page' => 100000,
            'page'     => 1,
        );
        $res = WPOG_Logger::query( $args );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=wpog-consent-logs.csv' );
        $fh = fopen( 'php://output', 'w' );
        fputcsv( $fh, array( 'id', 'consent_id', 'date', 'ip', 'action', 'necessary', 'functional', 'analytics', 'marketing', 'policy_version', 'user_agent' ) );
        foreach ( $res['rows'] as $r ) {
            fputcsv( $fh, array( $r->id, $r->consent_id, $r->consent_date, $r->ip_address, $r->action, $r->necessary, $r->functional, $r->analytics, $r->marketing, $r->policy_version, $r->user_agent ) );
        }
        fclose( $fh );
        exit;
    }

    public static function export_translations() {        if ( ! current_user_can( self::CAP ) ) {
            wp_die( 'Forbidden' );
        }
        check_admin_referer( self::NONCE );
        $data = get_option( 'wpog_translations', array() );
        if ( ! is_array( $data ) ) { $data = array(); }
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=wpog-translations.json' );
        echo wp_json_encode( $data, JSON_PRETTY_PRINT );
        exit;
    }

    public static function export_settings() {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( 'Forbidden' );
        }
        check_admin_referer( self::NONCE );

        $groups = array( 'general', 'banner', 'popup', 'categories', 'scripts', 'translations' );
        $data   = array();
        foreach ( $groups as $group ) {
            $data[ $group ] = get_option( 'wpog_' . $group, array() );
        }
        $data['cookie_policy'] = get_option( 'wpog_cookie_policy', '' );

        $filename = 'wpog-settings-' . gmdate( 'Y-m-d' ) . '.json';
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        exit;
    }

    /** Helpers for views */
    public static function notice() {
        if ( ! empty( $_GET['updated'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'wp-opengdpr' ) . '</p></div>';
        }
    }

    public static function form_open( $form_id ) {
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field( self::NONCE );
        echo '<input type="hidden" name="wpog_form" value="' . esc_attr( $form_id ) . '" />';
    }

    public static function form_close( $label = null ) {
        if ( null === $label ) {
            $label = __( 'Save Changes', 'wp-opengdpr' );
        }
        submit_button( $label );
        echo '</form>';
    }
}
