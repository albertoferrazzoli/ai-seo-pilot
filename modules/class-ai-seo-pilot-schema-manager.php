<?php
/**
 * Schema Manager — Schema.org JSON-LD output for AI search engines.
 *
 * @package AI_SEO_Pilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_SEO_Pilot_Schema_Manager {

	/**
	 * Schema types available for override.
	 *
	 * @var string[]
	 */
	private $allowed_types = array(
		'auto',
		'Article',
		'BlogPosting',
		'FAQPage',
		'HowTo',
		'NewsArticle',
		'none',
	);

	/**
	 * Constructor — register all hooks.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_schema' ), 1 );
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	/* ── Hook Callbacks ─────────────────────────────────────────── */

	/**
	 * Register post meta for schema type override.
	 */
	public function register_meta() {
		register_post_meta( '', '_ai_seo_pilot_schema_type', array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'default'       => 'auto',
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}

	/**
	 * Output JSON-LD in wp_head.
	 */
	public function output_schema() {
		if ( 'yes' !== get_option( 'ai_seo_pilot_schema_enabled', 'yes' ) ) {
			return;
		}

		$graph = array();

		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			$schema  = $this->get_schema_for_post( $post_id );
			if ( $schema ) {
				$graph[] = $schema;
			}
		}

		if ( is_front_page() ) {
			if ( 'yes' === get_option( 'ai_seo_pilot_schema_website', 'yes' ) ) {
				$graph[] = $this->build_website_schema();
			}
			if ( 'yes' === get_option( 'ai_seo_pilot_schema_organization', 'yes' ) ) {
				$graph[] = $this->build_organization_schema();
			}
		}

		if ( 'yes' === get_option( 'ai_seo_pilot_schema_breadcrumbs', 'yes' ) && ! is_front_page() ) {
			$breadcrumb = $this->build_breadcrumb_schema();
			if ( $breadcrumb ) {
				$graph[] = $breadcrumb;
			}
		}

		// Allow WooCommerce product schema on singular product pages.
		if ( is_singular( 'product' ) && function_exists( 'wc_get_product' ) ) {
			$product_schema = $this->build_product_schema( get_queried_object_id() );
			if ( $product_schema ) {
				$graph[] = $product_schema;
			}
		}

		if ( empty( $graph ) ) {
			return;
		}

		$output = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		$json = wp_json_encode( $output, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		if ( ! $json ) {
			return;
		}

		echo "\n<!-- AI-SEO Pilot Schema -->\n";
		echo '<script type="application/ld+json">' . "\n";
		echo $json . "\n";
		echo '</script>' . "\n";
	}

	/* ── Public API ─────────────────────────────────────────────── */

	/**
	 * Get schema array for a specific post.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null Schema array or null when type is 'none'.
	 */
	public function get_schema_for_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}

		$type = $this->get_detected_type( $post_id );
		if ( 'none' === $type ) {
			return null;
		}

		$builder = 'build_' . strtolower( str_replace( array( 'Page', 'Posting' ), array( '_page', '_posting' ), $type ) ) . '_schema';

		// Map type to builder method.
		$method_map = array(
			'Article'     => 'build_article_schema',
			'BlogPosting' => 'build_article_schema',
			'FAQPage'     => 'build_faq_page_schema',
			'HowTo'       => 'build_how_to_schema',
			'NewsArticle' => 'build_article_schema',
		);

		$method = isset( $method_map[ $type ] ) ? $method_map[ $type ] : 'build_article_schema';

		return $this->$method( $post, $type );
	}

	/**
	 * Get the detected (or overridden) schema type for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string Schema type string.
	 */
	public function get_detected_type( $post_id ) {
		$override = get_post_meta( $post_id, '_ai_seo_pilot_schema_type', true );
		if ( $override && 'auto' !== $override && in_array( $override, $this->allowed_types, true ) ) {
			return $override;
		}

		return $this->auto_detect_type( $post_id );
	}

	/**
	 * Get formatted JSON-LD string for a post (for preview).
	 *
	 * @param int $post_id Post ID.
	 * @return string JSON string.
	 */
	public function get_schema_json( $post_id ) {
		$schema = $this->get_schema_for_post( $post_id );
		if ( ! $schema ) {
			return '';
		}

		$output = array(
			'@context' => 'https://schema.org',
			'@graph'   => array( $schema ),
		);

		return wp_json_encode( $output, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
	}

	/* ── Auto-detection ─────────────────────────────────────────── */

	/**
	 * Auto-detect schema type from post content.
	 *
	 * @param int $post_id Post ID.
	 * @return string Detected schema type.
	 */
	private function auto_detect_type( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return 'Article';
		}

		// WooCommerce product.
		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			return 'Article'; // Product schema handled separately in output.
		}

		$content = $post->post_content;

		// Check for FAQ patterns.
		if ( $this->detect_faq( $content ) ) {
			return 'FAQPage';
		}

		// Check for HowTo patterns.
		if ( $this->detect_how_to( $content ) ) {
			return 'HowTo';
		}

		// Blog posts → BlogPosting, pages and other types → Article.
		if ( 'post' === $post->post_type ) {
			return 'BlogPosting';
		}

		return 'Article';
	}

	/**
	 * Detect FAQ content (Gutenberg FAQ block or H2-based Q&A pattern).
	 *
	 * @param string $content Post content.
	 * @return bool
	 */
	private function detect_faq( $content ) {
		// Gutenberg FAQ block (Yoast, Rank Math, or core).
		if ( has_block( 'yoast-seo/faq-block' ) || has_block( 'rank-math/faq-block' ) ) {
			return true;
		}

		// Pattern: multiple H2s that look like questions (contain "?").
		if ( preg_match_all( '/<h2[^>]*>([^<]*\?[^<]*)<\/h2>/i', $content, $matches ) ) {
			return count( $matches[0] ) >= 3;
		}

		return false;
	}

	/**
	 * Detect HowTo / step-by-step content.
	 *
	 * @param string $content Post content.
	 * @return bool
	 */
	private function detect_how_to( $content ) {
		// Pattern: ordered list with 3+ items following a "step" heading.
		if ( preg_match( '/step[\s\-]*by[\s\-]*step/i', $content ) ) {
			return true;
		}

		// Multiple "Step N" headings.
		if ( preg_match_all( '/<h[23][^>]*>\s*step\s+\d/i', $content, $matches ) ) {
			return count( $matches[0] ) >= 3;
		}

		return false;
	}

	/* ── Schema Builders ────────────────────────────────────────── */

	/**
	 * Build Article / BlogPosting / NewsArticle schema.
	 *
	 * @param WP_Post $post Post object.
	 * @param string  $type Schema type.
	 * @return array
	 */
	private function build_article_schema( $post, $type = 'Article' ) {
		$schema = array(
			'@type'            => $type,
			'@id'              => get_permalink( $post ) . '#article',
			'headline'         => get_the_title( $post ),
			'url'              => get_permalink( $post ),
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'inLanguage'       => $this->get_language(),
			'isAccessibleForFree' => $this->is_accessible_for_free( $post->ID ),
			'author'           => $this->build_author( $post ),
			'publisher'        => $this->build_publisher(),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => get_permalink( $post ),
			),
			'speakable'        => $this->build_speakable( $post ),
		);

		// Description.
		$excerpt = $this->get_description( $post );
		if ( $excerpt ) {
			$schema['description'] = $excerpt;
		}

		// Featured image.
		$image = $this->get_featured_image( $post->ID );
		if ( $image ) {
			$schema['image'] = $image;
		}

		// Word count.
		$word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
		if ( $word_count > 0 ) {
			$schema['wordCount'] = $word_count;
		}

		return $schema;
	}

	/**
	 * Build FAQPage schema.
	 *
	 * @param WP_Post $post Post object.
	 * @param string  $type Schema type (unused, always FAQPage).
	 * @return array
	 */
	private function build_faq_page_schema( $post, $type = 'FAQPage' ) {
		$schema = array(
			'@type'               => 'FAQPage',
			'@id'                 => get_permalink( $post ) . '#faqpage',
			'name'                => get_the_title( $post ),
			'url'                 => get_permalink( $post ),
			'dateModified'        => get_the_modified_date( 'c', $post ),
			'inLanguage'          => $this->get_language(),
			'isAccessibleForFree' => $this->is_accessible_for_free( $post->ID ),
			'speakable'           => $this->build_speakable( $post ),
		);

		// Extract Q&A pairs from H2 headings with questions.
		$questions = $this->extract_faq_questions( $post->post_content );
		if ( ! empty( $questions ) ) {
			$schema['mainEntity'] = $questions;
		}

		return $schema;
	}

	/**
	 * Build HowTo schema.
	 *
	 * @param WP_Post $post Post object.
	 * @param string  $type Schema type (unused, always HowTo).
	 * @return array
	 */
	private function build_how_to_schema( $post, $type = 'HowTo' ) {
		$schema = array(
			'@type'               => 'HowTo',
			'@id'                 => get_permalink( $post ) . '#howto',
			'name'                => get_the_title( $post ),
			'url'                 => get_permalink( $post ),
			'dateModified'        => get_the_modified_date( 'c', $post ),
			'inLanguage'          => $this->get_language(),
			'isAccessibleForFree' => $this->is_accessible_for_free( $post->ID ),
			'author'              => $this->build_author( $post ),
			'speakable'           => $this->build_speakable( $post ),
		);

		$description = $this->get_description( $post );
		if ( $description ) {
			$schema['description'] = $description;
		}

		$image = $this->get_featured_image( $post->ID );
		if ( $image ) {
			$schema['image'] = $image;
		}

		// Extract steps.
		$steps = $this->extract_how_to_steps( $post->post_content );
		if ( ! empty( $steps ) ) {
			$schema['step'] = $steps;
		}

		return $schema;
	}

	/**
	 * Build Product schema for WooCommerce products.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	private function build_product_schema( $post_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return null;
		}

		$schema = array(
			'@type'               => 'Product',
			'@id'                 => get_permalink( $post_id ) . '#product',
			'name'                => $product->get_name(),
			'url'                 => get_permalink( $post_id ),
			'description'         => wp_strip_all_tags( $product->get_short_description() ),
			'sku'                 => $product->get_sku(),
			'inLanguage'          => $this->get_language(),
			'isAccessibleForFree' => false,
		);

		$image = $this->get_featured_image( $post_id );
		if ( $image ) {
			$schema['image'] = $image;
		}

		// Offers.
		$price = $product->get_price();
		if ( '' !== $price ) {
			$schema['offers'] = array(
				'@type'         => 'Offer',
				'price'         => $price,
				'priceCurrency' => get_woocommerce_currency(),
				'availability'  => $product->is_in_stock()
					? 'https://schema.org/InStock'
					: 'https://schema.org/OutOfStock',
				'url'           => get_permalink( $post_id ),
			);
		}

		// Average rating.
		$rating = $product->get_average_rating();
		$count  = $product->get_review_count();
		if ( $rating > 0 && $count > 0 ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $rating,
				'reviewCount' => $count,
			);
		}

		return $schema;
	}

	/**
	 * Build WebSite schema.
	 *
	 * @return array
	 */
	private function build_website_schema() {
		$schema = array(
			'@type'      => 'WebSite',
			'@id'        => home_url( '/#website' ),
			'url'        => home_url( '/' ),
			'name'       => get_bloginfo( 'name' ),
			'inLanguage' => $this->get_language(),
		);

		$description = get_bloginfo( 'description' );
		if ( $description ) {
			$schema['description'] = $description;
		}

		// SearchAction for sitelinks search box.
		$schema['potentialAction'] = array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => home_url( '/?s={search_term_string}' ),
			),
			'query-input' => 'required name=search_term_string',
		);

		return $schema;
	}

	/**
	 * Build Organization schema.
	 *
	 * @return array
	 */
	private function build_organization_schema() {
		$schema = array(
			'@type' => 'Organization',
			'@id'   => home_url( '/#organization' ),
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		);

		$description = get_bloginfo( 'description' );
		if ( $description ) {
			$schema['description'] = $description;
		}

		// Custom logo.
		$logo_id = get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $logo_url ) {
				$schema['logo'] = array(
					'@type' => 'ImageObject',
					'url'   => $logo_url,
				);
			}
		}

		// Social profiles / sameAs.
		$same_as_raw = get_option( 'ai_seo_pilot_organization_same_as', '' );
		if ( $same_as_raw ) {
			$urls = array_filter( array_map( 'trim', explode( "\n", $same_as_raw ) ) );
			$urls = array_filter( $urls, 'wp_http_validate_url' );
			if ( ! empty( $urls ) ) {
				$schema['sameAs'] = array_values( $urls );
			}
		}

		return $schema;
	}

	/**
	 * Build BreadcrumbList schema.
	 *
	 * @return array|null
	 */
	private function build_breadcrumb_schema() {
		$items = array();
		$pos   = 1;

		// Home.
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $pos++,
			'name'     => __( 'Home', 'ai-seo-pilot' ),
			'item'     => home_url( '/' ),
		);

		if ( is_singular() ) {
			$post = get_queried_object();

			// Category for posts.
			if ( 'post' === $post->post_type ) {
				$categories = get_the_category( $post->ID );
				if ( ! empty( $categories ) ) {
					$cat     = $categories[0];
					$items[] = array(
						'@type'    => 'ListItem',
						'position' => $pos++,
						'name'     => $cat->name,
						'item'     => get_category_link( $cat->term_id ),
					);
				}
			}

			// Page ancestors.
			if ( 'page' === $post->post_type && $post->post_parent ) {
				$ancestors = array_reverse( get_post_ancestors( $post->ID ) );
				foreach ( $ancestors as $ancestor_id ) {
					$items[] = array(
						'@type'    => 'ListItem',
						'position' => $pos++,
						'name'     => get_the_title( $ancestor_id ),
						'item'     => get_permalink( $ancestor_id ),
					);
				}
			}

			// WooCommerce product category.
			if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
				$terms = get_the_terms( $post->ID, 'product_cat' );
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					$term    = $terms[0];
					$items[] = array(
						'@type'    => 'ListItem',
						'position' => $pos++,
						'name'     => $term->name,
						'item'     => get_term_link( $term ),
					);
				}
			}

			// Current page (no item URL per Google spec).
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos,
				'name'     => get_the_title( $post ),
			);
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term    = get_queried_object();
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos,
				'name'     => $term->name,
			);
		} elseif ( is_archive() ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos,
				'name'     => get_the_archive_title(),
			);
		}

		if ( count( $items ) < 2 ) {
			return null;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $this->get_current_url() . '#breadcrumb',
			'itemListElement' => $items,
		);
	}

	/* ── Content Extractors ─────────────────────────────────────── */

	/**
	 * Extract FAQ Question/Answer pairs from post content.
	 *
	 * @param string $content Post content.
	 * @return array Array of Question schema objects.
	 */
	private function extract_faq_questions( $content ) {
		$questions = array();

		// Match H2 questions followed by content until next H2 or end.
		if ( preg_match_all(
			'/<h2[^>]*>([^<]*\?[^<]*)<\/h2>(.*?)(?=<h2|$)/is',
			$content,
			$matches,
			PREG_SET_ORDER
		) ) {
			foreach ( $matches as $match ) {
				$question = wp_strip_all_tags( trim( $match[1] ) );
				$answer   = wp_strip_all_tags( trim( $match[2] ) );
				if ( $question && $answer ) {
					$questions[] = array(
						'@type'          => 'Question',
						'name'           => $question,
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => $answer,
						),
					);
				}
			}
		}

		return $questions;
	}

	/**
	 * Extract HowTo steps from post content.
	 *
	 * @param string $content Post content.
	 * @return array Array of HowToStep schema objects.
	 */
	private function extract_how_to_steps( $content ) {
		$steps = array();

		// Match "Step N:" or "Step N." headings.
		if ( preg_match_all(
			'/<h[23][^>]*>\s*(step\s+\d+[.:]\s*)(.*?)<\/h[23]>(.*?)(?=<h[23]|$)/is',
			$content,
			$matches,
			PREG_SET_ORDER
		) ) {
			$pos = 1;
			foreach ( $matches as $match ) {
				$name = wp_strip_all_tags( trim( $match[2] ) );
				$text = wp_strip_all_tags( trim( $match[3] ) );
				if ( $name ) {
					$step = array(
						'@type'    => 'HowToStep',
						'position' => $pos++,
						'name'     => $name,
					);
					if ( $text ) {
						$step['text'] = $text;
					}
					$steps[] = $step;
				}
			}
		}

		return $steps;
	}

	/* ── Helpers ─────────────────────────────────────────────────── */

	/**
	 * Build author schema.
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	private function build_author( $post ) {
		$author = array(
			'@type' => 'Person',
			'name'  => get_the_author_meta( 'display_name', $post->post_author ),
		);

		$author_url = get_author_posts_url( $post->post_author );
		if ( $author_url ) {
			$author['url']    = $author_url;
			$author['sameAs'] = array( $author_url );
		}

		$website = get_the_author_meta( 'url', $post->post_author );
		if ( $website && $website !== $author_url ) {
			$author['sameAs'][] = $website;
		}

		return $author;
	}

	/**
	 * Build publisher schema.
	 *
	 * @return array
	 */
	private function build_publisher() {
		$publisher = array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
			'@id'   => home_url( '/#organization' ),
		);

		$logo_id = get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $logo_url ) {
				$publisher['logo'] = array(
					'@type' => 'ImageObject',
					'url'   => $logo_url,
				);
			}
		}

		return $publisher;
	}

	/**
	 * Build speakable property targeting title + first paragraph.
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	private function build_speakable( $post ) {
		return array(
			'@type'       => 'SpeakableSpecification',
			'cssSelector' => array(
				'.entry-title',
				'.post-title',
				'h1',
				'.entry-content p:first-of-type',
				'article p:first-of-type',
			),
		);
	}

	/**
	 * Check if content is accessible for free.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function is_accessible_for_free( $post_id ) {
		// WooCommerce product pages are not "free".
		if ( 'product' === get_post_type( $post_id ) ) {
			return false;
		}

		// Check for common paywall / membership blocks.
		$content = get_post_field( 'post_content', $post_id );
		if ( has_block( 'woocommerce/product-on-sale', $content ) ) {
			return false;
		}

		// Check for restricted content shortcodes (MemberPress, Restrict Content Pro, etc.).
		$paywall_shortcodes = array(
			'mepr-membership-registration-form',
			'restrict',
			'members-only',
		);
		foreach ( $paywall_shortcodes as $shortcode ) {
			if ( has_shortcode( $content, $shortcode ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get post description from excerpt or content.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function get_description( $post ) {
		if ( $post->post_excerpt ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}

		$content = wp_strip_all_tags( $post->post_content );
		if ( strlen( $content ) > 160 ) {
			$content = mb_substr( $content, 0, 157 ) . '...';
		}

		return $content;
	}

	/**
	 * Get featured image data.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	private function get_featured_image( $post_id ) {
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( ! $thumb_id ) {
			return null;
		}

		$image = wp_get_attachment_image_src( $thumb_id, 'full' );
		if ( ! $image ) {
			return null;
		}

		return array(
			'@type'  => 'ImageObject',
			'url'    => $image[0],
			'width'  => $image[1],
			'height' => $image[2],
		);
	}

	/**
	 * Get the current language in BCP-47 format.
	 *
	 * @return string
	 */
	private function get_language() {
		$locale = get_locale();
		return str_replace( '_', '-', $locale );
	}

	/**
	 * Get the current page URL.
	 *
	 * @return string
	 */
	private function get_current_url() {
		global $wp;
		return home_url( add_query_arg( array(), $wp->request ) );
	}
}
