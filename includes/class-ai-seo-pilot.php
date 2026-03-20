<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_SEO_Pilot {

	private static $instance = null;

	public $llms_txt;
	public $schema_manager;
	public $content_analyzer;
	public $ai_visibility;
	public $sitemap_ai;
	public $ai_engine;
	public $readability;
	public $content_quality;
	public $keyword_tracker;
	public $internal_linking;
	public $content_optimizer;
	public $admin;
	public $public;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function init() {
		// Run database migrations.
		AI_SEO_Pilot_Migrator::run();

		// Load text domain.
		$i18n = new AI_SEO_Pilot_I18n();
		$i18n->load();

		// Instantiate modules.
		$this->llms_txt         = new AI_SEO_Pilot_LLMs_Txt();
		$this->schema_manager   = new AI_SEO_Pilot_Schema_Manager();
		$this->content_analyzer = new AI_SEO_Pilot_Content_Analyzer();
		$this->ai_visibility    = new AI_SEO_Pilot_AI_Visibility();
		$this->sitemap_ai       = new AI_SEO_Pilot_Sitemap_AI();
		$this->ai_engine        = new AI_SEO_Pilot_AI_Engine();

		// Content Optimization AI modules.
		$this->readability       = new AI_SEO_Pilot_Readability();
		$this->content_quality   = new AI_SEO_Pilot_Content_Quality();
		$this->keyword_tracker   = new AI_SEO_Pilot_Keyword_Tracker();
		$this->internal_linking  = new AI_SEO_Pilot_Internal_Linking();
		$this->content_optimizer = new AI_SEO_Pilot_Content_Optimizer();

		// Admin.
		if ( is_admin() ) {
			$this->admin = new AI_SEO_Pilot_Admin();
		}

		// Public.
		if ( ! is_admin() || wp_doing_ajax() ) {
			$this->public = new AI_SEO_Pilot_Public();
		}
	}
}
