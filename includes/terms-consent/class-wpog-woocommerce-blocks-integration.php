<?php
/**
 * WooCommerce Block Checkout integration for the Terms & Conditions module.
 *
 * Registers the same four consent checkboxes (Terms, Privacy, Marketing, Age)
 * used by the classic checkout into the WooCommerce Block Checkout via the
 * Additional Checkout Fields API (WC 8.6+, wc_register_additional_checkout_field).
 *
 * The classic checkout integration (WPOG_WooCommerce_Integration) is completely
 * separate and is not modified by this class.
 *
 * Field IDs (namespaced as required by the WC API):
 *   wpog/terms_consent     — Terms & Conditions (mandatory when required)
 *   wpog/privacy_consent   — Privacy policy (mandatory when required)
 *   wpog/marketing_consent — Marketing (always optional)
 *   wpog/age_consent       — Age verification (mandatory when required)
 *
 * Saved values are read from order meta with the prefix _wc_additional_field_
 * that WooCommerce writes automatically for block checkout fields:
 *   _wc_additional_field_wpog/terms_consent
 *   _wc_additional_field_wpog/privacy_consent
 *   _wc_additional_field_wpog/marketing_consent
 *   _wc_additional_field_wpog/age_consent
 *
 * HPOS: all order meta written through $order->update_meta_data() / save().
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_WooCommerce_Blocks_Integration {

    /**
     * Boot the integration. Called from WPOG_Terms_Consent::init() only when
     * WooCommerce is active. Guards against missing WC 8.6+ API and disabled
     * settings before registering any hooks.
     */
    public static function init() {
        if ( ! function_exists( 'wc_register_additional_checkout_field' ) ) {
            return;
        }

        $s = WPOG_Settings::get( 'terms_consent' );
        if ( empty( $s['enabled'] ) || empty( $s['woocommerce_enabled'] ) ) {
            return;
        }

        add_action( 'woocommerce_init', array( __CLASS__, 'register_fields' ) );
        add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'save_consent_data' ), 10, 2 );
    }

    /* ---------- field registration ---------- */

    /**
     * Register each consent field with the WC Additional Checkout Fields API.
     * Fields are registered conditionally based on the current settings.
     * Labels are stripped of HTML tags because the block checkout renders
     * labels as plain text (no raw HTML in the block editor field labels).
     */
    public static function register_fields() {
        $s = WPOG_Settings::get( 'terms_consent' );

        if ( ! empty( $s['require_terms_acceptance'] ) ) {
            wc_register_additional_checkout_field( array(
                'id'                => 'wpog/terms_consent',
                'label'             => wp_strip_all_tags( WPOG_Terms_Consent::terms_label() ),
                'location'          => 'order',
                'type'              => 'checkbox',
                'required'          => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ) );
        }

        if ( ! empty( $s['require_privacy_acceptance'] ) ) {
            wc_register_additional_checkout_field( array(
                'id'       => 'wpog/privacy_consent',
                'label'    => wp_strip_all_tags( WPOG_Terms_Consent::privacy_label() ),
                'location' => 'order',
                'type'     => 'checkbox',
                'required' => true,
            ) );
        }

        if ( ! empty( $s['show_marketing_consent'] ) ) {
            wc_register_additional_checkout_field( array(
                'id'       => 'wpog/marketing_consent',
                'label'    => wp_strip_all_tags( WPOG_Terms_Consent::marketing_label() ),
                'location' => 'order',
                'type'     => 'checkbox',
                'required' => false,
            ) );
        }

        if ( ! empty( $s['require_age_verification'] ) ) {
            wc_register_additional_checkout_field( array(
                'id'       => 'wpog/age_consent',
                'label'    => wp_strip_all_tags( WPOG_Terms_Consent::age_label() ),
                'location' => 'order',
                'type'     => 'checkbox',
                'required' => true,
            ) );
        }
    }

    /* ---------- logging ---------- */

    /**
     * Persist the consent proof to the log table and optionally to order meta.
     * Runs on woocommerce_store_api_checkout_update_order_from_request, i.e.
     * only after WooCommerce's own field validation has passed.
     *
     * @param WC_Order        $order   The order being created / updated.
     * @param WP_REST_Request $request The Store API request.
     */
    public static function save_consent_data( $order, $request ) {
        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $s = WPOG_Settings::get( 'terms_consent' );
        if ( empty( $s['log_enabled'] ) ) {
            return;
        }

        $order_id      = $order->get_id();
        $user_id       = $order->get_user_id(); // 0 for guests.
        $terms_version = (string) ( $s['terms_version'] ?? '1.0' );
        $document_hash = WPOG_Terms_Consent::terms_hash();
        $checkout_url  = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '';

        // Read field values stored by WC under the _wc_additional_field_ prefix.
        $terms_given   = (bool) $order->get_meta( '_wc_additional_field_wpog/terms_consent' );
        $privacy_given = (bool) $order->get_meta( '_wc_additional_field_wpog/privacy_consent' );
        $mkt_given     = (bool) $order->get_meta( '_wc_additional_field_wpog/marketing_consent' );
        $age_given     = (bool) $order->get_meta( '_wc_additional_field_wpog/age_consent' );

        // Terms acceptance.
        if ( $terms_given ) {
            WPOG_Terms_Consent_Logger::log( array(
                'order_id'         => $order_id,
                'user_id'          => $user_id,
                'document_type'    => 'terms',
                'document_version' => $terms_version,
                'document_hash'    => $document_hash,
                'consent_given'    => 1,
                'context'          => 'checkout',
                'integration'      => 'woocommerce_blocks',
                'page_url'         => $checkout_url,
            ) );
        }

        // Privacy acceptance.
        if ( $privacy_given ) {
            WPOG_Terms_Consent_Logger::log( array(
                'order_id'         => $order_id,
                'user_id'          => $user_id,
                'document_type'    => 'privacy',
                'document_version' => (string) WPOG_Settings::get( 'form_consent', 'privacy_policy_version', '1.0' ),
                'document_hash'    => '',
                'consent_given'    => 1,
                'context'          => 'checkout',
                'integration'      => 'woocommerce_blocks',
                'page_url'         => $checkout_url,
            ) );
        }

        // Marketing (record both given and not-given whenever the checkbox is shown).
        if ( ! empty( $s['show_marketing_consent'] ) ) {
            WPOG_Terms_Consent_Logger::log( array(
                'order_id'         => $order_id,
                'user_id'          => $user_id,
                'document_type'    => 'marketing',
                'document_version' => '1.0',
                'document_hash'    => '',
                'consent_given'    => $mkt_given ? 1 : 0,
                'context'          => 'checkout',
                'integration'      => 'woocommerce_blocks',
                'page_url'         => $checkout_url,
            ) );
        }

        // Age verification.
        if ( ! empty( $s['require_age_verification'] ) && $age_given ) {
            WPOG_Terms_Consent_Logger::log( array(
                'order_id'         => $order_id,
                'user_id'          => $user_id,
                'document_type'    => 'age',
                'document_version' => '1.0',
                'document_hash'    => '',
                'consent_given'    => 1,
                'context'          => 'checkout',
                'integration'      => 'woocommerce_blocks',
                'page_url'         => $checkout_url,
            ) );
        }

        // Order meta (proof linked to the order) — HPOS-safe.
        if ( ! empty( $s['log_save_to_order_meta'] ) ) {
            $order->update_meta_data( '_wpog_terms_consent',     $terms_given ? '1' : '0' );
            $order->update_meta_data( '_wpog_privacy_consent',   $privacy_given ? '1' : '0' );
            $order->update_meta_data( '_wpog_marketing_consent', $mkt_given ? '1' : '0' );
            $order->update_meta_data( '_wpog_age_consent',       $age_given ? '1' : '0' );
            $order->update_meta_data( '_wpog_terms_version',     $terms_version );
            $order->update_meta_data( '_wpog_terms_hash',        $document_hash );
            $order->update_meta_data( '_wpog_consent_date',      current_time( 'mysql' ) );
            $order->update_meta_data( '_wpog_consent_ip',        WPOG_Logger::anonymize_ip( WPOG_Logger::get_client_ip() ) );
            $order->save();
        }
    }
}
