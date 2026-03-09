<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_SEO_Pilot_Public {

	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_meta_description' ), 2 );
		add_filter( 'the_content', array( $this, 'append_readiness_enhancement' ), 99 );
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

	/**
	 * Append AI-readiness enhancement section to singular post/page content.
	 * Stored in post meta to avoid corrupting page-builder (Divi) content.
	 *
	 * @param string $content The post content.
	 * @return string
	 */
	public function append_readiness_enhancement( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$enhancement = get_post_meta( get_the_ID(), '_aisp_readiness_enhancement', true );
		if ( empty( $enhancement ) ) {
			return $content;
		}

		return $content . "\n" . '<div class="aisp-readiness-enhancement">' . wp_kses_post( $enhancement ) . '</div>';
	}
}
