<?php
/**
 * Frontend renderer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Public {

    public static function init() {
        if ( ! WPOG_Settings::get( 'general', 'enabled' ) ) {
            return;
        }
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 1 );
        add_action( 'wp_head', array( __CLASS__, 'inline_styles' ), 2 );
        add_action( 'wp_footer', array( __CLASS__, 'render' ), 99 );
        add_shortcode( 'wpog_settings', array( __CLASS__, 'shortcode' ) );
    }

    public static function enqueue() {
        wp_enqueue_style(
            'wpog-banner',
            WPOG_PLUGIN_URL . 'public/assets/wpog-banner.css',
            array(),
            WPOG_VERSION
        );
        wp_enqueue_style(
            'wpog-popup',
            WPOG_PLUGIN_URL . 'public/assets/wpog-popup.css',
            array(),
            WPOG_VERSION
        );
        wp_enqueue_script(
            'wpog-consent',
            WPOG_PLUGIN_URL . 'public/assets/wpog-consent.js',
            array(),
            WPOG_VERSION,
            false
        );

        $duration = (int) WPOG_Settings::get( 'general', 'consent_duration', 180 );

        wp_localize_script( 'wpog-consent', 'WPOG_DATA', array(
            'ajax_url'     => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'wpog_consent' ),
            'cookie_name'  => WPOG_Consent::COOKIE_NAME,
            'cookie_id'    => WPOG_Consent::COOKIE_ID_NAME,
            'duration'     => $duration,
            'version'      => (string) WPOG_Settings::get( 'general', 'policy_version' ),
            'secure'       => is_ssl(),
            'samesite'     => 'Lax',
        ) );
    }

    public static function inline_styles() {
        $b   = WPOG_Settings::get( 'banner' );
        $p   = WPOG_Settings::get( 'popup' );
        $g   = WPOG_Settings::get( 'general' );

        $fab_bg   = ! empty( $g['fab_bg_color'] )   ? $g['fab_bg_color']   : $b['primary_bg'];
        $fab_text = ! empty( $g['fab_text_color'] )  ? $g['fab_text_color'] : $b['primary_text'];

        $css = "
        .wpog-banner{background:{$b['bg_color']};color:{$b['text_color']};font-size:{$b['font_size']}px;}
        .wpog-banner.wpog-pos-top{top:0;bottom:auto;}
        .wpog-btn{border-radius:{$b['border_radius']}px;font-size:{$b['font_size']}px;}
        .wpog-btn-primary{background:{$b['primary_bg']};color:{$b['primary_text']};}
        .wpog-btn-secondary{background:{$b['secondary_bg']};color:{$b['secondary_text']};}
        .wpog-popup-overlay{background:{$p['overlay_color']};}
        .wpog-popup{background:{$p['bg_color']};color:{$p['text_color']};max-width:{$p['width']}px;font-size:{$p['font_size']}px;border-radius:{$p['border_radius']}px;}
        .wpog-popup .wpog-btn-primary{background:{$p['primary_bg']};color:{$p['primary_text']};border-radius:{$p['border_radius']}px;}
        .wpog-popup .wpog-btn-secondary{background:{$p['secondary_bg']};color:{$p['secondary_text']};border-radius:{$p['border_radius']}px;}
        .wpog-toggle input:checked + .wpog-toggle-slider{background:{$p['toggle_on']};}
        .wpog-toggle-slider{background:{$p['toggle_off']};}
        .wpog-fab{background:{$fab_bg};color:{$fab_text};}
        ";
        echo "<style id='wpog-inline-style'>" . wp_strip_all_tags( $css ) . "</style>\n";
    }

    public static function render() {
        include WPOG_PLUGIN_DIR . 'templates/banner.php';
        include WPOG_PLUGIN_DIR . 'templates/popup.php';

        $g = WPOG_Settings::get( 'general' );
        if ( $g['fab_enabled'] ) {
            $pos   = in_array( $g['fab_position'], array( 'bottom-right', 'bottom-left' ), true ) ? $g['fab_position'] : 'bottom-right';
            $label = ! empty( $g['fab_label'] ) ? $g['fab_label'] : '🍪';
            $aria  = esc_attr( WPOG_Settings::string( 'fab_aria_label' ) ?: __( 'Open Cookie Settings', 'wp-opengdpr' ) );
            echo '<button id="wpog-fab" class="wpog-fab wpog-fab--' . esc_attr( $pos ) . '" '
                . 'data-wpog-action="customize" aria-label="' . $aria . '" hidden>'
                . esc_html( $label )
                . '</button>' . "\n";
        }
    }

    /**
     * Shortcode [wpog_settings] — renders a button that opens the cookie popup.
     *
     * Attributes:
     *   label  — button text (default: "Cookie Settings")
     *   class  — extra CSS classes
     */
    public static function shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'label' => WPOG_Settings::string( 'shortcode_btn_label' ) ?: __( 'Cookie Settings', 'wp-opengdpr' ),
            'class' => '',
        ), $atts, 'wpog_settings' );

        $classes = trim( 'wpog-settings-link ' . sanitize_html_class( $atts['class'], '' ) );

        return '<button type="button" class="' . esc_attr( $classes ) . '" data-wpog-action="customize">'
            . esc_html( $atts['label'] )
            . '</button>';
    }
}
