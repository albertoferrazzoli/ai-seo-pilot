<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI-powered Readability Analysis module.
 *
 * Uses the AI Engine to analyze content readability and provide
 * structured scores, metrics, and actionable suggestions.
 */
class AI_SEO_Pilot_Readability {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'save_post', array( $this, 'invalidate_cache' ), 30 );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route( 'ai-seo-pilot/v1', '/readability/analyze', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_analyze' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'post_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
				'force' => array(
					'required' => false,
					'type'     => 'boolean',
					'default'  => false,
				),
			),
		) );
	}

	/**
	 * REST callback — analyze readability.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_analyze( $request ) {
		if ( 'yes' !== get_option( 'ai_seo_pilot_readability_enabled', 'yes' ) ) {
			return new \WP_Error( 'disabled', __( 'Readability analysis is disabled.', 'ai-seo-pilot' ), array( 'status' => 403 ) );
		}

		$post_id = $request->get_param( 'post_id' );
		$force   = $request->get_param( 'force' );

		$result = $this->analyze( $post_id, $force );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Analyze readability for a post.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $force   Force re-analysis (skip cache).
	 * @return array|WP_Error Parsed readability report.
	 */
	public function analyze( $post_id, $force = false ) {
		// Check cache first.
		if ( ! $force ) {
			$cached = get_post_meta( $post_id, '_ai_seo_pilot_readability_cache', true );
			if ( ! empty( $cached ) && is_array( $cached ) ) {
				return $cached;
			}
		}

		$plugin = AI_SEO_Pilot::get_instance();

		if ( ! $plugin->ai_engine->is_configured() ) {
			return new \WP_Error( 'ai_not_configured', __( 'AI Engine is not configured. Go to Settings > AI Providers.', 'ai-seo-pilot' ) );
		}

		$response = $plugin->ai_engine->analyze_readability( $post_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$result = $this->parse_json_response( $response );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Cache the result.
		update_post_meta( $post_id, '_ai_seo_pilot_readability_cache', $result );

		return $result;
	}

	/**
	 * Invalidate cache when a post is saved.
	 *
	 * @param int $post_id Post ID.
	 */
	public function invalidate_cache( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		delete_post_meta( $post_id, '_ai_seo_pilot_readability_cache' );
	}

	/**
	 * Parse JSON from AI response, handling potential markdown wrapping.
	 *
	 * @param string $response Raw AI response.
	 * @return array|WP_Error Parsed data or error.
	 */
	private function parse_json_response( $response ) {
		// Strip markdown code block wrappers if present.
		$response = trim( $response );
		$response = preg_replace( '/^```(?:json)?\s*/i', '', $response );
		$response = preg_replace( '/\s*```$/', '', $response );

		$data = json_decode( $response, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error( 'json_parse_error', __( 'Failed to parse AI response.', 'ai-seo-pilot' ) );
		}

		return $data;
	}
}
