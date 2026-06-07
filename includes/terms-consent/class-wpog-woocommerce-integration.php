<?php
/**
 * WooCommerce integration for the Terms & Conditions module.
 *
 * Adds, validates and logs the consent checkboxes (Terms, Privacy, Marketing,
 * Age) on the *classic* WooCommerce checkout, stores the proof both in the
 * dedicated log table and as order meta, surfaces the recorded consent in the
 * admin order screen and order list, and warns the admin when the Block
 * (Gutenberg) checkout is in use.
 *
 * Checkout field names (read raw from $_POST, compared against the string '1'):
 *   wpog_terms_consent        — Terms & Conditions (mandatory when required)
 *   wpog_privacy_consent_wc   — Privacy policy (mandatory when required)
 *   wpog_marketing_consent_wc — Marketing (always optional)
 *   wpog_age_consent          — Age verification (mandatory when required)
 *
 * HPOS: all order meta is written through $order->update_meta_data() /
 * $order->save(), never update_post_meta(), so the module is HPOS compatible.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_WooCommerce_Integration {

    const FIELD_TERMS     = 'wpog_terms_consent';
    const FIELD_PRIVACY   = 'wpog_privacy_consent_wc';
    const FIELD_MARKETING = 'wpog_marketing_consent_wc';
    const FIELD_AGE       = 'wpog_age_consent';

    public static function init() {
        $s = WPOG_Settings::get( 'terms_consent' );
        if ( empty( $s['enabled'] ) || empty( $s['woocommerce_enabled'] ) ) {
            return;
        }

        // Optionally disable WooCommerce's own native terms checkbox.
        if ( ! empty( $s['wc_replace_native_terms'] ) ) {
            add_filter( 'woocommerce_checkout_show_terms', '__return_false' );
        }

        // Inject the consent checkboxes at the chosen position.
        $position = $s['wc_checkout_position'] ?? 'before_submit';
        if ( 'after_terms' === $position ) {
            add_action( 'woocommerce_checkout_after_terms_and_conditions', array( __CLASS__, 'inject_consent_checkboxes' ), 10 );
        } elseif ( 'before_terms' === $position ) {
            add_action( 'woocommerce_checkout_before_terms_and_conditions', array( __CLASS__, 'inject_consent_checkboxes' ), 10 );
        } else {
            add_action( 'woocommerce_review_order_before_submit', array( __CLASS__, 'inject_consent_checkboxes' ), 10 );
        }

        // Server-side validation — blocks order creation when consent is missing.
        add_action( 'woocommerce_checkout_process', array( __CLASS__, 'validate_consent' ) );

        // Save log + order meta once the order has been created.
        add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'save_consent_data' ), 10, 1 );

        // Show the recorded consent inside the admin order screen.
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'display_consent_in_order_admin' ), 10, 1 );

        // "GDPR consents" column in the order list (legacy posts + HPOS).
        if ( ! empty( $s['wc_order_column'] ) ) {
            add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_order_column' ), 20 );
            add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_order_column_legacy' ), 20, 2 );
            add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_order_column' ), 20 );
            add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'render_order_column_hpos' ), 20, 2 );
        }

        // Registration consent (My Account page) is handled by
        // WPOG_Terms_Registration; checkout-account creation is also covered
        // there via the shared woocommerce_created_customer hook.

        // Block checkout compatibility notice.
        add_action( 'admin_notices', array( __CLASS__, 'maybe_show_blocks_notice' ) );
    }

    /* ---------- rendering ---------- */

    /**
     * Inject the consent checkboxes into the checkout. Field names are dedicated
     * so they never collide with WooCommerce's own native 'terms' checkbox.
     */
    public static function inject_consent_checkboxes() {
        $s          = WPOG_Settings::get( 'terms_consent' );
        $allowed    = array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) );
        $terms_url  = WPOG_Terms_Consent::terms_url();

        WPOG_Terms_Consent::enqueue_assets();

        echo '<div class="wpog-terms-consent-wrapper wpog-woocommerce">';

        // Optional collapsible inline Terms text.
        if ( ! empty( $s['wc_show_terms_inline'] ) ) {
            $content = WPOG_Terms_Consent::terms_content();
            if ( '' !== $content ) {
                echo '<details class="wpog-terms-inline"><summary>'
                    . esc_html__( 'Read the Terms &amp; Conditions', 'wp-opengdpr' )
                    . '</summary><div class="wpog-terms-inline-body">'
                    . wp_kses_post( apply_filters( 'the_content', $content ) )
                    . '</div></details>';
            }
        }

        // Terms & Conditions (mandatory when required).
        if ( ! empty( $s['require_terms_acceptance'] ) ) {
            printf(
                '<p class="form-row wpog-consent-field wpog-consent-terms validate-required">
                    <label class="woocommerce-form__label checkbox">
                        <input type="checkbox" name="%1$s" value="1" class="input-checkbox" required aria-required="true">
                        <span class="wpog-consent-label" data-wpog-url="%3$s">%2$s</span>
                    </label>
                </p>',
                esc_attr( self::FIELD_TERMS ),
                wp_kses( WPOG_Terms_Consent::terms_label(), $allowed ),
                esc_url( $terms_url )
            );
        }

        // Privacy (mandatory when required).
        if ( ! empty( $s['require_privacy_acceptance'] ) ) {
            printf(
                '<p class="form-row wpog-consent-field wpog-consent-privacy validate-required">
                    <label class="woocommerce-form__label checkbox">
                        <input type="checkbox" name="%1$s" value="1" class="input-checkbox" required aria-required="true">
                        <span class="wpog-consent-label" data-wpog-url="%3$s">%2$s</span>
                    </label>
                </p>',
                esc_attr( self::FIELD_PRIVACY ),
                wp_kses( WPOG_Terms_Consent::privacy_label(), $allowed ),
                esc_url( WPOG_Terms_Consent::privacy_url() )
            );
        }

        // Marketing (always optional).
        if ( ! empty( $s['show_marketing_consent'] ) ) {
            printf(
                '<p class="form-row wpog-consent-field wpog-consent-marketing">
                    <label class="woocommerce-form__label checkbox">
                        <input type="checkbox" name="%1$s" value="1" class="input-checkbox">
                        <span class="wpog-consent-label">%2$s</span>
                    </label>
                </p>',
                esc_attr( self::FIELD_MARKETING ),
                wp_kses( WPOG_Terms_Consent::marketing_label(), $allowed )
            );
        }

        // Age verification (mandatory when required).
        if ( ! empty( $s['require_age_verification'] ) ) {
            printf(
                '<p class="form-row wpog-consent-field wpog-consent-age validate-required">
                    <label class="woocommerce-form__label checkbox">
                        <input type="checkbox" name="%1$s" value="1" class="input-checkbox" required aria-required="true">
                        <span class="wpog-consent-label">%2$s</span>
                    </label>
                </p>',
                esc_attr( self::FIELD_AGE ),
                esc_html( WPOG_Terms_Consent::age_label() )
            );
        }

        echo '</div>';
    }

    /* ---------- validation ---------- */

    /**
     * Block the order (via wc_add_notice) when a mandatory consent is missing.
     * Marketing consent is never validated — its absence must not block the order.
     */
    public static function validate_consent() {
        $s = WPOG_Settings::get( 'terms_consent' );

        if ( ! empty( $s['require_terms_acceptance'] ) && ! self::posted( self::FIELD_TERMS ) ) {
            wc_add_notice(
                esc_html( WPOG_Settings::string( 'terms_terms_error_message' ) ),
                'error'
            );
        }

        if ( ! empty( $s['require_privacy_acceptance'] ) && ! self::posted( self::FIELD_PRIVACY ) ) {
            wc_add_notice(
                esc_html( WPOG_Settings::string( 'terms_privacy_error_message' ) ),
                'error'
            );
        }

        if ( ! empty( $s['require_age_verification'] ) && ! self::posted( self::FIELD_AGE ) ) {
            $min_age = absint( $s['minimum_age'] ?? 18 );
            $message = str_replace(
                '{min_age}',
                (string) $min_age,
                WPOG_Settings::string( 'terms_age_error_message' )
            );
            wc_add_notice( esc_html( $message ), 'error' );
        }
    }

    /* ---------- logging ---------- */

    /**
     * Persist the consent proof to the dedicated log table and (optionally) as
     * order meta. Runs on woocommerce_checkout_order_created, i.e. only after
     * validation has passed and the order exists.
     *
     * @param WC_Order $order
     */
    public static function save_consent_data( $order ) {
        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $s = WPOG_Settings::get( 'terms_consent' );
        if ( empty( $s['log_enabled'] ) ) {
            return;
        }

        $order_id      = $order->get_id();
        $user_id       = $order->get_user_id(); // 0 for guests.
        $terms_given   = self::posted( self::FIELD_TERMS );
        $privacy_given = self::posted( self::FIELD_PRIVACY );
        $mkt_given     = self::posted( self::FIELD_MARKETING );
        $age_given     = self::posted( self::FIELD_AGE );
        $terms_version = (string) ( $s['terms_version'] ?? '1.0' );
        $document_hash = WPOG_Terms_Consent::terms_hash();
        $checkout_url  = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '';

        // Terms — log regardless of value when the checkbox is shown.
        // Validation (validate_consent) already blocked the order if terms were required
        // and not given, so consent_given=0 here can only happen for non-required scenarios.
        if ( ! empty( $s['require_terms_acceptance'] ) ) {
            WPOG_Terms_Consent_Logger::log( array(
                'order_id'         => $order_id,
                'user_id'          => $user_id,
                'document_type'    => 'terms',
                'document_version' => $terms_version,
                'document_hash'    => $document_hash,
                'consent_given'    => $terms_given ? 1 : 0,
                'context'          => 'checkout',
                'integration'      => 'woocommerce',
                'page_url'         => $checkout_url,
            ) );
        }

        // Privacy — log regardless of value when the checkbox is shown.
        if ( ! empty( $s['require_privacy_acceptance'] ) ) {
            WPOG_Terms_Consent_Logger::log( array(
                'order_id'         => $order_id,
                'user_id'          => $user_id,
                'document_type'    => 'privacy',
                'document_version' => (string) WPOG_Settings::get( 'form_consent', 'privacy_policy_version', '1.0' ),
                'document_hash'    => '',
                'consent_given'    => $privacy_given ? 1 : 0,
                'context'          => 'checkout',
                'integration'      => 'woocommerce',
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
                'integration'      => 'woocommerce',
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
                'integration'      => 'woocommerce',
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

    /* ---------- admin order screen ---------- */

    /**
     * @param WC_Order $order
     */
    public static function display_consent_in_order_admin( $order ) {
        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $terms     = $order->get_meta( '_wpog_terms_consent' );
        $privacy   = $order->get_meta( '_wpog_privacy_consent' );
        $marketing = $order->get_meta( '_wpog_marketing_consent' );
        $version   = $order->get_meta( '_wpog_terms_version' );
        $date      = $order->get_meta( '_wpog_consent_date' );
        $ip        = $order->get_meta( '_wpog_consent_ip' );

        if ( '' === $terms && '' === $privacy ) {
            return; // Order predates the plugin.
        }

        $yes = '<span style="color:#46b450;">&#10004;</span>';
        $no  = '<span style="color:#dc3232;">&#10008;</span>';

        echo '<div class="wpog-order-consent-box" style="margin-top:1em; padding:0.75em; background:#f9f9f9; border:1px solid #e0e0e0; border-radius:4px;">';
        echo '<h4 style="margin:0 0 0.5em;">&#128274; ' . esc_html__( 'GDPR consents', 'wp-opengdpr' ) . '</h4>';
        echo '<p style="margin:0.2em 0">' . ( '1' === $terms ? $yes : $no ) . ' ' . esc_html__( 'Terms & Conditions', 'wp-opengdpr' ) . ' (v' . esc_html( $version ) . ')</p>';
        echo '<p style="margin:0.2em 0">' . ( '1' === $privacy ? $yes : $no ) . ' ' . esc_html__( 'Privacy Policy', 'wp-opengdpr' ) . '</p>';
        echo '<p style="margin:0.2em 0">' . ( '1' === $marketing ? $yes : $no ) . ' ' . esc_html__( 'Marketing', 'wp-opengdpr' ) . '</p>';
        echo '<p style="margin:0.2em 0; font-size:0.85em; color:#666">' . esc_html( $date ) . ' &mdash; IP: ' . esc_html( $ip ) . '</p>';
        echo '</div>';
    }

    /* ---------- order list column ---------- */

    public static function add_order_column( $columns ) {
        $new = array();
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'order_status' === $key ) {
                $new['wpog_consents'] = __( 'GDPR consents', 'wp-opengdpr' );
            }
        }
        if ( ! isset( $new['wpog_consents'] ) ) {
            $new['wpog_consents'] = __( 'GDPR consents', 'wp-opengdpr' );
        }
        return $new;
    }

    /** Legacy (post-based) orders. */
    public static function render_order_column_legacy( $column, $post_id ) {
        if ( 'wpog_consents' !== $column ) {
            return;
        }
        $order = wc_get_order( $post_id );
        self::render_consent_icons( $order );
    }

    /** HPOS (custom order tables). */
    public static function render_order_column_hpos( $column, $order ) {
        if ( 'wpog_consents' !== $column ) {
            return;
        }
        self::render_consent_icons( $order );
    }

    private static function render_consent_icons( $order ) {
        if ( ! $order instanceof WC_Order ) {
            echo '&mdash;';
            return;
        }
        $terms   = $order->get_meta( '_wpog_terms_consent' );
        $privacy = $order->get_meta( '_wpog_privacy_consent' );
        if ( '' === $terms && '' === $privacy ) {
            echo '&mdash;';
            return;
        }
        $yes = '<span style="color:#46b450;" title="' . esc_attr__( 'Given', 'wp-opengdpr' ) . '">&#10004;</span>';
        $no  = '<span style="color:#dc3232;" title="' . esc_attr__( 'Not given', 'wp-opengdpr' ) . '">&#10008;</span>';
        echo 'T&amp;C ' . ( '1' === $terms ? $yes : $no ) . ' &nbsp; ';
        echo esc_html__( 'Privacy', 'wp-opengdpr' ) . ' ' . ( '1' === $privacy ? $yes : $no );
    }

    /* ---------- block checkout notice ---------- */

    public static function maybe_show_blocks_notice() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        $checkout_page_id = (int) get_option( 'woocommerce_checkout_page_id' );
        if ( ! $checkout_page_id ) {
            return;
        }
        $checkout_page = get_post( $checkout_page_id );
        if ( $checkout_page && function_exists( 'has_block' ) && has_block( 'woocommerce/checkout', $checkout_page->post_content ) ) {
            if ( function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
                echo '<div class="notice notice-info is-dismissible"><p><strong>WP OpenGDPR:</strong> '
                    . esc_html__( 'Your checkout uses the WooCommerce Block Checkout. WP OpenGDPR will register the Terms & Conditions consent fields via the WooCommerce Additional Checkout Fields API automatically.', 'wp-opengdpr' )
                    . '</p></div>';
            } else {
                echo '<div class="notice notice-warning is-dismissible"><p><strong>WP OpenGDPR:</strong> '
                    . esc_html__( 'Your checkout uses the WooCommerce Block Checkout. Block Checkout consent fields require WooCommerce 8.7 or later. Please update WooCommerce, or switch to the Classic Checkout shortcode [woocommerce_checkout].', 'wp-opengdpr' )
                    . '</p></div>';
            }
        }
    }

    /* ---------- helpers ---------- */

    /**
     * True when a checkbox field was posted with the exact value '1'.
     */
    private static function posted( $field ) {
        return isset( $_POST[ $field ] ) && '1' === (string) wp_unslash( $_POST[ $field ] ); // phpcs:ignore WordPress.Security.NonceVerification -- runs inside WooCommerce's nonce-protected checkout flow.
    }
}
