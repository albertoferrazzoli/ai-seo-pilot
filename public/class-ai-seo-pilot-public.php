<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_SEO_Pilot_Public {

	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_meta_description' ), 2 );
	}

	/**
	 * Output AI-generated meta description in wp_head for singular pages.
	 */
	public function output_meta_description() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();
		$desc    = get_post_meta( $post_id, '_ai_seo_pilot_meta_description', true );

		if ( empty( $desc ) ) {
			return;
		}

		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
}
