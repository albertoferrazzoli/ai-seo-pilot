<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin       = AI_SEO_Pilot::get_instance();
$bot_stats    = $plugin->ai_visibility->get_visit_stats( 7 );
$crawl_health = $plugin->ai_visibility->get_crawl_health_score();
$top_pages    = $plugin->ai_visibility->get_top_pages( 7, 3 );

// AI readiness: sample last 10 published posts.
$recent_posts   = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 10 ) );
$analyzed_count = 0;
$ready_count    = 0;
$total_score    = 0;

foreach ( $recent_posts as $p ) {
	$result = $plugin->content_analyzer->analyze( $p->post_content, $p->post_title );
	$analyzed_count++;
	$total_score += $result['score'];
	if ( $result['ai_ready'] ) {
		$ready_count++;
	}
}

$avg_score = $analyzed_count > 0 ? round( $total_score / $analyzed_count ) : 0;

// Features status.
$features = array(
	'llms_txt' => 'auto' === get_option( 'ai_seo_pilot_llms_txt_mode', 'auto' ) || get_option( 'ai_seo_pilot_llms_txt_manual' ),
	'schema'   => 'yes' === get_option( 'ai_seo_pilot_schema_enabled', 'yes' ),
	'sitemap'  => 'yes' === get_option( 'ai_seo_pilot_sitemap_ai_enabled', 'yes' ),
	'tracking' => 'yes' === get_option( 'ai_seo_pilot_ai_visibility_enabled', 'yes' ),
	'ai_api'   => $plugin->ai_engine->is_configured(),
);
?>
<style>
	.aisp-widget { font-size: 13px; }
	.aisp-widget-stats { display: flex; gap: 12px; margin-bottom: 14px; }
	.aisp-widget-stat { flex: 1; text-align: center; background: #f6f7f7; border-radius: 4px; padding: 10px 6px; }
	.aisp-widget-stat .num { display: block; font-size: 22px; font-weight: 700; line-height: 1.2; color: #1d2327; }
	.aisp-widget-stat .lbl { font-size: 11px; color: #646970; }
	.aisp-widget-score { display: inline-flex; align-items: center; justify-content: center;
		width: 36px; height: 36px; border-radius: 50%; font-size: 14px; font-weight: 700; color: #fff; }
	.aisp-widget-features { display: flex; flex-wrap: wrap; gap: 6px; margin: 10px 0; }
	.aisp-widget-features .feat { font-size: 11px; padding: 3px 8px; border-radius: 10px; }
	.aisp-widget-features .feat.on { background: #d4edda; color: #155724; }
	.aisp-widget-features .feat.off { background: #f0f0f1; color: #646970; }
	.aisp-widget-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 8px; }
	.aisp-widget-table th { text-align: left; font-weight: 600; padding: 4px 0; border-bottom: 1px solid #e0e0e0; }
	.aisp-widget-table td { padding: 4px 0; border-bottom: 1px solid #f0f0f1; }
	.aisp-widget-table td:last-child { text-align: right; }
	.aisp-widget-links { margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; }
	.aisp-widget-links a { font-size: 12px; }
</style>

<div class="aisp-widget">

	<!-- Stats Row -->
	<div class="aisp-widget-stats">
		<div class="aisp-widget-stat">
			<span class="num"><?php echo esc_html( $bot_stats['total_visits'] ); ?></span>
			<span class="lbl"><?php esc_html_e( 'Bot Visits (7d)', 'ai-seo-pilot' ); ?></span>
		</div>
		<div class="aisp-widget-stat">
			<span class="num"><?php echo esc_html( $bot_stats['unique_bots'] ); ?></span>
			<span class="lbl"><?php esc_html_e( 'Unique Bots', 'ai-seo-pilot' ); ?></span>
		</div>
		<div class="aisp-widget-stat">
			<?php
			$health_bg = '#d63638';
			if ( $crawl_health >= 75 ) {
				$health_bg = '#00a32a';
			} elseif ( $crawl_health >= 50 ) {
				$health_bg = '#dba617';
			}
			?>
			<span class="aisp-widget-score" style="background:<?php echo esc_attr( $health_bg ); ?>;">
				<?php echo esc_html( $crawl_health ); ?>
			</span>
			<span class="lbl"><?php esc_html_e( 'Crawl Health', 'ai-seo-pilot' ); ?></span>
		</div>
		<div class="aisp-widget-stat">
			<?php
			$score_bg = '#d63638';
			if ( $avg_score >= 75 ) {
				$score_bg = '#00a32a';
			} elseif ( $avg_score >= 50 ) {
				$score_bg = '#dba617';
			}
			?>
			<span class="aisp-widget-score" style="background:<?php echo esc_attr( $score_bg ); ?>;">
				<?php echo esc_html( $avg_score ); ?>
			</span>
			<span class="lbl"><?php esc_html_e( 'Avg AI Score', 'ai-seo-pilot' ); ?></span>
		</div>
	</div>

	<!-- AI Readiness -->
	<?php if ( $analyzed_count > 0 ) : ?>
		<p style="margin:0 0 6px;">
			<strong><?php echo esc_html( $ready_count ); ?>/<?php echo esc_html( $analyzed_count ); ?></strong>
			<?php esc_html_e( 'recent posts are AI-ready (score 75+)', 'ai-seo-pilot' ); ?>
		</p>
	<?php endif; ?>

	<!-- Features Status -->
	<div class="aisp-widget-features">
		<span class="feat <?php echo $features['llms_txt'] ? 'on' : 'off'; ?>">llms.txt</span>
		<span class="feat <?php echo $features['schema'] ? 'on' : 'off'; ?>">Schema</span>
		<span class="feat <?php echo $features['sitemap'] ? 'on' : 'off'; ?>">Sitemap</span>
		<span class="feat <?php echo $features['tracking'] ? 'on' : 'off'; ?>">Tracking</span>
		<span class="feat <?php echo $features['ai_api'] ? 'on' : 'off'; ?>">AI API</span>
	</div>

	<!-- Top Bot -->
	<?php if ( $bot_stats['top_bot'] ) : ?>
		<p style="margin:6px 0; font-size:12px; color:#646970;">
			<?php
			printf(
				/* translators: %1$s: bot name, %2$s: visit count */
				esc_html__( 'Top bot: %1$s (%2$s visits)', 'ai-seo-pilot' ),
				'<strong>' . esc_html( $bot_stats['top_bot'] ) . '</strong>',
				esc_html( $bot_stats['visits_by_bot'][ $bot_stats['top_bot'] ] ?? 0 )
			);
			?>
		</p>
	<?php endif; ?>

	<!-- Top Crawled Pages -->
	<?php if ( ! empty( $top_pages ) ) : ?>
		<table class="aisp-widget-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Top Crawled Pages', 'ai-seo-pilot' ); ?></th>
					<th><?php esc_html_e( 'Visits', 'ai-seo-pilot' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $top_pages as $page ) : ?>
					<?php
					$display_url = wp_parse_url( $page['url'], PHP_URL_PATH ) ?: $page['url'];
					if ( mb_strlen( $display_url ) > 40 ) {
						$display_url = mb_substr( $display_url, 0, 37 ) . '...';
					}
					?>
					<tr>
						<td title="<?php echo esc_attr( $page['url'] ); ?>">
							<code style="font-size:11px;"><?php echo esc_html( $display_url ); ?></code>
						</td>
						<td><?php echo esc_html( $page['visits'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p style="font-size:12px; color:#646970; margin:6px 0;">
			<?php esc_html_e( 'No AI bot visits recorded yet.', 'ai-seo-pilot' ); ?>
		</p>
	<?php endif; ?>

	<!-- Quick Links -->
	<div class="aisp-widget-links">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-seo-pilot' ) ); ?>" class="button button-small button-primary">
			<?php esc_html_e( 'Dashboard', 'ai-seo-pilot' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-seo-pilot-settings' ) ); ?>" class="button button-small">
			<?php esc_html_e( 'Settings', 'ai-seo-pilot' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-seo-pilot-llms-txt' ) ); ?>" class="button button-small">
			<?php esc_html_e( 'llms.txt', 'ai-seo-pilot' ); ?>
		</a>
		<a href="<?php echo esc_url( site_url( '/ai-sitemap.xml' ) ); ?>" class="button button-small" target="_blank">
			<?php esc_html_e( 'Sitemap', 'ai-seo-pilot' ); ?>
		</a>
	</div>
</div>
