<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_SEO_Pilot_Activator {

	public static function activate() {
		// Create / update database tables.
		AI_SEO_Pilot_Migrator::run();

		// Set default options.
		self::set_defaults();

		// Schedule a rewrite-rules flush on next load.
		set_transient( 'ai_seo_pilot_flush_rewrite', 1, 60 );
	}

	private static function set_defaults() {
		$defaults = array(
			'ai_seo_pilot_llms_txt_mode'           => 'auto',
			'ai_seo_pilot_llms_txt_manual'         => '',
			'ai_seo_pilot_schema_enabled'          => 'yes',
			'ai_seo_pilot_schema_organization'     => 'yes',
			'ai_seo_pilot_schema_breadcrumbs'      => 'yes',
			'ai_seo_pilot_schema_website'          => 'yes',
			'ai_seo_pilot_analyzer_enabled'        => 'yes',
			'ai_seo_pilot_ai_visibility_enabled'   => 'yes',
			'ai_seo_pilot_bot_retention_days'      => 90,
			'ai_seo_pilot_sitemap_ai_enabled'      => 'yes',
			'ai_seo_pilot_robots_txt_enhance'      => 'yes',
			'ai_seo_pilot_x_robots_tag'            => 'yes',
			'ai_seo_pilot_ai_provider'              => 'openai',
			'ai_seo_pilot_ai_openai_api_key'       => '',
			'ai_seo_pilot_ai_openai_model'         => '',
			'ai_seo_pilot_ai_anthropic_api_key'    => '',
			'ai_seo_pilot_ai_anthropic_model'      => '',
			'ai_seo_pilot_ai_gemini_api_key'       => '',
			'ai_seo_pilot_ai_gemini_model'         => '',
			'ai_seo_pilot_ai_grok_api_key'         => '',
			'ai_seo_pilot_ai_grok_model'           => '',
			'ai_seo_pilot_ai_ollama_server_url'    => 'http://localhost:11434',
			'ai_seo_pilot_ai_ollama_model'         => '',
			'ai_seo_pilot_ai_deepseek_api_key'     => '',
			'ai_seo_pilot_ai_deepseek_model'       => '',
			'ai_seo_pilot_remove_data_on_uninstall' => 'no',
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}
}
