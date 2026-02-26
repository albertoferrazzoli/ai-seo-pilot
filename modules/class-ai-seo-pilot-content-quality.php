<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI-powered Content Quality module.
 *
 * Analyzes content quality, detects thin content, compares similarity
 * between posts, and identifies duplicate meta descriptions.
 */
class AI_SEO_Pilot_Content_Quality {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'save_post', array( $this, 'invalidate_cache' ), 30 );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route( 'ai-seo-pilot/v1', '/quality/analyze', array(
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
			),
		) );

		register_rest_route( 'ai-seo-pilot/v1', '/quality/compare', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_compare' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'post_id_a' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
				'post_id_b' => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
			),
		) );

		register_rest_route( 'ai-seo-pilot/v1', '/quality/overview', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_overview' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		) );

		register_rest_route( 'ai-seo-pilot/v1', '/quality/scan', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_scan_batch' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'offset' => array(
					'required' => false,
					'type'     => 'integer',
					'default'  => 0,
				),
				'batch_size' => array(
					'required' => false,
					'type'     => 'integer',
					'default'  => 5,
				),
			),
		) );
	}

	/**
	 * REST callback — analyze a single post's quality.
	 */
	public function rest_analyze( $request ) {
		if ( 'yes' !== get_option( 'ai_seo_pilot_content_quality_enabled', 'yes' ) ) {
			return new \WP_Error( 'disabled', __( 'Content quality analysis is disabled.', 'ai-seo-pilot' ), array( 'status' => 403 ) );
		}

		$post_id = $request->get_param( 'post_id' );
		$result  = $this->analyze( $post_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * REST callback — compare two posts for similarity.
	 */
	public function rest_compare( $request ) {
		if ( 'yes' !== get_option( 'ai_seo_pilot_content_quality_enabled', 'yes' ) ) {
			return new \WP_Error( 'disabled', __( 'Content quality analysis is disabled.', 'ai-seo-pilot' ), array( 'status' => 403 ) );
		}

		$post_id_a = $request->get_param( 'post_id_a' );
		$post_id_b = $request->get_param( 'post_id_b' );

		$plugin   = AI_SEO_Pilot::get_instance();
		$response = $plugin->ai_engine->compare_content_similarity( $post_id_a, $post_id_b );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$result = $this->parse_json_response( $response );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Store in DB.
		$this->save_similarity( $post_id_a, $post_id_b, $result['similarity_score'] ?? 0 );

		return rest_ensure_response( $result );
	}

	/**
	 * REST callback — get quality overview across all scanned posts.
	 */
	public function rest_overview( $request ) {
		$scanned = $this->get_scanned_posts();

		$total_scanned = count( $scanned );
		$total_quality = 0;
		$total_read    = 0;
		$thin_count    = 0;
		$good_count    = 0;
		$utility_count = 0;
		$content_count = 0;

		foreach ( $scanned as $sp ) {
			if ( $this->is_utility_page( $sp->post_id ) ) {
				$utility_count++;
				continue;
			}
			$content_count++;
			$s = (float) $sp->quality_score;
			$total_quality += $s;
			$total_read    += (float) $sp->readability_score;
			if ( $s < 40 ) {
				$thin_count++;
			}
			if ( $s >= 70 ) {
				$good_count++;
			}
		}

		$total_posts = wp_count_posts( 'post' );
		$total_pages = wp_count_posts( 'page' );
		$total       = ( $total_posts->publish ?? 0 ) + ( $total_pages->publish ?? 0 );

		return rest_ensure_response( array(
			'total_content'    => $total,
			'total_scanned'    => $total_scanned,
			'avg_quality'      => $content_count > 0 ? round( $total_quality / $content_count, 1 ) : 0,
			'avg_readability'  => $content_count > 0 ? round( $total_read / $content_count, 1 ) : 0,
			'thin_count'       => $thin_count,
			'good_count'       => $good_count,
			'utility_count'    => $utility_count,
		) );
	}

	/**
	 * REST callback — scan a batch of posts for quality.
	 */
	public function rest_scan_batch( $request ) {
		if ( 'yes' !== get_option( 'ai_seo_pilot_content_quality_enabled', 'yes' ) ) {
			return new \WP_Error( 'disabled', __( 'Content quality analysis is disabled.', 'ai-seo-pilot' ), array( 'status' => 403 ) );
		}

		$offset     = $request->get_param( 'offset' );
		$batch_size = min( $request->get_param( 'batch_size' ), 10 );

		$posts = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
			'offset'         => $offset,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		) );

		$total_posts = wp_count_posts( 'post' );
		$total_pages = wp_count_posts( 'page' );
		$total       = ( $total_posts->publish ?? 0 ) + ( $total_pages->publish ?? 0 );

		$processed = 0;
		$errors    = 0;

		foreach ( $posts as $post ) {
			$result = $this->analyze( $post->ID, true );
			if ( is_wp_error( $result ) ) {
				$errors++;
			} else {
				$processed++;
			}
		}

		return rest_ensure_response( array(
			'processed' => $processed,
			'errors'    => $errors,
			'offset'    => $offset,
			'total'     => $total,
			'remaining' => max( 0, $total - $offset - $batch_size ),
			'complete'  => ( $offset + $batch_size ) >= $total,
		) );
	}

	/**
	 * Analyze a single post's quality.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $force   Force re-analysis.
	 * @return array|WP_Error
	 */
	public function analyze( $post_id, $force = false ) {
		if ( ! $force ) {
			$cached = get_post_meta( $post_id, '_ai_seo_pilot_quality_cache', true );
			if ( ! empty( $cached ) && is_array( $cached ) ) {
				return $cached;
			}
		}

		$plugin = AI_SEO_Pilot::get_instance();

		if ( ! $plugin->ai_engine->is_configured() ) {
			return new \WP_Error( 'ai_not_configured', __( 'AI Engine is not configured.', 'ai-seo-pilot' ) );
		}

		$response = $plugin->ai_engine->analyze_content_quality( $post_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$result = $this->parse_json_response( $response );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Cache in post meta.
		update_post_meta( $post_id, '_ai_seo_pilot_quality_cache', $result );

		// Store in quality table.
		$this->save_quality( $post_id, $result );

		return $result;
	}

	/**
	 * Save quality scores to the database table.
	 */
	private function save_quality( $post_id, $result ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ai_seo_pilot_content_quality';

		$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'post_id'           => $post_id,
				'quality_score'     => $result['quality_score'] ?? 0,
				'readability_score' => $result['depth'] ?? 0,
				'ai_analysis'       => wp_json_encode( $result ),
				'scanned_at'        => current_time( 'mysql' ),
			),
			array( '%d', '%f', '%f', '%s', '%s' )
		);
	}

	/**
	 * Save similarity score between two posts.
	 */
	private function save_similarity( $post_id_a, $post_id_b, $score ) {
		global $wpdb;

		// Ensure consistent ordering.
		if ( $post_id_a > $post_id_b ) {
			list( $post_id_a, $post_id_b ) = array( $post_id_b, $post_id_a );
		}

		$table = $wpdb->prefix . 'ai_seo_pilot_content_quality';

		// Store as a note — similarity is returned inline, not in a separate table for now.
		// The comparison results are returned directly to the client.
	}

	/**
	 * Invalidate cache on post save.
	 */
	public function invalidate_cache( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		delete_post_meta( $post_id, '_ai_seo_pilot_quality_cache' );
	}

	/**
	 * Get all scanned posts with quality data.
	 *
	 * @return array
	 */
	public function get_scanned_posts() {
		global $wpdb;

		$table = $wpdb->prefix . 'ai_seo_pilot_content_quality';

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT q.*, p.post_title, p.post_type
			FROM {$table} q
			INNER JOIN {$wpdb->posts} p ON q.post_id = p.ID
			WHERE p.post_status = 'publish'
			ORDER BY q.quality_score ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Check if a post is a utility/functional page (shop, cart, checkout, login, etc.).
	 *
	 * These pages naturally have sparse content and should not be flagged as "thin".
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_utility_page( $post_id ) {
		$post_id = (int) $post_id;

		// WooCommerce pages.
		$wc_option_keys = array(
			'woocommerce_shop_page_id',
			'woocommerce_cart_page_id',
			'woocommerce_checkout_page_id',
			'woocommerce_myaccount_page_id',
			'woocommerce_terms_page_id',
		);
		foreach ( $wc_option_keys as $opt ) {
			if ( (int) get_option( $opt, 0 ) === $post_id ) {
				return true;
			}
		}

		// WordPress blog index page.
		if ( (int) get_option( 'page_for_posts', 0 ) === $post_id ) {
			return true;
		}

		// Common utility page slugs.
		$post = get_post( $post_id );
		if ( $post && 'page' === $post->post_type ) {
			$utility_slugs = array( 'login', 'register', 'contact', 'contact-us', 'cart', 'checkout', 'my-account', 'account' );
			if ( in_array( $post->post_name, $utility_slugs, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detect duplicate meta descriptions across posts.
	 *
	 * @return array Groups of posts sharing the same meta description.
	 */
	public function detect_duplicate_meta() {
		global $wpdb;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT meta_value as description, GROUP_CONCAT(post_id) as post_ids, COUNT(*) as count
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_ai_seo_pilot_meta_description'
			AND meta_value != ''
			GROUP BY meta_value
			HAVING count > 1
			ORDER BY count DESC"
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
