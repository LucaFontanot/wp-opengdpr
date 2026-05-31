<?php
/**
 * REST routes for the Legal Document Prompt Generator.
 *
 * All routes live under the existing wpog/v1 namespace and require the
 * manage_options capability. The wizard authenticates with the standard
 * wp_rest cookie nonce sent in the X-WP-Nonce header.
 *
 *   GET    /wpog/v1/legal-generator/prefill        — settings for pre-filling
 *   GET    /wpog/v1/legal-generator/prompts        — list saved prompts
 *   POST   /wpog/v1/legal-generator/prompts        — save a prompt
 *   GET    /wpog/v1/legal-generator/prompts/{id}   — load one prompt (full)
 *   DELETE /wpog/v1/legal-generator/prompts/{id}   — delete a prompt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPOG_Legal_REST {

	const NAMESPACE_V1 = 'wpog/v1';
	const BASE         = 'legal-generator';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	public static function register_routes() {
		register_rest_route( self::NAMESPACE_V1, '/' . self::BASE . '/prefill', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_prefill' ),
			'permission_callback' => array( __CLASS__, 'can_manage' ),
		) );

		register_rest_route( self::NAMESPACE_V1, '/' . self::BASE . '/prompts', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_prompts' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_prompt' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			),
		) );

		register_rest_route( self::NAMESPACE_V1, '/' . self::BASE . '/prompts/(?P<id>\d+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_prompt' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'delete_prompt' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			),
		) );
	}

	public static function get_prefill() {
		return new WP_REST_Response( WPOG_Legal_Generator::prefill_data(), 200 );
	}

	public static function list_prompts( WP_REST_Request $request ) {
		$page = max( 1, (int) $request->get_param( 'page' ) );
		$res  = WPOG_Legal_Generator::query( array( 'page' => $page, 'per_page' => 50 ) );
		return new WP_REST_Response( $res, 200 );
	}

	public static function create_prompt( WP_REST_Request $request ) {
		$id = WPOG_Legal_Generator::save( array(
			'prompt_name' => (string) $request->get_param( 'prompt_name' ),
			'form_data'   => (array) $request->get_param( 'form_data' ),
			'prompt_text' => (string) $request->get_param( 'prompt_text' ),
			'documents'   => $request->get_param( 'documents' ),
			'language'    => (string) $request->get_param( 'language' ),
		) );

		if ( ! $id ) {
			return new WP_REST_Response( array( 'ok' => false, 'reason' => 'save_failed' ), 500 );
		}
		return new WP_REST_Response( array( 'ok' => true, 'id' => $id ), 201 );
	}

	public static function get_prompt( WP_REST_Request $request ) {
		$row = WPOG_Legal_Generator::get( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_REST_Response( array( 'ok' => false, 'reason' => 'not_found' ), 404 );
		}
		return new WP_REST_Response( $row, 200 );
	}

	public static function delete_prompt( WP_REST_Request $request ) {
		$ok = WPOG_Legal_Generator::delete( (int) $request['id'] );
		return new WP_REST_Response( array( 'ok' => (bool) $ok ), $ok ? 200 : 404 );
	}
}
