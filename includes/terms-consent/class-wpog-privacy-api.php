<?php
/**
 * WordPress Privacy API integration (R5) for the Terms & Conditions module.
 *
 * Registers a personal-data exporter and eraser so the consent records stored by
 * WPOG_Terms_Consent_Logger are included in the native WordPress "Export Personal
 * Data" / "Erase Personal Data" tools (Tools → Export/Erase Personal Data) and in
 * the requests a customer can trigger from the My Account → Privacy & Consents tab.
 *
 * Erasure policy: the consent rows are *anonymised* (IP + user agent scrubbed)
 * rather than hard-deleted, because the proof that consent was given/withdrawn is
 * itself a legal-accountability record (GDPR Art. 7(1) / Art. 5(2)). This is
 * reported back to WordPress as "data retained" with an explanatory message.
 *
 * Hooks:
 *   wp_privacy_personal_data_exporters → register the exporter
 *   wp_privacy_personal_data_erasers   → register the eraser
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Privacy_API {

    const EXPORTER_ID = 'wpog-terms-consent';
    const ERASER_ID   = 'wpog-terms-consent';

    public static function init() {
        add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
        add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
    }

    /* ---------- registration ---------- */

    public static function register_exporter( $exporters ) {
        $exporters[ self::EXPORTER_ID ] = array(
            'exporter_friendly_name' => __( 'GDPR Consents (WP OpenGDPR)', 'wp-opengdpr' ),
            'callback'               => array( __CLASS__, 'export' ),
        );
        return $exporters;
    }

    public static function register_eraser( $erasers ) {
        $erasers[ self::ERASER_ID ] = array(
            'eraser_friendly_name' => __( 'GDPR Consents (WP OpenGDPR)', 'wp-opengdpr' ),
            'callback'             => array( __CLASS__, 'erase' ),
        );
        return $erasers;
    }

    /* ---------- exporter ---------- */

    /**
     * @param string $email_address
     * @param int    $page
     * @return array { data: array, done: bool }
     */
    public static function export( $email_address, $page = 1 ) {
        $data = array();
        $rows = self::rows_for_email( $email_address );

        $labels = array(
            'terms'     => __( 'Terms & Conditions', 'wp-opengdpr' ),
            'privacy'   => __( 'Privacy Policy', 'wp-opengdpr' ),
            'marketing' => __( 'Marketing', 'wp-opengdpr' ),
            'age'       => __( 'Age verification', 'wp-opengdpr' ),
        );

        foreach ( $rows as $r ) {
            $doc   = $labels[ $r->document_type ] ?? $r->document_type;
            $items = array(
                array( 'name' => __( 'Date', 'wp-opengdpr' ),         'value' => $r->consent_date ),
                array( 'name' => __( 'Document', 'wp-opengdpr' ),     'value' => $doc ),
                array( 'name' => __( 'Version', 'wp-opengdpr' ),      'value' => $r->document_version ),
                array( 'name' => __( 'Consent given', 'wp-opengdpr' ), 'value' => (int) $r->consent_given ? __( 'Yes', 'wp-opengdpr' ) : __( 'No', 'wp-opengdpr' ) ),
                array( 'name' => __( 'Context', 'wp-opengdpr' ),      'value' => $r->context ),
                array( 'name' => __( 'Order', 'wp-opengdpr' ),        'value' => $r->order_id ? '#' . $r->order_id : '' ),
                array( 'name' => __( 'IP address (anonymised)', 'wp-opengdpr' ), 'value' => $r->ip_address ),
            );

            $data[] = array(
                'group_id'    => 'wpog-consents',
                'group_label' => __( 'GDPR Consents', 'wp-opengdpr' ),
                'item_id'     => 'wpog-consent-' . (int) $r->id,
                'data'        => $items,
            );
        }

        return array(
            'data' => $data,
            'done' => true,
        );
    }

    /* ---------- eraser ---------- */

    /**
     * @param string $email_address
     * @param int    $page
     * @return array { items_removed: bool, items_retained: bool, messages: array, done: bool }
     */
    public static function erase( $email_address, $page = 1 ) {
        $user      = get_user_by( 'email', $email_address );
        $user_id   = $user ? (int) $user->ID : 0;
        $order_ids = self::order_ids_for_email( $email_address, $user_id );

        $affected = WPOG_Terms_Consent_Logger::anonymize_user( $user_id, $order_ids );

        $messages = array();
        $retained = false;
        if ( $affected > 0 ) {
            $retained   = true;
            $messages[] = __( 'Your GDPR consent records were anonymised (IP address and user agent removed). The consent proof itself is retained as a legal-accountability record (GDPR Art. 7).', 'wp-opengdpr' );
        }

        // Also remove the consent user-meta keys we control.
        if ( $user_id ) {
            delete_user_meta( $user_id, WPOG_Terms_Registration::META_MARKETING );
            delete_user_meta( $user_id, WPOG_Terms_Registration::META_PRIVACY );
            delete_user_meta( $user_id, WPOG_Terms_Registration::META_IP );
        }

        return array(
            'items_removed'  => false,
            'items_retained' => $retained,
            'messages'       => $messages,
            'done'           => true,
        );
    }

    /* ---------- helpers ---------- */

    private static function rows_for_email( $email_address ) {
        $user      = get_user_by( 'email', $email_address );
        $user_id   = $user ? (int) $user->ID : 0;
        $order_ids = self::order_ids_for_email( $email_address, $user_id );
        return WPOG_Terms_Consent_Logger::get_by_user( $user_id, $order_ids );
    }

    /**
     * Order IDs tied to an email, covering both registered-user orders and guest
     * orders placed with that billing email.
     *
     * @return int[]
     */
    private static function order_ids_for_email( $email_address, $user_id = 0 ) {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            return array();
        }
        $args = array(
            'limit'   => -1,
            'return'  => 'ids',
            'billing_email' => $email_address,
        );
        $ids = wc_get_orders( $args );
        $ids = array_map( 'absint', (array) $ids );

        if ( $user_id ) {
            $owned = wc_get_orders( array( 'customer_id' => $user_id, 'limit' => -1, 'return' => 'ids' ) );
            $ids   = array_merge( $ids, array_map( 'absint', (array) $owned ) );
        }
        return array_values( array_unique( array_filter( $ids ) ) );
    }
}
