<?php
/**
 * Plugin Name: AI SEO Pilot
 * Plugin URI:  https://slotix.ai/ai-seo-pilot
 * Description: Optimize your WordPress site for AI search engines (ChatGPT, Perplexity, Claude, Gemini) through GEO/AEO best practices — llms.txt, Schema.org JSON-LD, content analysis, AI bot tracking, and AI-optimized sitemaps.
 * Version:     1.0.0
 * Author:      Slotix
 * Author URI:  https://slotix.ai
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-seo-pilot
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ── Constants ────────────────────────────────────────────────── */
define( 'AI_SEO_PILOT_VERSION', '1.0.0' );
define( 'AI_SEO_PILOT_FILE', __FILE__ );
define( 'AI_SEO_PILOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'AI_SEO_PILOT_URL', plugin_dir_url( __FILE__ ) );
define( 'AI_SEO_PILOT_BASENAME', plugin_basename( __FILE__ ) );

/* ── SPL Autoloader ───────────────────────────────────────────── */
spl_autoload_register( function ( $class ) {
	// Only handle our prefix.
	$prefix = 'AI_SEO_Pilot';
	if ( strpos( $class, $prefix ) !== 0 ) {
		return;
	}

	// AI_SEO_Pilot → class-ai-seo-pilot.php
	// AI_SEO_Pilot_LLMs_Txt → class-ai-seo-pilot-llms-txt.php
	$file_name = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';

	$paths = array(
		AI_SEO_PILOT_PATH . 'includes/' . $file_name,
		AI_SEO_PILOT_PATH . 'admin/' . $file_name,
		AI_SEO_PILOT_PATH . 'public/' . $file_name,
		AI_SEO_PILOT_PATH . 'modules/' . $file_name,
	);

	foreach ( $paths as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			return;
		}
	}
} );

/* ── Activation / Deactivation ────────────────────────────────── */
register_activation_hook( __FILE__, array( 'AI_SEO_Pilot_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AI_SEO_Pilot_Deactivator', 'deactivate' ) );

/* ── Pilot Updater ────────────────────────────────────────────── */
require_once __DIR__ . '/pilot-updater/class-pilot-updater.php';
new Pilot_Updater( 'ai-seo-pilot', __FILE__ );

/* ── Shared Admin UI Design System ───────────────────────────── */
require_once __DIR__ . '/pilot-admin-ui/class-pilot-admin-ui.php';

/* ── Bootstrap ────────────────────────────────────────────────── */
add_action( 'plugins_loaded', function () {
	AI_SEO_Pilot::get_instance()->init();
} );
