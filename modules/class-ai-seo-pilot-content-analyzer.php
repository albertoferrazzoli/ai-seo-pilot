<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content Analyzer module.
 *
 * Provides real-time content analysis scoring via a REST API endpoint
 * and a public `analyze()` method for server-side use.
 */
class AI_SEO_Pilot_Content_Analyzer {

	/** @var string REST namespace. */
	private $namespace = 'ai-seo-pilot/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/* ── REST API ─────────────────────────────────────────────────── */

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

	/* ── Public analysis method ───────────────────────────────────── */

	/**
	 * Analyze content and return a score with individual checks.
	 *
	 * @param string $content HTML content.
	 * @param string $title   Post title.
	 * @return array{score: int, ai_ready: bool, checks: array}
	 */
	public function analyze( $content, $title ) {
		$plain = wp_strip_all_tags( $content );

		$checks = array(
			$this->check_direct_answer( $content, $plain ),
			$this->check_qa_structure( $content ),
			$this->check_definitions( $plain ),
			$this->check_paragraph_length( $content ),
			$this->check_list_optimization( $content ),
			$this->check_entity_density( $plain ),
			$this->check_citable_statistics( $plain ),
			$this->check_semantic_completeness( $content, $plain ),
			$this->check_snippet_optimization( $content ),
			$this->check_freshness_signals( $plain ),
		);

		$score = 0;
		foreach ( $checks as &$check ) {
			$score += $check['score'];
			if ( $check['score'] >= 7 ) {
				$check['status'] = 'good';
			} elseif ( $check['score'] >= 4 ) {
				$check['status'] = 'warning';
			} else {
				$check['status'] = 'poor';
			}
		}
		unset( $check );

		return array(
			'score'    => $score,
			'ai_ready' => ( $score >= 75 ),
			'checks'   => $checks,
		);
	}

	/* ── Gutenberg integration ────────────────────────────────────── */

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

	/* ── Individual checks (each returns array with name, label, score, max, suggestion) */

	/**
	 * Check 1: Direct Answer — first paragraph answers a question directly.
	 *
	 * @param string $content HTML content.
	 * @param string $plain   Plain text content.
	 * @return array
	 */
	private function check_direct_answer( $content, $plain ) {
		$result = array(
			'name'       => 'direct_answer',
			'label'      => 'Direct Answer',
			'score'      => 0,
			'max'        => 10,
			'suggestion' => '',
		);

		// Extract first paragraph.
		$first_para = '';
		if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $content, $m ) ) {
			$first_para = wp_strip_all_tags( $m[1] );
		} else {
			// No <p> tags — use first line of plain text.
			$lines = preg_split( '/\n+/', trim( $plain ), 2 );
			if ( ! empty( $lines[0] ) ) {
				$first_para = trim( $lines[0] );
			}
		}

		if ( empty( $first_para ) ) {
			$result['suggestion'] = 'Add an opening paragraph that directly answers a question.';
			return $result;
		}

		$len = mb_strlen( $first_para );

		// Declarative statement under 300 characters.
		$is_declarative = (
			$len <= 300
			&& ! str_ends_with( trim( $first_para ), '?' )
			&& $len >= 20
		);

		if ( $is_declarative && $len <= 200 ) {
			$result['score']      = 10;
			$result['suggestion'] = 'Your opening paragraph provides a clear direct answer.';
		} elseif ( $is_declarative ) {
			$result['score']      = 8;
			$result['suggestion'] = 'Good direct answer. Consider making it slightly more concise.';
		} else {
			$result['score']      = 3;
			$result['suggestion'] = 'Start with a concise declarative statement (under 300 characters) that directly answers the main question.';
		}

		return $result;
	}

	/**
	 * Check 2: Q&A Structure — contains question headings (h2/h3 ending with ?).
	 *
	 * @param string $content HTML content.
	 * @return array
	 */
	private function check_qa_structure( $content ) {
		$result = array(
			'name'       => 'qa_structure',
			'label'      => 'Q&A Structure',
			'score'      => 0,
			'max'        => 10,
			'suggestion' => '',
		);

		preg_match_all( '/<h[23][^>]*>.*?\?<\/h[23]>/is', $content, $matches );
		$count = count( $matches[0] );

		if ( $count >= 3 ) {
			$result['score']      = 10;
			$result['suggestion'] = 'Excellent Q&A structure with ' . $count . ' question headings.';
		} elseif ( 2 === $count ) {
			$result['score']      = 6;
			$result['suggestion'] = 'Good start. Add one more question heading (H2/H3 ending with ?) to maximize score.';
		} elseif ( 1 === $count ) {
			$result['score']      = 3;
			$result['suggestion'] = 'Only 1 question heading found. Use H2/H3 headings phrased as questions to improve AI snippet extraction.';
		} else {
			$result['suggestion'] = 'No question headings found. Add H2 or H3 headings that end with "?" to create Q&A structure.';
		}

		return $result;
	}

	/**
	 * Check 3: Definitions — contains definition patterns.
	 *
	 * @param string $plain Plain text content.
	 * @return array
	 */
	private function check_definitions( $plain ) {
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

		if ( $count >= 2 ) {
			$result['score']      = 10;
			$result['suggestion'] = 'Content includes ' . $count . ' definition patterns. AI models can extract clear definitions.';
		} elseif ( 1 === $count ) {
			$result['score']      = 5;
			$result['suggestion'] = 'Only 1 definition found. Add more "X is a...", "refers to", or "defined as" patterns.';
		} else {
			$result['suggestion'] = 'No definition patterns detected. Include explicit definitions using "X is...", "refers to...", or "defined as".';
		}

		return $result;
	}

	/**
	 * Check 4: Paragraph Length — average paragraph length under 150 words.
	 *
	 * @param string $content HTML content.
	 * @return array
	 */
	private function check_paragraph_length( $content ) {
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

		if ( $avg <= 100 ) {
			$result['score']      = 10;
			$result['suggestion'] = 'Excellent paragraph length (avg ' . round( $avg ) . ' words). Short paragraphs are ideal for AI extraction.';
		} elseif ( $avg <= 150 ) {
			$result['score']      = 7;
			$result['suggestion'] = 'Good paragraph length (avg ' . round( $avg ) . ' words). Consider breaking longer paragraphs for better readability.';
		} elseif ( $avg <= 300 ) {
			$result['score']      = 4;
			$result['suggestion'] = 'Paragraphs are too long (avg ' . round( $avg ) . ' words). Aim for under 150 words per paragraph.';
		} else {
			$result['suggestion'] = 'Paragraphs are very long (avg ' . round( $avg ) . ' words). Break them into shorter, focused paragraphs under 100 words.';
		}

		return $result;
	}

	/**
	 * Check 5: List Optimization — contains ul, ol, or table elements.
	 *
	 * @param string $content HTML content.
	 * @return array
	 */
	private function check_list_optimization( $content ) {
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

		if ( $count >= 2 ) {
			$result['score']      = 10;
			$result['suggestion'] = 'Content uses ' . $count . ' structured lists/tables. AI models favor structured data.';
		} elseif ( 1 === $count ) {
			$result['score']      = 5;
			$result['suggestion'] = 'One list or table found. Add another to improve structured data extraction.';
		} else {
			$result['suggestion'] = 'No lists or tables found. Add <ul>, <ol>, or <table> elements to help AI extract structured information.';
		}

		return $result;
	}

	/**
	 * Check 6: Entity Density — proper nouns / capitalized multi-word phrases.
	 *
	 * @param string $plain Plain text content.
	 * @return array
	 */
	private function check_entity_density( $plain ) {
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

		// Match capitalized multi-word phrases (proper nouns / entities).
		// Exclude words at the start of sentences by requiring non-period/newline before.
		preg_match_all( '/(?<=[.!?]\s|^)\s*/m', $plain ); // just to count sentences
		preg_match_all( '/[A-Z][a-z]+(?:\s+[A-Z][a-z]+)+/', $plain, $matches );
		$entity_count = count( $matches[0] );

		// Also count standalone known entity patterns (e.g., acronyms).
		preg_match_all( '/\b[A-Z]{2,}\b/', $plain, $acronyms );
		$entity_count += count( $acronyms[0] );

		$per_100 = ( $entity_count / $word_count ) * 100;

		if ( $per_100 >= 2 ) {
			$result['score']      = 10;
			$result['suggestion'] = 'Good entity density (' . $entity_count . ' entities detected). Content references specific entities AI can identify.';
		} elseif ( $per_100 >= 1 ) {
			$result['score']      = 5;
			$result['suggestion'] = 'Moderate entity density. Add more specific names, brands, or technical terms.';
		} else {
			$result['suggestion'] = 'Low entity density. Reference specific people, organizations, products, or technical terms to help AI cite your content.';
		}

		return $result;
	}

	/**
	 * Check 7: Citable Statistics — numbers with context.
	 *
	 * @param string $plain Plain text content.
	 * @return array
	 */
	private function check_citable_statistics( $plain ) {
		$result = array(
			'name'       => 'citable_statistics',
			'label'      => 'Citable Statistics',
			'score'      => 0,
			'max'        => 10,
			'suggestion' => '',
		);

		$patterns = array(
			'/\d+(?:\.\d+)?%/',                          // percentages
			'/\$\d[\d,.]*/',                             // dollar amounts
			'/\b\d{4}\b/',                               // years
			'/\d+(?:\.\d+)?\s*(?:million|billion|trillion|thousand)/i', // large numbers
			'/\b\d[\d,.]+\s+(?:users?|customers?|people|companies|employees)/i', // counts with context
		);

		$count = 0;
		foreach ( $patterns as $pattern ) {
			$count += preg_match_all( $pattern, $plain );
		}

		if ( $count >= 3 ) {
			$result['score']      = 10;
			$result['suggestion'] = 'Excellent! ' . $count . ' citable statistics found. AI models prioritize content with specific data points.';
		} elseif ( 2 === $count ) {
			$result['score']      = 7;
			$result['suggestion'] = 'Good — 2 statistics found. Add one more data point (percentage, dollar amount, or specific figure).';
		} elseif ( 1 === $count ) {
			$result['score']      = 4;
			$result['suggestion'] = 'Only 1 statistic found. Include more percentages, dollar amounts, or specific numbers with context.';
		} else {
			$result['suggestion'] = 'No citable statistics found. Add specific numbers, percentages, or data points that AI can reference.';
		}

		return $result;
	}

	/**
	 * Check 8: Semantic Completeness — word count + intro + conclusion.
	 *
	 * @param string $content HTML content.
	 * @param string $plain   Plain text content.
	 * @return array
	 */
	private function check_semantic_completeness( $content, $plain ) {
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

		// Word count (300-2000 optimal).
		if ( $word_count >= 300 && $word_count <= 2000 ) {
			$sub_score += 4;
		} elseif ( $word_count > 2000 ) {
			$sub_score += 2;
			$issues[]   = 'Content is quite long (' . $word_count . ' words) — consider trimming to under 2000 words';
		} else {
			$issues[] = 'Content is short (' . $word_count . ' words) — aim for at least 300 words';
		}

		// Has intro (first paragraph exists).
		preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $content, $paragraphs );
		$has_intro = ! empty( $paragraphs[1][0] ) && str_word_count( wp_strip_all_tags( $paragraphs[1][0] ) ) >= 10;
		if ( $has_intro ) {
			$sub_score += 3;
		} else {
			$issues[] = 'Add a clear introductory paragraph (at least 10 words)';
		}

		// Has conclusion (last paragraph with conclusion keywords).
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
	 *
	 * @param string $content HTML content.
	 * @return array
	 */
	private function check_snippet_optimization( $content ) {
		$result = array(
			'name'       => 'snippet_optimization',
			'label'      => 'Snippet Optimization',
			'score'      => 0,
			'max'        => 10,
			'suggestion' => '',
		);

		$issues    = array();
		$sub_score = 0;

		// Check for a concise summary paragraph (under 60 words) in the first 3 paragraphs.
		preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $content, $paragraphs );
		$has_concise = false;
		$check_count = min( 3, count( $paragraphs[1] ) );

		for ( $i = 0; $i < $check_count; $i++ ) {
			$text  = wp_strip_all_tags( $paragraphs[1][ $i ] );
			$words = str_word_count( $text );
			if ( $words > 0 && $words <= 60 ) {
				$has_concise = true;
				break;
			}
		}

		if ( $has_concise ) {
			$sub_score += 5;
		} else {
			$issues[] = 'Add a concise summary paragraph (under 60 words) within the first 3 paragraphs';
		}

		// Check for bold/strong tags.
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
	 *
	 * @param string $plain Plain text content.
	 * @return array
	 */
	private function check_freshness_signals( $plain ) {
		$result = array(
			'name'       => 'freshness_signals',
			'label'      => 'Freshness Signals',
			'score'      => 0,
			'max'        => 10,
			'suggestion' => '',
		);

		$patterns = array(
			'/\b20[2-3]\d\b/',                  // years like 2024, 2025, 2026, etc.
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

		if ( $count >= 2 ) {
			$result['score']      = 10;
			$result['suggestion'] = 'Strong freshness signals (' . $count . ' references). AI models favor up-to-date content.';
		} elseif ( 1 === $count ) {
			$result['score']      = 5;
			$result['suggestion'] = 'One freshness signal found. Add more date references or words like "updated", "latest", "current".';
		} else {
			$result['suggestion'] = 'No freshness signals detected. Include year references (e.g., 2026), "updated", "latest", or "as of" to signal recency.';
		}

		return $result;
	}
}
