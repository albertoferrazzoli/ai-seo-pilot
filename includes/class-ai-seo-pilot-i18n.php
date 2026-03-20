<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_SEO_Pilot_I18n {

	public function load() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'ai-seo-pilot',
			false,
			dirname( AI_SEO_PILOT_BASENAME ) . '/languages'
		);
	}
}
