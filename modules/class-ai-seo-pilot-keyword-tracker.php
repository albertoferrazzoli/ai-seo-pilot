<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI-powered Keyword Tracker module.
 *
 * Manages focus keywords, keyword extraction, related keyword suggestions,
 * density analysis, and cannibalization detection — all via AI.
 */
class AI_SEO_Pilot_Keyword_Tracker {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'save_post', array( $this, 'on_save_post' ), 20, 2 );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route( 'ai-seo-pilot/v1', '/keywords/extract', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_extract' ),
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

		register_rest_route( 'ai-seo-pilot/v1', '/keywords/related', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_related' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'keyword' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );

		register_rest_route( 'ai-seo-pilot/v1', '/keywords/analyze', array(
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
				'keyword' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );

		register_rest_route( 'ai-seo-pilot/v1', '/keywords/cannibalization', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_cannibalization' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		) );

		register_rest_route( 'ai-seo-pilot/v1', '/keywords/save', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_save_focus' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'post_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
				'keyword' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );
	}

	/**
	 * REST callback — extract keywords from a post.
	 */
	public function rest_extract( $request ) {
		if ( 'yes' !== get_option( 'ai_seo_pilot_keyword_tracker_enabled', 'yes' ) ) {
			return new \WP_Error( 'disabled', __( 'Keyword tracker is disabled.', 'ai-seo-pilot' ), array( 'status' => 403 ) );
		}

		$post_id = $request->get_param( 'post_id' );
		$plugin  = AI_SEO_Pilot::get_instance();

		if ( ! $plugin->ai_engine->is_configured() ) {
			return new \WP_Error( 'ai_not_configured', __( 'AI Engine is not configured.', 'ai-seo-pilot' ) );
		}

		$response = $plugin->ai_engine->extract_keywords( $post_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$keywords = $this->parse_json_response( $response );

		if ( is_wp_error( $keywords ) ) {
			return $keywords;
		}

		// Store extracted keywords in tracking table.
		$this->store_keywords( $post_id, $keywords );

		return rest_ensure_response( $keywords );
	}

	/**
	 * REST callback — suggest related keywords.
	 */
	public function rest_related( $request ) {
		if ( 'yes' !== get_option( 'ai_seo_pilot_keyword_tracker_enabled', 'yes' ) ) {
			return new \WP_Error( 'disabled', __( 'Keyword tracker is disabled.', 'ai-seo-pilot' ), array( 'status' => 403 ) );
		}

		$keyword = $request->get_param( 'keyword' );
		$plugin  = AI_SEO_Pilot::get_instance();

		if ( ! $plugin->ai_engine->is_configured() ) {
			return new \WP_Error( 'ai_not_configured', __( 'AI Engine is not configured.', 'ai-seo-pilot' ) );
		}

		$response = $plugin->ai_engine->suggest_related_keywords( $keyword );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$result = $this->parse_json_response( $response );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * REST callback — analyze keyword usage in a post.
	 */
	public function rest_analyze( $request ) {
		if ( 'yes' !== get_option( 'ai_seo_pilot_keyword_tracker_enabled', 'yes' ) ) {
			return new \WP_Error( 'disabled', __( 'Keyword tracker is disabled.', 'ai-seo-pilot' ), array( 'status' => 403 ) );
		}

		$post_id = $request->get_param( 'post_id' );
		$keyword = $request->get_param( 'keyword' );
		$plugin  = AI_SEO_Pilot::get_instance();

		if ( ! $plugin->ai_engine->is_configured() ) {
			return new \WP_Error( 'ai_not_configured', __( 'AI Engine is not configured.', 'ai-seo-pilot' ) );
		}

		$response = $plugin->ai_engine->analyze_keyword_usage( $post_id, $keyword );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$result = $this->parse_json_response( $response );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * REST callback — detect cannibalization across all focus keywords.
	 */
	public function rest_cannibalization( $request ) {
		if ( 'yes' !== get_option( 'ai_seo_pilot_keyword_tracker_enabled', 'yes' ) ) {
			return new \WP_Error( 'disabled', __( 'Keyword tracker is disabled.', 'ai-seo-pilot' ), array( 'status' => 403 ) );
		}

		$groups = $this->get_keyword_groups();

		if ( empty( $groups ) ) {
			return rest_ensure_response( array(
				'message' => __( 'No focus keywords found. Extract keywords from your posts first.', 'ai-seo-pilot' ),
				'groups'  => array(),
			) );
		}

		$plugin = AI_SEO_Pilot::get_instance();

		if ( ! $plugin->ai_engine->is_configured() ) {
			return new \WP_Error( 'ai_not_configured', __( 'AI Engine is not configured.', 'ai-seo-pilot' ) );
		}

		$results = array();

		foreach ( $groups as $keyword => $posts ) {
			if ( count( $posts ) < 2 ) {
				continue;
			}

			$response = $plugin->ai_engine->detect_cannibalization( $keyword, $posts );

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$analysis = $this->parse_json_response( $response );

			if ( ! is_wp_error( $analysis ) && ! empty( $analysis['has_cannibalization'] ) ) {
				$results[] = array(
					'keyword'  => $keyword,
					'posts'    => $posts,
					'analysis' => $analysis,
				);
			}
		}

		return rest_ensure_response( array( 'groups' => $results ) );
	}

	/**
	 * REST callback — save focus keyword for a post.
	 */
	public function rest_save_focus( $request ) {
		$post_id = $request->get_param( 'post_id' );
		$keyword = $request->get_param( 'keyword' );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'forbidden', __( 'Permission denied.', 'ai-seo-pilot' ), array( 'status' => 403 ) );
		}

		update_post_meta( $post_id, '_ai_seo_pilot_focus_keyword', $keyword );

		// Update tracking table.
		$this->set_focus_keyword( $post_id, $keyword );

		return rest_ensure_response( array( 'saved' => true, 'keyword' => $keyword ) );
	}

	/**
	 * Handle post save — update keyword tracking.
	 */
	public function on_save_post( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// If a focus keyword was submitted via meta box, save it.
		if ( isset( $_POST['ai_seo_pilot_focus_keyword'] ) && wp_verify_nonce( $_POST['_ai_seo_pilot_keyword_nonce'] ?? '', 'ai_seo_pilot_save_keyword' ) ) {
			$keyword = sanitize_text_field( wp_unslash( $_POST['ai_seo_pilot_focus_keyword'] ) );
			update_post_meta( $post_id, '_ai_seo_pilot_focus_keyword', $keyword );
			if ( ! empty( $keyword ) ) {
				$this->set_focus_keyword( $post_id, $keyword );
			}
		}
	}

	/**
	 * Store extracted keywords in the tracking table.
	 */
	private function store_keywords( $post_id, $keywords ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ai_seo_pilot_keyword_tracking';

		foreach ( $keywords as $kw ) {
			if ( empty( $kw['keyword'] ) ) {
				continue;
			}

			$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'post_id'         => $post_id,
					'keyword'         => $kw['keyword'],
					'relevance_score' => $kw['relevance_score'] ?? 0,
					'is_focus'        => ( $kw['type'] ?? '' ) === 'primary' ? 1 : 0,
					'ai_analysis'     => wp_json_encode( $kw ),
					'updated_at'      => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%f', '%d', '%s', '%s' )
			);
		}

		// Auto-set focus keyword to the primary keyword if none is set.
		$current_focus = get_post_meta( $post_id, '_ai_seo_pilot_focus_keyword', true );
		if ( empty( $current_focus ) && ! empty( $keywords[0]['keyword'] ) ) {
			update_post_meta( $post_id, '_ai_seo_pilot_focus_keyword', $keywords[0]['keyword'] );
		}
	}

	/**
	 * Set the focus keyword in the tracking table.
	 */
	private function set_focus_keyword( $post_id, $keyword ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ai_seo_pilot_keyword_tracking';

		// Unset previous focus keywords for this post.
		$wpdb->update( $table, array( 'is_focus' => 0 ), array( 'post_id' => $post_id ), array( '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// Set the new focus keyword.
		$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'post_id'         => $post_id,
				'keyword'         => $keyword,
				'relevance_score' => 1.0,
				'is_focus'        => 1,
				'updated_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%f', '%d', '%s' )
		);
	}

	/**
	 * Get groups of posts sharing similar focus keywords.
	 *
	 * @return array Keyword => posts array.
	 */
	public function get_keyword_groups() {
		global $wpdb;
		$table = $wpdb->prefix . 'ai_seo_pilot_keyword_tracking';

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT kt.keyword, kt.post_id, p.post_title, p.guid
			FROM {$table} kt
			INNER JOIN {$wpdb->posts} p ON kt.post_id = p.ID
			WHERE kt.is_focus = 1
			AND p.post_status = 'publish'
			ORDER BY kt.keyword, kt.post_id" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$groups = array();
		foreach ( $rows as $row ) {
			$keyword = strtolower( $row->keyword );
			if ( ! isset( $groups[ $keyword ] ) ) {
				$groups[ $keyword ] = array();
			}
			$groups[ $keyword ][] = array(
				'id'      => (int) $row->post_id,
				'title'   => $row->post_title,
				'url'     => get_permalink( $row->post_id ),
				'excerpt' => wp_trim_words( get_the_excerpt( $row->post_id ), 20, '...' ),
			);
		}

		return $groups;
	}

	/**
	 * Get all posts with their focus keywords.
	 *
	 * @return array
	 */
	public function get_all_focus_keywords() {
		global $wpdb;
		$table = $wpdb->prefix . 'ai_seo_pilot_keyword_tracking';

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT kt.*, p.post_title, p.post_type
			FROM {$table} kt
			INNER JOIN {$wpdb->posts} p ON kt.post_id = p.ID
			WHERE kt.is_focus = 1
			AND p.post_status = 'publish'
			ORDER BY kt.keyword ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
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
