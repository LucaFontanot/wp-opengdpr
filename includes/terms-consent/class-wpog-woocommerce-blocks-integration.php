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

        // Log consent on every status transition that a block-checkout order can
        // reach. Using status hooks (rather than the Store API request hook) ensures
        // the order is fully persisted to the DB so all _wc_additional_field_ meta is
        // readable. The duplicate-guard on _wpog_blocks_consent_logged prevents
        // double-logging across multiple status transitions.
        add_action( 'woocommerce_order_status_pending',    array( __CLASS__, 'save_consent_data' ), 10, 1 );
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'save_consent_data' ), 10, 1 );
        add_action( 'woocommerce_order_status_completed',  array( __CLASS__, 'save_consent_data' ), 10, 1 );
        add_action( 'woocommerce_order_status_on_hold',    array( __CLASS__, 'save_consent_data' ), 10, 1 );

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
     * Entry point for the order-status hooks. Fetches the order by ID (safe because the
     * order is fully persisted by the time any status hook fires, so all
     * _wc_additional_field_ meta is readable from the DB). The duplicate-guard on
     * _wpog_blocks_consent_logged prevents double-logging across status transitions.
     *
     * @param int $order_id
     */
    public static function save_consent_data( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order instanceof WC_Order ) {
            self::do_log_consent( $order );
        }
    }

    /**
     * Core logging logic shared by all status-hook entry points.
     *
     * @param WC_Order $order
     */
    private static function do_log_consent( WC_Order $order ) {
        // Only handle orders placed through the WooCommerce Store API (block checkout).
        // Classic checkout orders are logged by WPOG_WooCommerce_Integration.
        if ( 'store-api' !== $order->get_created_via() ) {
            return;
        }

        // Prevent duplicate logging across multiple status transitions.
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

        // WooCommerce Additional Checkout Fields API stores 'order' location fields
        // in order meta with the prefix '_wc_other/' (CheckoutFields::OTHER_FIELDS_PREFIX).
        // The older '_wc_additional/' prefix (< 8.9) is also tried as a fallback.
        $terms_given   = self::get_acf_meta( $order, 'wpog/terms_consent' );
        $privacy_given = self::get_acf_meta( $order, 'wpog/privacy_consent' );
        $mkt_given     = self::get_acf_meta( $order, 'wpog/marketing_consent' );
        $age_given     = self::get_acf_meta( $order, 'wpog/age_consent' );

        // Terms acceptance — log regardless of value when the checkbox is shown.
        if ( ! empty( $s['require_terms_acceptance'] ) ) {
            WPOG_Terms_Consent_Logger::log( array(
                'order_id'         => $order_id,
                'user_id'          => $user_id,
                'document_type'    => 'terms',
                'document_version' => $terms_version,
                'document_hash'    => $document_hash,
                'consent_given'    => $terms_given ? 1 : 0,
                'context'          => 'checkout',
                'integration'      => 'woocommerce_blocks',
                'page_url'         => $checkout_url,
            ) );
        }

        // Privacy acceptance — log regardless of value when the checkbox is shown.
        if ( ! empty( $s['require_privacy_acceptance'] ) ) {
            WPOG_Terms_Consent_Logger::log( array(
                'order_id'         => $order_id,
                'user_id'          => $user_id,
                'document_type'    => 'privacy',
                'document_version' => (string) WPOG_Settings::get( 'form_consent', 'privacy_policy_version', '1.0' ),
                'document_hash'    => '',
                'consent_given'    => $privacy_given ? 1 : 0,
                'context'          => 'checkout',
                'integration'      => 'woocommerce_blocks',
                'page_url'         => $checkout_url,
            ) );
        }

        // Marketing — always record both given and not-given when the checkbox is shown.
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

        // Age verification — log regardless of value when the checkbox is shown.
        if ( ! empty( $s['require_age_verification'] ) ) {
            WPOG_Terms_Consent_Logger::log( array(
                'order_id'         => $order_id,
                'user_id'          => $user_id,
                'document_type'    => 'age',
                'document_version' => '1.0',
                'document_hash'    => '',
                'consent_given'    => $age_given ? 1 : 0,
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

        // Mark as logged — prevents duplicate entries across hooks and status transitions.
        $order->update_meta_data( '_wpog_blocks_consent_logged', '1' );
        $order->save();
    }

    /**
     * Read a WooCommerce Additional Checkout Field value from order meta.
     *
     * WooCommerce stores 'order' location fields under '_wc_other/{field_id}'.
     * Before WC 8.9 the prefix was '_wc_additional/{field_id}'.
     * Some older WooCommerce versions used '_wc_additional_field_{field_id}'.
     * We try all three so the code works across WC versions.
     *
     * @param WC_Order $order
     * @param string   $field_id  e.g. 'wpog/terms_consent'
     * @return bool
     */
    private static function get_acf_meta( WC_Order $order, string $field_id ): bool {
        // Current (WC 8.9+): '_wc_other/'
        $value = $order->get_meta( '_wc_other/' . $field_id, true );
        if ( '' !== $value && null !== $value ) {
            return (bool) $value;
        }
        // Legacy (WC 8.6–8.8): '_wc_additional/'
        $value = $order->get_meta( '_wc_additional/' . $field_id, true );
        if ( '' !== $value && null !== $value ) {
            return (bool) $value;
        }
        // Older attempt with underscore separator.
        $safe  = str_replace( '/', '_', $field_id );
        $value = $order->get_meta( '_wc_additional_field_' . $safe, true );
        return (bool) $value;
    }
}
