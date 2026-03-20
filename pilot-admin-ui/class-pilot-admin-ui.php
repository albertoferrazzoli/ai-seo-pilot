<?php
/**
 * Pilot Admin UI - Shared design system for Pilot plugins.
 *
 * This file acts as a version-aware loader. Each plugin that bundles
 * pilot-admin-ui/ calls require_once on this file. A global candidate
 * registry ensures only the newest version is loaded, regardless of
 * plugin activation order.
 *
 * @version 1.1.0
 * @package PilotAdminUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Register this copy as a candidate.
global $pilot_admin_ui_candidates;

if ( ! isset( $pilot_admin_ui_candidates ) ) {
	$pilot_admin_ui_candidates = [];
}

$pilot_admin_ui_candidates[] = [
	'version' => '1.1.0',
	'file'    => __DIR__ . '/class-pilot-admin-ui-core.php',
	'url'     => plugin_dir_url( __FILE__ ),
];

// Hook the loader only once (first plugin to register wins the hook).
if ( ! function_exists( '_pilot_admin_ui_load_winner' ) ) {

	/**
	 * Sort candidates by version descending and load the newest one.
	 *
	 * @internal
	 */
	function _pilot_admin_ui_load_winner() {
		global $pilot_admin_ui_candidates;

		if ( empty( $pilot_admin_ui_candidates ) ) {
			return;
		}

		usort( $pilot_admin_ui_candidates, function ( $a, $b ) {
			return version_compare( $b['version'], $a['version'] );
		} );

		$winner = $pilot_admin_ui_candidates[0];

		if ( ! defined( 'PILOT_ADMIN_UI_VERSION' ) ) {
			define( 'PILOT_ADMIN_UI_VERSION', $winner['version'] );
		}
		if ( ! defined( 'PILOT_ADMIN_UI_URL' ) ) {
			define( 'PILOT_ADMIN_UI_URL', $winner['url'] );
		}

		require_once $winner['file'];
	}

	add_action( 'plugins_loaded', '_pilot_admin_ui_load_winner', 0 );
}
