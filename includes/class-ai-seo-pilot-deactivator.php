<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_SEO_Pilot_Deactivator {

	public static function deactivate() {
		// Clear scheduled cron events.
		$timestamp = wp_next_scheduled( 'ai_seo_pilot_cleanup_bot_visits' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ai_seo_pilot_cleanup_bot_visits' );
		}

		// Delete transients.
		delete_transient( 'ai_seo_pilot_llms_txt_cache' );
		delete_transient( 'ai_seo_pilot_sitemap_ai_cache' );
		delete_transient( 'ai_seo_pilot_flush_rewrite' );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}
