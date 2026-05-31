<?php
/**
 * WooCommerce Block Checkout integration for the Terms & Conditions module.
 *
 * Registers the same four consent checkboxes (Terms, Privacy, Marketing, Age)
 * used by the classic checkout into the WooCommerce Block Checkout via the
 * Additional Checkout Fields API (WC 8.7+,
 * woocommerce_register_additional_checkout_field).
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
     * WooCommerce is active.
     */
    public static function init() {
        $s = WPOG_Settings::get( 'terms_consent' );
        if ( empty( $s['enabled'] ) || empty( $s['woocommerce_enabled'] ) ) {
            return;
        }

        add_action( 'woocommerce_init', array( __CLASS__, 'register_fields' ) );
        // Fire after payment is confirmed. Also hook 'completed' for virtual/free orders
        // that skip 'processing'. A duplicate-guard on order meta prevents double logging.
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'save_consent_data' ), 10, 1 );
        add_action( 'woocommerce_order_status_completed',  array( __CLASS__, 'save_consent_data' ), 10, 1 );

        // Suppress native WC Terms block when our fields handle consent.
        add_filter( 'render_block', array( __CLASS__, 'suppress_native_terms_block' ), 10, 2 );
    }

    /* ---------- native Terms block suppression ---------- */

    public static function suppress_native_terms_block( $block_content, $block ) {
        if ( 'woocommerce/checkout-terms-block' !== $block['blockName'] ) {
            return $block_content;
        }
        if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
            return $block_content;
        }
        $s = WPOG_Settings::get( 'terms_consent' );
        if (
            ! empty( $s['require_terms_acceptance'] ) ||
            ! empty( $s['require_privacy_acceptance'] ) ||
            ! empty( $s['show_marketing_consent'] ) ||
            ! empty( $s['require_age_verification'] )
        ) {
            return '';
        }
        return $block_content;
    }

    /* ---------- field registration ---------- */

    /**
     * Register each consent field with the WC Additional Checkout Fields API.
     *
     * The WooCommerce Store API serializes the label as a plain string before
     * it reaches the React CheckboxControl — HTML tags are not rendered.
     * We convert <a href="URL">text</a> to "text (URL)" so the link destination
     * is still visible to the user without raw markup showing.
     */
    public static function register_fields() {
        if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
            return;
        }

        $s = WPOG_Settings::get( 'terms_consent' );

        if ( ! empty( $s['require_terms_acceptance'] ) ) {
            woocommerce_register_additional_checkout_field( array(
                'id'       => 'wpog/terms_consent',
                'label'    => self::plain_label( WPOG_Terms_Consent::terms_label() ),
                'location' => 'order',
                'type'     => 'checkbox',
                'required' => true,
            ) );
        }

        if ( ! empty( $s['require_privacy_acceptance'] ) ) {
            woocommerce_register_additional_checkout_field( array(
                'id'       => 'wpog/privacy_consent',
                'label'    => self::plain_label( WPOG_Terms_Consent::privacy_label() ),
                'location' => 'order',
                'type'     => 'checkbox',
                'required' => true,
            ) );
        }

        if ( ! empty( $s['show_marketing_consent'] ) ) {
            woocommerce_register_additional_checkout_field( array(
                'id'       => 'wpog/marketing_consent',
                'label'    => self::plain_label( WPOG_Terms_Consent::marketing_label() ),
                'location' => 'order',
                'type'     => 'checkbox',
                'required' => false,
            ) );
        }

        if ( ! empty( $s['require_age_verification'] ) ) {
            woocommerce_register_additional_checkout_field( array(
                'id'       => 'wpog/age_consent',
                'label'    => esc_html( WPOG_Terms_Consent::age_label() ),
                'location' => 'order',
                'type'     => 'checkbox',
                'required' => true,
            ) );
        }
    }

    /**
     * Convert an HTML label to plain text for the WC block checkout API.
     * <a href="URL">text</a> → "text (URL)"; empty href omits the URL.
     * All remaining tags are stripped.
     */
    private static function plain_label( $html ) {
        $plain = preg_replace_callback(
            '/<a\s[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is',
            static function ( $m ) {
                $url  = trim( $m[1] );
                $text = wp_strip_all_tags( $m[2] );
                return $url ? "{$text} ({$url})" : $text;
            },
            $html
        );
        return wp_strip_all_tags( $plain ?? $html );
    }

    /* ---------- logging ---------- */

    /**
     * Persist the consent proof to the log table and optionally to order meta.
     *
     * Hooked on woocommerce_order_status_processing (paid orders) and
     * woocommerce_order_status_completed (virtual / free orders that skip
     * the 'processing' status). A meta flag prevents double-logging when an
     * order moves through both statuses.
     *
     * The WC Additional Checkout Fields API writes _wc_additional_field_wpog/*
     * order meta when the customer submits the block checkout, so by the time
     * either status hook fires the values are already in the order.
     *
     * @param int $order_id The order ID.
     */
    public static function save_consent_data( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order instanceof WC_Order ) {
            return;
        }

        // Prevent duplicate logging when the order passes through both
        // 'processing' and 'completed' statuses.
        if ( $order->get_meta( '_wpog_blocks_consent_logged' ) ) {
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
        }

        // Mark as logged — prevents duplicate entries if order status changes again.
        $order->update_meta_data( '_wpog_blocks_consent_logged', '1' );
        $order->save();
    }
}
