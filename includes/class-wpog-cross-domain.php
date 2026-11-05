<?php
/**
 * Cross-Domain Consent Bridge — enables external (non-WordPress) applications
 * to integrate the same consent management flow via a single <script> embed.
 *
 * Flow:
 *   1. Admin whitelists external domains under Cookie Consent → Cross-Domain.
 *   2. External site embeds:
 *        <script src="https://wp.example.com/wp-json/wpog/v1/consent-bridge" async></script>
 *   3. The endpoint returns a self-contained JS file (config + CSS + HTML + logic)
 *      that renders the identical banner/popup on the external page.
 *   4. Consent cookies are stored on the external domain; audit-trail logs are
 *      POSTed back to the WordPress REST endpoint with CORS headers.
 *   5. Request origin is validated against the whitelist on every request.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Cross_Domain {

    const OPTION_KEY = 'wpog_cross_domains';

    /**
     * Buffer used to pass the generated JS from the REST callback to the
     * rest_pre_serve_request filter, where it is echoed directly. This
     * avoids WP_REST_Response JSON-encoding the script body.
     *
     * @var string|null
     */
    private static $bridge_js_buffer = null;

    /* ------------------------------------------------------------------ */
    /*  Bootstrap                                                          */
    /* ------------------------------------------------------------------ */

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
        // Hook into WordPress `rest_pre_serve_request` to serve the bridge
        // script as raw JavaScript (bypassing REST API JSON encoding) and to
        // add CORS headers for the consent-bridge endpoints.
        add_filter( 'rest_pre_serve_request', array( __CLASS__, 'on_pre_serve_request' ), 10, 4 );
    }

    /* ------------------------------------------------------------------ */
    /*  REST Routes                                                        */
    /* ------------------------------------------------------------------ */

    public static function register_routes() {
        // ── GET /wpog/v1/consent-bridge — serve the cross-domain script ──
        register_rest_route(
            WPOG_REST::NAMESPACE_V1,
            '/consent-bridge',
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'serve_bridge' ),
                'permission_callback' => '__return_true',
            )
        );

        // ── POST /wpog/v1/consent-bridge/log — receive consent logs ──
        register_rest_route(
            WPOG_REST::NAMESPACE_V1,
            '/consent-bridge/log',
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'receive_log' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Hook on rest_pre_serve_request to:
     *   - Add CORS headers for consent-bridge routes.
     *   - Serve the bridge JS as raw application/javascript (not JSON).
     *   - Handle CORS preflight (OPTIONS).
     */
    public static function on_pre_serve_request( $served, $result, $request, $server ) {
        $route = $request->get_route();
        if ( strpos( $route, '/wpog/v1/consent-bridge' ) !== 0 ) {
            return $served;
        }

        // CORS: only ever reflect origins that are on the whitelist. The
        // endpoints are anonymous and never rely on cookies, so credentials
        // are NOT allowed (previously any Origin was reflected together with
        // Access-Control-Allow-Credentials: true).
        $origin  = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
        $allowed = $origin && self::is_domain_allowed( $origin );
        if ( $allowed ) {
            $server->send_header( 'Access-Control-Allow-Origin', $origin );
        }
        $server->send_header( 'Vary', 'Origin' );
        $server->send_header( 'X-Content-Type-Options', 'nosniff' );

        // ── CORS preflight ──
        if ( 'OPTIONS' === $request->get_method() ) {
            if ( $allowed ) {
                $server->send_header( 'Access-Control-Allow-Methods', 'GET, POST, OPTIONS' );
                $server->send_header( 'Access-Control-Allow-Headers', 'Content-Type' );
                $server->send_header( 'Access-Control-Max-Age', '86400' );
            }
            status_header( $allowed ? 204 : 403 );
            exit;
        }

        // ── Serve bridge JS as raw JavaScript ──
        if ( 'GET' === $request->get_method() && self::$bridge_js_buffer !== null ) {
            $js = self::$bridge_js_buffer;
            self::$bridge_js_buffer = null;
            $ok = ( $result instanceof WP_HTTP_Response ) && 200 === $result->get_status();
            status_header( $ok ? 200 : 403 );
            $server->send_header( 'Content-Type', 'application/javascript; charset=utf-8' );
            // Never let a shared cache/CDN store a "not authorized" answer and
            // serve it to legitimate sites.
            $server->send_header( 'Cache-Control', $ok ? 'public, max-age=300' : 'no-store' );
            echo $js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — deliberately raw JS
            return true; // Signal: request already served.
        }

        return $served;
    }

    /* ------------------------------------------------------------------ */
    /*  Domain whitelist                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Return the stored domain list.
     *
     * @return array[] Array of { domain, active }.
     */
    public static function get_domains() {
        $domains = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $domains ) ) {
            $domains = array();
        }
        // Normalize: ensure every entry has the expected keys.
        $clean = array();
        foreach ( $domains as $d ) {
            if ( empty( $d['domain'] ) ) {
                continue;
            }
            $clean[] = array(
                'domain' => sanitize_text_field( $d['domain'] ),
                'active' => ! empty( $d['active'] ) ? 1 : 0,
            );
        }
        return $clean;
    }

    /**
     * Replace the stored domain list.
     *
     * @param array[] $domains Array of { domain, active }.
     */
    public static function save_domains( $domains ) {
        $clean = array();
        foreach ( (array) $domains as $d ) {
            if ( empty( $d['domain'] ) ) {
                continue;
            }
            $clean[] = array(
                'domain' => sanitize_text_field( $d['domain'] ),
                'active' => ! empty( $d['active'] ) ? 1 : 0,
            );
        }
        update_option( self::OPTION_KEY, $clean );
    }

    /**
     * Validate an origin against the whitelist.
     *
     * @param string $origin Full origin URL (scheme + host + port).
     * @return bool
     */
    public static function is_domain_allowed( $origin ) {
        if ( empty( $origin ) ) {
            return false;
        }
        $host = wp_parse_url( $origin, PHP_URL_HOST );
        if ( ! $host ) {
            return false;
        }
        $host = strtolower( $host );

        foreach ( self::get_domains() as $d ) {
            if ( empty( $d['active'] ) || empty( $d['domain'] ) ) {
                continue;
            }
            $allowed = strtolower( $d['domain'] );
            $allowed = ltrim( $allowed, '.' ); // Normalize leading dots
            // Exact match or subdomain match.
            if ( $host === $allowed || $host === 'www.' . $allowed ) {
                return true;
            }
            // Allow *.example.com pattern — strip leading "*." if present.
            if ( strpos( $allowed, '*.' ) === 0 ) {
                $suffix = substr( $allowed, 2 );
                if ( $host === $suffix || substr( $host, - ( strlen( $suffix ) + 1 ) ) === '.' . $suffix ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Extract the request origin from HTTP headers.
     *
     * @return string Empty string when neither header is present.
     */
    private static function get_request_origin() {
        if ( ! empty( $_SERVER['HTTP_ORIGIN'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) );
        }
        // Fall back to Referer (less reliable but works for GET requests).
        if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
            $referer = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
            $parts   = wp_parse_url( $referer );
            if ( ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
                return $parts['scheme'] . '://' . $parts['host'] . ( ! empty( $parts['port'] ) ? ':' . $parts['port'] : '' );
            }
        }
        return '';
    }

    /* ------------------------------------------------------------------ */
    /*  Endpoint: serve bridge script                                      */
    /* ------------------------------------------------------------------ */

    public static function serve_bridge( WP_REST_Request $request ) {
        $origin = self::get_request_origin();

        // Reject cross-origin requests from non-whitelisted domains.
        if ( ! empty( $origin ) && ! self::is_domain_allowed( $origin ) ) {
            // Buffer a short error message — served raw by on_pre_serve_request.
            self::$bridge_js_buffer = '/* WP OpenGDPR: domain not authorized. Add this domain in Cookie Consent → Cross-Domain. */';
            return new WP_REST_Response( null, 403 );
        }

        // Generate the JS and buffer it for raw output via on_pre_serve_request.
        // WP_REST_Response would JSON-encode the string, which breaks the script.
        self::$bridge_js_buffer = self::generate_bridge_js();
        return new WP_REST_Response( null, 200 );
    }

    /* ------------------------------------------------------------------ */
    /*  Endpoint: receive consent log                                      */
    /* ------------------------------------------------------------------ */

    public static function receive_log( WP_REST_Request $request ) {
        $origin = self::get_request_origin();

        if ( empty( $origin ) || ! self::is_domain_allowed( $origin ) ) {
            return new WP_REST_Response(
                array( 'ok' => false, 'reason' => 'unauthorized_origin' ),
                403
            );
        }

        // NOTE: the Origin header is trivially forgeable by non-browser
        // clients, so the whitelist only stops browsers on other sites; the
        // real abuse protection is the per-IP rate limit below. It is keyed by
        // client IP only (not origin+IP) so it cannot be bypassed by rotating
        // subdomains of a "*.example.com" wildcard entry.
        if ( ! WPOG_Consent::rate_limit_ok( 'cross' ) ) {
            return new WP_REST_Response(
                array( 'ok' => false, 'reason' => 'rate_limited' ),
                429
            );
        }

        $body    = $request->get_json_params();
        $payload = is_array( $body ) ? $body : null;
        if ( ! $payload ) {
            return new WP_REST_Response(
                array( 'ok' => false, 'reason' => 'invalid_payload' ),
                400
            );
        }

        // Validate required fields.
        if ( empty( $payload['categories'] ) || ! is_array( $payload['categories'] ) ) {
            return new WP_REST_Response(
                array( 'ok' => false, 'reason' => 'missing_categories' ),
                400
            );
        }

        // Use the same logging pipeline as the WordPress front-end.
        WPOG_Logger::log( $payload );

        // CORS headers are added centrally in on_pre_serve_request().
        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }

    /* ------------------------------------------------------------------ */
    /*  Bridge JS generator                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Assemble the complete self-contained cross-domain JavaScript payload.
     *
     * The generated script is cached in a transient keyed by a hash of all
     * contributing settings.  Any admin change (banner colours, categories,
     * translations, blocker rules, etc.) produces a different hash, which
     * automatically invalidates the cache — no explicit cache-busting needed.
     *
     * @return string Valid JavaScript.
     */
    private static function generate_bridge_js() {
        // ── Read every option that influences the bridge output ──
        $general       = WPOG_Settings::get( 'general' );
        // Re-validated: these values are concatenated into CSS on third-party sites.
        $banner        = WPOG_Settings::sanitize_banner( WPOG_Settings::get( 'banner' ) );
        $popup         = WPOG_Settings::sanitize_popup( WPOG_Settings::get( 'popup' ) );
        $categories    = WPOG_Settings::get( 'categories' );
        $scripts_data  = WPOG_Settings::get( 'scripts' );
        $translations  = get_option( 'wpog_translations', array() );
        $blocker_rules = class_exists( 'WPOG_Domain_Blocker' ) ? WPOG_Domain_Blocker::all() : array();
        $cookie_policy = get_option( 'wpog_cookie_policy', '' );

        // ── Build a hash fingerprint of all inputs ──
        $fingerprint = md5( serialize( array(
            'v'              => WPOG_VERSION,
            'general'        => $general,
            'banner'         => $banner,
            'popup'          => $popup,
            'categories'     => $categories,
            'scripts'        => $scripts_data,
            'translations'   => $translations,
            'blocker_rules'  => $blocker_rules,
            'cookie_policy'  => $cookie_policy,
        ) ) );

        $cache_key = 'wpog_bridge_js_' . $fingerprint;
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        // Build the consent config object (mirrors WPOG_DATA).
        $duration = (int) ( $general['consent_duration'] ?? 180 );
        $version  = (string) ( $general['policy_version'] ?? '1.0' );

        $config = array(
            'restUrl'         => rest_url( 'wpog/v1/consent-bridge/log' ),
            'cookieName'      => WPOG_Consent::COOKIE_NAME,
            'cookieIdName'    => WPOG_Consent::COOKIE_ID_NAME,
            'duration'        => $duration,
            'version'         => $version,
            'reloadOnAccept'  => ! empty( $general['reload_on_accept'] ) ? 1 : 0,
            'fabEnabled'      => ! empty( $general['fab_enabled'] ) ? 1 : 0,
            'fabPosition'     => in_array( $general['fab_position'] ?? 'bottom-right', array( 'bottom-right', 'bottom-left' ), true ) ? $general['fab_position'] : 'bottom-right',
            'fabLabel'        => $general['fab_label'] ?? '🍪',
            'fabBgColor'      => sanitize_hex_color( (string) ( $general['fab_bg_color'] ?? '' ) ) ?: $banner['primary_bg'],
            'fabTextColor'    => sanitize_hex_color( (string) ( $general['fab_text_color'] ?? '' ) ) ?: $banner['primary_text'],
            'autoblockerEnabled' => ! empty( $general['autoblocker_enabled'] ) ? 1 : 0,
        );

        // Blocker config (mirrors WPOG_BLOCKER_CONFIG).
        $blocker_config = array(
            'rules'       => $blocker_rules,
            'consent'     => array(
                'necessary'  => true,
                'functional' => false,
                'analytics'  => false,
                'marketing'  => false,
            ),
            'sameOrigin'  => '', // Filled client-side from location.hostname.
            'placeholder' => 'wpog-lazyload',
        );

        // Build strings (translations, with admin overrides resolved).
        $strings = array(
            'banner_message'        => WPOG_Settings::string( 'banner_message' ),
            'banner_accept_all'     => WPOG_Settings::string( 'banner_accept_all' ),
            'banner_reject_all'     => WPOG_Settings::string( 'banner_reject_all' ),
            'banner_customize'      => WPOG_Settings::string( 'banner_customize' ),
            'banner_privacy_link'   => WPOG_Settings::string( 'banner_privacy_link' ),
            'banner_cookie_link'    => WPOG_Settings::string( 'banner_cookie_link' ),
            'popup_title'           => WPOG_Settings::string( 'popup_title' ),
            'popup_save'            => WPOG_Settings::string( 'popup_save' ),
            'popup_accept_all'      => WPOG_Settings::string( 'popup_accept_all' ),
            'popup_reject_all'      => WPOG_Settings::string( 'popup_reject_all' ),
            'popup_necessary_label' => WPOG_Settings::string( 'popup_necessary_label' ),
            'popup_necessary_desc'  => WPOG_Settings::string( 'popup_necessary_desc' ),
            'popup_functional_label'=> WPOG_Settings::string( 'popup_functional_label' ),
            'popup_functional_desc' => WPOG_Settings::string( 'popup_functional_desc' ),
            'popup_analytics_label' => WPOG_Settings::string( 'popup_analytics_label' ),
            'popup_analytics_desc'  => WPOG_Settings::string( 'popup_analytics_desc' ),
            'popup_marketing_label' => WPOG_Settings::string( 'popup_marketing_label' ),
            'popup_marketing_desc'  => WPOG_Settings::string( 'popup_marketing_desc' ),
            'popup_always_active'   => WPOG_Settings::string( 'popup_always_active' ),
            'popup_cookies_summary' => WPOG_Settings::string( 'popup_cookies_summary' ),
            'popup_col_name'        => WPOG_Settings::string( 'popup_col_name' ),
            'popup_col_provider'    => WPOG_Settings::string( 'popup_col_provider' ),
            'popup_col_duration'    => WPOG_Settings::string( 'popup_col_duration' ),
            'popup_col_purpose'     => WPOG_Settings::string( 'popup_col_purpose' ),
            'fab_aria_label'        => WPOG_Settings::string( 'fab_aria_label' ),
        );

        // Privacy / cookie URLs.
        $privacy_url = ! empty( $general['privacy_url'] ) ? $general['privacy_url'] : '';
        $cookie_url  = ! empty( $general['cookie_url'] )  ? $general['cookie_url']  : '';

        // Category data for the popup body.
        $cats_data = array();
        $cat_order = array( 'necessary', 'functional', 'analytics', 'marketing' );
        $cat_labels = array(
            'necessary'  => array( $strings['popup_necessary_label'], $strings['popup_necessary_desc'] ),
            'functional' => array( $strings['popup_functional_label'], $strings['popup_functional_desc'] ),
            'analytics'  => array( $strings['popup_analytics_label'], $strings['popup_analytics_desc'] ),
            'marketing'  => array( $strings['popup_marketing_label'], $strings['popup_marketing_desc'] ),
        );

        foreach ( $cat_order as $key ) {
            $cat = isset( $categories[ $key ] ) ? $categories[ $key ] : array();
            $cats_data[ $key ] = array(
                'name'        => $cat_labels[ $key ][0],
                'description' => $cat_labels[ $key ][1],
                'extended'    => $cat['extended'] ?? '',
                'cookies'     => $cat['cookies'] ?? array(),
            );
        }

        // Banner config.
        $banner_cfg = array(
            'position'     => $banner['position'],
            'bgColor'      => $banner['bg_color'],
            'textColor'    => $banner['text_color'],
            'primaryBg'    => $banner['primary_bg'],
            'primaryText'  => $banner['primary_text'],
            'secondaryBg'  => $banner['secondary_bg'],
            'secondaryText'=> $banner['secondary_text'],
            'borderRadius' => (int) $banner['border_radius'],
            'fontSize'     => (int) $banner['font_size'],
            'animation'    => $banner['animation'],
            'overlay'      => ! empty( $banner['overlay'] ),
            'logo'         => $banner['logo'] ?? '',
        );

        // Popup config.
        $popup_cfg = array(
            'width'         => (int) $popup['width'],
            'overlayColor'  => $popup['overlay_color'],
            'toggleOn'      => $popup['toggle_on'],
            'toggleOff'     => $popup['toggle_off'],
            'showExtended'  => ! empty( $popup['show_extended'] ),
            'bgColor'       => $popup['bg_color'],
            'textColor'     => $popup['text_color'],
            'primaryBg'     => $popup['primary_bg'],
            'primaryText'   => $popup['primary_text'],
            'secondaryBg'   => $popup['secondary_bg'],
            'secondaryText' => $popup['secondary_text'],
            'borderRadius'  => (int) $popup['border_radius'],
            'fontSize'      => (int) $popup['font_size'],
        );

        // Custom scripts.
        $custom_scripts = array();
        if ( is_array( $scripts_data ) ) {
            foreach ( $scripts_data as $s ) {
                if ( empty( $s['active'] ) ) {
                    continue;
                }
                $custom_scripts[] = array(
                    'name'     => $s['name'] ?? '',
                    'category' => $s['category'] ?? 'marketing',
                    'type'     => $s['type'] ?? 'inline',
                    'content'  => $s['content'] ?? '',
                    'position' => $s['position'] ?? 'head',
                );
            }
        }

        // Serialize all settings into the JS payload.
        $settings_json = wp_json_encode( array(
            'config'         => $config,
            'blockerConfig'  => $blocker_config,
            'strings'        => $strings,
            'privacyUrl'     => $privacy_url,
            'cookieUrl'      => $cookie_url,
            'banner'         => $banner_cfg,
            'popup'          => $popup_cfg,
            'categories'     => $cats_data,
            'customScripts'  => $custom_scripts,
            'cookiePolicy'   => wp_kses_post( $cookie_policy ),
        ), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_AMP );

        // Build the monolithic JS.
        $js  = self::bridge_css();
        $js .= self::bridge_html();
        $js .= self::bridge_logic();

        // Prepend the settings injection.
        $out  = "/* WP OpenGDPR Cross-Domain Consent Bridge v" . WPOG_VERSION . " */\n";
        $out .= "/* Generated: " . gmdate( 'c' ) . " */\n";
        $out .= "/* Do not edit — this file is dynamically generated. */\n";
        $out .= "(function(){'use strict';\n";
        $out .= "var S=" . $settings_json . ";\n";
        $out .= $js;
        $out .= "})();\n";

        // Cache for 24 hours.  The key includes a settings fingerprint so any
        // admin change automatically produces a cache miss on the next request.
        set_transient( $cache_key, $out, DAY_IN_SECONDS );

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /*  CSS builder (mirrors public/assets/*.css + inline styles)          */
    /* ------------------------------------------------------------------ */

    private static function bridge_css() {
        return <<<'CSS'
function R(){var b=S.banner,p=S.popup,c=S.config,f=c.fabPosition,B=c.fabBgColor,T=c.fabTextColor;
var css=[
/* Banner */
'.wpog-banner{position:fixed;left:0;right:0;bottom:0;z-index:99999;padding:16px;box-shadow:0 -2px 12px rgba(0,0,0,.15);}',
'.wpog-banner[hidden]{display:none;}',
'.wpog-banner-inner{max-width:1200px;margin:0 auto;display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between;}',
'.wpog-banner-text{flex:1 1 60%;min-width:240px;}',
'.wpog-banner-text p{margin:0 0 6px;}',
'.wpog-banner-links a{margin-right:12px;text-decoration:underline;color:inherit;}',
'.wpog-banner-actions{display:flex;flex-wrap:wrap;gap:8px;}',
'.wpog-btn{padding:10px 16px;border:0;cursor:pointer;font-weight:600;line-height:1;}',
'.wpog-btn:focus{outline:2px solid #000;outline-offset:2px;}',
'.wpog-banner-logo{max-height:40px;width:auto;}',
'.wpog-pos-top{top:0;bottom:auto;}',
'.wpog-pos-bottom{top:auto;bottom:0;}',
'.wpog-banner--box{left:auto;right:auto;bottom:auto;top:auto;max-width:420px;width:calc(100% - 32px);border-radius:8px;box-shadow:0 4px 24px rgba(0,0,0,.2);}',
'.wpog-banner--box .wpog-banner-inner{flex-direction:column;align-items:flex-start;gap:12px;}',
'.wpog-banner--box .wpog-banner-text{flex:1 1 auto;min-width:0;}',
'.wpog-banner--box .wpog-banner-actions{width:100%;justify-content:flex-end;}',
'.wpog-pos-center{top:50%;left:50%;transform:translate(-50%,-50%);}',
'.wpog-pos-left{top:50%;left:16px;transform:translateY(-50%);}',
'.wpog-pos-right{top:50%;right:16px;transform:translateY(-50%);}',
'.wpog-pos-top-left{top:16px;left:16px;}',
'.wpog-pos-top-right{top:16px;right:16px;}',
'.wpog-pos-bottom-left{bottom:16px;left:16px;}',
'.wpog-pos-bottom-right{bottom:16px;right:16px;}',
'.wpog-anim-slide{transform:translateY(100%);animation:wpog-slide-in .35s ease forwards;}',
'.wpog-pos-top.wpog-anim-slide{transform:translateY(-100%);animation:wpog-slide-in-top .35s ease forwards;}',
'.wpog-anim-fade{opacity:0;animation:wpog-fade-in .35s ease forwards;}',
'.wpog-banner--box.wpog-anim-fade{opacity:0;animation:wpog-scale-in .3s ease forwards;}',
'@keyframes wpog-slide-in{to{transform:translateY(0);}}',
'@keyframes wpog-slide-in-top{to{transform:translateY(0);}}',
'@keyframes wpog-fade-in{to{opacity:1;}}',
'@keyframes wpog-scale-in{to{opacity:1;transform:translate(-50%,-50%) scale(1);}}',
'.wpog-pos-left.wpog-anim-fade,.wpog-pos-right.wpog-anim-fade{animation:wpog-fade-slide-x .3s ease forwards;}',
'.wpog-pos-top-left.wpog-anim-fade,.wpog-pos-bottom-left.wpog-anim-fade{animation:wpog-fade-slide-x-left .3s ease forwards;}',
'.wpog-pos-top-right.wpog-anim-fade,.wpog-pos-bottom-right.wpog-anim-fade{animation:wpog-fade-slide-x-right .3s ease forwards;}',
'@keyframes wpog-fade-slide-x{from{opacity:0;transform:translateY(-50%) translateX(16px);}to{opacity:1;transform:translateY(-50%) translateX(0);}}',
'@keyframes wpog-fade-slide-x-left{from{opacity:0;transform:translateX(-8px);}to{opacity:1;transform:translateX(0);}}',
'@keyframes wpog-fade-slide-x-right{from{opacity:0;transform:translateX(8px);}to{opacity:1;transform:translateX(0);}}',
'.wpog-banner-overlay{position:fixed;inset:0;z-index:99998;}',
'.wpog-banner-overlay[hidden]{display:none;}',
/* Popup */
'.wpog-popup-overlay{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:16px;}',
'.wpog-popup-overlay[hidden]{display:none;}',
'.wpog-popup{width:100%;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 4px 30px rgba(0,0,0,.25);}',
'.wpog-popup-header{display:flex;align-items:center;justify-content:space-between;padding:16px 24px;border-bottom:1px solid #eee;flex-shrink:0;}',
'.wpog-popup-header h2{margin:0;font-size:1.25em;}',
'.wpog-popup-close{background:none;border:0;font-size:26px;cursor:pointer;line-height:1;}',
'.wpog-popup-body{padding:8px 24px;overflow-y:auto;flex:1 1 auto;}',
'.wpog-category{padding:14px 0;border-bottom:1px solid #f0f0f0;}',
'.wpog-category:last-child{border-bottom:0;}',
'.wpog-category-head{display:flex;align-items:center;justify-content:space-between;gap:12px;}',
'.wpog-category-desc{margin:6px 0 0;font-size:.92em;opacity:.85;}',
'.wpog-always-active{font-size:.85em;opacity:.7;}',
'.wpog-toggle{position:relative;display:inline-block;width:44px;height:24px;}',
'.wpog-toggle input{opacity:0;width:0;height:0;}',
'.wpog-toggle-slider{position:absolute;cursor:pointer;inset:0;border-radius:24px;transition:background .2s;}',
'.wpog-toggle-slider:before{content:"";position:absolute;height:18px;width:18px;left:3px;top:3px;background:#fff;border-radius:50%;transition:transform .2s;}',
'.wpog-toggle input:checked + .wpog-toggle-slider:before{transform:translateX(20px);}',
'.wpog-popup-footer{display:flex;flex-direction:column;gap:8px;padding:16px 24px;border-top:1px solid #eee;flex-shrink:0;}',
'.wpog-popup-policy-links{margin:0;font-size:.85em;display:flex;gap:12px;flex-wrap:wrap;}',
'.wpog-popup-policy-links a{text-decoration:underline;color:inherit;opacity:.8;}',
'.wpog-popup-policy-links a:hover{opacity:1;}',
'.wpog-popup-footer-actions{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;}',
'.wpog-cookie-details{margin-top:8px;font-size:.9em;}',
'.wpog-cookie-table{width:100%;border-collapse:collapse;margin-top:6px;}',
'.wpog-cookie-table th,.wpog-cookie-table td{text-align:left;padding:4px 8px;border-bottom:1px solid #f0f0f0;}',
'.wpog-cookie-policy{padding:16px 0 8px;border-top:1px solid #eee;margin-top:8px;font-size:.92em;line-height:1.6;}',
'.wpog-cookie-policy a{text-decoration:underline;color:inherit;}',
'.wpog-cookie-policy p{margin:0 0 8px;}',
'.wpog-cookie-policy p:last-child{margin-bottom:0;}',
/* FAB */
'.wpog-fab{position:fixed;z-index:99997;width:48px;height:48px;border-radius:50%;border:0;cursor:pointer;font-size:22px;line-height:1;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 12px rgba(0,0,0,.3);transition:transform .2s,box-shadow .2s;padding:0;}',
'.wpog-fab:hover{transform:scale(1.12);box-shadow:0 4px 18px rgba(0,0,0,.35);}',
'.wpog-fab:focus{outline:2px solid #000;outline-offset:3px;}',
'.wpog-fab[hidden]{display:none;}',
'.wpog-fab--bottom-right{bottom:24px;right:24px;}',
'.wpog-fab--bottom-left{bottom:24px;left:24px;}',
/* Shortcode button */
'.wpog-settings-link{display:inline-block;cursor:pointer;background:none;border:none;padding:0;font:inherit;color:inherit;text-decoration:underline;}',
'.wpog-settings-link:hover{opacity:.8;}',
/* Responsive */
'@media (max-width:600px){',
'.wpog-banner-actions{width:100%;}.wpog-banner-actions .wpog-btn{flex:1 1 auto;}',
'.wpog-banner--box{max-width:none;width:calc(100% - 16px);margin:8px;}',
'.wpog-pos-center,.wpog-pos-left,.wpog-pos-right{top:auto;bottom:8px;left:8px;right:8px;transform:none;}',
'.wpog-pos-top-left,.wpog-pos-top-right{top:8px;}',
'.wpog-pos-bottom-left,.wpog-pos-bottom-right{bottom:8px;}',
'}',
/* User-configurable styles (inline equivalent) */
'.wpog-banner{background:'+b.bgColor+';color:'+b.textColor+';font-size:'+b.fontSize+'px;}',
'.wpog-banner-overlay{background:'+p.overlayColor+';}',
'.wpog-btn{border-radius:'+b.borderRadius+'px;font-size:'+b.fontSize+'px;}',
'.wpog-btn-primary{background:'+b.primaryBg+';color:'+b.primaryText+';}',
'.wpog-btn-secondary{background:'+b.secondaryBg+';color:'+b.secondaryText+';}',
'.wpog-popup-overlay{background:'+p.overlayColor+';}',
'.wpog-popup{background:'+p.bgColor+';color:'+p.textColor+';max-width:'+p.width+'px;font-size:'+p.fontSize+'px;border-radius:'+p.borderRadius+'px;}',
'.wpog-popup .wpog-btn-primary{background:'+p.primaryBg+';color:'+p.primaryText+';border-radius:'+p.borderRadius+'px;}',
'.wpog-popup .wpog-btn-secondary{background:'+p.secondaryBg+';color:'+p.secondaryText+';border-radius:'+p.borderRadius+'px;}',
'.wpog-toggle input:checked + .wpog-toggle-slider{background:'+p.toggleOn+';}',
'.wpog-toggle-slider{background:'+p.toggleOff+';}',
'.wpog-fab{background:'+B+';color:'+T+';}'
];
var st=document.createElement('style');st.id='wpog-bridge-css';st.textContent=css.join('');document.head.appendChild(st);
}
CSS;
    }

    /* ------------------------------------------------------------------ */
    /*  HTML builder (mirrors templates/banner.php + templates/popup.php)  */
    /* ------------------------------------------------------------------ */

    private static function bridge_html() {
        return <<<'HTML'
function H(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function I(){var b=S.banner,s=S.strings,p=S.privacyUrl,c=S.cookieUrl;
var isBox=['top','bottom'].indexOf(b.position)===-1;
var posCls='wpog-pos-'+b.position.replace(/[^a-z0-9-]/g,'');
var anim='';
if(isBox){anim=b.animation==='none'?'':'wpog-anim-fade';}
else{anim='wpog-anim-'+(b.animation||'slide').replace(/[^a-z0-9-]/g,'');}
var boxCls=isBox?' wpog-banner--box':'';
var overlay=b.overlay?'<div id="wpog-banner-overlay" class="wpog-banner-overlay" hidden></div>':'';
var logo=b.logo?'<img class="wpog-banner-logo" src="'+H(b.logo)+'" alt="">':'';
var privacyLink=p?'<a href="'+H(p)+'" target="_blank" rel="noopener">'+H(s.banner_privacy_link)+'</a>':'';
var cookieLink=c?'<a href="'+H(c)+'" target="_blank" rel="noopener">'+H(s.banner_cookie_link)+'</a>':'';
var links=(privacyLink||cookieLink)?'<p class="wpog-banner-links">'+privacyLink+cookieLink+'</p>':'';
var html='';
html+=overlay;
html+='<div id="wpog-banner" class="wpog-banner '+posCls+boxCls+' '+anim+'" role="dialog" aria-live="polite" aria-label="'+H(s.popup_title)+'" hidden>';
html+='<div class="wpog-banner-inner">';
html+=logo;
html+='<div class="wpog-banner-text"><p>'+s.banner_message+'</p>'+links+'</div>';
html+='<div class="wpog-banner-actions">';
html+='<button type="button" class="wpog-btn wpog-btn-secondary" data-wpog-action="reject_all">'+H(s.banner_reject_all)+'</button>';
html+='<button type="button" class="wpog-btn wpog-btn-secondary" data-wpog-action="customize">'+H(s.banner_customize)+'</button>';
html+='<button type="button" class="wpog-btn wpog-btn-primary" data-wpog-action="accept_all">'+H(s.banner_accept_all)+'</button>';
html+='</div></div></div>';
/* Popup */
html+='<div id="wpog-popup-overlay" class="wpog-popup-overlay" hidden>';
html+='<div id="wpog-popup" class="wpog-popup" role="dialog" aria-modal="true" aria-labelledby="wpog-popup-title">';
html+='<div class="wpog-popup-header"><h2 id="wpog-popup-title">'+H(s.popup_title)+'</h2><button type="button" class="wpog-popup-close" data-wpog-action="close" aria-label="Close">&times;</button></div>';
html+='<div class="wpog-popup-body">';
var cats=S.categories,order=['necessary','functional','analytics','marketing'];
for(var i=0;i<order.length;i++){
var k=order[i],cat=cats[k];if(!cat)continue;
var disabled=(k==='necessary');
var cookies=cat.cookies||[],cList='';
if(S.popup.showExtended&&cookies.length>0){
cList+='<details class="wpog-cookie-details"><summary>'+H(s.popup_cookies_summary)+'</summary><table class="wpog-cookie-table"><thead><tr><th>'+H(s.popup_col_name)+'</th><th>'+H(s.popup_col_provider)+'</th><th>'+H(s.popup_col_duration)+'</th><th>'+H(s.popup_col_purpose)+'</th></tr></thead><tbody>';
for(var j=0;j<cookies.length;j++){var ck=cookies[j];cList+='<tr><td>'+H(ck.name||'')+'</td><td>'+H(ck.provider||'')+'</td><td>'+H(ck.duration||'')+'</td><td>'+H(ck.purpose||'')+'</td></tr>';}
cList+='</tbody></table></details>';
}
var toggle=disabled?'<span class="wpog-always-active">'+H(s.popup_always_active)+'</span>':'<label class="wpog-toggle"><input type="checkbox" data-wpog-cat="'+H(k)+'"><span class="wpog-toggle-slider"></span></label>';
html+='<div class="wpog-category"><div class="wpog-category-head"><strong>'+H(cat.name)+'</strong>'+toggle+'</div><p class="wpog-category-desc">'+H(cat.description)+'</p>'+cList+'</div>';
}
if(S.cookiePolicy){html+='<div class="wpog-cookie-policy">'+S.cookiePolicy+'</div>';}
html+='</div>';
html+='<div class="wpog-popup-footer">';
if(p||c){html+='<p class="wpog-popup-policy-links">'+(p?'<a href="'+H(p)+'" target="_blank" rel="noopener">'+H(s.banner_privacy_link)+'</a>':'')+(c?'<a href="'+H(c)+'" target="_blank" rel="noopener">'+H(s.banner_cookie_link)+'</a>':'')+'</p>';}
html+='<div class="wpog-popup-footer-actions">';
html+='<button type="button" class="wpog-btn wpog-btn-secondary" data-wpog-action="reject_all">'+H(s.popup_reject_all)+'</button>';
html+='<button type="button" class="wpog-btn wpog-btn-secondary" data-wpog-action="save">'+H(s.popup_save)+'</button>';
html+='<button type="button" class="wpog-btn wpog-btn-primary" data-wpog-action="accept_all">'+H(s.popup_accept_all)+'</button>';
html+='</div></div>';
html+='</div></div>';
/* FAB */
if(S.config.fabEnabled){
html+='<button id="wpog-fab" class="wpog-fab wpog-fab--'+H(S.config.fabPosition)+'" data-wpog-action="customize" aria-label="'+H(s.fab_aria_label)+'" hidden>'+H(S.config.fabLabel)+'</button>';
}
var wrapper=document.createElement('div');wrapper.id='wpog-bridge-root';wrapper.innerHTML=html;
document.body.appendChild(wrapper);
}
HTML;
    }

    /* ------------------------------------------------------------------ */
    /*  JS logic (mirrors public/assets/wpog-consent.js + blocker.js)      */
    /* ------------------------------------------------------------------ */

    private static function bridge_logic() {
        $js  = '';
        $js .= "function L(){\n";
        $js .= "var D=S.config;\n";
        $js .= self::bridge_consent_js();
        $js .= "}\n";

        // Append blocker logic if autoblocker is enabled.
        $js .= "\nif(S.config.autoblockerEnabled){\n";
        $js .= self::bridge_blocker();
        $js .= "}\n";

        // Call the builder functions in order, then init.
        $js .= "R();I();L();\n";
        return $js;
    }

    /**
     * Core consent JS (mirrors public/assets/wpog-consent.js).
     * Returned as the body of function L() above.
     */
    private static function bridge_consent_js() {
        return <<<'CONSENT'

/* ---- helpers ---- */
function uuid(){if(window.crypto&&crypto.randomUUID)return crypto.randomUUID();return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,function(c){var r=Math.random()*16|0,v=c==='x'?r:(r&0x3|0x8);return v.toString(16);});}
function readCookie(name){var m=document.cookie.match(new RegExp('(?:^|;\\s*)'+name+'=([^;]+)'));return m?decodeURIComponent(m[1]):null;}
function writeCookie(name,value,days){var d=new Date();d.setTime(d.getTime()+(days*24*60*60*1000));var attrs='; expires='+d.toUTCString()+'; path=/; SameSite=Lax';if(location.protocol==='https:')attrs+='; Secure';document.cookie=name+'='+encodeURIComponent(value)+attrs;}
function loadConsent(){var raw=readCookie(D.cookieName);if(!raw)return null;try{var data=JSON.parse(raw);if(!data||typeof data!=='object'||!data.categories||typeof data.categories!=='object')return null;if(String(data.version)!==String(D.version))return null;if(data.date&&D.duration){var t=Date.parse(data.date);if(!isNaN(t)&&(Date.now()-t)>D.duration*864e5)return null;}return data;}catch(e){return null;}}
function saveConsent(action,cats){
var id=readCookie(D.cookieIdName)||uuid();
var payload={id:id,date:new Date().toISOString(),version:D.version,categories:{necessary:true,functional:!!cats.functional,analytics:!!cats.analytics,marketing:!!cats.marketing},action:action};
writeCookie(D.cookieIdName,id,D.duration);
writeCookie(D.cookieName,JSON.stringify(payload),D.duration);
window.wpogConsent=payload.categories;
/* Server-side log via REST */
try{var x=new XMLHttpRequest();x.open('POST',D.restUrl,true);x.setRequestHeader('Content-Type','application/json');x.send(JSON.stringify(payload));}catch(e){}
activateAll(payload.categories);
document.dispatchEvent(new CustomEvent('wpog:consent',{detail:payload}));
}
function activateAll(cats){Object.keys(cats).forEach(function(cat){if(cats[cat]){activateScripts(cat);activateIframes(cat);}});injectCustomScripts(cats);}
function activateScripts(category){
var nodes=document.querySelectorAll('script[type="text/plain"][data-wpog-category="'+category+'"]');
nodes.forEach(function(s){var ns=document.createElement('script');for(var i=0;i<s.attributes.length;i++){var a=s.attributes[i];if(a.name==='type'||a.name==='data-wpog-src')continue;ns.setAttribute(a.name,a.value);}var src=s.getAttribute('data-wpog-src');if(src)ns.src=src;else ns.textContent=s.textContent;s.parentNode.replaceChild(ns,s);});
}
function activateIframes(category){
var nodes=document.querySelectorAll('iframe[data-src][data-wpog-category="'+category+'"]');
nodes.forEach(function(n){n.src=n.getAttribute('data-src');n.removeAttribute('data-src');});
}

var BANNER,POPUP,FAB,BANNER_OVERLAY;
function showBanner(){if(BANNER){BANNER.hidden=false;}if(BANNER_OVERLAY){BANNER_OVERLAY.hidden=false;}hideFab();}
function hideBanner(){if(BANNER){BANNER.hidden=true;}if(BANNER_OVERLAY){BANNER_OVERLAY.hidden=true;}}
function showPopup(){if(POPUP){POPUP.hidden=false;}}
function hidePopup(){if(POPUP){POPUP.hidden=true;}}
function showFab(){if(FAB){FAB.hidden=false;}}
function hideFab(){if(FAB){FAB.hidden=true;}}
function getCustomCats(){var out={necessary:true,functional:false,analytics:false,marketing:false};document.querySelectorAll('#wpog-popup input[data-wpog-cat]').forEach(function(inp){out[inp.getAttribute('data-wpog-cat')]=inp.checked;});return out;}
function maybeReload(){if(D.reloadOnAccept){setTimeout(function(){window.location.reload();},150);}}
function handleAction(action){
if(action==='accept_all'){saveConsent('accept_all',{functional:true,analytics:true,marketing:true});hidePopup();hideBanner();showFab();maybeReload();}
else if(action==='reject_all'){saveConsent('reject_all',{functional:false,analytics:false,marketing:false});hidePopup();hideBanner();showFab();maybeReload();}
else if(action==='save'){saveConsent('custom',getCustomCats());hidePopup();hideBanner();showFab();maybeReload();}
else if(action==='customize'){showPopup();}
else if(action==='close'){hidePopup();}
}
function bind(root){if(!root)return;root.addEventListener('click',function(e){var btn=e.target.closest('[data-wpog-action]');if(!btn)return;handleAction(btn.getAttribute('data-wpog-action'));});}
function bindDocument(){document.addEventListener('click',function(e){var btn=e.target.closest('[data-wpog-action]');if(!btn)return;if((BANNER&&BANNER.contains(btn))||(POPUP&&POPUP.contains(btn)))return;handleAction(btn.getAttribute('data-wpog-action'));});}
function setupReopenLinks(){document.querySelectorAll('a[href="#wpog-settings"], .wpog-reopen').forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();showPopup();});});}

/* ---- custom scripts injection ---- */
function injectCustomScripts(cats){
var scripts=S.customScripts;if(!scripts||!scripts.length)return;
/* Inject each snippet at most once per page (saving consent twice used to duplicate trackers). */
window.__wpogInjected=window.__wpogInjected||{};
scripts.forEach(function(sc,idx){if(!cats[sc.category]||window.__wpogInjected[idx])return;window.__wpogInjected[idx]=1;
if(sc.type==='inline'){var s=document.createElement('script');s.textContent=sc.content;document.body.appendChild(s);}
else if(sc.type==='src'){var s=document.createElement('script');s.src=sc.content;document.body.appendChild(s);}
else if(sc.type==='iframe'){var f=document.createElement('iframe');f.src=sc.content;f.style.display='none';document.body.appendChild(f);}
});
}

/* ---- init ---- */
function init(){
BANNER=document.getElementById('wpog-banner');
POPUP=document.getElementById('wpog-popup-overlay');
FAB=document.getElementById('wpog-fab');
BANNER_OVERLAY=document.getElementById('wpog-banner-overlay');
bind(BANNER);bind(POPUP);bindDocument();setupReopenLinks();
var consent=loadConsent();
if(consent){window.wpogConsent=consent.categories;document.querySelectorAll('#wpog-popup input[data-wpog-cat]').forEach(function(inp){inp.checked=!!consent.categories[inp.getAttribute('data-wpog-cat')];});activateAll(consent.categories);if(window.WPOG_BLOCKER&&window.WPOG_BLOCKER.unblock){window.WPOG_BLOCKER.unblock();}hideBanner();showFab();}
else{showBanner();hideFab();}
}

if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}else{init();}
window.WPOG={open:showPopup,getConsent:loadConsent,revoke:function(){writeCookie(D.cookieName,'',-1);writeCookie(D.cookieIdName,'',-1);location.reload();}};
CONSENT;
    }

    /* ------------------------------------------------------------------ */
    /*  Blocker JS (mirrors public/assets/wpog-blocker.js)                  */
    /* ------------------------------------------------------------------ */

    private static function bridge_blocker() {
        return <<<'BLOCKER'
(function(){
var CFG=S.blockerConfig;if(!CFG||!Array.isArray(CFG.rules)||!CFG.rules.length)return;
/* Read stored consent synchronously so already-consented domains are never blocked. */
try{var _m=document.cookie.match(new RegExp('(?:^|;\\s*)'+S.config.cookieName+'=([^;]+)'));if(_m&&!window.wpogConsent){var _d=JSON.parse(decodeURIComponent(_m[1]));if(_d&&String(_d.version)===String(S.config.version)&&_d.categories&&typeof _d.categories==='object'){var _ok=true;if(_d.date){var _t=Date.parse(_d.date);if(!isNaN(_t)&&(Date.now()-_t)>S.config.duration*864e5)_ok=false;}if(_ok)window.wpogConsent=_d.categories;}}}catch(e){}
CFG.sameOrigin=location.hostname||'';
var BLOCKABLE=['script','iframe','img','link','audio','video','source','embed','object','picture'];
var PLACEHOLDER_CLASS=CFG.placeholder||'wpog-lazyload';
var SAME_ORIGIN=CFG.sameOrigin.toLowerCase();
var RULES=CFG.rules.slice();
function consentMap(){var c=window.wpogConsent||CFG.consent||{};return{necessary:true,functional:!!c.functional,analytics:!!c.analytics,marketing:!!c.marketing};}
function urlToDomain(url){if(!url)return'';try{if(/^[a-z][a-z0-9+\-.]*:/i.test(url)||url.indexOf('//')===0){var a=document.createElement('a');a.href=(url.indexOf('//')===0?location.protocol+url:url);return(a.hostname||'').toLowerCase();}}catch(e){}return'';}
function urlToPath(url){if(!url)return'';try{var a=document.createElement('a');a.href=url;return a.pathname||'';}catch(e){}return'';}
function findRule(url,domain){if(!domain)return null;domain=domain.toLowerCase().replace(/^www\./,'');var urlPath='';for(var i=0;i<RULES.length;i++){var r=RULES[i];if(!r||!r.active||!r.domain)continue;var rd=r.domain.toLowerCase().replace(/^www\./,'');if(domain!==rd&&!domain.endsWith('.'+rd))continue;if(r.path&&r.path!=='/'){if(!urlPath)urlPath=urlToPath(url);if(!urlPath.startsWith(r.path))continue;}return r;}return null;}
function shouldBlock(url){var domain=urlToDomain(url);if(!domain)return null;if(domain===SAME_ORIGIN)return null;if(domain.endsWith('.'+SAME_ORIGIN))return null;var rule=findRule(url,domain);if(!rule)return null;var cm=consentMap();if(cm[rule.category])return null;return rule;}
var nativeCreateElement=document.createElement;
document.createElement=function(tagName,options){var el=nativeCreateElement.apply(document,arguments);var t=(typeof tagName==='string')?tagName.toLowerCase():'';if(BLOCKABLE.indexOf(t)===-1)return el;defineBlockedAttr(el,'src');if(t==='link'||t==='a'){defineBlockedAttr(el,'href');}return el;};
function defineBlockedAttr(el,attr){try{Object.defineProperty(el,attr,{configurable:true,enumerable:true,get:function(){return el.getAttribute(attr)||'';},set:function(value){var rule=shouldBlock(value);if(rule){el.setAttribute('data-wpog-src',value);el.setAttribute('data-wpog-category',rule.category);el.setAttribute('data-wpog-domain',rule.domain);if(el.classList)el.classList.add(PLACEHOLDER_CLASS);el.setAttribute(attr,'');if(el.tagName&&el.tagName.toLowerCase()==='script'){el.type='text/plain';}}else{el.setAttribute(attr,value);}}});}catch(e){}}
function checkNode(node){if(!node||node.nodeType!==1)return;var tag=node.tagName?node.tagName.toLowerCase():'';if(BLOCKABLE.indexOf(tag)===-1)return;if(node.classList&&node.classList.contains(PLACEHOLDER_CLASS))return;if(node.hasAttribute&&node.hasAttribute('data-wpog-blocked'))return;var attr=(tag==='link')?'href':'src';var url=node.getAttribute(attr);if(!url)return;var rule=shouldBlock(url);if(!rule)return;freezeNode(node,attr,url,rule);}
function freezeNode(node,attr,url,rule){node.setAttribute('data-wpog-blocked','1');node.setAttribute('data-wpog-src',url);node.setAttribute('data-wpog-category',rule.category);node.setAttribute('data-wpog-domain',rule.domain);if(node.classList)node.classList.add(PLACEHOLDER_CLASS);if(node.tagName.toLowerCase()==='script'){var clone=nativeCreateElement.call(document,'script');for(var i=0;i<node.attributes.length;i++){var a=node.attributes[i];clone.setAttribute(a.name,a.value);}clone.type='text/plain';clone.removeAttribute('src');if(node.parentNode){node.parentNode.replaceChild(clone,node);}}else{node.removeAttribute(attr);}}
function scan(root){if(!root||!root.querySelectorAll)return;var sel=BLOCKABLE.join(',');var nodes=root.querySelectorAll(sel);for(var i=0;i<nodes.length;i++){checkNode(nodes[i]);}}
var ObserverCtor=window.MutationObserver||window.WebKitMutationObserver;
if(ObserverCtor){var observer=new ObserverCtor(function(mutations){for(var i=0;i<mutations.length;i++){var m=mutations[i];if(m.addedNodes){for(var j=0;j<m.addedNodes.length;j++){var n=m.addedNodes[j];checkNode(n);if(n&&n.querySelectorAll)scan(n);}}}});try{observer.observe(document.documentElement||document,{childList:true,subtree:true});}catch(e){}}
if(typeof HTMLElement!=='undefined'&&HTMLElement.prototype.attachShadow){var nativeAttachShadow=HTMLElement.prototype.attachShadow;HTMLElement.prototype.attachShadow=function(options){var root=nativeAttachShadow.call(this,options);if(ObserverCtor){try{new ObserverCtor(function(mutations){for(var i=0;i<mutations.length;i++){var m=mutations[i];if(!m.addedNodes)continue;for(var j=0;j<m.addedNodes.length;j++){checkNode(m.addedNodes[j]);}}}).observe(root,{childList:true,subtree:true});}catch(e){}}return root;};}
function fullScan(){scan(document);}
document.addEventListener('DOMContentLoaded',fullScan);document.addEventListener('readystatechange',fullScan);window.addEventListener('load',fullScan);setTimeout(fullScan,50);setTimeout(fullScan,200);
function unblock(){var cm=consentMap();var nodes=document.querySelectorAll('.'+PLACEHOLDER_CLASS);for(var i=0;i<nodes.length;i++){var n=nodes[i];var cat=n.getAttribute('data-wpog-category');var url=n.getAttribute('data-wpog-src');if(!cat||!url)continue;if(!cm[cat])continue;var tag=n.tagName?n.tagName.toLowerCase():'';if(tag==='script'){var s=nativeCreateElement.call(document,'script');for(var k=0;k<n.attributes.length;k++){var a=n.attributes[k];if(a.name==='type'||a.name==='data-wpog-src'||a.name==='data-wpog-blocked'||a.name==='class')continue;s.setAttribute(a.name,a.value);}s.src=url;if(n.parentNode){n.parentNode.replaceChild(s,n);}}else{var attr=(tag==='link')?'href':'src';n.setAttribute(attr,url);n.classList.remove(PLACEHOLDER_CLASS);n.setAttribute('data-wpog-unblocked','1');}}}
document.addEventListener('wpog:consent',unblock);
window.WPOG_BLOCKER={rescan:fullScan,unblock:unblock,rules:RULES};
})();
BLOCKER;
    }
}
