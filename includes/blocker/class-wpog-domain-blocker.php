<?php
/**
 * Domain Blocker — manages a list of domains to block at the JS layer
 * (autoblocker) and renders the inline bootstrap in <head> before any
 * other asset on the page.
 *
 * Rule shape:
 *   array(
 *     'domain'   => 'google-analytics.com',
 *     'path'     => '',                      // optional URL path prefix, e.g. '/maps/api'
 *     'category' => 'analytics',             // necessary|functional|analytics|marketing
 *     'note'     => 'Google Analytics',      // free-text vendor hint
 *     'active'   => 1,
 *   )
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Domain_Blocker {

    const OPTION = 'wpog_blocker_rules';

    public static function init() {
        // Print boot config + load the autoblocker as early as possible.
        add_action( 'wp_head', array( __CLASS__, 'render_bootstrap' ), 0 );
    }

    /* ---------- storage ---------- */

    public static function all() {
        $rules = get_option( self::OPTION, array() );
        return is_array( $rules ) ? array_values( $rules ) : array();
    }

    public static function save_all( array $rules ) {
        $clean = array();
        foreach ( $rules as $r ) {
            $domain = self::normalize_domain( $r['domain'] ?? '' );
            if ( '' === $domain ) {
                continue;
            }
            $clean[] = array(
                'domain'   => $domain,
                'path'     => self::normalize_path( $r['path'] ?? '' ),
                'category' => in_array( $r['category'] ?? 'marketing', array( 'necessary', 'functional', 'analytics', 'marketing' ), true )
                    ? $r['category'] : 'marketing',
                'note'     => sanitize_text_field( $r['note'] ?? '' ),
                'active'   => ! empty( $r['active'] ) ? 1 : 0,
            );
        }
        update_option( self::OPTION, $clean );
    }

    public static function add_rule( $domain, $category = 'marketing', $note = '', $path = '' ) {
        $domain = self::normalize_domain( $domain );
        if ( '' === $domain ) {
            return false;
        }
        $path  = self::normalize_path( $path );
        $rules = self::all();
        foreach ( $rules as $r ) {
            // Treat domain+path as the uniqueness key so the same domain
            // can have multiple path-scoped rules (e.g. google.com/maps vs
            // google.com/analytics).
            if ( $r['domain'] === $domain && ( $r['path'] ?? '' ) === $path ) {
                return false;
            }
        }
        $rules[] = array(
            'domain'   => $domain,
            'path'     => $path,
            'category' => in_array( $category, array( 'necessary', 'functional', 'analytics', 'marketing' ), true ) ? $category : 'marketing',
            'note'     => sanitize_text_field( $note ),
            'active'   => 1,
        );
        update_option( self::OPTION, $rules );
        return true;
    }

    public static function normalize_domain( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return '';
        }
        // Accept full URLs too: parse host.
        if ( false !== strpos( $value, '/' ) || false !== strpos( $value, ':' ) ) {
            $host = wp_parse_url( $value, PHP_URL_HOST );
            if ( $host ) {
                $value = $host;
            }
        }
        $value = strtolower( $value );
        // Strip leading "www." and trailing dots.
        $value = preg_replace( '/^www\./', '', $value );
        $value = trim( $value, '.' );
        // Allow letters, digits, dot, hyphen.
        if ( ! preg_match( '/^[a-z0-9\.\-]+$/', $value ) ) {
            return '';
        }
        return $value;
    }

    public static function normalize_path( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value || '/' === $value ) {
            return '';
        }
        // If given a full URL, extract path.
        if ( false !== strpos( $value, '://' ) ) {
            $p = wp_parse_url( $value, PHP_URL_PATH );
            $value = $p ?: '';
        }
        // Ensure leading slash.
        $value = '/' . ltrim( $value, '/' );
        // Allow only safe path characters.
        if ( ! preg_match( '#^[a-zA-Z0-9/_\-\.~%]+$#', $value ) ) {
            return '';
        }
        return $value;
    }

    /* ---------- frontend ---------- */

    public static function render_bootstrap() {
        if ( ! WPOG_Settings::get( 'general', 'enabled' ) ) {
            return;
        }
        if ( ! WPOG_Settings::get( 'general', 'autoblocker_enabled' ) ) {
            return;
        }
        $rules = array_values( array_filter( self::all(), function ( $r ) { return ! empty( $r['active'] ); } ) );
        if ( empty( $rules ) ) {
            return;
        }

        $consent = WPOG_Consent::allowed_categories();
        // Same-origin host is always allowed (never blocked).
        $same_origin = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

        $config = array(
            'rules'       => $rules,
            'consent'     => $consent,
            'sameOrigin'  => $same_origin,
            'placeholder' => 'wpog-lazyload',
        );

        $boot_url = WPOG_PLUGIN_URL . 'public/assets/wpog-blocker.js?ver=' . WPOG_VERSION;

        // Inline config, then synchronously load the blocker script.
        // Both are printed at priority 0 of wp_head so they execute before
        // anything else outputs to <head>.
        echo "<script id=\"wpog-blocker-config\">window.WPOG_BLOCKER_CONFIG="
            . wp_json_encode( $config ) . ";</script>\n";
        echo '<script id="wpog-blocker-js" src="' . esc_url( $boot_url ) . '"></script>' . "\n";
    }
}
