<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package AI_SEO_Pilot
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if user opted to remove data on uninstall.
if ( get_option( 'ai_seo_pilot_remove_data_on_uninstall' ) !== 'yes' ) {
	return;
}

// 1. Delete all plugin options.
$options = array(
	'ai_seo_pilot_llms_txt_mode',
	'ai_seo_pilot_llms_txt_manual',
	'ai_seo_pilot_schema_enabled',
	'ai_seo_pilot_schema_organization',
	'ai_seo_pilot_schema_breadcrumbs',
	'ai_seo_pilot_schema_website',
	'ai_seo_pilot_organization_same_as',
	'ai_seo_pilot_analyzer_enabled',
	'ai_seo_pilot_ai_visibility_enabled',
	'ai_seo_pilot_bot_retention_days',
	'ai_seo_pilot_sitemap_ai_enabled',
	'ai_seo_pilot_robots_txt_enhance',
	'ai_seo_pilot_x_robots_tag',
	'ai_seo_pilot_ai_provider',
	'ai_seo_pilot_ai_openai_api_key',
	'ai_seo_pilot_ai_openai_model',
	'ai_seo_pilot_ai_anthropic_api_key',
	'ai_seo_pilot_ai_anthropic_model',
	'ai_seo_pilot_ai_gemini_api_key',
	'ai_seo_pilot_ai_gemini_model',
	'ai_seo_pilot_ai_grok_api_key',
	'ai_seo_pilot_ai_grok_model',
	'ai_seo_pilot_ai_ollama_server_url',
	'ai_seo_pilot_ai_ollama_model',
	'ai_seo_pilot_ai_deepseek_api_key',
	'ai_seo_pilot_ai_deepseek_model',
	'ai_seo_pilot_remove_data_on_uninstall',
	'ai_seo_pilot_db_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// 2. Drop the bot visits table.
global $wpdb;
$table_name = $wpdb->prefix . 'ai_seo_pilot_bot_visits';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// 3. Delete all post meta with the plugin's schema type key.
delete_post_meta_by_key( '_ai_seo_pilot_schema_type' );
delete_post_meta_by_key( '_ai_seo_pilot_meta_description' );

// 4. Delete transients.
delete_transient( 'ai_seo_pilot_llms_txt_cache' );
delete_transient( 'ai_seo_pilot_sitemap_ai_cache' );

// 5. Clear scheduled cron events.
wp_clear_scheduled_hook( 'ai_seo_pilot_cleanup_bot_visits' );
