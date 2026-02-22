<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LLMs.txt virtual endpoint module.
 *
 * Serves a /llms.txt file for AI search engines, either auto-generated
 * from site data or from manually entered content.
 */
class AI_SEO_Pilot_LLMs_Txt {

	/** @var string */
	private $cache_key = 'ai_seo_pilot_llms_txt_cache';

	/** @var int Cache TTL in seconds (1 hour). */
	private $cache_ttl = HOUR_IN_SECONDS;

	/** @var string[] Option keys whose changes invalidate the cache. */
	private $watched_options = array(
		'ai_seo_pilot_llms_txt_mode',
		'ai_seo_pilot_llms_txt_manual',
		'blogname',
		'blogdescription',
	);

	public function __construct() {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'template_redirect', array( $this, 'handle_request' ) );

		// Invalidate cache on content changes.
		add_action( 'save_post', array( $this, 'invalidate_cache' ) );
		foreach ( $this->watched_options as $option ) {
			add_action( "update_option_{$option}", array( $this, 'invalidate_cache' ) );
		}

	}

	/**
	 * Register the rewrite rule for /llms.txt.
	 */
	public function add_rewrite_rule() {
		add_rewrite_rule( '^llms\.txt$', 'index.php?llms_txt=1', 'top' );
	}

	/**
	 * Register llms_txt as a public query variable.
	 *
	 * @param string[] $vars Existing query vars.
	 * @return string[]
	 */
	public function register_query_var( $vars ) {
		$vars[] = 'llms_txt';
		return $vars;
	}

	/**
	 * Serve the llms.txt output when the query var is present.
	 */
	public function handle_request() {
		if ( ! get_query_var( 'llms_txt' ) ) {
			return;
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo $this->get_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Return the llms.txt content (cached).
	 *
	 * @return string
	 */
	public function get_content() {
		$cached = get_transient( $this->cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$mode    = get_option( 'ai_seo_pilot_llms_txt_mode', 'auto' );
		$content = ( 'manual' === $mode )
			? get_option( 'ai_seo_pilot_llms_txt_manual', '' )
			: $this->generate();

		set_transient( $this->cache_key, $content, $this->cache_ttl );
		return $content;
	}

	/**
	 * Validate that /llms.txt is accessible via HTTP.
	 *
	 * @return array{accessible: bool, status_code: int, content_length: int, content_preview: string}
	 */
	public function validate_accessibility() {
		$url      = home_url( '/llms.txt' );
		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return array(
				'accessible'      => false,
				'status_code'     => 0,
				'content_length'  => 0,
				'content_preview' => $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		return array(
			'accessible'      => ( 200 === $status_code ),
			'status_code'     => $status_code,
			'content_length'  => strlen( $body ),
			'content_preview' => substr( $body, 0, 500 ),
		);
	}

	/**
	 * Delete the cached content.
	 */
	public function invalidate_cache() {
		delete_transient( $this->cache_key );
	}

	/**
	 * Force auto-generation from template, clear cache, and return content.
	 *
	 * @return string
	 */
	public function regenerate_auto() {
		delete_transient( $this->cache_key );
		$content = $this->generate();
		set_transient( $this->cache_key, $content, $this->cache_ttl );
		return $content;
	}

	/* ── Private helpers ──────────────────────────────────────────── */

	/**
	 * Auto-generate llms.txt content from site data.
	 *
	 * @return string
	 */
	private function generate() {
		$site_name = get_bloginfo( 'name' );
		$site_desc = get_bloginfo( 'description' );
		$site_url  = site_url( '/' );

		$lines   = array();
		$lines[] = "# {$site_name}";
		$lines[] = '';
		$lines[] = "> {$site_desc}";
		$lines[] = '';

		// About This Site.
		$lines[] = '## About This Site';
		$lines[] = "{$site_name} is a website that {$site_desc}.";
		$lines[] = '';

		// Key Pages.
		$key_pages = $this->get_key_pages();
		if ( ! empty( $key_pages ) ) {
			$lines[] = '## Key Pages';
			foreach ( $key_pages as $page ) {
				$excerpt = $this->get_short_excerpt( $page );
				$lines[] = "- [{$page->post_title}](" . get_permalink( $page ) . "): {$excerpt}";
			}
			$lines[] = '';
		}

		// Recent Content.
		$posts = $this->get_recent_posts();
		if ( ! empty( $posts ) ) {
			$lines[] = '## Recent Content';
			foreach ( $posts as $post ) {
				$excerpt = $this->get_short_excerpt( $post );
				$lines[] = "- [{$post->post_title}](" . get_permalink( $post ) . "): {$excerpt}";
			}
			$lines[] = '';
		}

		// Contact.
		$lines[] = '## Contact';
		$lines[] = "- Website: {$site_url}";
		$lines[] = '';

		return implode( "\n", $lines );
	}

	/**
	 * Retrieve key pages (About, Contact, Privacy, Terms) by slug.
	 *
	 * @return WP_Post[]
	 */
	private function get_key_pages() {
		$slugs = array( 'about', 'about-us', 'contact', 'contact-us', 'privacy-policy', 'privacy', 'terms', 'terms-of-service', 'terms-and-conditions' );

		$query = new WP_Query( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'post_name__in'  => $slugs,
			'posts_per_page' => 10,
			'no_found_rows'  => true,
			'orderby'        => 'post_name__in',
		) );

		return $query->posts;
	}

	/**
	 * Retrieve the 10 most recent published posts.
	 *
	 * @return WP_Post[]
	 */
	private function get_recent_posts() {
		$query = new WP_Query( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );

		return $query->posts;
	}

	/**
	 * Get a short, plain-text excerpt for a post.
	 *
	 * @param WP_Post $post The post object.
	 * @return string
	 */
	private function get_short_excerpt( $post ) {
		$text = $post->post_excerpt;
		if ( empty( $text ) ) {
			$text = $post->post_content;
		}

		$text = wp_strip_all_tags( strip_shortcodes( $text ) );
		$text = str_replace( array( "\r", "\n" ), ' ', $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( mb_strlen( $text ) > 150 ) {
			$text = mb_substr( $text, 0, 147 ) . '...';
		}

		return $text;
	}
}
