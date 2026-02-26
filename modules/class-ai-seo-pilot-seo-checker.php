<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEO Checker — runs a comprehensive list of SEO health checks.
 */
class AI_SEO_Pilot_SEO_Checker {

	/**
	 * Run all checks and return results.
	 *
	 * @return array {
	 *     @type array  $checks   Individual check results.
	 *     @type int    $passed   Number of passed checks.
	 *     @type int    $warnings Number of warnings.
	 *     @type int    $failed   Number of failed checks.
	 *     @type int    $total    Total checks run.
	 *     @type int    $score    Overall percentage (0-100).
	 * }
	 */
	public function run_all() {
		$checks = array(
			$this->check_ssl(),
			$this->check_site_title(),
			$this->check_tagline(),
			$this->check_search_visibility(),
			$this->check_permalinks(),
			$this->check_llms_txt(),
			$this->check_schema_enabled(),
			$this->check_ai_sitemap(),
			$this->check_ai_bot_tracking(),
			$this->check_robots_txt_enhanced(),
			$this->check_ai_api_configured(),
			$this->check_meta_descriptions(),
			$this->check_content_readiness(),
			$this->check_image_alt_tags(),
			$this->check_heading_structure(),
		);

		$passed   = 0;
		$warnings = 0;
		$failed   = 0;

		foreach ( $checks as $c ) {
			if ( 'pass' === $c['status'] ) {
				$passed++;
			} elseif ( 'warning' === $c['status'] ) {
				$warnings++;
			} else {
				$failed++;
			}
		}

		$total = count( $checks );
		$score = $total > 0 ? round( ( $passed + $warnings * 0.5 ) / $total * 100 ) : 0;

		return array(
			'checks'   => $checks,
			'passed'   => $passed,
			'warnings' => $warnings,
			'failed'   => $failed,
			'total'    => $total,
			'score'    => $score,
		);
	}

	/* ── Technical SEO ──────────────────────────────────────────── */

	private function check_ssl() {
		$is_ssl = is_ssl() || ( strpos( home_url(), 'https://' ) === 0 );
		return array(
			'category'   => __( 'Technical', 'ai-seo-pilot' ),
			'label'      => __( 'HTTPS / SSL', 'ai-seo-pilot' ),
			'severity'   => 'critical',
			'status'     => $is_ssl ? 'pass' : 'fail',
			'message'    => $is_ssl
				? __( 'Your site uses HTTPS. AI crawlers trust secure sites.', 'ai-seo-pilot' )
				: __( 'Your site is not using HTTPS. AI crawlers may deprioritize insecure content.', 'ai-seo-pilot' ),
			'fix'        => $is_ssl ? '' : __( 'Configure an SSL certificate and force HTTPS in wp-config.php.', 'ai-seo-pilot' ),
			'fix_action' => null,
		);
	}

	private function check_site_title() {
		$title = get_bloginfo( 'name' );
		$ok    = ! empty( $title ) && 'My WordPress Site' !== $title;
		return array(
			'category'   => __( 'Technical', 'ai-seo-pilot' ),
			'label'      => __( 'Site Title', 'ai-seo-pilot' ),
			'severity'   => 'high',
			'status'     => $ok ? 'pass' : 'fail',
			'message'    => $ok
				? sprintf( __( 'Site title is set: "%s"', 'ai-seo-pilot' ), $title )
				: __( 'Set a meaningful site title. AI engines use it to identify your site.', 'ai-seo-pilot' ),
			'fix'        => $ok ? '' : __( 'Go to Settings > General and set a descriptive site title.', 'ai-seo-pilot' ),
			'fix_action' => ! $ok ? array(
				'type'  => 'link',
				'url'   => admin_url( 'options-general.php' ),
				'label' => __( 'Go to Settings', 'ai-seo-pilot' ),
			) : null,
		);
	}

	private function check_tagline() {
		$tagline  = get_bloginfo( 'description' );
		$defaults = array( '', 'Just another WordPress site' );
		$ok       = ! in_array( $tagline, $defaults, true );
		return array(
			'category'   => __( 'Technical', 'ai-seo-pilot' ),
			'label'      => __( 'Site Tagline', 'ai-seo-pilot' ),
			'severity'   => 'medium',
			'status'     => $ok ? 'pass' : 'warning',
			'message'    => $ok
				? sprintf( __( 'Tagline is set: "%s"', 'ai-seo-pilot' ), $tagline )
				: __( 'Default or empty tagline. It appears in llms.txt and Schema.org output.', 'ai-seo-pilot' ),
			'fix'        => $ok ? '' : __( 'Go to Settings > General and write a custom tagline describing your site.', 'ai-seo-pilot' ),
			'fix_action' => ! $ok ? array(
				'type'   => 'ajax',
				'action' => 'ai_seo_pilot_generate_tagline',
				'label'  => __( 'Generate with AI', 'ai-seo-pilot' ),
			) : null,
		);
	}

	private function check_search_visibility() {
		$visible = '1' !== get_option( 'blog_public', '1' ) ? false : true;
		return array(
			'category'   => __( 'Technical', 'ai-seo-pilot' ),
			'label'      => __( 'Search Engine Visibility', 'ai-seo-pilot' ),
			'severity'   => 'critical',
			'status'     => $visible ? 'pass' : 'fail',
			'message'    => $visible
				? __( 'Search engines are allowed to index your site.', 'ai-seo-pilot' )
				: __( '"Discourage search engines" is ON. This blocks all bots including AI crawlers.', 'ai-seo-pilot' ),
			'fix'        => $visible ? '' : __( 'Go to Settings > Reading and uncheck "Discourage search engines from indexing this site".', 'ai-seo-pilot' ),
			'fix_action' => ! $visible ? array(
				'type'   => 'option_toggle',
				'option' => 'blog_public',
				'value'  => '1',
				'label'  => __( 'Enable Indexing', 'ai-seo-pilot' ),
			) : null,
		);
	}

	private function check_permalinks() {
		$structure = get_option( 'permalink_structure', '' );
		$ok        = ! empty( $structure );
		return array(
			'category'   => __( 'Technical', 'ai-seo-pilot' ),
			'label'      => __( 'Permalink Structure', 'ai-seo-pilot' ),
			'severity'   => 'high',
			'status'     => $ok ? 'pass' : 'fail',
			'message'    => $ok
				? sprintf( __( 'Pretty permalinks enabled: %s', 'ai-seo-pilot' ), '<code>' . esc_html( $structure ) . '</code>' )
				: __( 'Plain permalinks in use. Readable URLs help AI bots understand page content.', 'ai-seo-pilot' ),
			'fix'        => $ok ? '' : __( 'Go to Settings > Permalinks and select "Post name" or another pretty permalink structure.', 'ai-seo-pilot' ),
			'fix_action' => ! $ok ? array(
				'type'  => 'link',
				'url'   => admin_url( 'options-permalink.php' ),
				'label' => __( 'Go to Permalinks', 'ai-seo-pilot' ),
			) : null,
		);
	}

	/* ── AI SEO (Plugin Features) ───────────────────────────────── */

	private function check_llms_txt() {
		$plugin     = AI_SEO_Pilot::get_instance();
		$validation = $plugin->llms_txt->validate_accessibility();
		$accessible = ! empty( $validation['accessible'] );

		if ( $accessible ) {
			$status  = 'pass';
			$message = sprintf(
				__( 'llms.txt is accessible at %s (%d bytes). AI crawlers can discover your content.', 'ai-seo-pilot' ),
				home_url( '/llms.txt' ),
				$validation['content_length']
			);
		} else {
			$mode   = get_option( 'ai_seo_pilot_llms_txt_mode', 'auto' );
			$manual = get_option( 'ai_seo_pilot_llms_txt_manual', '' );

			if ( 'manual' === $mode && empty( $manual ) ) {
				$status  = 'fail';
				$message = __( 'llms.txt is in manual mode but empty. The file is not accessible.', 'ai-seo-pilot' );
			} else {
				$status  = 'fail';
				$message = sprintf(
					__( 'llms.txt is not accessible (HTTP %s). AI crawlers cannot discover your content.', 'ai-seo-pilot' ),
					$validation['status_code'] ?: 'error'
				);
			}
		}

		return array(
			'category'   => __( 'AI SEO', 'ai-seo-pilot' ),
			'label'      => __( 'llms.txt', 'ai-seo-pilot' ),
			'severity'   => 'high',
			'status'     => $status,
			'message'    => $message,
			'fix'        => 'pass' !== $status ? __( 'Go to the LLMS page and generate content, or switch to auto mode.', 'ai-seo-pilot' ) : '',
			'fix_action' => 'pass' !== $status ? array(
				'type'  => 'link',
				'url'   => admin_url( 'admin.php?page=ai-seo-pilot-llms-txt' ),
				'label' => __( 'Go to LLMS', 'ai-seo-pilot' ),
			) : null,
		);
	}

	private function check_schema_enabled() {
		$ok = 'yes' === get_option( 'ai_seo_pilot_schema_enabled', 'yes' );
		return array(
			'category'   => __( 'AI SEO', 'ai-seo-pilot' ),
			'label'      => __( 'Schema.org JSON-LD', 'ai-seo-pilot' ),
			'severity'   => 'critical',
			'status'     => $ok ? 'pass' : 'fail',
			'message'    => $ok
				? __( 'Schema.org JSON-LD output is enabled. AI engines use structured data to understand content.', 'ai-seo-pilot' )
				: __( 'Schema.org is disabled. AI engines rely on structured data to understand your content.', 'ai-seo-pilot' ),
			'fix'        => $ok ? '' : __( 'Enable Schema.org in Settings > General.', 'ai-seo-pilot' ),
			'fix_action' => ! $ok ? array(
				'type'   => 'option_toggle',
				'option' => 'ai_seo_pilot_schema_enabled',
				'value'  => 'yes',
				'label'  => __( 'Enable', 'ai-seo-pilot' ),
			) : null,
		);
	}

	private function check_ai_sitemap() {
		$ok = 'yes' === get_option( 'ai_seo_pilot_sitemap_ai_enabled', 'yes' );
		return array(
			'category'   => __( 'AI SEO', 'ai-seo-pilot' ),
			'label'      => __( 'AI Sitemap', 'ai-seo-pilot' ),
			'severity'   => 'medium',
			'status'     => $ok ? 'pass' : 'warning',
			'message'    => $ok
				? __( 'AI sitemap is active at /ai-sitemap.xml with enriched metadata.', 'ai-seo-pilot' )
				: __( 'AI sitemap is disabled.', 'ai-seo-pilot' ),
			'fix'        => $ok ? '' : __( 'Enable the AI sitemap in Settings > General to give AI crawlers enriched content metadata.', 'ai-seo-pilot' ),
			'fix_action' => ! $ok ? array(
				'type'   => 'option_toggle',
				'option' => 'ai_seo_pilot_sitemap_ai_enabled',
				'value'  => 'yes',
				'label'  => __( 'Enable', 'ai-seo-pilot' ),
			) : null,
		);
	}

	private function check_ai_bot_tracking() {
		$ok = 'yes' === get_option( 'ai_seo_pilot_ai_visibility_enabled', 'yes' );
		return array(
			'category'   => __( 'AI SEO', 'ai-seo-pilot' ),
			'label'      => __( 'AI Bot Tracking', 'ai-seo-pilot' ),
			'severity'   => 'info',
			'status'     => $ok ? 'pass' : 'warning',
			'message'    => $ok
				? __( 'AI bot tracking is enabled. You can monitor which AI crawlers visit your site.', 'ai-seo-pilot' )
				: __( 'Bot tracking is disabled.', 'ai-seo-pilot' ),
			'fix'        => $ok ? '' : __( 'Enable bot tracking in Settings > General to understand how AI engines interact with your content.', 'ai-seo-pilot' ),
			'fix_action' => ! $ok ? array(
				'type'   => 'option_toggle',
				'option' => 'ai_seo_pilot_ai_visibility_enabled',
				'value'  => 'yes',
				'label'  => __( 'Enable', 'ai-seo-pilot' ),
			) : null,
		);
	}

	private function check_robots_txt_enhanced() {
		$ok = 'yes' === get_option( 'ai_seo_pilot_robots_txt_enhance', 'yes' );
		return array(
			'category'   => __( 'AI SEO', 'ai-seo-pilot' ),
			'label'      => __( 'robots.txt AI Enhancement', 'ai-seo-pilot' ),
			'severity'   => 'medium',
			'status'     => $ok ? 'pass' : 'warning',
			'message'    => $ok
				? __( 'robots.txt includes explicit Allow directives for AI bots and AI sitemap reference.', 'ai-seo-pilot' )
				: __( 'AI bot directives not added to robots.txt.', 'ai-seo-pilot' ),
			'fix'        => $ok ? '' : __( 'Enable in Settings > Advanced to add Allow directives for AI bots.', 'ai-seo-pilot' ),
			'fix_action' => ! $ok ? array(
				'type'   => 'option_toggle',
				'option' => 'ai_seo_pilot_robots_txt_enhance',
				'value'  => 'yes',
				'label'  => __( 'Enable', 'ai-seo-pilot' ),
			) : null,
		);
	}

	private function check_ai_api_configured() {
		$plugin     = AI_SEO_Pilot::get_instance();
		$configured = $plugin->ai_engine->is_configured();
		return array(
			'category'   => __( 'AI SEO', 'ai-seo-pilot' ),
			'label'      => __( 'AI API Connection', 'ai-seo-pilot' ),
			'severity'   => 'info',
			'status'     => $configured ? 'pass' : 'warning',
			'message'    => $configured
				? __( 'AI API is configured. AI-powered content generation is available.', 'ai-seo-pilot' )
				: __( 'No AI API configured. Optional but enables AI-powered features.', 'ai-seo-pilot' ),
			'fix'        => $configured ? '' : __( 'Go to Settings > AI Providers and configure an AI provider for content generation.', 'ai-seo-pilot' ),
			'fix_action' => ! $configured ? array(
				'type'  => 'link',
				'url'   => admin_url( 'admin.php?page=ai-seo-pilot-settings#ai-providers' ),
				'label' => __( 'Configure', 'ai-seo-pilot' ),
			) : null,
		);
	}

	/* ── Content Quality ────────────────────────────────────────── */

	private function check_meta_descriptions() {
		global $wpdb;

		$total_posts = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('post','page') AND post_status = 'publish'"
		);

		$with_meta = 0;
		if ( $total_posts > 0 ) {
			$with_meta = (int) $wpdb->get_var(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type IN ('post','page')
				AND p.post_status = 'publish'
				AND pm.meta_key = '_ai_seo_pilot_meta_description'
				AND pm.meta_value != ''"
			);
		}

		$pct = $total_posts > 0 ? round( $with_meta / $total_posts * 100 ) : 0;

		if ( $pct >= 80 ) {
			$status = 'pass';
		} elseif ( $pct >= 30 ) {
			$status = 'warning';
		} else {
			$status = 'fail';
		}

		return array(
			'category'   => __( 'Content', 'ai-seo-pilot' ),
			'label'      => __( 'AI Meta Descriptions', 'ai-seo-pilot' ),
			'severity'   => 'high',
			'status'     => $status,
			'message'    => sprintf(
				/* translators: %1$d: count with meta, %2$d: total, %3$d: percentage */
				__( '%1$d / %2$d published posts have AI meta descriptions (%3$d%%).', 'ai-seo-pilot' ),
				$with_meta,
				$total_posts,
				$pct
			),
			'fix'        => 'pass' !== $status ? __( 'Meta descriptions are auto-generated when publishing if AI is configured. Re-save existing posts or use "Generate with AI" in the post editor.', 'ai-seo-pilot' ) : '',
			'fix_action' => 'pass' !== $status ? array(
				'type'   => 'ajax',
				'action' => 'ai_seo_pilot_bulk_generate_meta',
				'label'  => __( 'Generate All', 'ai-seo-pilot' ),
			) : null,
		);
	}

	private function check_content_readiness() {
		$plugin       = AI_SEO_Pilot::get_instance();
		$recent_posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
		) );

		if ( empty( $recent_posts ) ) {
			return array(
				'category'   => __( 'Content', 'ai-seo-pilot' ),
				'label'      => __( 'Content AI-Readiness', 'ai-seo-pilot' ),
				'severity'   => 'medium',
				'status'     => 'warning',
				'message'    => __( 'No published posts to analyze.', 'ai-seo-pilot' ),
				'fix'        => __( 'Publish some posts so AI-readiness can be evaluated.', 'ai-seo-pilot' ),
				'fix_action' => null,
			);
		}

		$ca_settings = AI_SEO_Pilot_Content_Analyzer::get_defaults();
		$saved       = get_option( 'ai_seo_pilot_content_analysis', array() );
		if ( ! empty( $saved['ai_ready_threshold'] ) ) {
			$ca_settings['ai_ready_threshold'] = (int) $saved['ai_ready_threshold'];
		}
		$threshold = $ca_settings['ai_ready_threshold'];

		$total_pct   = 0;
		$ready_count = 0;

		foreach ( $recent_posts as $p ) {
			$result     = $plugin->content_analyzer->analyze( $p->post_content, $p->post_title );
			$total_pct += $result['percentage'];
			if ( $result['ai_ready'] ) {
				$ready_count++;
			}
		}

		$avg    = round( $total_pct / count( $recent_posts ) );
		$status = $avg >= $threshold ? 'pass' : ( $avg >= 50 ? 'warning' : 'fail' );

		return array(
			'category'   => __( 'Content', 'ai-seo-pilot' ),
			'label'      => __( 'Content AI-Readiness', 'ai-seo-pilot' ),
			'severity'   => 'high',
			'status'     => $status,
			'message'    => sprintf(
				/* translators: %1$d: avg percentage, %2$d: ready count, %3$d: total, %4$d: threshold */
				__( 'Average AI score: %1$d%%. %2$d of %3$d recent posts are AI-ready (%4$d%%+).', 'ai-seo-pilot' ),
				$avg,
				$ready_count,
				count( $recent_posts ),
				$threshold
			),
			'fix'        => 'pass' !== $status ? __( 'Use the Gutenberg sidebar analyzer to improve individual posts.', 'ai-seo-pilot' ) : '',
			'fix_action' => null,
		);
	}

	private function check_image_alt_tags() {
		$recent_posts = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => 20,
		) );

		$total_images  = 0;
		$images_no_alt = 0;

		foreach ( $recent_posts as $p ) {
			if ( preg_match_all( '/<img[^>]*>/i', $p->post_content, $matches ) ) {
				foreach ( $matches[0] as $img ) {
					$total_images++;
					if ( ! preg_match( '/alt\s*=\s*"[^"]+"/i', $img ) && ! preg_match( "/alt\s*=\s*'[^']+'/i", $img ) ) {
						$images_no_alt++;
					}
				}
			}
		}

		if ( 0 === $total_images ) {
			return array(
				'category'   => __( 'Content', 'ai-seo-pilot' ),
				'label'      => __( 'Image Alt Tags', 'ai-seo-pilot' ),
				'severity'   => 'medium',
				'status'     => 'pass',
				'message'    => __( 'No images found in recent content (or images are handled externally).', 'ai-seo-pilot' ),
				'fix'        => '',
				'fix_action' => null,
			);
		}

		$with_alt = $total_images - $images_no_alt;
		$pct      = round( $with_alt / $total_images * 100 );
		$status   = $pct >= 90 ? 'pass' : ( $pct >= 50 ? 'warning' : 'fail' );

		return array(
			'category'   => __( 'Content', 'ai-seo-pilot' ),
			'label'      => __( 'Image Alt Tags', 'ai-seo-pilot' ),
			'severity'   => 'medium',
			'status'     => $status,
			'message'    => sprintf(
				/* translators: %1$d: with alt, %2$d: total, %3$d: percentage */
				__( '%1$d / %2$d images have alt text (%3$d%%). Alt text helps AI understand visual content.', 'ai-seo-pilot' ),
				$with_alt,
				$total_images,
				$pct
			),
			'fix'        => 'pass' !== $status ? __( 'Add descriptive alt text to images in the Media Library or post editor.', 'ai-seo-pilot' ) : '',
			'fix_action' => null,
		);
	}

	private function check_heading_structure() {
		$recent_posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
		) );

		if ( empty( $recent_posts ) ) {
			return array(
				'category'   => __( 'Content', 'ai-seo-pilot' ),
				'label'      => __( 'Heading Structure', 'ai-seo-pilot' ),
				'severity'   => 'medium',
				'status'     => 'warning',
				'message'    => __( 'No published posts to analyze.', 'ai-seo-pilot' ),
				'fix'        => __( 'Publish some posts so heading structure can be evaluated.', 'ai-seo-pilot' ),
				'fix_action' => null,
			);
		}

		$posts_with_headings = 0;

		foreach ( $recent_posts as $p ) {
			if ( preg_match( '/<h[2-6][^>]*>/i', $p->post_content ) ) {
				$posts_with_headings++;
			}
		}

		$pct    = round( $posts_with_headings / count( $recent_posts ) * 100 );
		$status = $pct >= 80 ? 'pass' : ( $pct >= 50 ? 'warning' : 'fail' );

		return array(
			'category'   => __( 'Content', 'ai-seo-pilot' ),
			'label'      => __( 'Heading Structure', 'ai-seo-pilot' ),
			'severity'   => 'medium',
			'status'     => $status,
			'message'    => sprintf(
				/* translators: %1$d: with headings, %2$d: total */
				__( '%1$d / %2$d recent posts use sub-headings (H2-H6). Structured content is easier for AI to parse and cite.', 'ai-seo-pilot' ),
				$posts_with_headings,
				count( $recent_posts )
			),
			'fix'        => 'pass' !== $status ? __( 'Add H2-H6 sub-headings to break up content into logical sections.', 'ai-seo-pilot' ) : '',
			'fix_action' => null,
		);
	}
}
