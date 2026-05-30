<?php
/**
 * REST routes for WP OpenGDPR.
 *
 * Currently exposes:
 *   POST /wpog/v1/track  — receive a batch of detections from the admin
 *                          tracking JS (auth: manage_options).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_REST {

    const NAMESPACE_V1 = 'wpog/v1';

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        register_rest_route(
            self::NAMESPACE_V1,
            '/track',
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'track' ),
                'permission_callback' => array( __CLASS__, 'can_track' ),
            )
        );
    }

    public static function can_track() {
        return current_user_can( 'manage_options' );
    }

    public static function track( WP_REST_Request $request ) {
        $items = $request->get_param( 'items' );
        if ( ! is_array( $items ) ) {
            return new WP_REST_Response( array( 'ok' => false, 'reason' => 'invalid_payload' ), 400 );
        }
        // Hard cap.
        $items = array_slice( $items, 0, 500 );
        $stored = 0;
        foreach ( $items as $it ) {
            if ( ! is_array( $it ) ) {
                continue;
            }
            if ( WPOG_Tracking::upsert( $it ) ) {
                $stored++;
            }
        }
        return new WP_REST_Response( array( 'ok' => true, 'stored' => $stored ), 200 );
    }
}
