<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI-optimized sitemap and robots.txt enhancements module.
 *
 * Serves /ai-sitemap.xml with extended AI metadata, enhances robots.txt
 * for AI crawlers, and adds X-Robots-Tag headers.
 */
class AI_SEO_Pilot_Sitemap_AI {

	/** @var string */
	private $cache_key = 'ai_seo_pilot_sitemap_ai_cache';

	/** @var int Cache TTL in seconds (1 hour). */
	private $cache_ttl = HOUR_IN_SECONDS;

	public function __construct() {
		if ( 'yes' !== get_option( 'ai_seo_pilot_sitemap_ai_enabled', 'yes' ) ) {
			return;
		}

		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'template_redirect', array( $this, 'handle_request' ) );

		// Cache invalidation on content changes.
		add_action( 'save_post', array( $this, 'invalidate_cache' ) );
		add_action( 'delete_post', array( $this, 'invalidate_cache' ) );

		// Robots.txt enhancement.
		if ( 'yes' === get_option( 'ai_seo_pilot_robots_txt_enhance', 'yes' ) ) {
			add_filter( 'robots_txt', array( $this, 'enhance_robots_txt' ), 10, 2 );
		}

		// X-Robots-Tag header.
		if ( 'yes' === get_option( 'ai_seo_pilot_x_robots_tag', 'yes' ) ) {
			add_action( 'send_headers', array( $this, 'add_x_robots_tag' ) );
		}

	}

	/**
	 * Register the rewrite rule for /ai-sitemap.xml.
	 */
	public function add_rewrite_rule() {
		add_rewrite_rule( '^ai-sitemap\.xml$', 'index.php?ai_sitemap=1', 'top' );
	}

	/**
	 * Register ai_sitemap as a public query variable.
	 *
	 * @param string[] $vars Existing query vars.
	 * @return string[]
	 */
	public function register_query_var( $vars ) {
		$vars[] = 'ai_sitemap';
		return $vars;
	}

	/**
	 * Serve the AI sitemap XML when the query var is present.
	 */
	public function handle_request() {
		if ( ! get_query_var( 'ai_sitemap' ) ) {
			return;
		}

		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo $this->get_sitemap_xml(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Return the AI sitemap XML string (cached).
	 *
	 * @return string
	 */
	public function get_sitemap_xml() {
		$cached = get_transient( $this->cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$xml = $this->generate();

		set_transient( $this->cache_key, $xml, $this->cache_ttl );
		return $xml;
	}

	/**
	 * Delete the cached sitemap.
	 */
	public function invalidate_cache() {
		delete_transient( $this->cache_key );
	}

	/**
	 * Enhance robots.txt with AI crawler directives.
	 *
	 * @param string $output  Existing robots.txt content.
	 * @param bool   $public  Whether the site is public.
	 * @return string
	 */
	public function enhance_robots_txt( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}

		$sitemap_url = home_url( '/ai-sitemap.xml' );

		$output .= "\n# AI Search Engine Directives\n";
		$output .= "User-agent: GPTBot\nAllow: /\n\n";
		$output .= "User-agent: ChatGPT-User\nAllow: /\n\n";
		$output .= "User-agent: Claude-Web\nAllow: /\n\n";
		$output .= "User-agent: ClaudeBot\nAllow: /\n\n";
		$output .= "User-agent: PerplexityBot\nAllow: /\n\n";
		$output .= "User-agent: Google-Extended\nAllow: /\n\n";
		$output .= "User-agent: Amazonbot\nAllow: /\n\n";
		$output .= "Sitemap: {$sitemap_url}\n";

		return $output;
	}

	/**
	 * Add X-Robots-Tag header to allow all indexing.
	 */
	public function add_x_robots_tag() {
		if ( ! headers_sent() ) {
			header( 'X-Robots-Tag: all' );
		}
	}

	/* ── Private helpers ──────────────────────────────────────────── */

	/**
	 * Generate the AI sitemap XML.
	 *
	 * @return string
	 */
	private function generate() {
		$posts = $this->get_published_content();

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
		$xml .= '        xmlns:ai="http://www.ai-seo-pilot.com/schemas/ai-sitemap/1.0">' . "\n";

		foreach ( $posts as $post ) {
			$xml .= $this->build_url_entry( $post );
		}

		$xml .= '</urlset>' . "\n";

		return $xml;
	}

	/**
	 * Build a single <url> entry for a post.
	 *
	 * @param WP_Post $post The post object.
	 * @return string
	 */
	private function build_url_entry( $post ) {
		$permalink    = esc_url( get_permalink( $post ) );
		$lastmod      = get_post_modified_time( 'c', true, $post );
		$content      = $post->post_content;
		$plain_text   = wp_strip_all_tags( strip_shortcodes( $content ) );
		$word_count   = str_word_count( $plain_text );
		$content_type = $this->detect_content_type( $post, $content );
		$reading_lvl  = $this->estimate_reading_level( $plain_text );
		$has_stats    = $this->has_statistics( $plain_text ) ? 'true' : 'false';
		$has_faq      = $this->has_faq( $content ) ? 'true' : 'false';

		$xml  = "\t<url>\n";
		$xml .= "\t\t<loc>{$permalink}</loc>\n";
		$xml .= "\t\t<lastmod>{$lastmod}</lastmod>\n";
		$xml .= "\t\t<ai:content_type>{$content_type}</ai:content_type>\n";
		$xml .= "\t\t<ai:reading_level>{$reading_lvl}</ai:reading_level>\n";
		$xml .= "\t\t<ai:word_count>{$word_count}</ai:word_count>\n";
		$xml .= "\t\t<ai:has_statistics>{$has_stats}</ai:has_statistics>\n";
		$xml .= "\t\t<ai:has_faq>{$has_faq}</ai:has_faq>\n";
		$xml .= "\t</url>\n";

		return $xml;
	}

	/**
	 * Retrieve all published posts and pages.
	 *
	 * @return WP_Post[]
	 */
	private function get_published_content() {
		$query = new WP_Query( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );

		return $query->posts;
	}

	/**
	 * Detect content type from post type and content patterns.
	 *
	 * @param WP_Post $post    The post object.
	 * @param string  $content Raw post content.
	 * @return string Article|Page|Product|FAQ
	 */
	private function detect_content_type( $post, $content ) {
		// FAQ detection: question headings or FAQ-like patterns.
		if ( $this->has_faq( $content ) ) {
			return 'FAQ';
		}

		// Product detection: WooCommerce product post type.
		if ( 'product' === $post->post_type ) {
			return 'Product';
		}

		// Posts are articles, pages are pages.
		if ( 'post' === $post->post_type ) {
			return 'Article';
		}

		return 'Page';
	}

	/**
	 * Estimate reading level from average sentence length.
	 *
	 * Heuristic: < 15 avg words/sentence = basic,
	 * 15-25 = intermediate, > 25 = advanced.
	 *
	 * @param string $plain_text Plain text content.
	 * @return string basic|intermediate|advanced
	 */
	private function estimate_reading_level( $plain_text ) {
		$sentences = preg_split( '/[.!?]+\s+/', $plain_text, -1, PREG_SPLIT_NO_EMPTY );

		if ( empty( $sentences ) ) {
			return 'basic';
		}

		$total_words = 0;
		foreach ( $sentences as $sentence ) {
			$total_words += str_word_count( $sentence );
		}

		$avg = $total_words / count( $sentences );

		if ( $avg < 15 ) {
			return 'basic';
		}

		if ( $avg <= 25 ) {
			return 'intermediate';
		}

		return 'advanced';
	}

	/**
	 * Check whether the text contains statistical data.
	 *
	 * Looks for numbers alongside %, $, or digit patterns in context
	 * (e.g. "50%", "$1,000", "increased by 30").
	 *
	 * @param string $plain_text Plain text content.
	 * @return bool
	 */
	private function has_statistics( $plain_text ) {
		// Match percentages (50%), currency ($1,000), or numbers with context.
		return (bool) preg_match( '/\d+[\.,]?\d*\s*%|\$\s*\d+|\d{2,}[\.,]\d+/', $plain_text );
	}

	/**
	 * Check whether the content contains FAQ-like patterns.
	 *
	 * Looks for question headings (h2-h6 with ?) or FAQ schema patterns.
	 *
	 * @param string $content Raw post content (HTML).
	 * @return bool
	 */
	private function has_faq( $content ) {
		// Question in heading tags.
		if ( preg_match( '/<h[2-6][^>]*>[^<]*\?[^<]*<\/h[2-6]>/i', $content ) ) {
			return true;
		}

		// FAQ in heading text.
		if ( preg_match( '/<h[2-6][^>]*>[^<]*\bFAQ\b[^<]*<\/h[2-6]>/i', $content ) ) {
			return true;
		}

		// wp:yoast/faq-block or similar FAQ block patterns.
		if ( false !== stripos( $content, 'faq' ) && preg_match( '/wp:.*faq/i', $content ) ) {
			return true;
		}

		return false;
	}
}
