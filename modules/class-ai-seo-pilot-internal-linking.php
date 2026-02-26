<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI-powered Internal Linking module.
 *
 * Uses AI to semantically analyze content and suggest relevant internal links,
 * detect orphan pages, and provide anchor text recommendations.
 */
class AI_SEO_Pilot_Internal_Linking {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route( 'ai-seo-pilot/v1', '/linking/suggestions', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_suggestions' ),
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

		register_rest_route( 'ai-seo-pilot/v1', '/linking/orphan-pages', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_orphan_pages' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}

	/**
	 * REST callback — get internal link suggestions for a post.
	 */
	public function rest_suggestions( $request ) {
		if ( 'yes' !== get_option( 'ai_seo_pilot_internal_linking_enabled', 'yes' ) ) {
			return new \WP_Error( 'disabled', __( 'Internal linking is disabled.', 'ai-seo-pilot' ), array( 'status' => 403 ) );
		}

		$post_id = $request->get_param( 'post_id' );
		$result  = $this->get_suggestions( $post_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * REST callback — find orphan pages.
	 */
	public function rest_orphan_pages( $request ) {
		if ( 'yes' !== get_option( 'ai_seo_pilot_internal_linking_enabled', 'yes' ) ) {
			return new \WP_Error( 'disabled', __( 'Internal linking is disabled.', 'ai-seo-pilot' ), array( 'status' => 403 ) );
		}

		$orphans = $this->find_orphan_pages();

		return rest_ensure_response( $orphans );
	}

	/**
	 * Get AI-powered internal link suggestions for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array|WP_Error Link suggestions.
	 */
	public function get_suggestions( $post_id ) {
		$plugin = AI_SEO_Pilot::get_instance();

		if ( ! $plugin->ai_engine->is_configured() ) {
			return new \WP_Error( 'ai_not_configured', __( 'AI Engine is not configured.', 'ai-seo-pilot' ) );
		}

		// Get candidate posts (exclude current post).
		$candidates = $this->get_candidate_posts( $post_id );

		if ( empty( $candidates ) ) {
			return array(
				'suggestions' => array(),
				'message'     => __( 'No candidate pages found for linking.', 'ai-seo-pilot' ),
			);
		}

		// Get existing links in this post to exclude them.
		$existing_links = $this->get_existing_links( $post_id );

		// Filter out already-linked posts.
		$filtered = array_filter( $candidates, function ( $c ) use ( $existing_links ) {
			return ! in_array( $c['url'], $existing_links, true );
		} );

		if ( empty( $filtered ) ) {
			return array(
				'suggestions' => array(),
				'message'     => __( 'All relevant pages are already linked from this post.', 'ai-seo-pilot' ),
			);
		}

		// Limit to 15 candidates to keep the prompt manageable.
		$filtered = array_slice( array_values( $filtered ), 0, 15 );

		$response = $plugin->ai_engine->suggest_internal_links( $post_id, $filtered );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$suggestions = $this->parse_json_response( $response );

		if ( is_wp_error( $suggestions ) ) {
			return $suggestions;
		}

		return array( 'suggestions' => $suggestions );
	}

	/**
	 * Find orphan pages (published pages with no internal links pointing to them).
	 *
	 * @return array List of orphan pages.
	 */
	public function find_orphan_pages() {
		// Check transient cache.
		$cached = get_transient( 'ai_seo_pilot_orphan_pages' );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		// Get all published pages and posts.
		$all_posts = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		$orphans = array();

		foreach ( $all_posts as $target_id ) {
			$target_url  = get_permalink( $target_id );
			$target_path = wp_parse_url( $target_url, PHP_URL_PATH );

			if ( empty( $target_path ) ) {
				continue;
			}

			// Search for links to this post in other posts' content.
			$found = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT COUNT(*) FROM {$wpdb->posts}
				WHERE post_status = 'publish'
				AND post_type IN ('post', 'page')
				AND ID != %d
				AND post_content LIKE %s",
				$target_id,
				'%' . $wpdb->esc_like( $target_path ) . '%'
			) );

			if ( 0 === (int) $found ) {
				$post = get_post( $target_id );
				$orphans[] = array(
					'id'    => $target_id,
					'title' => $post->post_title,
					'url'   => $target_url,
					'type'  => $post->post_type,
				);
			}
		}

		// Cache for 12 hours.
		set_transient( 'ai_seo_pilot_orphan_pages', $orphans, 12 * HOUR_IN_SECONDS );

		return $orphans;
	}

	/**
	 * Get candidate posts for internal linking.
	 *
	 * @param int $exclude_id Post ID to exclude.
	 * @return array Candidate posts.
	 */
	private function get_candidate_posts( $exclude_id ) {
		$posts = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'exclude'        => array( $exclude_id ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		$candidates = array();
		foreach ( $posts as $post ) {
			$candidates[] = array(
				'id'      => $post->ID,
				'title'   => $post->post_title,
				'url'     => get_permalink( $post ),
				'excerpt' => wp_trim_words( $post->post_excerpt ?: wp_strip_all_tags( $post->post_content ), 30, '...' ),
			);
		}

		return $candidates;
	}

	/**
	 * Get existing internal links in a post's content.
	 *
	 * @param int $post_id Post ID.
	 * @return array List of URLs already linked.
	 */
	private function get_existing_links( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$home_url = home_url();
		$links    = array();

		if ( preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				if ( strpos( $url, $home_url ) === 0 || strpos( $url, '/' ) === 0 ) {
					$links[] = $url;
				}
			}
		}

		return $links;
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
