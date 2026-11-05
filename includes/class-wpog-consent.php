<?php
/**
 * Consent reader / writer (server-side perspective).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Consent {

    const COOKIE_NAME    = 'wpog_consent';
    const COOKIE_ID_NAME = 'wpog_consent_id';

    /**
     * Returns the validated consent payload for the current request, or null.
     */
    public static function current() {
        if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            return null;
        }
        $raw = wp_unslash( (string) $_COOKIE[ self::COOKIE_NAME ] );
        if ( strlen( $raw ) > 1024 ) {
            return null;
        }
        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            return null;
        }
        // Validate version & expiry. A cookie without a version is treated as
        // stale (previously it bypassed the policy-version check entirely).
        $policy = (string) WPOG_Settings::get( 'general', 'policy_version' );
        if ( ! isset( $data['version'] ) || (string) $data['version'] !== $policy ) {
            return null;
        }
        if ( ! empty( $data['date'] ) ) {
            $ts = strtotime( $data['date'] );
            $duration = (int) WPOG_Settings::get( 'general', 'consent_duration', 180 );
            if ( $ts && ( time() - $ts ) > ( $duration * DAY_IN_SECONDS ) ) {
                return null;
            }
        }
        // Normalize categories.
        $cats = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : array();
        $data['categories'] = array(
            'necessary'  => true,
            'functional' => ! empty( $cats['functional'] ),
            'analytics'  => ! empty( $cats['analytics'] ),
            'marketing'  => ! empty( $cats['marketing'] ),
        );
        return $data;
    }

    /**
     * True if the consent banner should be displayed for the current visitor.
     */
    public static function should_show_banner() {
        if ( ! WPOG_Settings::get( 'general', 'enabled' ) ) {
            return false;
        }
        return null === self::current();
    }

    /**
     * Returns category allow-map for the current visitor.
     */
    public static function allowed_categories() {
        $current = self::current();
        if ( ! $current ) {
            return array(
                'necessary'  => true,
                'functional' => false,
                'analytics'  => false,
                'marketing'  => false,
            );
        }
        return $current['categories'];
    }

    /**
     * AJAX endpoint — persists consent server-side log.
     * Cookie writing happens client-side (so it works without a page reload).
     */
    public static function ajax_save() {
        // The nonce is only enforced for logged-in users. For anonymous
        // visitors a WordPress nonce gives no CSRF protection (it is the same
        // for every visitor) and, on sites behind a full-page cache, the
        // cached nonce expires after 12–24h: every consent log would then be
        // rejected with "-1", silently destroying the GDPR audit trail.
        if ( is_user_logged_in() && ! check_ajax_referer( 'wpog_consent', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
        }

        if ( ! self::rate_limit_ok( 'ajax' ) ) {
            wp_send_json_error( array( 'message' => 'Too many requests' ), 429 );
        }

        $payload = isset( $_POST['payload'] ) ? wp_unslash( (string) $_POST['payload'] ) : '';
        if ( strlen( $payload ) > 2048 ) {
            wp_send_json_error( array( 'message' => 'Invalid payload' ), 400 );
        }
        $data = json_decode( $payload, true );
        if ( ! is_array( $data ) || ( isset( $data['categories'] ) && ! is_array( $data['categories'] ) ) ) {
            wp_send_json_error( array( 'message' => 'Invalid payload' ), 400 );
        }
        WPOG_Logger::log( $data );
        wp_send_json_success();
    }

    /**
     * Fixed-window rate limiter: max N writes per hour per client IP.
     *
     * Uses the persistent object cache when available (no DB writes on busy
     * sites), falling back to transients. The IP comes from
     * WPOG_Logger::get_client_ip(), so sites behind a reverse proxy / CDN
     * must enable the `wpog_trust_proxy_headers` filter — otherwise every
     * visitor shares the proxy IP and the limit is hit site-wide.
     *
     * @param string $bucket Namespace (e.g. 'ajax', 'cross').
     * @return bool True when the request is allowed.
     */
    public static function rate_limit_ok( $bucket ) {
        $limit = (int) apply_filters( 'wpog_rate_limit_per_hour', 30, $bucket );
        if ( $limit <= 0 ) {
            return true;
        }
        $ip  = WPOG_Logger::get_client_ip();
        $key = 'wpog_rl_' . $bucket . '_' . md5( $ip . '|' . wp_salt( 'nonce' ) );

        if ( wp_using_ext_object_cache() ) {
            wp_cache_add( $key, 0, 'wpog', HOUR_IN_SECONDS );
            $count = wp_cache_incr( $key, 1, 'wpog' );
            return false === $count || $count <= $limit;
        }

        $count = (int) get_transient( $key );
        if ( $count >= $limit ) {
            return false;
        }
        set_transient( $key, $count + 1, HOUR_IN_SECONDS );
        return true;
    }
}
