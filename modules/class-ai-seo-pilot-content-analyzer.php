<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content Analyzer module.
 *
 * Provides content analysis scoring via:
 * - `analyze()` — fast regex-based checks (used by widget and editor in real-time).
 * - `analyze_with_ai()` — AI-powered scoring for language-dependent checks (manual trigger only).
 *
 * AI results are stored persistently and displayed in the widget when available.
 */
class AI_SEO_Pilot_Content_Analyzer {

	/** @var string REST namespace. */
	private $namespace = 'ai-seo-pilot/v1';

	/** @var array Resolved settings (defaults merged with DB). */
	private $settings;

	/** @var string Option key for stored AI scores. */
	const AI_SCORES_OPTION = 'aisp_ai_content_scores';

	/** @var array Checks evaluated by AI (language-dependent). */
	private static $ai_checks = array(
		'direct_answer',
		'definitions',
		'entity_density',
		'citable_statistics',
		'semantic_completeness',
		'freshness_signals',
	);

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'wp_ajax_aisp_run_ai_analysis', array( $this, 'ajax_run_ai_analysis' ) );
	}

	/* ── Settings ─────────────────────────────────────────────── */

	/**
	 * Return hard-coded default settings.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'ai_ready_threshold' => 75,
			'checks'             => array(
				'direct_answer'        => array( 'enabled' => true, 'weight' => 20, 'max_chars' => 200 ),
				'qa_structure'         => array( 'enabled' => true, 'weight' => 10, 'min_questions' => 3 ),
				'definitions'          => array( 'enabled' => true, 'weight' => 10, 'min_definitions' => 2 ),
				'paragraph_length'     => array( 'enabled' => true, 'weight' => 10, 'max_avg_words' => 100 ),
				'list_optimization'    => array( 'enabled' => true, 'weight' => 8,  'min_lists' => 2 ),
				'entity_density'       => array( 'enabled' => true, 'weight' => 5,  'min_density' => 2 ),
				'citable_statistics'   => array( 'enabled' => true, 'weight' => 8,  'min_stats' => 3 ),
				'semantic_completeness' => array( 'enabled' => true, 'weight' => 15, 'min_words' => 300, 'max_words' => 2000 ),
				'snippet_optimization' => array( 'enabled' => true, 'weight' => 15, 'max_summary_words' => 60 ),
				'freshness_signals'    => array( 'enabled' => true, 'weight' => 5,  'min_signals' => 2 ),
			),
		);
	}

	/**
	 * Get resolved settings (DB merged with defaults).
	 *
	 * @return array
	 */
	private function get_settings() {
		if ( null !== $this->settings ) {
			return $this->settings;
		}

		$defaults = self::get_defaults();
		$saved    = get_option( 'ai_seo_pilot_content_analysis', array() );
		$settings = wp_parse_args( $saved, $defaults );

		foreach ( $defaults['checks'] as $key => $def ) {
			$settings['checks'][ $key ] = wp_parse_args(
				isset( $settings['checks'][ $key ] ) ? $settings['checks'][ $key ] : array(),
				$def
			);
		}

		$this->settings = $settings;
		return $settings;
	}

	/**
	 * Get settings for a specific check.
	 *
	 * @param string $check_name Check key.
	 * @return array
	 */
	private function check_settings( $check_name ) {
		$settings = $this->get_settings();
		return $settings['checks'][ $check_name ];
	}

	/* ── Universal content extraction ─────────────────────────── */

	/**
	 * Extract standard HTML from any content format.
	 *
	 * This is a universal extractor that works with any page builder or editor:
	 * - Divi 5 (JSON-in-HTML-comments with wp:divi/* blocks)
	 * - Gutenberg (native WordPress blocks)
	 * - Elementor (_elementor_data JSON in post meta)
	 * - WPBakery / Visual Composer ([vc_*] shortcodes)
	 * - Beaver Builder ([fl_builder_*] shortcodes)
	 * - Divi 4 classic ([et_pb_*] shortcodes)
	 * - Plain HTML
	 *
	 * Strategy: try multiple extraction methods, pick the richest result.
	 *
	 * @param string $content Raw post content.
	 * @return string Clean HTML with standard tags (<p>, <h2>, <strong>, <ul>, etc.)
	 */
	public function render_content( $content ) {
		if ( empty( $content ) ) {
			return '';
		}

		// Already clean HTML with standard tags? Return as-is.
		$has_standard_html = preg_match( '/<(?:p|h[1-6]|ul|ol|table)[\s>]/i', $content )
			&& ! preg_match( '/<!--\s+wp:/', $content )
			&& ! preg_match( '/\[(?:vc_|et_pb_|fl_builder)/', $content );

		if ( $has_standard_html ) {
			return $content;
		}

		$candidates = array();

		// 1. Divi 5 blocks (JSON-in-comments).
		if ( preg_match( '/<!--\s+wp:divi\//', $content ) ) {
			$html = $this->extract_from_block_json( $content );
			if ( ! empty( $html ) ) {
				$candidates[] = $html;
			}
		}

		// 2. Gutenberg blocks (native or third-party).
		if ( preg_match( '/<!--\s+wp:/', $content ) ) {
			// Try WordPress native rendering first.
			if ( function_exists( 'do_blocks' ) ) {
				$rendered = do_blocks( $content );
				if ( function_exists( 'do_shortcode' ) ) {
					$rendered = do_shortcode( $rendered );
				}
				if ( preg_match( '/<(?:p|h[1-6]|ul|ol|li|strong)[\s>]/i', $rendered ) ) {
					$candidates[] = $rendered;
				}
			}

			// Also try JSON extraction (works for any block storing content in JSON attrs).
			if ( empty( $candidates ) ) {
				$html = $this->extract_from_block_json( $content );
				if ( ! empty( $html ) ) {
					$candidates[] = $html;
				}
			}
		}

		// 3. Shortcode-based builders (WPBakery, Divi 4, Beaver Builder).
		if ( preg_match( '/\[(?:vc_|et_pb_|fl_builder)/i', $content ) && function_exists( 'do_shortcode' ) ) {
			$rendered = do_shortcode( $content );
			if ( preg_match( '/<(?:p|h[1-6]|ul|ol|li|strong)[\s>]/i', $rendered ) ) {
				$candidates[] = $rendered;
			}
		}

		// Pick the richest candidate (most HTML tags).
		if ( ! empty( $candidates ) ) {
			$best    = '';
			$best_ct = 0;
			foreach ( $candidates as $c ) {
				$tag_count = preg_match_all( '/<(?:p|h[1-6]|ul|ol|li|strong|em|table|tr|td|th)[\s>]/i', $c );
				if ( $tag_count > $best_ct ) {
					$best    = $c;
					$best_ct = $tag_count;
				}
			}
			if ( ! empty( $best ) ) {
				return $best;
			}
		}

		// 4. Last resort: strip block comments and shortcodes, return whatever HTML remains.
		$raw = preg_replace( '/<!--\s+\/?wp:[^\-]*?-->/s', '', $content );
		$raw = preg_replace( '/\[\/?[^\]]+\]/', '', $raw );

		return $raw;
	}

	/**
	 * Extract HTML from block comments that embed content as JSON attributes.
	 *
	 * Works with Divi 5, and any block that stores content in JSON.
	 *
	 * @param string $content Raw content with block comments.
	 * @return string Extracted HTML.
	 */
	private function extract_from_block_json( $content ) {
		$html_parts = array();

		preg_match_all( '/<!--\s+wp:(\S+)\s+(\{.+?\})\s+(?:\/)?-->/s', $content, $blocks, PREG_SET_ORDER );

		foreach ( $blocks as $block ) {
			$block_type = $block[1];
			$json       = json_decode( $block[2], true );

			if ( ! is_array( $json ) ) {
				continue;
			}

			// Heading blocks → wrap in proper heading tag.
			if ( preg_match( '/heading$/i', $block_type ) ) {
				$text  = $this->json_deep_value( $json, 'title' );
				$level = $this->json_heading_level( $json );
				if ( $text ) {
					$html_parts[] = "<{$level}>{$text}</{$level}>";
				}
				continue;
			}

			// Text / content blocks → extract inner HTML.
			$value = $this->json_deep_value( $json, 'content' );
			if ( $value ) {
				$html_parts[] = $value;
				continue;
			}

			// Generic fallback: recursively find any HTML string in JSON.
			$found = array();
			$this->collect_html_strings( $json, $found );
			foreach ( $found as $html ) {
				$html_parts[] = $html;
			}
		}

		return implode( "\n", $html_parts );
	}

	/**
	 * Extract a value from block JSON, trying multiple common paths.
	 *
	 * Paths tried (covers Divi 5, Kadence, GenerateBlocks, etc.):
	 * - {key}.innerContent.desktop.value  (Divi 5)
	 * - {key}.text                        (Kadence, GenerateBlocks)
	 * - {key}                             (direct string value)
	 *
	 * @param array  $json Block JSON.
	 * @param string $key  Top-level key ('content', 'title', 'text').
	 * @return string|null
	 */
	private function json_deep_value( $json, $key ) {
		// Divi 5 path.
		$val = $json[ $key ]['innerContent']['desktop']['value'] ?? null;
		if ( is_string( $val ) && ! empty( $val ) ) {
			return $val;
		}

		// Generic nested text path.
		$val = $json[ $key ]['text'] ?? null;
		if ( is_string( $val ) && ! empty( $val ) ) {
			return $val;
		}

		// Direct string value.
		$val = $json[ $key ] ?? null;
		if ( is_string( $val ) && ! empty( $val ) && preg_match( '/<[a-z]/i', $val ) ) {
			return $val;
		}

		return null;
	}

	/**
	 * Extract heading level from block JSON (supports multiple builder formats).
	 *
	 * @param array $json Block JSON.
	 * @return string h1-h6, defaults to h2.
	 */
	private function json_heading_level( $json ) {
		// Divi 5: title.decoration.font.font.desktop.value.headingLevel
		$level = $json['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] ?? null;
		if ( $level && preg_match( '/^h[1-6]$/', $level ) ) {
			return $level;
		}

		// Gutenberg / Kadence: level or htmlTag
		$level = $json['level'] ?? $json['htmlTag'] ?? $json['tag'] ?? null;
		if ( is_int( $level ) && $level >= 1 && $level <= 6 ) {
			return 'h' . $level;
		}
		if ( is_string( $level ) && preg_match( '/^h[1-6]$/', $level ) ) {
			return $level;
		}

		return 'h2';
	}

	/**
	 * Recursively collect HTML strings from any JSON structure.
	 *
	 * @param mixed $data   JSON data.
	 * @param array $found  Collected HTML strings (by reference).
	 */
	private function collect_html_strings( $data, &$found ) {
		if ( is_string( $data ) ) {
			// Skip CSS/style content.
			if ( preg_match( '/^\s*(?:<style|display:|width:|height:|font-)/i', $data ) ) {
				return;
			}
			// Collect strings that contain HTML tags.
			if ( preg_match( '/<(?:p|h[1-6]|ul|ol|li|strong|em|b|table|div|span|a)[\s>]/i', $data ) ) {
				$found[] = $data;
			}
			return;
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $value ) {
				$this->collect_html_strings( $value, $found );
			}
		}
	}

	/* ── REST API ─────────────────────────────────────────────── */

	/**
	 * Register the /analyze endpoint.
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/analyze', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_analyze' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'content' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'wp_kses_post',
				),
				'title'   => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'post_id' => array(
					'required' => false,
					'type'     => 'integer',
					'default'  => 0,
				),
			),
		) );
	}

	/**
	 * REST callback for POST /analyze.
	 *
	 * @param WP_REST_Request $request The incoming request.
	 * @return WP_REST_Response
	 */
	public function rest_analyze( $request ) {
		$content = $request->get_param( 'content' );
		$title   = $request->get_param( 'title' );

		return rest_ensure_response( $this->analyze( $content, $title ) );
	}

	/* ── AJAX: Manual AI Analysis ────────────────────────────── */

	/**
	 * AJAX handler: run AI analysis on recent posts (manual trigger).
	 */
	public function ajax_run_ai_analysis() {
		check_ajax_referer( 'aisp_ai_analysis', '_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		$plugin = AI_SEO_Pilot::get_instance();
		if ( ! $plugin->ai_engine->is_configured() ) {
			wp_send_json_error( 'AI API not configured. Go to Settings > AI API.' );
		}

		$posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
		) );

		if ( empty( $posts ) ) {
			wp_send_json_error( 'No published posts found.' );
		}

		$settings  = $this->get_settings();
		$threshold = (int) $settings['ai_ready_threshold'];
		$results   = array();

		foreach ( $posts as $p ) {
			$rendered  = $this->render_content( $p->post_content );
			$plain     = wp_strip_all_tags( $rendered );
			$ai_scores = $this->call_ai_for_post( $rendered, $plain, $p->post_title );

			if ( null === $ai_scores ) {
				// AI call failed — use regex fallback for this post.
				$analysis = $this->analyze( $rendered, $p->post_title );
				$results[ $p->ID ] = array(
					'percentage' => $analysis['percentage'],
					'ai_ready'   => $analysis['ai_ready'],
					'checks'     => $analysis['checks'],
					'source'     => 'regex',
					'timestamp'  => time(),
				);
				continue;
			}

			// Build full analysis merging AI scores + regex for non-AI checks.
			$analysis = $this->build_merged_analysis( $rendered, $plain, $p->post_title, $ai_scores );

			$results[ $p->ID ] = array(
				'percentage' => $analysis['percentage'],
				'ai_ready'   => $analysis['ai_ready'],
				'checks'     => $analysis['checks'],
				'source'     => 'ai',
				'timestamp'  => time(),
			);
		}

		// Store results persistently.
		update_option( self::AI_SCORES_OPTION, $results, false );

		// Calculate summary.
		$total_pct   = 0;
		$ready_count = 0;
		foreach ( $results as $r ) {
			$total_pct += $r['percentage'];
			if ( $r['ai_ready'] ) {
				$ready_count++;
			}
		}
		$avg_score = count( $results ) > 0 ? round( $total_pct / count( $results ) ) : 0;

		wp_send_json_success( array(
			'avg_score'      => $avg_score,
			'ready_count'    => $ready_count,
			'analyzed_count' => count( $results ),
			'threshold'      => $threshold,
		) );
	}

	/**
	 * Call AI engine to evaluate language-dependent checks for a single post.
	 *
	 * @param string $content HTML content.
	 * @param string $plain   Plain text content.
	 * @param string $title   Post title.
	 * @return array|null AI scores or null on failure.
	 */
	private function call_ai_for_post( $content, $plain, $title ) {
		$plugin = AI_SEO_Pilot::get_instance();

		$truncated = mb_substr( $plain, 0, 3000 );
		$settings  = $this->get_settings();

		$prompt = <<<PROMPT
You are an AI SEO content analyst. Evaluate the following content for AI-readiness (how well AI models like ChatGPT, Perplexity, Gemini can extract and cite this content).

Title: {$title}

Content (truncated):
{$truncated}

Score EACH criterion from 0 to 10 and provide a brief suggestion. Return ONLY valid JSON, no markdown fences.

Criteria:
1. "direct_answer": Does the FIRST paragraph provide a concise, declarative answer (under {$settings['checks']['direct_answer']['max_chars']} chars)? Not a question, not vague — a clear statement.
2. "definitions": Does the content include explicit definitions of key terms (e.g., "X is a...", "X refers to...", "X means...")? Target: {$settings['checks']['definitions']['min_definitions']}+ definitions. Works in ANY language.
3. "entity_density": Does it reference specific named entities (companies, products, people, technical terms, acronyms)?
4. "citable_statistics": Does it contain specific numbers, percentages, dollar amounts, years, or data points that AI can cite? Target: {$settings['checks']['citable_statistics']['min_stats']}+.
5. "semantic_completeness": Is the content comprehensive? Check: word count {$settings['checks']['semantic_completeness']['min_words']}-{$settings['checks']['semantic_completeness']['max_words']} words ideal, has clear introduction, has concluding paragraph with summary language.
6. "freshness_signals": Does it contain date references, years, or words indicating recency (updated, latest, current, as of...) in ANY language?

Return format:
{"direct_answer":{"score":N,"suggestion":"..."},"definitions":{"score":N,"suggestion":"..."},"entity_density":{"score":N,"suggestion":"..."},"citable_statistics":{"score":N,"suggestion":"..."},"semantic_completeness":{"score":N,"suggestion":"..."},"freshness_signals":{"score":N,"suggestion":"..."}}
PROMPT;

		$response = $plugin->ai_engine->prompt( $prompt, 600 );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$json_str = $response;
		if ( preg_match( '/\{[\s\S]*\}/', $response, $m ) ) {
			$json_str = $m[0];
		}

		$parsed = json_decode( $json_str, true );
		if ( ! is_array( $parsed ) ) {
			return null;
		}

		$scores = array();
		foreach ( self::$ai_checks as $check_name ) {
			if ( isset( $parsed[ $check_name ] ) && is_array( $parsed[ $check_name ] ) ) {
				$scores[ $check_name ] = array(
					'score'      => max( 0, min( 10, (int) $parsed[ $check_name ]['score'] ) ),
					'suggestion' => sanitize_text_field( $parsed[ $check_name ]['suggestion'] ?? '' ),
				);
			}
		}

		return ! empty( $scores ) ? $scores : null;
	}

	/**
	 * Build a full analysis result merging AI scores with regex checks.
	 *
	 * @param string $content   HTML content.
	 * @param string $plain     Plain text content.
	 * @param string $title     Post title.
	 * @param array  $ai_scores AI scores for language-dependent checks.
	 * @return array Full analysis result.
	 */
	private function build_merged_analysis( $content, $plain, $title, $ai_scores ) {
		$settings = $this->get_settings();

		$labels = array(
			'direct_answer'        => 'Direct Answer',
			'qa_structure'         => 'Q&A Structure',
			'definitions'          => 'Definitions',
			'paragraph_length'     => 'Paragraph Length',
			'list_optimization'    => 'List Optimization',
			'entity_density'       => 'Entity Density',
			'citable_statistics'   => 'Citable Statistics',
			'semantic_completeness' => 'Semantic Completeness',
			'snippet_optimization' => 'Snippet Optimization',
			'freshness_signals'    => 'Freshness Signals',
		);

		$all_checks = array(
			'direct_answer'        => array( $this, 'check_direct_answer' ),
			'qa_structure'         => array( $this, 'check_qa_structure' ),
			'definitions'          => array( $this, 'check_definitions' ),
			'paragraph_length'     => array( $this, 'check_paragraph_length' ),
			'list_optimization'    => array( $this, 'check_list_optimization' ),
			'entity_density'       => array( $this, 'check_entity_density' ),
			'citable_statistics'   => array( $this, 'check_citable_statistics' ),
			'semantic_completeness' => array( $this, 'check_semantic_completeness' ),
			'snippet_optimization' => array( $this, 'check_snippet_optimization' ),
			'freshness_signals'    => array( $this, 'check_freshness_signals' ),
		);

		$checks    = array();
		$score     = 0;
		$max_score = 0;

		foreach ( $all_checks as $name => $callback ) {
			$check_cfg = $settings['checks'][ $name ];
			if ( empty( $check_cfg['enabled'] ) ) {
				continue;
			}

			$weight = (int) $check_cfg['weight'];
			if ( 0 === $weight ) {
				continue;
			}

			// Use AI score for language-dependent checks, regex for the rest.
			if ( isset( $ai_scores[ $name ] ) && in_array( $name, self::$ai_checks, true ) ) {
				$check = array(
					'name'       => $name,
					'label'      => $labels[ $name ],
					'score'      => $ai_scores[ $name ]['score'],
					'max'        => 10,
					'suggestion' => $ai_scores[ $name ]['suggestion'],
				);
			} else {
				$check = $this->run_check( $callback, $name, $content, $plain );
			}

			$scaled_score   = round( ( $check['score'] / 10 ) * $weight );
			$check['score'] = $scaled_score;
			$check['max']   = $weight;

			if ( $check['score'] >= $weight * 0.7 ) {
				$check['status'] = 'good';
			} elseif ( $check['score'] >= $weight * 0.4 ) {
				$check['status'] = 'warning';
			} else {
				$check['status'] = 'poor';
			}

			$score     += $check['score'];
			$max_score += $weight;
			$checks[]   = $check;
		}

		$percentage = $max_score > 0 ? round( ( $score / $max_score ) * 100 ) : 0;
		$threshold  = (int) $settings['ai_ready_threshold'];

		return array(
			'score'      => $score,
			'max_score'  => $max_score,
			'percentage' => $percentage,
			'ai_ready'   => ( $percentage >= $threshold ),
			'checks'     => $checks,
		);
	}

	/* ── Stored AI scores (for widget) ───────────────────────── */

	/**
	 * Get stored AI analysis results.
	 *
	 * @return array|false Stored results or false if none.
	 */
	public function get_stored_ai_scores() {
		return get_option( self::AI_SCORES_OPTION, false );
	}

	/* ── Public analysis method (regex only, always fast) ────── */

	/**
	 * Analyze content using regex checks only (no AI calls).
	 *
	 * @param string $content HTML content.
	 * @param string $title   Post title.
	 * @return array{score: int, max_score: int, percentage: int, ai_ready: bool, checks: array}
	 */
	public function analyze( $content, $title ) {
		$content  = $this->render_content( $content );
		$plain    = wp_strip_all_tags( $content );
		$settings = $this->get_settings();

		$all_checks = array(
			'direct_answer'        => array( $this, 'check_direct_answer' ),
			'qa_structure'         => array( $this, 'check_qa_structure' ),
			'definitions'          => array( $this, 'check_definitions' ),
			'paragraph_length'     => array( $this, 'check_paragraph_length' ),
			'list_optimization'    => array( $this, 'check_list_optimization' ),
			'entity_density'       => array( $this, 'check_entity_density' ),
			'citable_statistics'   => array( $this, 'check_citable_statistics' ),
			'semantic_completeness' => array( $this, 'check_semantic_completeness' ),
			'snippet_optimization' => array( $this, 'check_snippet_optimization' ),
			'freshness_signals'    => array( $this, 'check_freshness_signals' ),
		);

		$checks    = array();
		$score     = 0;
		$max_score = 0;

		foreach ( $all_checks as $name => $callback ) {
			$check_cfg = $settings['checks'][ $name ];

			if ( empty( $check_cfg['enabled'] ) ) {
				continue;
			}

			$weight = (int) $check_cfg['weight'];
			if ( 0 === $weight ) {
				continue;
			}

			$check = $this->run_check( $callback, $name, $content, $plain );

			$scaled_score   = round( ( $check['score'] / 10 ) * $weight );
			$check['score'] = $scaled_score;
			$check['max']   = $weight;

			if ( $check['score'] >= $weight * 0.7 ) {
				$check['status'] = 'good';
			} elseif ( $check['score'] >= $weight * 0.4 ) {
				$check['status'] = 'warning';
			} else {
				$check['status'] = 'poor';
			}

			$score     += $check['score'];
			$max_score += $weight;
			$checks[]   = $check;
		}

		$percentage = $max_score > 0 ? round( ( $score / $max_score ) * 100 ) : 0;
		$threshold  = (int) $settings['ai_ready_threshold'];

		return array(
			'score'      => $score,
			'max_score'  => $max_score,
			'percentage' => $percentage,
			'ai_ready'   => ( $percentage >= $threshold ),
			'checks'     => $checks,
		);
	}

	/**
	 * Run a single check with the right arguments.
	 *
	 * @param callable $callback Check method.
	 * @param string   $name     Check key.
	 * @param string   $content  HTML content.
	 * @param string   $plain    Plain text content.
	 * @return array
	 */
	private function run_check( $callback, $name, $content, $plain ) {
		switch ( $name ) {
			case 'direct_answer':
				return call_user_func( $callback, $content, $plain );
			case 'qa_structure':
			case 'paragraph_length':
			case 'list_optimization':
			case 'snippet_optimization':
				return call_user_func( $callback, $content );
			case 'definitions':
			case 'entity_density':
			case 'citable_statistics':
			case 'freshness_signals':
				return call_user_func( $callback, $plain );
			case 'semantic_completeness':
				return call_user_func( $callback, $content, $plain );
			default:
				return array( 'name' => $name, 'label' => $name, 'score' => 0, 'max' => 10, 'suggestion' => '' );
		}
	}

	/* ── Gutenberg integration ────────────────────────────────── */

	/**
	 * Enqueue the analyzer script in the block editor.
	 */
	public function enqueue_editor_assets() {
		$asset_file = AI_SEO_PILOT_PATH . 'assets/js/analyzer.asset.php';
		$deps       = array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch' );
		$version    = AI_SEO_PILOT_VERSION;

		if ( file_exists( $asset_file ) ) {
			$asset   = require $asset_file;
			$deps    = $asset['dependencies'];
			$version = $asset['version'];
		}

		wp_enqueue_script(
			'ai-seo-pilot-analyzer',
			AI_SEO_PILOT_URL . 'assets/js/analyzer.js',
			$deps,
			$version,
			true
		);

		wp_localize_script( 'ai-seo-pilot-analyzer', 'aiSeoPilotAnalyzer', array(
			'restUrl'   => rest_url( $this->namespace . '/analyze' ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'pluginUrl' => AI_SEO_PILOT_URL,
		) );
	}

	/* ── Individual checks (regex, always fast) ───────────────── */

	/**
	 * Check 1: Direct Answer — first paragraph answers a question directly.
	 */
	private function check_direct_answer( $content, $plain ) {
		$cfg    = $this->check_settings( 'direct_answer' );
		$target = (int) $cfg['max_chars'];

		$result = array(
			'name'       => 'direct_answer',
			'label'      => 'Direct Answer',
			'score'      => 0,
			'max'        => 10,
			'suggestion' => '',
		);

		$first_para = '';
		if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $content, $m ) ) {
			$first_para = wp_strip_all_tags( $m[1] );
		} else {
			$lines = preg_split( '/\n+/', trim( $plain ), 2 );
			if ( ! empty( $lines[0] ) ) {
				$first_para = trim( $lines[0] );
			}
		}

		if ( empty( $first_para ) ) {
			$result['suggestion'] = 'Add an opening paragraph that directly answers a question.';
			return $result;
		}

		$len        = mb_strlen( $first_para );
		$hard_limit = (int) round( $target * 1.5 );

		$is_declarative = (
			$len <= $hard_limit
			&& ! str_ends_with( trim( $first_para ), '?' )
			&& $len >= 20
		);

		if ( $is_declarative && $len <= $target ) {
			$result['score']      = 10;
			$result['suggestion'] = 'Your opening paragraph provides a clear direct answer.';
		} elseif ( $is_declarative ) {
			$result['score']      = 8;
			$result['suggestion'] = sprintf( 'Good direct answer. Consider making it under %d characters for best results.', $target );
		} else {
			$result['score']      = 3;
			$result['suggestion'] = sprintf( 'Start with a concise declarative statement (under %d characters) that directly answers the main question.', $hard_limit );
		}

		return $result;
	}

	/**
	 * Check 2: Q&A Structure — contains question headings (h2/h3 ending with ?).
	 */
	private function check_qa_structure( $content ) {
		$cfg    = $this->check_settings( 'qa_structure' );
		$target = (int) $cfg['min_questions'];

		$result = array(
			'name'       => 'qa_structure',
			'label'      => 'Q&A Structure',
			'score'      => 0,
			'max'        => 10,
			'suggestion' => '',
		);

		preg_match_all( '/<h[23][^>]*>.*?\?<\/h[23]>/is', $content, $matches );
		$count = count( $matches[0] );

		if ( $count >= $target ) {
			$result['score']      = 10;
			$result['suggestion'] = 'Excellent Q&A structure with ' . $count . ' question headings.';
		} elseif ( $count >= max( 1, $target - 1 ) ) {
			$result['score']      = 6;
			$result['suggestion'] = sprintf( 'Good start. Add %d more question heading(s) (H2/H3 ending with ?) to reach %d.', $target - $count, $target );
		} elseif ( $count >= 1 ) {
			$result['score']      = 3;
			$result['suggestion'] = sprintf( 'Only %d question heading found. Use H2/H3 headings phrased as questions (target: %d).', $count, $target );
		} else {
			$result['suggestion'] = 'No question headings found. Add H2 or H3 headings that end with "?" to create Q&A structure.';
		}

		return $result;
	}

	/**
	 * Check 3: Definitions — contains definition patterns (English regex fallback).
	 */
	private function check_definitions( $plain ) {
		$cfg    = $this->check_settings( 'definitions' );
		$target = (int) $cfg['min_definitions'];

		$result = array(
			'name'       => 'definitions',
			'label'      => 'Definitions',
			'score'      => 0,
			'max'        => 10,
			'suggestion' => '',
		);

		$patterns = array(
			'/\b\w+\s+is\s+(?:a|an|the)\b/i',
			'/\brefers?\s+to\b/i',
			'/\bdefined\s+as\b/i',
			'/\bmeans?\s+(?:that|the|a|an)\b/i',
			'/\bknown\s+as\b/i',
		);

		$count = 0;
		foreach ( $patterns as $pattern ) {
			$count += preg_match_all( $pattern, $plain );
		}

		if ( $count >= $target ) {
			$result['score']      = 10;
			$result['suggestion'] = 'Content includes ' . $count . ' definition patterns. AI models can extract clear definitions.';
		} elseif ( $count >= 1 ) {
			$result['score']      = 5;
			$result['suggestion'] = sprintf( 'Only %d definition found (target: %d). Add more "X is a...", "refers to", or "defined as" patterns.', $count, $target );
		} else {
			$result['suggestion'] = 'No definition patterns detected. Include explicit definitions using "X is...", "refers to...", or "defined as".';
		}

		return $result;
	}

	/**
	 * Check 4: Paragraph Length — average paragraph length under configured words.
	 */
	private function check_paragraph_length( $content ) {
		$cfg        = $this->check_settings( 'paragraph_length' );
		$max_avg    = (int) $cfg['max_avg_words'];
		$warn_limit = (int) round( $max_avg * 1.5 );

		$result = array(
			'name'       => 'paragraph_length',
			'label'      => 'Paragraph Length',
			'score'      => 0,
			'max'        => 10,
			'suggestion' => '',
		);

		preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $content, $matches );

		if ( empty( $matches[1] ) ) {
			$result['score']      = 5;
			$result['suggestion'] = 'No HTML paragraphs detected. Wrap content in <p> tags for better structure.';
			return $result;
		}

		$total_words = 0;
		$para_count  = count( $matches[1] );

		foreach ( $matches[1] as $para ) {
			$text         = wp_strip_all_tags( $para );
			$total_words += str_word_count( $text );
		}

		$avg = $para_count > 0 ? $total_words / $para_count : 0;

		if ( $avg <= $max_avg ) {
			$result['score']      = 10;
			$result['suggestion'] = sprintf( 'Excellent paragraph length (avg %d words). Short paragraphs are ideal for AI extraction.', round( $avg ) );
		} elseif ( $avg <= $warn_limit ) {
			$result['score']      = 7;
			$result['suggestion'] = sprintf( 'Good paragraph length (avg %d words). Consider breaking longer paragraphs for better readability.', round( $avg ) );
		} elseif ( $avg <= $warn_limit * 2 ) {
			$result['score']      = 4;
			$result['suggestion'] = sprintf( 'Paragraphs are too long (avg %d words). Aim for under %d words per paragraph.', round( $avg ), $warn_limit );
		} else {
			$result['suggestion'] = sprintf( 'Paragraphs are very long (avg %d words). Break them into shorter, focused paragraphs under %d words.', round( $avg ), $max_avg );
		}

		return $result;
	}

	/**
	 * Check 5: List Optimization — contains ul, ol, or table elements.
	 */
	private function check_list_optimization( $content ) {
		$cfg    = $this->check_settings( 'list_optimization' );
		$target = (int) $cfg['min_lists'];

		$result = array(
			'name'       => 'list_optimization',
			'label'      => 'List Optimization',
			'score'      => 0,
			'max'        => 10,
			'suggestion' => '',
		);

		$count  = 0;
		$count += preg_match_all( '/<ul[\s>]/i', $content );
		$count += preg_match_all( '/<ol[\s>]/i', $content );
		$count += preg_match_all( '/<table[\s>]/i', $content );

		if ( $count >= $target ) {
			$result['score']      = 10;
			$result['suggestion'] = 'Content uses ' . $count . ' structured lists/tables. AI models favor structured data.';
		} elseif ( $count >= 1 ) {
			$result['score']      = 5;
			$result['suggestion'] = sprintf( '%d list or table found (target: %d). Add another to improve structured data extraction.', $count, $target );
		} else {
			$result['suggestion'] = 'No lists or tables found. Add <ul>, <ol>, or <table> elements to help AI extract structured information.';
		}

		return $result;
	}

	/**
	 * Check 6: Entity Density — proper nouns / capitalized multi-word phrases.
	 */
	private function check_entity_density( $plain ) {
		$cfg         = $this->check_settings( 'entity_density' );
		$min_density = (float) $cfg['min_density'];

		$result = array(
			'name'       => 'entity_density',
			'label'      => 'Entity Density',
			'score'      => 0,
			'max'        => 10,
			'suggestion' => '',
		);

		$word_count = str_word_count( $plain );
		if ( 0 === $word_count ) {
			$result['suggestion'] = 'No content to analyze for entity density.';
			return $result;
		}

		preg_match_all( '/[A-Z][a-z]+(?:\s+[A-Z][a-z]+)+/', $plain, $matches );
		$entity_count = count( $matches[0] );

		preg_match_all( '/\b[A-Z]{2,}\b/', $plain, $acronyms );
		$entity_count += count( $acronyms[0] );

		$per_100     = ( $entity_count / $word_count ) * 100;
		$half_target = $min_density / 2;

		if ( $per_100 >= $min_density ) {
			$result['score']      = 10;
			$result['suggestion'] = 'Good entity density (' . $entity_count . ' entities detected). Content references specific entities AI can identify.';
		} elseif ( $per_100 >= $half_target ) {
			$result['score']      = 5;
			$result['suggestion'] = 'Moderate entity density. Add more specific names, brands, or technical terms.';
		} else {
			$result['suggestion'] = 'Low entity density. Reference specific people, organizations, products, or technical terms to help AI cite your content.';
		}

		return $result;
	}

	/**
	 * Check 7: Citable Statistics — numbers with context.
	 */
	private function check_citable_statistics( $plain ) {
		$cfg    = $this->check_settings( 'citable_statistics' );
		$target = (int) $cfg['min_stats'];

		$result = array(
			'name'       => 'citable_statistics',
			'label'      => 'Citable Statistics',
			'score'      => 0,
			'max'        => 10,
			'suggestion' => '',
		);

		$patterns = array(
			'/\d+(?:\.\d+)?%/',
			'/\$\d[\d,.]*/',
			'/\b\d{4}\b/',
			'/\d+(?:\.\d+)?\s*(?:million|billion|trillion|thousand)/i',
			'/\b\d[\d,.]+\s+(?:users?|customers?|people|companies|employees)/i',
		);

		$count = 0;
		foreach ( $patterns as $pattern ) {
			$count += preg_match_all( $pattern, $plain );
		}

		if ( $count >= $target ) {
			$result['score']      = 10;
			$result['suggestion'] = 'Excellent! ' . $count . ' citable statistics found. AI models prioritize content with specific data points.';
		} elseif ( $count >= max( 1, $target - 1 ) ) {
			$result['score']      = 7;
			$result['suggestion'] = sprintf( 'Good — %d statistics found (target: %d). Add one more data point.', $count, $target );
		} elseif ( $count >= 1 ) {
			$result['score']      = 4;
			$result['suggestion'] = sprintf( 'Only %d statistic found. Include more percentages, dollar amounts, or specific numbers with context.', $count );
		} else {
			$result['suggestion'] = 'No citable statistics found. Add specific numbers, percentages, or data points that AI can reference.';
		}

		return $result;
	}

	/**
	 * Check 8: Semantic Completeness — word count + intro + conclusion.
	 */
	private function check_semantic_completeness( $content, $plain ) {
		$cfg       = $this->check_settings( 'semantic_completeness' );
		$min_words = (int) $cfg['min_words'];
		$max_words = isset( $cfg['max_words'] ) ? (int) $cfg['max_words'] : 2000;

		$result = array(
			'name'       => 'semantic_completeness',
			'label'      => 'Semantic Completeness',
			'score'      => 0,
			'max'        => 10,
			'suggestion' => '',
		);

		$word_count = str_word_count( $plain );
		$issues     = array();
		$sub_score  = 0;

		if ( $word_count >= $min_words && $word_count <= $max_words ) {
			$sub_score += 4;
		} elseif ( $word_count > $max_words ) {
			$sub_score += 2;
			$issues[]   = sprintf( 'Content is quite long (%d words) — consider trimming to under %d words', $word_count, $max_words );
		} else {
			$issues[] = sprintf( 'Content is short (%d words) — aim for at least %d words', $word_count, $min_words );
		}

		preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $content, $paragraphs );
		$has_intro = ! empty( $paragraphs[1][0] ) && str_word_count( wp_strip_all_tags( $paragraphs[1][0] ) ) >= 10;
		if ( $has_intro ) {
			$sub_score += 3;
		} else {
			$issues[] = 'Add a clear introductory paragraph (at least 10 words)';
		}

		$conclusion_words = array( 'conclusion', 'summary', 'in summary', 'to summarize', 'overall', 'in short', 'final', 'finally', 'takeaway', 'key takeaway', 'bottom line' );
		$has_conclusion   = false;
		if ( ! empty( $paragraphs[1] ) ) {
			$last_para     = wp_strip_all_tags( end( $paragraphs[1] ) );
			$last_para_low = strtolower( $last_para );
			foreach ( $conclusion_words as $word ) {
				if ( false !== strpos( $last_para_low, $word ) ) {
					$has_conclusion = true;
					break;
				}
			}
		}

		if ( $has_conclusion ) {
			$sub_score += 3;
		} else {
			$issues[] = 'Add a concluding paragraph with summary language (e.g., "In summary", "Overall", "Key takeaway")';
		}

		$result['score'] = $sub_score;
		if ( empty( $issues ) ) {
			$result['suggestion'] = 'Content has optimal length, a clear introduction, and a conclusion.';
		} else {
			$result['suggestion'] = implode( '. ', $issues ) . '.';
		}

		return $result;
	}

	/**
	 * Check 9: Snippet Optimization — concise summary paragraph + bold/strong tags.
	 */
	private function check_snippet_optimization( $content ) {
		$cfg               = $this->check_settings( 'snippet_optimization' );
		$max_summary_words = (int) $cfg['max_summary_words'];

		$result = array(
			'name'       => 'snippet_optimization',
			'label'      => 'Snippet Optimization',
			'score'      => 0,
			'max'        => 10,
			'suggestion' => '',
		);

		$issues    = array();
		$sub_score = 0;

		preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $content, $paragraphs );
		$has_concise = false;
		$check_count = min( 3, count( $paragraphs[1] ) );

		for ( $i = 0; $i < $check_count; $i++ ) {
			$text  = wp_strip_all_tags( $paragraphs[1][ $i ] );
			$words = str_word_count( $text );
			if ( $words > 0 && $words <= $max_summary_words ) {
				$has_concise = true;
				break;
			}
		}

		if ( $has_concise ) {
			$sub_score += 5;
		} else {
			$issues[] = sprintf( 'Add a concise summary paragraph (under %d words) within the first 3 paragraphs', $max_summary_words );
		}

		$has_bold = preg_match( '/<(?:strong|b)[\s>]/i', $content );
		if ( $has_bold ) {
			$sub_score += 5;
		} else {
			$issues[] = 'Use <strong> or <b> tags to highlight key phrases for snippet extraction';
		}

		$result['score'] = $sub_score;
		if ( empty( $issues ) ) {
			$result['suggestion'] = 'Content has a concise summary paragraph and uses bold formatting for key phrases.';
		} else {
			$result['suggestion'] = implode( '. ', $issues ) . '.';
		}

		return $result;
	}

	/**
	 * Check 10: Freshness Signals — date references and freshness keywords.
	 */
	private function check_freshness_signals( $plain ) {
		$cfg    = $this->check_settings( 'freshness_signals' );
		$target = (int) $cfg['min_signals'];

		$result = array(
			'name'       => 'freshness_signals',
			'label'      => 'Freshness Signals',
			'score'      => 0,
			'max'        => 10,
			'suggestion' => '',
		);

		$patterns = array(
			'/\b20[2-3]\d\b/',
			'/\bupdated\b/i',
			'/\blatest\b/i',
			'/\bcurrent(?:ly)?\b/i',
			'/\brecent(?:ly)?\b/i',
			'/\bas of\b/i',
			'/\bthis year\b/i',
		);

		$count = 0;
		foreach ( $patterns as $pattern ) {
			$count += preg_match_all( $pattern, $plain );
		}

		if ( $count >= $target ) {
			$result['score']      = 10;
			$result['suggestion'] = 'Strong freshness signals (' . $count . ' references). AI models favor up-to-date content.';
		} elseif ( $count >= 1 ) {
			$result['score']      = 5;
			$result['suggestion'] = sprintf( '%d freshness signal found (target: %d). Add more date references or words like "updated", "latest", "current".', $count, $target );
		} else {
			$result['suggestion'] = 'No freshness signals detected. Include year references (e.g., 2026), "updated", "latest", or "as of" to signal recency.';
		}

		return $result;
	}
}
