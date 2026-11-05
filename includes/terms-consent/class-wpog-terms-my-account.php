<?php
/**
 * My Account "Privacy & Consents" page (R3) for the Terms & Conditions module.
 *
 * Adds a dedicated tab to the WooCommerce My Account area where the logged-in
 * customer can:
 *   - review the full history of the consents they have given (registration +
 *     orders), read from WPOG_Terms_Consent_Logger;
 *   - revoke any non-mandatory consent (marketing) — stored in user meta and
 *     written to the audit-trail log as a withdrawal row;
 *   - request an export of their personal data (WP Privacy API);
 *   - request erasure of their personal data (WP Privacy API).
 *
 * Hooks:
 *   init                                          → add_rewrite_endpoint()
 *   woocommerce_account_menu_items                → add the menu entry
 *   woocommerce_account_{endpoint}_endpoint       → render the page
 *   template_redirect                             → handle the POST actions
 *
 * The rewrite endpoint is flushed on plugin activation (see WPOG_Core::activate).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Terms_My_Account {

    const ENDPOINT = 'privacy-consent';
    const NONCE    = 'wpog_myaccount_privacy';

    public static function init() {
        $s = WPOG_Settings::get( 'terms_consent' );
        if ( empty( $s['enabled'] ) || empty( $s['woocommerce_enabled'] ) || empty( $s['myaccount_privacy_enabled'] ) ) {
            return;
        }

        add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
        add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );
        add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render' ) );
        add_action( 'template_redirect', array( __CLASS__, 'handle_actions' ) );
        add_filter( 'query_vars', array( __CLASS__, 'add_query_var' ) );
    }

    /* ---------- endpoint / menu wiring ---------- */

    public static function add_endpoint() {
        add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
    }

    public static function add_query_var( $vars ) {
        $vars[] = self::ENDPOINT;
        return $vars;
    }

    /**
     * Insert the menu entry just before "Logout".
     */
    public static function add_menu_item( $items ) {
        $new = array();
        foreach ( $items as $key => $label ) {
            if ( 'customer-logout' === $key ) {
                $new[ self::ENDPOINT ] = __( 'Privacy & Consents', 'wp-opengdpr' );
            }
            $new[ $key ] = $label;
        }
        if ( ! isset( $new[ self::ENDPOINT ] ) ) {
            $new[ self::ENDPOINT ] = __( 'Privacy & Consents', 'wp-opengdpr' );
        }
        return $new;
    }

    /* ---------- POST actions ---------- */

    public static function handle_actions() {
        if ( empty( $_POST['wpog_myaccount_action'] ) || ! is_user_logged_in() ) {
            return;
        }
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE ) ) {
            return;
        }

        $action  = sanitize_key( wp_unslash( $_POST['wpog_myaccount_action'] ) );
        $user    = wp_get_current_user();
        $user_id = (int) $user->ID;

        switch ( $action ) {
            case 'revoke_marketing':
                self::revoke_marketing( $user_id );
                break;

            case 'request_export':
                self::create_privacy_request( $user->user_email, 'export_personal_data' );
                break;

            case 'request_erase':
                self::create_privacy_request( $user->user_email, 'remove_personal_data' );
                break;
        }

        // Post/Redirect/Get: a browser refresh must not re-submit the action
        // (which wrote a duplicate withdrawal row / re-sent request emails).
        if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
            wp_safe_redirect( wc_get_account_endpoint_url( self::ENDPOINT ) );
            exit;
        }
    }

    private static function revoke_marketing( $user_id ) {
        if ( '0' === (string) get_user_meta( $user_id, WPOG_Terms_Registration::META_MARKETING, true ) ) {
            return; // Already withdrawn: don't log a duplicate withdrawal.
        }
        update_user_meta( $user_id, WPOG_Terms_Registration::META_MARKETING, '0' );

        WPOG_Terms_Consent_Logger::log( array(
            'user_id'          => $user_id,
            'document_type'    => 'marketing',
            'document_version' => '1.0',
            'consent_given'    => 0,
            'context'          => 'account',
            'integration'      => 'woocommerce',
            'page_url'         => wc_get_account_endpoint_url( self::ENDPOINT ),
        ) );

        if ( function_exists( 'wc_add_notice' ) ) {
            wc_add_notice( __( 'Your marketing consent has been withdrawn.', 'wp-opengdpr' ), 'success' );
        }
    }

    /**
     * Create and dispatch a WordPress personal-data request (export or erase).
     * WordPress emails the user a confirmation link; once confirmed, the request
     * appears under Tools → Export/Erase Personal Data for the admin to process
     * (our exporter/eraser in WPOG_Privacy_API supply the data).
     *
     * @param string $email
     * @param string $action_name export_personal_data | remove_personal_data
     */
    private static function create_privacy_request( $email, $action_name ) {
        if ( ! function_exists( 'wp_create_user_request' ) ) {
            return;
        }
        $request_id = wp_create_user_request( $email, $action_name );

        if ( is_wp_error( $request_id ) ) {
            if ( function_exists( 'wc_add_notice' ) ) {
                wc_add_notice( $request_id->get_error_message(), 'error' );
            }
            return;
        }

        wp_send_user_request( $request_id );

        if ( function_exists( 'wc_add_notice' ) ) {
            wc_add_notice(
                __( 'We have emailed you a confirmation link. Please click it to confirm your request.', 'wp-opengdpr' ),
                'success'
            );
        }
    }

    /* ---------- rendering ---------- */

    public static function render() {
        $user_id   = get_current_user_id();
        $order_ids = self::user_order_ids( $user_id );
        $rows      = WPOG_Terms_Consent_Logger::get_by_user( $user_id, $order_ids );
        $marketing = get_user_meta( $user_id, WPOG_Terms_Registration::META_MARKETING, true );

        echo '<div class="wpog-myaccount-privacy">';

        // --- Current consent state + revoke ---
        echo '<h3>' . esc_html__( 'Your consents', 'wp-opengdpr' ) . '</h3>';

        if ( '' !== $marketing ) {
            echo '<p>' . esc_html__( 'Marketing communications:', 'wp-opengdpr' ) . ' <strong>'
                . ( '1' === (string) $marketing ? esc_html__( 'Granted', 'wp-opengdpr' ) : esc_html__( 'Not granted', 'wp-opengdpr' ) )
                . '</strong></p>';

            if ( '1' === (string) $marketing ) {
                echo '<form method="post" class="wpog-consent-action">';
                wp_nonce_field( self::NONCE );
                echo '<input type="hidden" name="wpog_myaccount_action" value="revoke_marketing">';
                echo '<button type="submit" class="button">' . esc_html__( 'Withdraw marketing consent', 'wp-opengdpr' ) . '</button>';
                echo '</form>';
            }
        } else {
            echo '<p>' . esc_html__( 'No revocable (optional) consents are currently on record.', 'wp-opengdpr' ) . '</p>';
        }

        // --- History ---
        echo '<h3>' . esc_html__( 'Consent history', 'wp-opengdpr' ) . '</h3>';
        self::render_history_table( $rows );

        // --- Data rights ---
        echo '<h3>' . esc_html__( 'Your data rights', 'wp-opengdpr' ) . '</h3>';
        echo '<p>' . esc_html__( 'You can request a copy of your personal data or ask us to erase it. We will email you a confirmation link to verify the request.', 'wp-opengdpr' ) . '</p>';
        echo '<div class="wpog-data-rights-buttons" style="display:flex;gap:0.5em;flex-wrap:wrap;">';

        echo '<form method="post" class="wpog-consent-action">';
        wp_nonce_field( self::NONCE );
        echo '<input type="hidden" name="wpog_myaccount_action" value="request_export">';
        echo '<button type="submit" class="button">' . esc_html__( 'Request data export', 'wp-opengdpr' ) . '</button>';
        echo '</form>';

        echo '<form method="post" class="wpog-consent-action">';
        wp_nonce_field( self::NONCE );
        echo '<input type="hidden" name="wpog_myaccount_action" value="request_erase">';
        echo '<button type="submit" class="button">' . esc_html__( 'Request data erasure', 'wp-opengdpr' ) . '</button>';
        echo '</form>';

        echo '</div>';
        echo '</div>';
    }

    private static function render_history_table( $rows ) {
        if ( empty( $rows ) ) {
            echo '<p>' . esc_html__( 'No consent records found yet.', 'wp-opengdpr' ) . '</p>';
            return;
        }

        $labels = array(
            'terms'     => __( 'Terms & Conditions', 'wp-opengdpr' ),
            'privacy'   => __( 'Privacy Policy', 'wp-opengdpr' ),
            'marketing' => __( 'Marketing', 'wp-opengdpr' ),
            'age'       => __( 'Age verification', 'wp-opengdpr' ),
        );

        echo '<table class="woocommerce-table shop_table wpog-consent-history">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Date', 'wp-opengdpr' ) . '</th>';
        echo '<th>' . esc_html__( 'Document', 'wp-opengdpr' ) . '</th>';
        echo '<th>' . esc_html__( 'Context', 'wp-opengdpr' ) . '</th>';
        echo '<th>' . esc_html__( 'Order', 'wp-opengdpr' ) . '</th>';
        echo '<th>' . esc_html__( 'Consent', 'wp-opengdpr' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $rows as $r ) {
            $doc   = $labels[ $r->document_type ] ?? $r->document_type;
            $given = (int) $r->consent_given
                ? '<span style="color:#46b450;">&#10004; ' . esc_html__( 'Given', 'wp-opengdpr' ) . '</span>'
                : '<span style="color:#dc3232;">&#10008; ' . esc_html__( 'Withdrawn / not given', 'wp-opengdpr' ) . '</span>';

            echo '<tr>';
            echo '<td>' . esc_html( $r->consent_date ) . '</td>';
            echo '<td>' . esc_html( $doc ) . ' <small>v' . esc_html( $r->document_version ) . '</small></td>';
            echo '<td>' . esc_html( $r->context ) . '</td>';
            echo '<td>' . ( $r->order_id ? esc_html( '#' . $r->order_id ) : '&mdash;' ) . '</td>';
            echo '<td>' . wp_kses_post( $given ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /* ---------- helpers ---------- */

    /**
     * IDs of every order belonging to the user, so order-bound consent rows
     * (including guest-then-linked orders) are included in the history/export.
     *
     * @return int[]
     */
    public static function user_order_ids( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $user_id || ! function_exists( 'wc_get_orders' ) ) {
            return array();
        }
        $ids = wc_get_orders( array(
            'customer_id' => $user_id,
            'limit'       => -1,
            'return'      => 'ids',
        ) );
        return array_map( 'absint', (array) $ids );
    }
}
