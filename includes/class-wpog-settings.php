<?php
/**
 * Settings — wrappers around WP options with defaults and translation helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Settings {

    const DB_VERSION = '1.6.1';

    public static function defaults( $group ) {
        $defaults = array(
            'general' => array(
                'enabled'              => 1,
                'consent_duration'     => 180,
                'policy_version'       => '1.0',
                'privacy_url'          => '',
                'privacy_page_id'      => 0,
                'cookie_url'           => '',
                'cookie_page_id'       => 0,
                'eu_only'              => 0,
                'log_enabled'          => 1,
                'anonymize_ip'         => 1,
                'trust_proxy_headers'  => 0,
                'log_retention_days'   => 365,
                'fab_enabled'          => 1,
                'fab_position'         => 'bottom-right',
                'fab_bg_color'         => '',
                'fab_text_color'       => '',
                'fab_label'            => '🍪',
                'reload_on_accept'     => 1,
                'autoblocker_enabled'  => 1,
                'tracking_enabled'     => 1,
            ),
            'banner' => array(
                'position'             => 'bottom',
                'bg_color'             => '#ffffff',
                'text_color'           => '#333333',
                'primary_bg'           => '#0073aa',
                'primary_text'         => '#ffffff',
                'secondary_bg'         => '#f0f0f0',
                'secondary_text'       => '#333333',
                'border_radius'        => 4,
                'font_size'            => 14,
                'animation'            => 'slide',
                'overlay'              => 0,
                'logo'                 => '',
            ),
            'popup' => array(
                'width'                => 760,
                'overlay_color'        => 'rgba(0,0,0,0.5)',
                'toggle_on'            => '#0073aa',
                'toggle_off'           => '#cccccc',
                'show_extended'        => 1,
                'bg_color'             => '#ffffff',
                'text_color'           => '#333333',
                'primary_bg'           => '#0073aa',
                'primary_text'         => '#ffffff',
                'secondary_bg'         => '#f0f0f0',
                'secondary_text'       => '#333333',
                'border_radius'        => 4,
                'font_size'            => 14,
            ),
            'categories' => array(
                'necessary' => array(
                    'name'         => 'Necessary',
                    'description'  => 'These cookies are required for the website to function and cannot be disabled.',
                    'extended'     => '',
                    'cookies'      => array(),
                ),
                'functional' => array(
                    'name'         => 'Functional',
                    'description'  => 'These cookies enable enhanced functionality and personalisation.',
                    'extended'     => '',
                    'cookies'      => array(),
                ),
                'analytics' => array(
                    'name'         => 'Analytics',
                    'description'  => 'These cookies help us understand how visitors interact with our website.',
                    'extended'     => '',
                    'cookies'      => array(),
                ),
                'marketing' => array(
                    'name'         => 'Marketing',
                    'description'  => 'These cookies are used to deliver personalised advertisements.',
                    'extended'     => '',
                    'cookies'      => array(),
                ),
            ),
            'scripts' => array(),
            'translations' => array(),
            // Form privacy consent module (GDPR Art. 6/7) — independent from
            // the cookie consent.
            'form_consent' => array(
                'enabled'                      => 1,
                'privacy_policy_version'       => '1.0',

                // Main checkbox (mandatory).
                'checkbox_main_enabled'        => 1,
                'checkbox_main_required'       => 1,
                'checkbox_main_label'          => 'I have read and accept the <a href="{privacy_url}" target="_blank" rel="noopener">Privacy Policy</a> and consent to the processing of my personal data.',
                'checkbox_main_error'          => 'You must accept the privacy policy to send your message.',

                // Marketing checkbox (optional).
                'checkbox_marketing_enabled'   => 0,
                'checkbox_marketing_required'  => 0,
                'checkbox_marketing_label'     => 'I consent to receive commercial communications and updates.',

                // Behaviour.
                'block_submit_without_consent' => 1,
                'log_enabled'                  => 1,
                'log_retention_days'           => 365,

                // Contact Form 7.
                'cf7_enabled'                  => 1,
                'cf7_auto_inject'              => 1,
                'cf7_form_ids'                 => array(),
                'cf7_position'                 => 'before_submit',

                // WPForms.
                'wpforms_enabled'              => 1,
                'wpforms_form_ids'             => array(),
            ),
            // Terms & Conditions module (e-commerce) — independent from the
            // cookie and form consent modules.
            'terms_consent' => array(
                'enabled'                    => 1,

                // Documents.
                'terms_page_id'              => 0,
                'terms_version'              => '1.0',
                'terms_last_updated'         => '',
                'privacy_page_id'            => 0,

                // Checkout behaviour.
                'require_terms_acceptance'   => 1,
                'require_privacy_acceptance' => 1,
                'show_marketing_consent'     => 0,
                'require_age_verification'   => 0,
                'minimum_age'                => 18,

                // Checkbox labels.
                'terms_checkbox_label'       => 'I have read and accept the <a href="{terms_url}" target="_blank" rel="noopener">Terms & Conditions of Sale</a>.',
                'privacy_checkbox_label'     => 'I have read and accept the <a href="{privacy_url}" target="_blank" rel="noopener">Privacy Policy</a> and consent to the processing of my data to fulfil my order.',
                'marketing_checkbox_label'   => 'I would like to receive offers and updates by email.',
                'age_checkbox_label'         => 'I confirm that I am at least {min_age} years old.',

                // Error messages.
                'terms_error_message'        => 'You must accept the Terms & Conditions to complete your order.',
                'privacy_error_message'      => 'You must accept the Privacy Policy to complete your order.',
                'age_error_message'          => 'You must be at least {min_age} years old to purchase on our site.',

                // Log.
                'log_enabled'                => 1,
                'log_save_to_order_meta'     => 1,
                'log_retention_days'         => 730,

                // WooCommerce.
                'woocommerce_enabled'        => 1,
                'wc_checkout_position'       => 'before_submit',
                'wc_replace_native_terms'    => 0,
                'wc_show_terms_inline'       => 0,
                'wc_order_column'            => 1,
                'wc_registration_consent'    => 1,

                // My Account → Privacy & Consents tab (R3).
                'myaccount_privacy_enabled'  => 1,
            ),
        );
        return isset( $defaults[ $group ] ) ? $defaults[ $group ] : array();
    }

    /**
     * Per-request cache of merged (defaults + stored) option groups.
     * Invalidated whenever a wpog_* option is added/updated/deleted.
     *
     * @var array
     */
    private static $cache = array();

    /** @var array|null */
    private static $strings_cache = null;

    /**
     * Register cache-invalidation hooks. Called once from the loader.
     */
    public static function init_cache() {
        add_action( 'added_option', array( __CLASS__, 'maybe_flush_cache' ) );
        add_action( 'updated_option', array( __CLASS__, 'maybe_flush_cache' ) );
        add_action( 'deleted_option', array( __CLASS__, 'maybe_flush_cache' ) );
    }

    public static function maybe_flush_cache( $option ) {
        if ( is_string( $option ) && 0 === strpos( $option, 'wpog_' ) ) {
            self::flush_cache();
        }
    }

    public static function flush_cache() {
        self::$cache         = array();
        self::$strings_cache = null;
    }

    public static function get( $group, $key = null, $fallback = null ) {
        if ( ! isset( self::$cache[ $group ] ) ) {
            $opt = get_option( 'wpog_' . $group, array() );
            if ( ! is_array( $opt ) ) {
                $opt = array();
            }
            self::$cache[ $group ] = array_merge( self::defaults( $group ), $opt );
        }
        $opt = self::$cache[ $group ];
        if ( null === $key ) {
            return $opt;
        }
        if ( isset( $opt[ $key ] ) ) {
            return $opt[ $key ];
        }
        return $fallback;
    }

    public static function update( $group, $values ) {
        $current = get_option( 'wpog_' . $group, array() );
        if ( ! is_array( $current ) ) {
            $current = array();
        }
        $merged = array_merge( $current, (array) $values );
        update_option( 'wpog_' . $group, $merged );
        self::flush_cache();
    }

    public static function replace( $group, $values ) {
        update_option( 'wpog_' . $group, $values );
        self::flush_cache();
    }

    /* ------------------------------------------------------------------ */
    /*  Shared sanitizers (used by admin forms AND settings import)        */
    /* ------------------------------------------------------------------ */

    const CATEGORY_KEYS = array( 'necessary', 'functional', 'analytics', 'marketing' );

    /**
     * Strictly validate a CSS colour: #hex, rgb()/rgba()/hsl()/hsla() with
     * numeric arguments only, or "transparent". Anything else (which could
     * break out of the CSS declaration) returns $fallback.
     */
    public static function sanitize_css_color( $value, $fallback = '' ) {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return $fallback;
        }
        $hex = sanitize_hex_color( $value );
        if ( $hex ) {
            return $hex;
        }
        if ( 'transparent' === strtolower( $value ) ) {
            return 'transparent';
        }
        if ( preg_match( '/^(rgba?|hsla?)\(\s*[0-9.]+%?\s*,\s*[0-9.]+%?\s*,\s*[0-9.]+%?\s*(,\s*[0-9.]+%?\s*)?\)$/i', $value ) ) {
            return $value;
        }
        return $fallback;
    }

    /**
     * Sanitize a (partial) wpog_general array. Only keys present in $in are
     * returned, so callers can merge the result into existing settings.
     */
    public static function sanitize_general( array $in ) {
        $out   = array();
        $bools = array( 'enabled', 'eu_only', 'log_enabled', 'anonymize_ip', 'trust_proxy_headers', 'fab_enabled', 'reload_on_accept', 'autoblocker_enabled', 'tracking_enabled' );
        foreach ( $bools as $k ) {
            if ( array_key_exists( $k, $in ) ) {
                $out[ $k ] = ! empty( $in[ $k ] ) ? 1 : 0;
            }
        }
        if ( array_key_exists( 'consent_duration', $in ) ) {
            $out['consent_duration'] = min( 3650, max( 1, (int) $in['consent_duration'] ) );
        }
        if ( array_key_exists( 'log_retention_days', $in ) ) {
            $out['log_retention_days'] = max( 0, (int) $in['log_retention_days'] );
        }
        foreach ( array( 'privacy_page_id', 'cookie_page_id' ) as $k ) {
            if ( array_key_exists( $k, $in ) ) {
                $out[ $k ] = max( 0, (int) $in[ $k ] );
            }
        }
        if ( array_key_exists( 'policy_version', $in ) ) {
            $out['policy_version'] = substr( sanitize_text_field( (string) $in['policy_version'] ), 0, 20 );
        }
        foreach ( array( 'privacy_url', 'cookie_url' ) as $k ) {
            if ( array_key_exists( $k, $in ) ) {
                $out[ $k ] = esc_url_raw( (string) $in[ $k ], array( 'http', 'https' ) );
            }
        }
        if ( array_key_exists( 'fab_position', $in ) ) {
            $out['fab_position'] = in_array( $in['fab_position'], array( 'bottom-right', 'bottom-left' ), true ) ? $in['fab_position'] : 'bottom-right';
        }
        if ( array_key_exists( 'fab_label', $in ) ) {
            $out['fab_label'] = substr( sanitize_text_field( (string) $in['fab_label'] ), 0, 40 );
        }
        foreach ( array( 'fab_bg_color', 'fab_text_color' ) as $k ) {
            if ( array_key_exists( $k, $in ) ) {
                $out[ $k ] = sanitize_hex_color( (string) $in[ $k ] ) ?: '';
            }
        }
        return $out;
    }

    public static function sanitize_banner( array $in ) {
        $d = self::defaults( 'banner' );
        return array(
            'position'       => in_array( $in['position'] ?? 'bottom', array( 'top', 'bottom', 'center', 'left', 'right', 'bottom-left', 'bottom-right', 'top-left', 'top-right' ), true ) ? $in['position'] : 'bottom',
            'bg_color'       => sanitize_hex_color( (string) ( $in['bg_color'] ?? '' ) ) ?: $d['bg_color'],
            'text_color'     => sanitize_hex_color( (string) ( $in['text_color'] ?? '' ) ) ?: $d['text_color'],
            'primary_bg'     => sanitize_hex_color( (string) ( $in['primary_bg'] ?? '' ) ) ?: $d['primary_bg'],
            'primary_text'   => sanitize_hex_color( (string) ( $in['primary_text'] ?? '' ) ) ?: $d['primary_text'],
            'secondary_bg'   => sanitize_hex_color( (string) ( $in['secondary_bg'] ?? '' ) ) ?: $d['secondary_bg'],
            'secondary_text' => sanitize_hex_color( (string) ( $in['secondary_text'] ?? '' ) ) ?: $d['secondary_text'],
            'border_radius'  => min( 100, max( 0, (int) ( $in['border_radius'] ?? 4 ) ) ),
            'font_size'      => min( 48, max( 8, (int) ( $in['font_size'] ?? 14 ) ) ),
            'animation'      => in_array( $in['animation'] ?? 'slide', array( 'slide', 'fade', 'none' ), true ) ? $in['animation'] : 'slide',
            'overlay'        => ! empty( $in['overlay'] ) ? 1 : 0,
            'logo'           => esc_url_raw( (string) ( $in['logo'] ?? '' ), array( 'http', 'https' ) ),
        );
    }

    public static function sanitize_popup( array $in ) {
        $d = self::defaults( 'popup' );
        return array(
            'width'          => min( 2000, max( 280, (int) ( $in['width'] ?? 600 ) ) ),
            'overlay_color'  => self::sanitize_css_color( $in['overlay_color'] ?? '', $d['overlay_color'] ),
            'toggle_on'      => sanitize_hex_color( (string) ( $in['toggle_on'] ?? '' ) ) ?: $d['toggle_on'],
            'toggle_off'     => sanitize_hex_color( (string) ( $in['toggle_off'] ?? '' ) ) ?: $d['toggle_off'],
            'show_extended'  => ! empty( $in['show_extended'] ) ? 1 : 0,
            'bg_color'       => sanitize_hex_color( (string) ( $in['bg_color'] ?? '' ) ) ?: $d['bg_color'],
            'text_color'     => sanitize_hex_color( (string) ( $in['text_color'] ?? '' ) ) ?: $d['text_color'],
            'primary_bg'     => sanitize_hex_color( (string) ( $in['primary_bg'] ?? '' ) ) ?: $d['primary_bg'],
            'primary_text'   => sanitize_hex_color( (string) ( $in['primary_text'] ?? '' ) ) ?: $d['primary_text'],
            'secondary_bg'   => sanitize_hex_color( (string) ( $in['secondary_bg'] ?? '' ) ) ?: $d['secondary_bg'],
            'secondary_text' => sanitize_hex_color( (string) ( $in['secondary_text'] ?? '' ) ) ?: $d['secondary_text'],
            'border_radius'  => min( 100, max( 0, (int) ( $in['border_radius'] ?? 4 ) ) ),
            'font_size'      => min( 48, max( 8, (int) ( $in['font_size'] ?? 14 ) ) ),
        );
    }

    public static function sanitize_categories( array $in ) {
        $out = self::defaults( 'categories' );
        foreach ( self::CATEGORY_KEYS as $k ) {
            $row     = is_array( $in[ $k ] ?? null ) ? $in[ $k ] : array();
            $cookies = array();
            if ( ! empty( $row['cookies'] ) && is_array( $row['cookies'] ) ) {
                foreach ( $row['cookies'] as $c ) {
                    if ( ! is_array( $c ) || empty( $c['name'] ) ) {
                        continue;
                    }
                    $cookies[] = array(
                        'name'     => sanitize_text_field( (string) $c['name'] ),
                        'provider' => sanitize_text_field( (string) ( $c['provider'] ?? '' ) ),
                        'duration' => sanitize_text_field( (string) ( $c['duration'] ?? '' ) ),
                        'purpose'  => sanitize_text_field( (string) ( $c['purpose'] ?? '' ) ),
                        'privacy'  => esc_url_raw( (string) ( $c['privacy'] ?? '' ), array( 'http', 'https' ) ),
                    );
                }
            }
            $out[ $k ] = array(
                'name'        => sanitize_text_field( (string) ( $row['name'] ?? $out[ $k ]['name'] ) ),
                'description' => sanitize_textarea_field( (string) ( $row['description'] ?? $out[ $k ]['description'] ) ),
                'extended'    => wp_kses_post( (string) ( $row['extended'] ?? '' ) ),
                'cookies'     => $cookies,
            );
        }
        return $out;
    }

    /**
     * Sanitize the custom-script list. Custom scripts are arbitrary JavaScript
     * by definition, so callers MUST check current_user_can( 'unfiltered_html' )
     * before persisting the result.
     */
    public static function sanitize_scripts( array $in ) {
        $out = array();
        foreach ( $in as $row ) {
            if ( ! is_array( $row ) || ( empty( $row['name'] ) && empty( $row['content'] ) ) ) {
                continue;
            }
            $type = in_array( $row['type'] ?? 'inline', array( 'inline', 'src', 'iframe' ), true ) ? $row['type'] : 'inline';
            if ( 'inline' === $type ) {
                // Raw JS. Neutralise a closing </script> so the snippet cannot
                // break out of the wrapper tag it is printed into.
                $content = preg_replace( '#</(script)#i', '<\\/$1', (string) ( $row['content'] ?? '' ) );
            } else {
                $content = esc_url_raw( (string) ( $row['content'] ?? '' ), array( 'http', 'https' ) );
            }
            $out[] = array(
                'name'     => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
                'category' => in_array( $row['category'] ?? 'marketing', self::CATEGORY_KEYS, true ) ? $row['category'] : 'marketing',
                'type'     => $type,
                'content'  => $content,
                'position' => in_array( $row['position'] ?? 'head', array( 'head', 'body-top', 'body-bottom' ), true ) ? $row['position'] : 'head',
                'active'   => ! empty( $row['active'] ) ? 1 : 0,
            );
        }
        return $out;
    }

    /** Translation keys whose values may contain limited HTML (links). */
    const HTML_STRING_KEYS = array( 'banner_message', 'form_privacy_checkbox_label', 'terms_terms_checkbox_label', 'terms_privacy_checkbox_label' );

    public static function sanitize_translations( array $in ) {
        $out = array();
        foreach ( array_keys( self::default_strings() ) as $k ) {
            if ( ! isset( $in[ $k ] ) || ! is_scalar( $in[ $k ] ) || '' === trim( (string) $in[ $k ] ) ) {
                continue;
            }
            $out[ $k ] = in_array( $k, self::HTML_STRING_KEYS, true )
                ? wp_kses_post( (string) $in[ $k ] )
                : sanitize_textarea_field( (string) $in[ $k ] );
        }
        return $out;
    }

    /**
     * Generic sanitizer for the form_consent / terms_consent groups, driven by
     * the type of each default value. Unknown keys are dropped.
     */
    public static function sanitize_by_defaults( $group, array $in ) {
        $defaults = self::defaults( $group );
        $enums    = array(
            'cf7_position'         => array( 'before_submit', 'after_fields', 'manual' ),
            'wc_checkout_position' => array( 'before_submit', 'before_terms', 'after_terms' ),
        );
        $out = array();
        foreach ( $defaults as $k => $default ) {
            if ( ! array_key_exists( $k, $in ) ) {
                continue;
            }
            $v = $in[ $k ];
            if ( isset( $enums[ $k ] ) ) {
                $out[ $k ] = in_array( $v, $enums[ $k ], true ) ? $v : $default;
            } elseif ( is_array( $default ) ) {
                $out[ $k ] = array_values( array_filter( array_map( 'intval', (array) $v ) ) );
            } elseif ( is_int( $default ) ) {
                $out[ $k ] = max( 0, (int) $v );
            } elseif ( false !== strpos( $k, 'label' ) ) {
                $out[ $k ] = wp_kses_post( (string) $v );
            } else {
                $out[ $k ] = sanitize_text_field( (string) $v );
            }
        }
        return $out;
    }

    /**
     * Default English strings exposed to end users.
     */
    public static function default_strings() {
        return array(
            'banner_message'         => 'We use cookies to improve your experience on our website.',
            'banner_accept_all'      => 'Accept All',
            'banner_reject_all'      => 'Reject All',
            'banner_customize'       => 'Customize',
            'banner_privacy_link'    => 'Privacy Policy',
            'banner_cookie_link'     => 'Cookie Policy',
            'popup_title'            => 'Cookie Settings',
            'popup_save'             => 'Save Preferences',
            'popup_accept_all'       => 'Accept All',
            'popup_reject_all'       => 'Reject All',
            'popup_necessary_label'  => 'Necessary',
            'popup_necessary_desc'   => 'These cookies are required for the website to function and cannot be disabled.',
            'popup_functional_label' => 'Functional',
            'popup_functional_desc'  => 'These cookies enable enhanced functionality and personalisation.',
            'popup_analytics_label'  => 'Analytics',
            'popup_analytics_desc'   => 'These cookies help us understand how visitors interact with our website.',
            'popup_marketing_label'  => 'Marketing',
            'popup_marketing_desc'   => 'These cookies are used to deliver personalised advertisements.',
            'popup_always_active'    => 'Always active',
            'popup_cookies_summary'  => 'Cookies',
            'popup_col_name'         => 'Name',
            'popup_col_provider'     => 'Provider',
            'popup_col_duration'     => 'Duration',
            'popup_col_purpose'      => 'Purpose',
            'footer_reopen_link'     => 'Cookie Settings',
            'fab_aria_label'         => 'Open Cookie Settings',
            'shortcode_btn_label'    => 'Cookie Settings',

            // Form Privacy Consent — checkbox labels & errors.
            'form_privacy_checkbox_label'   => 'I have read and accept the <a href="{privacy_url}" target="_blank" rel="noopener">Privacy Policy</a> and consent to the processing of my personal data.',
            'form_privacy_error'            => 'You must accept the privacy policy to send your message.',
            'form_marketing_checkbox_label' => 'I consent to receive commercial communications and updates.',

            // Terms & Conditions — checkbox labels.
            'terms_terms_checkbox_label'     => 'I have read and accept the <a href="{terms_url}" target="_blank" rel="noopener">Terms & Conditions of Sale</a>.',
            'terms_privacy_checkbox_label'   => 'I have read and accept the <a href="{privacy_url}" target="_blank" rel="noopener">Privacy Policy</a> and consent to the processing of my data to fulfil my order.',
            'terms_marketing_checkbox_label' => 'I would like to receive offers and updates by email.',
            'terms_age_checkbox_label'       => 'I confirm that I am at least {min_age} years old.',

            // Terms & Conditions — error messages.
            'terms_terms_error_message'   => 'You must accept the Terms & Conditions to complete your order.',
            'terms_privacy_error_message' => 'You must accept the Privacy Policy to complete your order.',
            'terms_age_error_message'     => 'You must be at least {min_age} years old to purchase on our site.',
        );
    }

    /**
     * Get a user-facing string, with admin override > .po translation > English default.
     */
    public static function string( $key ) {
        if ( null === self::$strings_cache ) {
            $overrides = get_option( 'wpog_translations', array() );
            self::$strings_cache = array(
                'overrides' => is_array( $overrides ) ? $overrides : array(),
                'defaults'  => self::default_strings(),
            );
        }
        $overrides = self::$strings_cache['overrides'];
        if ( ! empty( $overrides[ $key ] ) && is_scalar( $overrides[ $key ] ) ) {
            return (string) $overrides[ $key ];
        }
        $defaults = self::$strings_cache['defaults'];
        $default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
        // Allow .po/.mo translation of the English default.
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
        return $default ? __( $default, 'wp-opengdpr' ) : '';
    }
}
