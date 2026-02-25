<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Engine — communicates with AI APIs to generate optimized SEO content.
 *
 * Supported providers: OpenAI, Anthropic, Gemini, Grok, Ollama, DeepSeek.
 */
class AI_SEO_Pilot_AI_Engine {

	/** @var array Provider definitions. */
	private $providers = array();

	public function __construct() {
		$this->providers = array(
			'openai'    => array(
				'label'    => 'OpenAI',
				'endpoint' => 'https://api.openai.com/v1/chat/completions',
				'format'   => 'openai',
				'models'   => array(
					'gpt-5.2'             => 'GPT-5.2 Thinking (best quality)',
					'gpt-5.2-chat-latest' => 'GPT-5.2 Instant (fast)',
					'gpt-5-mini'   => 'GPT-5 Mini (fast)',
					'gpt-5-nano'   => 'GPT-5 Nano (cheapest)',
					'gpt-4.1'      => 'GPT-4.1',
					'gpt-4.1-mini' => 'GPT-4.1 Mini',
					'gpt-4o-mini'  => 'GPT-4o Mini',
					'o4-mini'      => 'o4 Mini (reasoning)',
					'o3-mini'      => 'o3 Mini (reasoning)',
				),
				'auth'     => 'api_key',
				'key_url'  => 'https://platform.openai.com/api-keys',
			),
			'anthropic' => array(
				'label'    => 'Anthropic',
				'endpoint' => 'https://api.anthropic.com/v1/messages',
				'format'   => 'anthropic',
				'models'   => array(
					'claude-opus-4-6'            => 'Claude Opus 4.6 (best quality)',
					'claude-sonnet-4-6'          => 'Claude Sonnet 4.6 (recommended)',
					'claude-opus-4-5-20251101'   => 'Claude Opus 4.5',
					'claude-sonnet-4-5-20250929' => 'Claude Sonnet 4.5',
					'claude-haiku-4-5-20251001'  => 'Claude Haiku 4.5 (fast, cheap)',
				),
				'auth'     => 'api_key',
				'key_url'  => 'https://console.anthropic.com/settings/keys',
			),
			'gemini'    => array(
				'label'    => 'Google Gemini',
				'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/',
				'format'   => 'gemini',
				'models'   => array(
					'gemini-3.1-pro-preview' => 'Gemini 3.1 Pro (best quality)',
					'gemini-3-flash-preview' => 'Gemini 3 Flash (fast)',
					'gemini-2.5-pro'         => 'Gemini 2.5 Pro',
					'gemini-2.5-flash'       => 'Gemini 2.5 Flash (best value)',
					'gemini-2.5-flash-lite'  => 'Gemini 2.5 Flash Lite (cheapest)',
				),
				'auth'     => 'api_key',
				'key_url'  => 'https://aistudio.google.com/apikey',
			),
			'grok'      => array(
				'label'    => 'xAI Grok',
				'endpoint' => 'https://api.x.ai/v1/chat/completions',
				'format'   => 'openai',
				'models'   => array(
					'grok-4-1-fast-non-reasoning' => 'Grok 4.1 Fast',
					'grok-4-0709'                 => 'Grok 4 (best quality)',
					'grok-3'                      => 'Grok 3',
					'grok-3-mini'                 => 'Grok 3 Mini (cheapest)',
				),
				'auth'     => 'api_key',
				'key_url'  => 'https://console.x.ai/',
			),
			'ollama'    => array(
				'label'       => 'Ollama (Local)',
				'endpoint'    => '',
				'format'      => 'ollama',
				'models'      => array(
					'llama4'      => 'Llama 4 (recommended)',
					'llama3.3'    => 'Llama 3.3',
					'llama3.2'    => 'Llama 3.2',
					'mistral'     => 'Mistral (7B)',
					'mixtral'     => 'Mixtral (8x7B)',
					'qwen3'       => 'Qwen 3',
					'qwen2.5'     => 'Qwen 2.5',
					'gemma3'      => 'Gemma 3',
					'gemma2'      => 'Gemma 2',
					'phi4'        => 'Phi 4',
					'deepseek-r1' => 'DeepSeek R1',
					'command-r'   => 'Command R',
				),
				'auth'        => 'server_url',
				'default_url' => 'http://localhost:11434',
			),
			'deepseek'  => array(
				'label'    => 'DeepSeek',
				'endpoint' => 'https://api.deepseek.com/v1/chat/completions',
				'format'   => 'openai',
				'models'   => array(
					'deepseek-chat'     => 'DeepSeek V3.2 Chat (fast)',
					'deepseek-reasoner' => 'DeepSeek V3.2 Reasoner (best quality)',
				),
				'auth'     => 'api_key',
				'key_url'  => 'https://platform.deepseek.com/api_keys',
			),
		);
	}

	/* ── Public Getters ─────────────────────────────────────────── */

	/**
	 * Get all provider definitions.
	 *
	 * @return array
	 */
	public function get_providers() {
		return $this->providers;
	}

	/**
	 * Get models for a specific provider.
	 *
	 * @param string $provider_key Provider key.
	 * @return array Associative array of model_id => label.
	 */
	public function get_provider_models( $provider_key ) {
		if ( isset( $this->providers[ $provider_key ] ) ) {
			return $this->providers[ $provider_key ]['models'];
		}
		return array();
	}

	/**
	 * Check whether the active AI provider is configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		$provider = get_option( 'ai_seo_pilot_ai_provider', 'openai' );

		if ( ! isset( $this->providers[ $provider ] ) ) {
			return false;
		}

		$config = $this->providers[ $provider ];

		if ( 'server_url' === $config['auth'] ) {
			$url = get_option( "ai_seo_pilot_ai_{$provider}_server_url", $config['default_url'] ?? '' );
			return ! empty( $url );
		}

		$key = get_option( "ai_seo_pilot_ai_{$provider}_api_key", '' );
		return ! empty( $key );
	}

	/* ── Content Generation ─────────────────────────────────────── */

	/**
	 * Generate an AI-optimized llms.txt by analyzing the entire site.
	 *
	 * @return string|WP_Error Generated content or error.
	 */
	public function generate_llms_txt() {
		$context = $this->get_site_context();

		$prompt  = "Generate an llms.txt file for this website. Do NOT mention the underlying technology (WordPress, CMS, etc.).\n\n";
		$prompt .= "IMPORTANT: Output ONLY the llms.txt content. No explanations, no preamble, no commentary, no \"---\" separators. Start directly with the # title line.\n\n";
		$prompt .= "Format rules:\n";
		$prompt .= "- Start with: # Site Name\n";
		$prompt .= "- Then: > One-line tagline (max 150 chars)\n";
		$prompt .= "- Then an extended description paragraph\n";
		$prompt .= "- Then ## sections: Key Pages, Recent Content, Optional\n";
		$prompt .= "- Each link as: - [Title](URL): Brief description\n";
		$prompt .= "- Include actual URLs from the site data below\n";
		$prompt .= "- Focus on what makes this site authoritative and citation-worthy\n\n";
		$prompt .= "SITE DATA:\n" . $context;

		return $this->call_api( $prompt, 2000 );
	}

	/**
	 * Generate an AI-optimized site tagline.
	 *
	 * @return string|WP_Error Generated tagline or error.
	 */
	public function generate_tagline() {
		$context = $this->get_site_context();

		$prompt  = "You are an AI SEO expert. Generate an optimized site tagline (description) for this website.\n\n";
		$prompt .= "Requirements:\n";
		$prompt .= "- Maximum 120 characters\n";
		$prompt .= "- Concise, memorable, and descriptive\n";
		$prompt .= "- Clearly communicates the site's core value proposition\n";
		$prompt .= "- Optimized for AI search engines to understand the site's purpose\n";
		$prompt .= "- Professional tone, no hype or buzzwords\n\n";
		$prompt .= "Return ONLY the tagline text, nothing else. No quotes.\n\n";
		$prompt .= "SITE DATA:\n" . $context;

		return $this->call_api( $prompt, 100 );
	}

	/**
	 * Generate an AI-optimized meta description for a specific post.
	 *
	 * @param int $post_id Post ID.
	 * @return string|WP_Error Generated description or error.
	 */
	public function generate_meta_description( $post_id ) {
		$post_context = $this->get_post_context( $post_id );
		$site_context = $this->get_site_context_brief();

		$prompt  = "You are an AI SEO expert. Generate an optimized meta description for this content.\n\n";
		$prompt .= "Requirements:\n";
		$prompt .= "- 150-160 characters maximum\n";
		$prompt .= "- Optimized for AI search engines (ChatGPT, Claude, Perplexity) to cite this page\n";
		$prompt .= "- Include a clear value proposition — why an AI should reference this page\n";
		$prompt .= "- Use a factual, authoritative tone\n";
		$prompt .= "- Include key entities and specific data points if available\n\n";
		$prompt .= "Return ONLY the meta description, nothing else.\n\n";
		$prompt .= "SITE: {$site_context}\n\n";
		$prompt .= "POST:\n{$post_context}";

		return $this->call_api( $prompt, 200 );
	}

	/**
	 * Generate AI-powered SEO improvement suggestions for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string|WP_Error JSON-formatted suggestions or error.
	 */
	public function generate_seo_suggestions( $post_id ) {
		$post_context = $this->get_post_context( $post_id );
		$site_context = $this->get_site_context_brief();

		$prompt  = "You are an AI SEO expert specializing in GEO (Generative Engine Optimization).\n";
		$prompt .= "Analyze this content and provide specific, actionable suggestions to make it more likely to be cited by AI search engines.\n\n";
		$prompt .= "Return a JSON array of suggestions. Each suggestion must have:\n";
		$prompt .= "- \"category\": one of \"content\", \"structure\", \"schema\", \"freshness\", \"authority\"\n";
		$prompt .= "- \"priority\": \"high\", \"medium\", or \"low\"\n";
		$prompt .= "- \"title\": short action title (imperative form)\n";
		$prompt .= "- \"description\": specific explanation of what to do and why\n\n";
		$prompt .= "Focus on:\n";
		$prompt .= "- Direct answer optimization (can AI extract a clear answer?)\n";
		$prompt .= "- Citable facts and statistics\n";
		$prompt .= "- Entity clarity (are key terms well-defined?)\n";
		$prompt .= "- Structural improvements (Q&A, lists, tables)\n";
		$prompt .= "- Freshness signals\n";
		$prompt .= "- Missing topics that should be covered\n\n";
		$prompt .= "Return 3-7 suggestions, ordered by priority. Return ONLY valid JSON.\n\n";
		$prompt .= "SITE: {$site_context}\n\n";
		$prompt .= "POST:\n{$post_context}";

		return $this->call_api( $prompt, 1500 );
	}

	/**
	 * Generate an enriched Schema.org description for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string|WP_Error Generated description or error.
	 */
	public function generate_schema_description( $post_id ) {
		$post_context = $this->get_post_context( $post_id );

		$prompt  = "You are an AI SEO expert. Generate an optimized Schema.org description for this content.\n\n";
		$prompt .= "Requirements:\n";
		$prompt .= "- 200-300 characters\n";
		$prompt .= "- Semantically rich — include key entities, topics, and scope\n";
		$prompt .= "- Factual and specific, not promotional\n";
		$prompt .= "- Optimized for AI search engines to understand what this content covers\n\n";
		$prompt .= "Return ONLY the description text, nothing else.\n\n";
		$prompt .= "POST:\n{$post_context}";

		return $this->call_api( $prompt, 300 );
	}

	/* ── Site Context Builders ──────────────────────────────────── */

	/**
	 * Build a comprehensive site context string from all DB content.
	 *
	 * @return string
	 */
	private function get_site_context() {
		$lines = array();

		// Site info.
		$lines[] = 'Site Name: ' . get_bloginfo( 'name' );
		$lines[] = 'Site URL: ' . home_url( '/' );
		$lines[] = 'Tagline: ' . get_bloginfo( 'description' );
		$lines[] = 'Language: ' . get_locale();
		$lines[] = '';

		// Categories.
		$categories = get_categories( array( 'hide_empty' => true ) );
		if ( ! empty( $categories ) ) {
			$lines[] = 'CATEGORIES:';
			foreach ( $categories as $cat ) {
				$lines[] = "- {$cat->name} ({$cat->count} posts): {$cat->description}";
			}
			$lines[] = '';
		}

		// Tags (top 20).
		$tags = get_tags( array( 'hide_empty' => true, 'number' => 20, 'orderby' => 'count', 'order' => 'DESC' ) );
		if ( ! empty( $tags ) ) {
			$tag_names = wp_list_pluck( $tags, 'name' );
			$lines[]   = 'TOP TAGS: ' . implode( ', ', $tag_names );
			$lines[]   = '';
		}

		// Pages.
		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 30,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		) );

		if ( ! empty( $pages ) ) {
			$lines[] = 'PAGES:';
			foreach ( $pages as $page ) {
				$excerpt  = $this->truncate_text( $page->post_content, 200 );
				$lines[]  = "- [{$page->post_title}](" . get_permalink( $page ) . ')';
				if ( $excerpt ) {
					$lines[] = "  {$excerpt}";
				}
			}
			$lines[] = '';
		}

		// Posts (top 30 by date).
		$posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 30,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		if ( ! empty( $posts ) ) {
			$lines[] = 'POSTS (most recent):';
			foreach ( $posts as $p ) {
				$excerpt    = $this->truncate_text( $p->post_excerpt ?: $p->post_content, 200 );
				$word_count = str_word_count( wp_strip_all_tags( $p->post_content ) );
				$cats       = wp_get_post_categories( $p->ID, array( 'fields' => 'names' ) );
				$cat_str    = ! empty( $cats ) ? ' [' . implode( ', ', $cats ) . ']' : '';
				$lines[]    = "- [{$p->post_title}](" . get_permalink( $p ) . "){$cat_str} ({$word_count} words)";
				if ( $excerpt ) {
					$lines[] = "  {$excerpt}";
				}
			}
			$lines[] = '';
		}

		// WooCommerce products (if active).
		if ( class_exists( 'WooCommerce' ) ) {
			$products = get_posts( array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
			) );

			if ( ! empty( $products ) ) {
				$lines[] = 'PRODUCTS:';
				foreach ( $products as $p ) {
					$excerpt = $this->truncate_text( $p->post_excerpt ?: $p->post_content, 150 );
					$lines[] = "- [{$p->post_title}](" . get_permalink( $p ) . ')';
					if ( $excerpt ) {
						$lines[] = "  {$excerpt}";
					}
				}
				$lines[] = '';
			}
		}

		// Stats.
		$total_posts = wp_count_posts( 'post' );
		$total_pages = wp_count_posts( 'page' );
		$lines[]     = 'STATS:';
		$lines[]     = "- Published posts: {$total_posts->publish}";
		$lines[]     = "- Published pages: {$total_pages->publish}";

		return implode( "\n", $lines );
	}

	/**
	 * Brief one-line site context for per-post prompts.
	 *
	 * @return string
	 */
	private function get_site_context_brief() {
		return get_bloginfo( 'name' ) . ' (' . home_url() . ') — ' . get_bloginfo( 'description' );
	}

	/**
	 * Build detailed context for a single post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_post_context( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$lines = array();

		$lines[] = 'Title: ' . $post->post_title;
		$lines[] = 'URL: ' . get_permalink( $post );
		$lines[] = 'Type: ' . $post->post_type;
		$lines[] = 'Published: ' . get_the_date( 'Y-m-d', $post );
		$lines[] = 'Modified: ' . get_the_modified_date( 'Y-m-d', $post );
		$lines[] = 'Author: ' . get_the_author_meta( 'display_name', $post->post_author );

		// Categories and tags.
		$cats = wp_get_post_categories( $post_id, array( 'fields' => 'names' ) );
		if ( ! empty( $cats ) ) {
			$lines[] = 'Categories: ' . implode( ', ', $cats );
		}
		$tags = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
		if ( ! empty( $tags ) ) {
			$lines[] = 'Tags: ' . implode( ', ', $tags );
		}

		// Excerpt.
		if ( $post->post_excerpt ) {
			$lines[] = 'Excerpt: ' . $post->post_excerpt;
		}

		// Full content (truncated to ~3000 words to fit in context).
		$plain = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		$words = explode( ' ', $plain );
		if ( count( $words ) > 3000 ) {
			$plain = implode( ' ', array_slice( $words, 0, 3000 ) ) . '...';
		}
		$lines[] = '';
		$lines[] = 'Content:';
		$lines[] = $plain;

		// Word count.
		$lines[] = '';
		$lines[] = 'Word count: ' . str_word_count( wp_strip_all_tags( $post->post_content ) );

		return implode( "\n", $lines );
	}

	/* ── API Communication ──────────────────────────────────────── */

	/**
	 * Call the configured AI API.
	 *
	 * @param string $prompt     The user prompt.
	 * @param int    $max_tokens Max tokens for the response.
	 * @return string|WP_Error Response text or error.
	 */
	private function call_api( $prompt, $max_tokens = 1000 ) {
		$provider = get_option( 'ai_seo_pilot_ai_provider', 'openai' );

		if ( ! isset( $this->providers[ $provider ] ) ) {
			return new \WP_Error( 'invalid_provider', __( 'Invalid AI provider configured.', 'ai-seo-pilot' ) );
		}

		$config = $this->providers[ $provider ];
		$model  = get_option( "ai_seo_pilot_ai_{$provider}_model", '' );

		// Get credential.
		if ( 'server_url' === $config['auth'] ) {
			$credential = get_option( "ai_seo_pilot_ai_{$provider}_server_url", $config['default_url'] ?? '' );
			if ( empty( $credential ) ) {
				return new \WP_Error( 'no_server_url', __( 'Server URL is not configured. Go to Settings > AI API.', 'ai-seo-pilot' ) );
			}
		} else {
			$credential = get_option( "ai_seo_pilot_ai_{$provider}_api_key", '' );
			if ( empty( $credential ) ) {
				return new \WP_Error( 'no_api_key', __( 'AI API key is not configured. Go to Settings > AI API to add your key.', 'ai-seo-pilot' ) );
			}
		}

		// Default model if not set. Local providers (Ollama) accept custom model names.
		$valid_models = array_keys( $config['models'] );
		if ( empty( $model ) ) {
			$model = $valid_models[0];
		} elseif ( 'server_url' !== $config['auth'] && ! in_array( $model, $valid_models, true ) ) {
			$model = $valid_models[0];
		}

		return $this->dispatch_call( $config, $credential, $model, $prompt, $max_tokens );
	}

	/**
	 * Public test method — called from AJAX with form values (not yet saved).
	 *
	 * @param string $provider   Provider key.
	 * @param string $credential API key or server URL.
	 * @param string $model      Model ID.
	 * @return string|WP_Error Response text or error.
	 */
	public function test_connection( $provider, $credential, $model ) {
		if ( ! isset( $this->providers[ $provider ] ) ) {
			return new \WP_Error( 'invalid_provider', __( 'Invalid AI provider.', 'ai-seo-pilot' ) );
		}

		$config       = $this->providers[ $provider ];
		$valid_models = array_keys( $config['models'] );

		if ( empty( $model ) ) {
			$model = $valid_models[0];
		} elseif ( 'server_url' !== $config['auth'] && ! in_array( $model, $valid_models, true ) ) {
			$model = $valid_models[0];
		}

		$site_context = $this->get_site_context_brief();

		// Include a few recent post titles so the AI knows what the site is actually about.
		$recent = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		$titles = array();
		foreach ( $recent as $p ) {
			$titles[] = $p->post_title;
		}

		$prompt  = "Based on the following info and recent content, respond with exactly one sentence describing what this website/application does. Do NOT mention the underlying technology (WordPress, CMS, etc.).\n\n";
		$prompt .= "Site: {$site_context}\n";
		if ( ! empty( $titles ) ) {
			$prompt .= "Recent content: " . implode( ' | ', $titles ) . "\n";
		}
		$prompt .= "\nRespond ONLY with the one-sentence description.";

		return $this->dispatch_call( $config, $credential, $model, $prompt, 150 );
	}

	/**
	 * Route to the correct API caller based on provider format.
	 *
	 * @param array  $config     Provider config.
	 * @param string $credential API key or server URL.
	 * @param string $model      Model ID.
	 * @param string $prompt     User prompt.
	 * @param int    $max_tokens Max tokens.
	 * @return string|WP_Error
	 */
	private function dispatch_call( $config, $credential, $model, $prompt, $max_tokens ) {
		$system = 'You are an expert AI SEO consultant specializing in Generative Engine Optimization (GEO) and Answer Engine Optimization (AEO). You help websites get cited by AI search engines like ChatGPT, Claude, Perplexity, and Gemini.';

		switch ( $config['format'] ) {
			case 'anthropic':
				return $this->call_anthropic( $config['endpoint'], $credential, $model, $system, $prompt, $max_tokens );

			case 'gemini':
				return $this->call_gemini( $config['endpoint'], $credential, $model, $system, $prompt, $max_tokens );

			case 'ollama':
				$endpoint = rtrim( $credential, '/' ) . '/api/chat';
				return $this->call_ollama( $endpoint, $model, $system, $prompt, $max_tokens );

			case 'openai':
			default:
				return $this->call_openai( $config['endpoint'], $credential, $model, $system, $prompt, $max_tokens );
		}
	}

	/**
	 * Call OpenAI-compatible API (OpenAI, Grok, DeepSeek).
	 */
	private function call_openai( $endpoint, $api_key, $model, $system, $prompt, $max_tokens ) {
		$body = array(
			'model'       => $model,
			'messages'    => array(
				array( 'role' => 'system', 'content' => $system ),
				array( 'role' => 'user', 'content' => $prompt ),
			),
			'max_tokens'  => $max_tokens,
			'temperature' => 0.7,
		);

		$response = wp_remote_post( $endpoint, array(
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : "API returned status {$code}";
			return new \WP_Error( 'api_error', $error_msg );
		}

		if ( empty( $data['choices'][0]['message']['content'] ) ) {
			return new \WP_Error( 'empty_response', __( 'AI returned an empty response.', 'ai-seo-pilot' ) );
		}

		return trim( $data['choices'][0]['message']['content'] );
	}

	/**
	 * Call Anthropic Messages API.
	 */
	private function call_anthropic( $endpoint, $api_key, $model, $system, $prompt, $max_tokens ) {
		$body = array(
			'model'      => $model,
			'max_tokens' => $max_tokens,
			'system'     => $system,
			'messages'   => array(
				array( 'role' => 'user', 'content' => $prompt ),
			),
		);

		$response = wp_remote_post( $endpoint, array(
			'timeout' => 60,
			'headers' => array(
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : "API returned status {$code}";
			return new \WP_Error( 'api_error', $error_msg );
		}

		if ( empty( $data['content'][0]['text'] ) ) {
			return new \WP_Error( 'empty_response', __( 'AI returned an empty response.', 'ai-seo-pilot' ) );
		}

		return trim( $data['content'][0]['text'] );
	}

	/**
	 * Call Google Gemini generateContent API.
	 */
	private function call_gemini( $base_endpoint, $api_key, $model, $system, $prompt, $max_tokens ) {
		$endpoint = rtrim( $base_endpoint, '/' ) . '/' . $model . ':generateContent?key=' . $api_key;

		$body = array(
			'systemInstruction' => array(
				'parts' => array( array( 'text' => $system ) ),
			),
			'contents'          => array(
				array(
					'parts' => array( array( 'text' => $prompt ) ),
				),
			),
			'generationConfig'  => array(
				'maxOutputTokens' => $max_tokens,
				'temperature'     => 0.7,
			),
		);

		$response = wp_remote_post( $endpoint, array(
			'timeout' => 60,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : "API returned status {$code}";
			return new \WP_Error( 'api_error', $error_msg );
		}

		if ( empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return new \WP_Error( 'empty_response', __( 'AI returned an empty response.', 'ai-seo-pilot' ) );
		}

		return trim( $data['candidates'][0]['content']['parts'][0]['text'] );
	}

	/**
	 * Call Ollama local API.
	 */
	private function call_ollama( $endpoint, $model, $system, $prompt, $max_tokens ) {
		$body = array(
			'model'    => $model,
			'messages' => array(
				array( 'role' => 'system', 'content' => $system ),
				array( 'role' => 'user', 'content' => $prompt ),
			),
			'stream'   => false,
			'options'  => array(
				'num_predict' => $max_tokens,
				'temperature' => 0.7,
			),
		);

		$response = wp_remote_post( $endpoint, array(
			'timeout' => 120,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'ollama_error', __( 'Could not connect to Ollama. Is it running?', 'ai-seo-pilot' ) . ' ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$error_msg = isset( $data['error'] ) ? $data['error'] : "Ollama returned status {$code}";
			return new \WP_Error( 'api_error', $error_msg );
		}

		if ( empty( $data['message']['content'] ) ) {
			return new \WP_Error( 'empty_response', __( 'AI returned an empty response.', 'ai-seo-pilot' ) );
		}

		return trim( $data['message']['content'] );
	}

	/* ── Helpers ─────────────────────────────────────────────────── */

	/**
	 * Truncate content to a max character length, stripping HTML.
	 *
	 * @param string $text Raw text/HTML.
	 * @param int    $max  Max characters.
	 * @return string
	 */
	private function truncate_text( $text, $max = 200 ) {
		$text = wp_strip_all_tags( strip_shortcodes( $text ) );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}

		return mb_substr( $text, 0, $max - 3 ) . '...';
	}
}
