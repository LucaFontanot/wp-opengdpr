<?php
/**
 * Form Privacy Consent — module bootstrap.
 *
 * Independent from the cookie-consent system (different legal basis: GDPR
 * Art. 6/7 for the processing of data submitted via a form, vs. ePrivacy
 * Art. 5(3) for cookies). Disabling one does not disable the other.
 *
 * Responsibilities:
 *   - load the Contact Form 7 integration when CF7 is active;
 *   - enqueue the lightweight client-side validation assets, but only on pages
 *     that actually render a CF7 form (via the wpcf7_enqueue_scripts hook).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Form_Consent {

    public static function init() {
        if ( ! WPOG_Settings::get( 'form_consent', 'enabled' ) ) {
            return;
        }

        // CF7 integration — only if Contact Form 7 is active.
        if ( class_exists( 'WPCF7' ) ) {
            WPOG_CF7_Integration::init();
        }

        // WPForms integration — only if WPForms (Lite or Pro) is active.
        if ( function_exists( 'wpforms' ) ) {
            WPOG_WPForms_Integration::init();
        }

        // Enqueue the consent assets only when a CF7 form is present on the page.
        // WPForms enqueues on demand from within its injection callback.
        add_action( 'wpcf7_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
    }

    public static function enqueue_assets() {
        if ( wp_style_is( 'wpog-form-consent', 'enqueued' ) ) {
            return;
        }
        wp_enqueue_style(
            'wpog-form-consent',
            WPOG_PLUGIN_URL . 'public/assets/wpog-form-consent.css',
            array(),
            WPOG_VERSION
        );
        wp_enqueue_script(
            'wpog-form-consent',
            WPOG_PLUGIN_URL . 'public/assets/wpog-form-consent.js',
            array(),
            WPOG_VERSION,
            true
        );
    }

    /**
     * Build the user-facing main checkbox label with {privacy_url} resolved.
     * This is the EXACT text recorded as proof of consent.
     */
    public static function main_label() {
        $s           = WPOG_Settings::get( 'form_consent' );
        $privacy_url = (string) WPOG_Settings::get( 'general', 'privacy_url', '' );
        return str_replace( '{privacy_url}', esc_url( $privacy_url ), (string) $s['checkbox_main_label'] );
    }

    public static function marketing_label() {
        $s = WPOG_Settings::get( 'form_consent' );
        return (string) $s['checkbox_marketing_label'];
    }
}
