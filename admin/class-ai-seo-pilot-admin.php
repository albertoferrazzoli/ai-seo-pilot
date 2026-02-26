<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_SEO_Pilot_Admin {

	public function __construct() {
		$this->init_hooks();
	}

	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'maybe_flush_rewrite_rules' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_ai_seo_pilot_validate_llms_txt', array( $this, 'ajax_validate_llms_txt' ) );
		add_action( 'wp_ajax_ai_seo_pilot_regenerate_llms_txt', array( $this, 'ajax_regenerate_llms_txt' ) );
		add_action( 'wp_ajax_ai_seo_pilot_ai_generate_llms_txt', array( $this, 'ajax_ai_generate_llms_txt' ) );
		add_action( 'wp_ajax_ai_seo_pilot_ai_generate_meta', array( $this, 'ajax_ai_generate_meta' ) );
		add_action( 'wp_ajax_ai_seo_pilot_ai_generate_suggestions', array( $this, 'ajax_ai_generate_suggestions' ) );
		add_action( 'wp_ajax_ai_seo_pilot_ai_test_connection', array( $this, 'ajax_ai_test_connection' ) );
		add_action( 'wp_ajax_ai_seo_pilot_seo_fix', array( $this, 'ajax_seo_fix' ) );
		add_action( 'wp_ajax_ai_seo_pilot_bulk_generate_meta', array( $this, 'ajax_bulk_generate_meta' ) );
		add_action( 'wp_ajax_ai_seo_pilot_generate_tagline', array( $this, 'ajax_generate_tagline' ) );

		// Meta boxes.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ) );

		// Auto-generate AI meta description on publish.
		add_action( 'transition_post_status', array( $this, 'auto_generate_meta_description' ), 10, 3 );

		// Dashboard widget.
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
	}

	/* ── Rewrite Rules Flush ─────────────────────────────────── */

	public function maybe_flush_rewrite_rules() {
		if ( get_transient( 'ai_seo_pilot_flush_rewrite' ) ) {
			delete_transient( 'ai_seo_pilot_flush_rewrite' );
			flush_rewrite_rules();
		}
	}

	/* ── Admin Menu ──────────────────────────────────────────── */

	public function add_admin_menu() {
		add_menu_page(
			__( 'AI SEO Pilot', 'ai-seo-pilot' ),
			__( 'AI SEO Pilot', 'ai-seo-pilot' ),
			'manage_options',
			'ai-seo-pilot',
			array( $this, 'render_dashboard_page' ),
			'dashicons-superhero-alt',
			80
		);

		add_submenu_page(
			'ai-seo-pilot',
			__( 'Dashboard', 'ai-seo-pilot' ),
			__( 'Dashboard', 'ai-seo-pilot' ),
			'manage_options',
			'ai-seo-pilot',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'ai-seo-pilot',
			__( 'SEO Check', 'ai-seo-pilot' ),
			__( 'SEO Check', 'ai-seo-pilot' ),
			'manage_options',
			'ai-seo-pilot-seo-check',
			array( $this, 'render_seo_check_page' )
		);

		add_submenu_page(
			'ai-seo-pilot',
			__( 'LLMS', 'ai-seo-pilot' ),
			__( 'LLMS', 'ai-seo-pilot' ),
			'manage_options',
			'ai-seo-pilot-llms-txt',
			array( $this, 'render_llms_txt_page' )
		);

		add_submenu_page(
			'ai-seo-pilot',
			__( 'Settings', 'ai-seo-pilot' ),
			__( 'Settings', 'ai-seo-pilot' ),
			'manage_options',
			'ai-seo-pilot-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/* ── Settings Registration ───────────────────────────────── */

	public function register_settings() {
		// General.
		register_setting( 'ai_seo_pilot_general', 'ai_seo_pilot_schema_enabled' );
		register_setting( 'ai_seo_pilot_general', 'ai_seo_pilot_analyzer_enabled' );
		register_setting( 'ai_seo_pilot_general', 'ai_seo_pilot_ai_visibility_enabled' );
		register_setting( 'ai_seo_pilot_general', 'ai_seo_pilot_sitemap_ai_enabled' );

		// llms.txt.
		register_setting( 'ai_seo_pilot_general', 'ai_seo_pilot_llms_txt_mode' );
		register_setting( 'ai_seo_pilot_general', 'ai_seo_pilot_llms_txt_manual' );

		// Schema.
		register_setting( 'ai_seo_pilot_general', 'ai_seo_pilot_schema_organization' );
		register_setting( 'ai_seo_pilot_general', 'ai_seo_pilot_schema_breadcrumbs' );
		register_setting( 'ai_seo_pilot_general', 'ai_seo_pilot_schema_website' );
		register_setting( 'ai_seo_pilot_general', 'ai_seo_pilot_organization_same_as' );

		// Content Analysis.
		register_setting( 'ai_seo_pilot_general', 'ai_seo_pilot_content_analysis', array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_content_analysis' ),
			'default'           => array(),
		) );

		// AI Bots.
		register_setting( 'ai_seo_pilot_general', 'ai_seo_pilot_bot_retention_days' );
		register_setting( 'ai_seo_pilot_general', 'ai_seo_pilot_custom_bots', array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_custom_bots' ),
			'default'           => array(),
		) );

		// AI API — provider selector.
		register_setting( 'ai_seo_pilot_general', 'ai_seo_pilot_ai_provider' );

		// AI API — per-provider settings.
		$ai_providers = array( 'openai', 'anthropic', 'gemini', 'grok', 'deepseek' );
		foreach ( $ai_providers as $prov ) {
			register_setting( 'ai_seo_pilot_general', "ai_seo_pilot_ai_{$prov}_api_key" );
			register_setting( 'ai_seo_pilot_general', "ai_seo_pilot_ai_{$prov}_model" );
		}
		// Ollama uses server_url instead of api_key.
		register_setting( 'ai_seo_pilot_general', 'ai_seo_pilot_ai_ollama_server_url' );
		register_setting( 'ai_seo_pilot_general', 'ai_seo_pilot_ai_ollama_model' );

		// Advanced.
		register_setting( 'ai_seo_pilot_general', 'ai_seo_pilot_robots_txt_enhance' );
		register_setting( 'ai_seo_pilot_general', 'ai_seo_pilot_x_robots_tag' );
		register_setting( 'ai_seo_pilot_general', 'ai_seo_pilot_remove_data_on_uninstall' );
	}

	/* ── Asset Enqueuing ─────────────────────────────────────── */

	public function enqueue_assets( $hook ) {
		// Admin CSS on all plugin pages.
		$plugin_pages = array(
			'toplevel_page_ai-seo-pilot',
			'ai-seo-pilot_page_ai-seo-pilot-settings',
			'ai-seo-pilot_page_ai-seo-pilot-llms-txt',
			'ai-seo-pilot_page_ai-seo-pilot-seo-check',
		);

		// Load on post edit screens for the meta box and on WP dashboard.
		$is_post_edit = in_array( $hook, array( 'post.php', 'post-new.php' ), true );
		$is_dashboard = ( 'index.php' === $hook );

		if ( ! in_array( $hook, $plugin_pages, true ) && ! $is_post_edit && ! $is_dashboard ) {
			return;
		}

		wp_enqueue_style(
			'ai-seo-pilot-admin',
			AI_SEO_PILOT_URL . 'assets/css/admin.css',
			array(),
			AI_SEO_PILOT_VERSION
		);

		wp_enqueue_script(
			'ai-seo-pilot-admin',
			AI_SEO_PILOT_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			AI_SEO_PILOT_VERSION,
			true
		);

		wp_localize_script( 'ai-seo-pilot-admin', 'aiSeoPilot', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ai_seo_pilot_nonce' ),
			'siteUrl' => site_url(),
		) );
	}

	/* ── Page Renderers ──────────────────────────────────────── */

	public function render_dashboard_page() {
		include AI_SEO_PILOT_PATH . 'admin/partials/dashboard.php';
	}

	public function render_settings_page() {
		include AI_SEO_PILOT_PATH . 'admin/partials/settings.php';
	}

	public function render_llms_txt_page() {
		include AI_SEO_PILOT_PATH . 'admin/partials/llms-txt-generator.php';
	}

	public function render_seo_check_page() {
		include AI_SEO_PILOT_PATH . 'admin/partials/seo-check.php';
	}

	/* ── Dashboard Widget ────────────────────────────────────── */

	public function add_dashboard_widget() {
		wp_add_dashboard_widget(
			'ai_seo_pilot_dashboard_widget',
			__( 'AI SEO Pilot', 'ai-seo-pilot' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	public function render_dashboard_widget() {
		include AI_SEO_PILOT_PATH . 'admin/partials/dashboard-widget.php';
	}

	/* ── Meta Boxes ──────────────────────────────────────────── */

	public function add_meta_boxes() {
		$post_types = get_post_types( array( 'public' => true ) );
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'ai-seo-pilot-schema',
				__( 'AI SEO Pilot Schema', 'ai-seo-pilot' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	public function render_meta_box( $post ) {
		include AI_SEO_PILOT_PATH . 'admin/partials/post-meta-box.php';
	}

	public function save_meta_box( $post_id ) {
		if ( ! isset( $_POST['ai_seo_pilot_schema_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['ai_seo_pilot_schema_nonce'], 'ai_seo_pilot_save_schema' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['ai_seo_pilot_schema_type'] ) ) {
			$type = sanitize_text_field( $_POST['ai_seo_pilot_schema_type'] );
			$allowed = array( 'auto', 'Article', 'BlogPosting', 'FAQPage', 'HowTo', 'NewsArticle', 'none' );
			if ( in_array( $type, $allowed, true ) ) {
				update_post_meta( $post_id, '_ai_seo_pilot_schema_type', $type );
			}
		}

		if ( isset( $_POST['ai_seo_pilot_meta_description'] ) ) {
			$desc = sanitize_text_field( $_POST['ai_seo_pilot_meta_description'] );
			update_post_meta( $post_id, '_ai_seo_pilot_meta_description', $desc );
		}
	}

	/* ── Auto-Generate Meta Description ─────────────────────── */

	/**
	 * Auto-generate AI meta description when a post is published
	 * and doesn't already have one.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public function auto_generate_meta_description( $new_status, $old_status, $post ) {
		// Only on publish.
		if ( 'publish' !== $new_status ) {
			return;
		}

		// Only for public post types.
		if ( ! in_array( $post->post_type, get_post_types( array( 'public' => true ) ), true ) ) {
			return;
		}

		// Skip if already has a meta description.
		$existing = get_post_meta( $post->ID, '_ai_seo_pilot_meta_description', true );
		if ( ! empty( $existing ) ) {
			return;
		}

		// Skip if AI is not configured.
		$plugin = AI_SEO_Pilot::get_instance();
		if ( ! $plugin->ai_engine->is_configured() ) {
			return;
		}

		// Generate and save.
		$description = $plugin->ai_engine->generate_meta_description( $post->ID );
		if ( ! is_wp_error( $description ) && ! empty( $description ) ) {
			update_post_meta( $post->ID, '_ai_seo_pilot_meta_description', sanitize_text_field( $description ) );
		}
	}

	/* ── Content Analysis Sanitization ───────────────────────── */

	public function sanitize_content_analysis( $input ) {
		$defaults  = AI_SEO_Pilot_Content_Analyzer::get_defaults();
		$sanitized = array();

		$sanitized['ai_ready_threshold'] = isset( $input['ai_ready_threshold'] )
			? max( 0, min( 100, absint( $input['ai_ready_threshold'] ) ) )
			: $defaults['ai_ready_threshold'];

		$sanitized['checks'] = array();
		foreach ( $defaults['checks'] as $key => $def ) {
			$src = isset( $input['checks'][ $key ] ) ? $input['checks'][ $key ] : array();
			$sanitized['checks'][ $key ] = array(
				'enabled' => ! empty( $src['enabled'] ),
				'weight'  => isset( $src['weight'] ) ? max( 0, min( 20, absint( $src['weight'] ) ) ) : $def['weight'],
			);
			// Sanitize each check-specific threshold.
			foreach ( $def as $param => $default_val ) {
				if ( in_array( $param, array( 'enabled', 'weight' ), true ) ) {
					continue;
				}
				$sanitized['checks'][ $key ][ $param ] = isset( $src[ $param ] )
					? max( 0, absint( $src[ $param ] ) )
					: $default_val;
			}
		}

		return $sanitized;
	}

	/* ── Custom Bots Sanitization ────────────────────────────── */

	public function sanitize_custom_bots( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $input as $bot ) {
			if ( ! is_array( $bot ) ) {
				continue;
			}
			$identifier = isset( $bot['identifier'] ) ? sanitize_text_field( trim( $bot['identifier'] ) ) : '';
			if ( empty( $identifier ) ) {
				continue;
			}
			$sanitized[] = array(
				'identifier' => $identifier,
				'name'       => isset( $bot['name'] ) ? sanitize_text_field( trim( $bot['name'] ) ) : $identifier,
				'service'    => isset( $bot['service'] ) ? sanitize_text_field( trim( $bot['service'] ) ) : '',
			);
		}

		return $sanitized;
	}

	/* ── AJAX Handlers ───────────────────────────────────────── */

	public function ajax_validate_llms_txt() {
		check_ajax_referer( 'ai_seo_pilot_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'ai-seo-pilot' ) );
		}

		$plugin   = AI_SEO_Pilot::get_instance();
		$result   = $plugin->llms_txt->validate_accessibility();

		wp_send_json_success( $result );
	}

	public function ajax_regenerate_llms_txt() {
		check_ajax_referer( 'ai_seo_pilot_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'ai-seo-pilot' ) );
		}

		$plugin = AI_SEO_Pilot::get_instance();

		// Force auto-generation from template (ignores manual mode).
		$content = $plugin->llms_txt->regenerate_auto();

		wp_send_json_success( array( 'content' => $content ) );
	}

	/* ── AI AJAX Handlers ────────────────────────────────────── */

	public function ajax_ai_generate_llms_txt() {
		check_ajax_referer( 'ai_seo_pilot_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'ai-seo-pilot' ) );
		}

		$plugin = AI_SEO_Pilot::get_instance();
		$result = $plugin->ai_engine->generate_llms_txt();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		// Save as manual content and switch to manual mode.
		update_option( 'ai_seo_pilot_llms_txt_mode', 'manual' );
		update_option( 'ai_seo_pilot_llms_txt_manual', $result );
		delete_transient( 'ai_seo_pilot_llms_txt_cache' );

		wp_send_json_success( array( 'content' => $result ) );
	}

	public function ajax_ai_generate_meta() {
		check_ajax_referer( 'ai_seo_pilot_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Permission denied.', 'ai-seo-pilot' ) );
		}

		$plugin = AI_SEO_Pilot::get_instance();
		$result = $plugin->ai_engine->generate_meta_description( $post_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		// Save to post meta.
		update_post_meta( $post_id, '_ai_seo_pilot_meta_description', sanitize_text_field( $result ) );

		wp_send_json_success( array( 'description' => $result ) );
	}

	public function ajax_ai_generate_suggestions() {
		check_ajax_referer( 'ai_seo_pilot_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Permission denied.', 'ai-seo-pilot' ) );
		}

		$plugin = AI_SEO_Pilot::get_instance();
		$result = $plugin->ai_engine->generate_seo_suggestions( $post_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		// Try to parse JSON from the response.
		$suggestions = json_decode( $result, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// Try extracting JSON from markdown code blocks.
			if ( preg_match( '/```(?:json)?\s*([\s\S]*?)```/', $result, $m ) ) {
				$suggestions = json_decode( trim( $m[1] ), true );
			}
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				wp_send_json_success( array( 'suggestions' => array(), 'raw' => $result ) );
				return;
			}
		}

		wp_send_json_success( array( 'suggestions' => $suggestions ) );
	}

	public function ajax_ai_test_connection() {
		check_ajax_referer( 'ai_seo_pilot_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'ai-seo-pilot' ) );
		}

		$provider   = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';
		$credential = isset( $_POST['credential'] ) ? sanitize_text_field( $_POST['credential'] ) : '';
		$model      = isset( $_POST['model'] ) ? sanitize_text_field( $_POST['model'] ) : '';

		if ( empty( $provider ) || empty( $credential ) ) {
			wp_send_json_error( __( 'Provider and credentials are required.', 'ai-seo-pilot' ) );
		}

		$plugin = AI_SEO_Pilot::get_instance();
		$result = $plugin->ai_engine->test_connection( $provider, $credential, $model );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( array(
			'message'  => __( 'Connection successful!', 'ai-seo-pilot' ),
			'response' => $result,
		) );
	}

	/* ── SEO Check Fix Handlers ─────────────────────────────── */

	public function ajax_seo_fix() {
		check_ajax_referer( 'ai_seo_pilot_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'ai-seo-pilot' ) );
		}

		$option = isset( $_POST['option'] ) ? sanitize_text_field( $_POST['option'] ) : '';
		$value  = isset( $_POST['value'] ) ? sanitize_text_field( $_POST['value'] ) : '';

		$allowed = array(
			'blog_public'                        => '1',
			'ai_seo_pilot_schema_enabled'        => 'yes',
			'ai_seo_pilot_sitemap_ai_enabled'    => 'yes',
			'ai_seo_pilot_ai_visibility_enabled' => 'yes',
			'ai_seo_pilot_robots_txt_enhance'    => 'yes',
		);

		if ( ! isset( $allowed[ $option ] ) || $allowed[ $option ] !== $value ) {
			wp_send_json_error( __( 'Invalid option.', 'ai-seo-pilot' ) );
		}

		update_option( $option, $value );

		wp_send_json_success();
	}

	public function ajax_bulk_generate_meta() {
		check_ajax_referer( 'ai_seo_pilot_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'ai-seo-pilot' ) );
		}

		$plugin = AI_SEO_Pilot::get_instance();

		if ( ! $plugin->ai_engine->is_configured() ) {
			wp_send_json_error( __( 'AI API is not configured.', 'ai-seo-pilot' ) );
		}

		global $wpdb;

		$post_ids = $wpdb->get_col(
			"SELECT p.ID
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm
				ON p.ID = pm.post_id
				AND pm.meta_key = '_ai_seo_pilot_meta_description'
				AND pm.meta_value != ''
			WHERE p.post_type IN ('post','page')
			AND p.post_status = 'publish'
			AND pm.meta_id IS NULL
			LIMIT 50"
		);

		$count = 0;
		foreach ( $post_ids as $post_id ) {
			$result = $plugin->ai_engine->generate_meta_description( (int) $post_id );
			if ( ! is_wp_error( $result ) && ! empty( $result ) ) {
				update_post_meta( (int) $post_id, '_ai_seo_pilot_meta_description', sanitize_text_field( $result ) );
				$count++;
			}
		}

		wp_send_json_success( array( 'count' => $count ) );
	}

	public function ajax_generate_tagline() {
		check_ajax_referer( 'ai_seo_pilot_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'ai-seo-pilot' ) );
		}

		$plugin = AI_SEO_Pilot::get_instance();

		if ( ! $plugin->ai_engine->is_configured() ) {
			wp_send_json_error( __( 'AI API is not configured.', 'ai-seo-pilot' ) );
		}

		$tagline = $plugin->ai_engine->generate_tagline();

		if ( is_wp_error( $tagline ) ) {
			wp_send_json_error( $tagline->get_error_message() );
		}

		$tagline = sanitize_text_field( trim( $tagline, '"\'  ' ) );
		update_option( 'blogdescription', $tagline );

		wp_send_json_success( array( 'tagline' => $tagline ) );
	}
}
