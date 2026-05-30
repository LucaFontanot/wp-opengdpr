<?php
/**
 * Settings — wrappers around WP options with defaults and translation helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Settings {

    const DB_VERSION = '1.2.0';

    public static function defaults( $group ) {
        $defaults = array(
            'general' => array(
                'enabled'              => 1,
                'consent_duration'     => 180,
                'policy_version'       => '1.0',
                'privacy_url'          => '',
                'cookie_url'           => '',
                'eu_only'              => 0,
                'log_enabled'          => 1,
                'anonymize_ip'         => 1,
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
        );
        return isset( $defaults[ $group ] ) ? $defaults[ $group ] : array();
    }

    public static function get( $group, $key = null, $fallback = null ) {
        $opt = get_option( 'wpog_' . $group, array() );
        if ( ! is_array( $opt ) ) {
            $opt = array();
        }
        $opt = array_merge( self::defaults( $group ), $opt );
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
    }

    public static function replace( $group, $values ) {
        update_option( 'wpog_' . $group, $values );
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
        );
    }

    /**
     * Get a user-facing string, with admin override > .po translation > English default.
     */
    public static function string( $key ) {
        $overrides = get_option( 'wpog_translations', array() );
        if ( is_array( $overrides ) && ! empty( $overrides[ $key ] ) ) {
            return $overrides[ $key ];
        }
        $defaults = self::default_strings();
        $default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
        // Allow .po/.mo translation of the English default.
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
        return $default ? __( $default, 'wp-opengdpr' ) : '';
    }
}
