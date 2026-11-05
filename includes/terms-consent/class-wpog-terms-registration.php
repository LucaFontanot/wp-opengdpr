<?php
/**
 * Account-registration consent (R1) for the Terms & Conditions module.
 *
 * Renders the Privacy (mandatory) and Marketing (optional) consent checkboxes on
 * the WooCommerce My Account registration form, validates the mandatory consent
 * before the account is created, and — once the customer exists — stores the
 * consent both in the user profile (user meta) and in the dedicated audit-trail
 * log table (WPOG_Terms_Consent_Logger).
 *
 * Previously this lived inside WPOG_WooCommerce_Integration; it was extracted to
 * a dedicated class so registration, checkout and My-Account concerns stay
 * separate and independently testable.
 *
 * Hooks (in order):
 *   woocommerce_register_form        → render the checkbox(es)
 *   woocommerce_registration_errors  → validate (WP_Error filter)
 *   woocommerce_created_customer     → save user meta + write the log row
 *
 * Field names (read raw from $_POST, compared against the string '1'):
 *   wpog_privacy_consent_reg    — Privacy policy (mandatory when required)
 *   wpog_marketing_consent_reg  — Marketing (always optional)
 *
 * User-meta keys written here are shared with WPOG_Terms_My_Account, which lets
 * the customer review and revoke the optional consent later.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Terms_Registration {

    const FIELD_PRIVACY   = 'wpog_privacy_consent_reg';
    const FIELD_MARKETING = 'wpog_marketing_consent_reg';

    /** Shared user-meta keys (also read by WPOG_Terms_My_Account). */
    const META_PRIVACY   = '_wpog_consent_privacy';
    const META_MARKETING = '_wpog_consent_marketing';
    const META_DATE      = '_wpog_consent_date';
    const META_IP        = '_wpog_consent_ip';

    public static function init() {
        $s = WPOG_Settings::get( 'terms_consent' );
        if ( empty( $s['enabled'] ) || empty( $s['woocommerce_enabled'] ) || empty( $s['wc_registration_consent'] ) ) {
            return;
        }

        add_action( 'woocommerce_register_form', array( __CLASS__, 'render_checkboxes' ) );
        add_filter( 'woocommerce_registration_errors', array( __CLASS__, 'validate' ), 10, 3 );
        add_action( 'woocommerce_created_customer', array( __CLASS__, 'save' ), 10, 1 );
    }

    /* ---------- rendering ---------- */

    /**
     * Render the Privacy (mandatory) and Marketing (optional) checkboxes.
     * Terms acceptance is checkout-specific and is deliberately not shown here.
     */
    public static function render_checkboxes() {
        $s       = WPOG_Settings::get( 'terms_consent' );
        $allowed = array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) );

        WPOG_Terms_Consent::enqueue_assets();

        echo '<div class="wpog-terms-consent-wrapper wpog-registration">';

        if ( ! empty( $s['require_privacy_acceptance'] ) ) {
            printf(
                '<p class="form-row wpog-consent-field wpog-consent-privacy validate-required">
                    <label class="woocommerce-form__label checkbox">
                        <input type="checkbox" name="%1$s" value="1" class="input-checkbox" required aria-required="true">
                        <span class="wpog-consent-label">%2$s</span>
                    </label>
                </p>',
                esc_attr( self::FIELD_PRIVACY ),
                wp_kses( WPOG_Terms_Consent::privacy_label(), $allowed )
            );
        }

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

        echo '</div>';
    }

    /* ---------- validation ---------- */

    /**
     * Block account creation when the mandatory privacy consent is missing.
     * Fires on the woocommerce_registration_errors WP_Error filter, before the
     * user row is created.
     *
     * @param  WP_Error $errors
     * @param  string   $username
     * @param  string   $email
     * @return WP_Error
     */
    public static function validate( $errors, $username = '', $email = '' ) {
        if ( ! $errors instanceof WP_Error ) {
            $errors = new WP_Error();
        }
        // woocommerce_registration_errors also fires when an account is created
        // during checkout (classic and Store API), where these fields are not
        // rendered: checkout consent is validated by the checkout integrations.
        if ( ! self::is_account_registration_form() ) {
            return $errors;
        }
        $s = WPOG_Settings::get( 'terms_consent' );

        if ( ! empty( $s['require_privacy_acceptance'] ) && ! self::posted( self::FIELD_PRIVACY ) ) {
            $errors->add(
                'wpog_privacy_required',
                esc_html( WPOG_Settings::string( 'terms_privacy_error_message' ) )
            );
        }

        return $errors;
    }

    /* ---------- persistence ---------- */

    /**
     * Persist the consent to the user profile (user meta) and to the audit-trail
     * log table once the customer has been created. Fires on My Account
     * registration and on checkout account-creation.
     *
     * @param int $customer_id
     */
    public static function save( $customer_id ) {
        $customer_id = absint( $customer_id );
        if ( ! $customer_id ) {
            return;
        }

        $s = WPOG_Settings::get( 'terms_consent' );

        if ( ! self::is_account_registration_form() ) {
            // Account created during the classic checkout: mirror the checkout
            // checkboxes to the profile so the customer can later withdraw the
            // marketing consent from My Account (GDPR Art. 7(3)). The checkout
            // integration writes the audit-trail rows itself.
            if ( isset( $_POST[ WPOG_WooCommerce_Integration::FIELD_PRIVACY ] ) || isset( $_POST[ WPOG_WooCommerce_Integration::FIELD_MARKETING ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                if ( ! empty( $s['require_privacy_acceptance'] ) ) {
                    update_user_meta( $customer_id, self::META_PRIVACY, self::posted( WPOG_WooCommerce_Integration::FIELD_PRIVACY ) ? '1' : '0' );
                }
                if ( ! empty( $s['show_marketing_consent'] ) ) {
                    update_user_meta( $customer_id, self::META_MARKETING, self::posted( WPOG_WooCommerce_Integration::FIELD_MARKETING ) ? '1' : '0' );
                }
                update_user_meta( $customer_id, self::META_DATE, current_time( 'mysql' ) );
                update_user_meta( $customer_id, self::META_IP, WPOG_Logger::anonymize_ip( WPOG_Logger::get_client_ip() ) );
            }
            return;
        }

        $privacy_given = self::posted( self::FIELD_PRIVACY );
        $mkt_given     = self::posted( self::FIELD_MARKETING );
        $ip            = WPOG_Logger::anonymize_ip( WPOG_Logger::get_client_ip() );
        $now           = current_time( 'mysql' );
        $page_url      = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';

        // --- user meta (profile) ---
        if ( ! empty( $s['require_privacy_acceptance'] ) ) {
            update_user_meta( $customer_id, self::META_PRIVACY, $privacy_given ? '1' : '0' );
        }
        if ( ! empty( $s['show_marketing_consent'] ) ) {
            update_user_meta( $customer_id, self::META_MARKETING, $mkt_given ? '1' : '0' );
        }
        update_user_meta( $customer_id, self::META_DATE, $now );
        update_user_meta( $customer_id, self::META_IP, $ip );

        // --- audit-trail log ---
        if ( empty( $s['log_enabled'] ) ) {
            return;
        }

        if ( ! empty( $s['require_privacy_acceptance'] ) ) {
            WPOG_Terms_Consent_Logger::log( array(
                'user_id'          => $customer_id,
                'document_type'    => 'privacy',
                'document_version' => (string) WPOG_Settings::get( 'form_consent', 'privacy_policy_version', '1.0' ),
                'consent_given'    => $privacy_given ? 1 : 0,
                'context'          => 'registration',
                'integration'      => 'woocommerce',
                'page_url'         => $page_url,
            ) );
        }

        if ( ! empty( $s['show_marketing_consent'] ) ) {
            WPOG_Terms_Consent_Logger::log( array(
                'user_id'          => $customer_id,
                'document_type'    => 'marketing',
                'document_version' => '1.0',
                'consent_given'    => $mkt_given ? 1 : 0,
                'context'          => 'registration',
                'integration'      => 'woocommerce',
                'page_url'         => $page_url,
            ) );
        }
    }

    /* ---------- helpers ---------- */

    /**
     * True only for the My Account registration form submission (which
     * carries WooCommerce's own registration nonce field).
     */
    private static function is_account_registration_form() {
        return isset( $_POST['woocommerce-register-nonce'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    }

    /**
     * True when a checkbox field was posted with the exact value '1'. The
     * registration form is protected by WooCommerce's own registration nonce.
     */
    private static function posted( $field ) {
        return isset( $_POST[ $field ] ) && '1' === (string) wp_unslash( $_POST[ $field ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    }
}
