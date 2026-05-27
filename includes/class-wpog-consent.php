<?php
/**
 * Consent reader / writer (server-side perspective).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Consent {

    const COOKIE_NAME    = 'wpog_consent';
    const COOKIE_ID_NAME = 'wpog_consent_id';

    /**
     * Returns the validated consent payload for the current request, or null.
     */
    public static function current() {
        if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            return null;
        }
        $raw = wp_unslash( $_COOKIE[ self::COOKIE_NAME ] );
        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            return null;
        }
        // Validate version & expiry.
        $policy = WPOG_Settings::get( 'general', 'policy_version' );
        if ( ! empty( $data['version'] ) && $data['version'] !== $policy ) {
            return null;
        }
        if ( ! empty( $data['date'] ) ) {
            $ts = strtotime( $data['date'] );
            $duration = (int) WPOG_Settings::get( 'general', 'consent_duration', 180 );
            if ( $ts && ( time() - $ts ) > ( $duration * DAY_IN_SECONDS ) ) {
                return null;
            }
        }
        // Normalize categories.
        $cats = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : array();
        $data['categories'] = array(
            'necessary'  => true,
            'functional' => ! empty( $cats['functional'] ),
            'analytics'  => ! empty( $cats['analytics'] ),
            'marketing'  => ! empty( $cats['marketing'] ),
        );
        return $data;
    }

    /**
     * True if the consent banner should be displayed for the current visitor.
     */
    public static function should_show_banner() {
        if ( ! WPOG_Settings::get( 'general', 'enabled' ) ) {
            return false;
        }
        return null === self::current();
    }

    /**
     * Returns category allow-map for the current visitor.
     */
    public static function allowed_categories() {
        $current = self::current();
        if ( ! $current ) {
            return array(
                'necessary'  => true,
                'functional' => false,
                'analytics'  => false,
                'marketing'  => false,
            );
        }
        return $current['categories'];
    }

    /**
     * AJAX endpoint — persists consent server-side log.
     * Cookie writing happens client-side (so it works without a page reload).
     */
    public static function ajax_save() {
        check_ajax_referer( 'wpog_consent', 'nonce' );

        $payload = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : '';
        $data = json_decode( $payload, true );
        if ( ! is_array( $data ) ) {
            wp_send_json_error( array( 'message' => 'Invalid payload' ), 400 );
        }
        WPOG_Logger::log( $data );
        wp_send_json_success();
    }
}
