<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI-powered Content Optimizer module.
 *
 * Provides AI content rewriting, tone adjustment, section generation,
 * and improvement suggestions based on content analyzer results.
 */
class AI_SEO_Pilot_Content_Optimizer {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route( 'ai-seo-pilot/v1', '/optimize/rewrite-paragraph', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_rewrite_paragraph' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'paragraph' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'wp_kses_post',
				),
				'instruction' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'post_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
			),
		) );

		register_rest_route( 'ai-seo-pilot/v1', '/optimize/adjust-tone', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_adjust_tone' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'content' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'wp_kses_post',
				),
				'tone' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'post_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
			),
		) );

		register_rest_route( 'ai-seo-pilot/v1', '/optimize/add-section', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_add_section' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'section_type' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'post_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
			),
		) );

		register_rest_route( 'ai-seo-pilot/v1', '/optimize/improve-content', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_improve_content' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'post_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
			),
		) );

		register_rest_route( 'ai-seo-pilot/v1', '/optimize/title', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_optimize_title' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'post_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
			),
		) );
	}

	/**
	 * REST callback — rewrite a paragraph.
	 */
	public function rest_rewrite_paragraph( $request ) {
		if ( 'yes' !== get_option( 'ai_seo_pilot_content_optimizer_enabled', 'yes' ) ) {
			return new \WP_Error( 'disabled', __( 'Content optimizer is disabled.', 'ai-seo-pilot' ), array( 'status' => 403 ) );
		}

		$plugin = AI_SEO_Pilot::get_instance();

		if ( ! $plugin->ai_engine->is_configured() ) {
			return new \WP_Error( 'ai_not_configured', __( 'AI Engine is not configured.', 'ai-seo-pilot' ) );
		}

		$paragraph   = $request->get_param( 'paragraph' );
		$instruction = $request->get_param( 'instruction' );
		$post_id     = $request->get_param( 'post_id' );

		$result = $plugin->ai_engine->rewrite_paragraph( $paragraph, $instruction, $post_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'rewritten' => $result ) );
	}

	/**
	 * REST callback — adjust content tone.
	 */
	public function rest_adjust_tone( $request ) {
		if ( 'yes' !== get_option( 'ai_seo_pilot_content_optimizer_enabled', 'yes' ) ) {
			return new \WP_Error( 'disabled', __( 'Content optimizer is disabled.', 'ai-seo-pilot' ), array( 'status' => 403 ) );
		}

		$plugin = AI_SEO_Pilot::get_instance();

		if ( ! $plugin->ai_engine->is_configured() ) {
			return new \WP_Error( 'ai_not_configured', __( 'AI Engine is not configured.', 'ai-seo-pilot' ) );
		}

		$content = $request->get_param( 'content' );
		$tone    = $request->get_param( 'tone' );
		$post_id = $request->get_param( 'post_id' );

		$valid_tones = array( 'authoritative', 'conversational', 'technical', 'simplified' );
		if ( ! in_array( $tone, $valid_tones, true ) ) {
			return new \WP_Error( 'invalid_tone', __( 'Invalid tone. Use: authoritative, conversational, technical, simplified.', 'ai-seo-pilot' ) );
		}

		// Build instruction for the rewrite_paragraph method.
		$instructions = array(
			'authoritative'  => 'Rewrite in an authoritative, expert tone. Use confident language, cite specifics, and establish credibility.',
			'conversational' => 'Rewrite in a conversational, friendly tone. Use simple language, contractions, and a warm approach.',
			'technical'      => 'Rewrite in a technical, precise tone. Use industry terminology, exact specifications, and structured language.',
			'simplified'     => 'Rewrite in a simplified, easy-to-understand tone. Use short sentences, common words, and clear explanations.',
		);

		$result = $plugin->ai_engine->rewrite_paragraph( $content, $instructions[ $tone ], $post_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'content' => $result, 'tone' => $tone ) );
	}

	/**
	 * REST callback — generate a content section.
	 */
	public function rest_add_section( $request ) {
		if ( 'yes' !== get_option( 'ai_seo_pilot_content_optimizer_enabled', 'yes' ) ) {
			return new \WP_Error( 'disabled', __( 'Content optimizer is disabled.', 'ai-seo-pilot' ), array( 'status' => 403 ) );
		}

		$plugin = AI_SEO_Pilot::get_instance();

		if ( ! $plugin->ai_engine->is_configured() ) {
			return new \WP_Error( 'ai_not_configured', __( 'AI Engine is not configured.', 'ai-seo-pilot' ) );
		}

		$section_type = $request->get_param( 'section_type' );
		$post_id      = $request->get_param( 'post_id' );

		$valid_types = array( 'faq', 'statistics', 'definitions', 'summary', 'conclusion' );
		if ( ! in_array( $section_type, $valid_types, true ) ) {
			return new \WP_Error( 'invalid_type', __( 'Invalid section type. Use: faq, statistics, definitions, summary, conclusion.', 'ai-seo-pilot' ) );
		}

		$result = $plugin->ai_engine->generate_content_section( $section_type, $post_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'html' => $result, 'type' => $section_type ) );
	}

	/**
	 * REST callback — get AI improvement suggestions based on content analyzer results.
	 */
	public function rest_improve_content( $request ) {
		if ( 'yes' !== get_option( 'ai_seo_pilot_content_optimizer_enabled', 'yes' ) ) {
			return new \WP_Error( 'disabled', __( 'Content optimizer is disabled.', 'ai-seo-pilot' ), array( 'status' => 403 ) );
		}

		$plugin  = AI_SEO_Pilot::get_instance();
		$post_id = $request->get_param( 'post_id' );

		if ( ! $plugin->ai_engine->is_configured() ) {
			return new \WP_Error( 'ai_not_configured', __( 'AI Engine is not configured.', 'ai-seo-pilot' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'no_post', __( 'Post not found.', 'ai-seo-pilot' ) );
		}

		// Run content analyzer to find failed checks.
		$analysis     = $plugin->content_analyzer->analyze( $post->post_content, $post->post_title );
		$failed_checks = array();

		if ( ! empty( $analysis['checks'] ) ) {
			foreach ( $analysis['checks'] as $check ) {
				if ( 'poor' === $check['status'] || 'warning' === $check['status'] ) {
					$failed_checks[] = $check['name'] . ' (' . $check['label'] . ': ' . $check['suggestion'] . ')';
				}
			}
		}

		if ( empty( $failed_checks ) ) {
			return rest_ensure_response( array(
				'suggestions' => array(),
				'message'     => __( 'Content passed all checks! No improvements needed.', 'ai-seo-pilot' ),
			) );
		}

		$response = $plugin->ai_engine->suggest_improvements( $post->post_content, $failed_checks, $post_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$suggestions = $this->parse_json_response( $response );

		if ( is_wp_error( $suggestions ) ) {
			return $suggestions;
		}

		return rest_ensure_response( array( 'suggestions' => $suggestions ) );
	}

	/**
	 * REST callback — generate optimized title variants.
	 */
	public function rest_optimize_title( $request ) {
		if ( 'yes' !== get_option( 'ai_seo_pilot_content_optimizer_enabled', 'yes' ) ) {
			return new \WP_Error( 'disabled', __( 'Content optimizer is disabled.', 'ai-seo-pilot' ), array( 'status' => 403 ) );
		}

		$plugin  = AI_SEO_Pilot::get_instance();
		$post_id = $request->get_param( 'post_id' );

		if ( ! $plugin->ai_engine->is_configured() ) {
			return new \WP_Error( 'ai_not_configured', __( 'AI Engine is not configured.', 'ai-seo-pilot' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'no_post', __( 'Post not found.', 'ai-seo-pilot' ) );
		}

		$prompt  = "Generate 3-5 SEO-optimized title alternatives for this content.\n\n";
		$prompt .= "Current title: {$post->post_title}\n\n";
		$prompt .= "Requirements:\n";
		$prompt .= "- Each title should be 50-65 characters\n";
		$prompt .= "- Optimized for AI search engines to cite\n";
		$prompt .= "- Include key entities and specific value propositions\n";
		$prompt .= "- Vary the approach: question, how-to, list, definitive guide, etc.\n\n";
		$prompt .= "Return a JSON array of strings. Return ONLY valid JSON.\n\n";
		$prompt .= "Content excerpt: " . wp_trim_words( wp_strip_all_tags( $post->post_content ), 200 );

		// Use call_api indirectly through a generate method pattern.
		$response = $plugin->ai_engine->rewrite_paragraph( $post->post_title, $prompt, $post_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$titles = $this->parse_json_response( $response );

		if ( is_wp_error( $titles ) ) {
			// If parsing fails, try to split by newlines.
			$lines = array_filter( array_map( 'trim', explode( "\n", $response ) ) );
			$titles = array_values( $lines );
		}

		return rest_ensure_response( array( 'titles' => $titles, 'current' => $post->post_title ) );
	}

	/**
	 * Parse JSON from AI response.
	 */
	private function parse_json_response( $response ) {
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
