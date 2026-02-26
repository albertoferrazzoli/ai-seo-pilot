<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin       = AI_SEO_Pilot::get_instance();
$bot_stats    = $plugin->ai_visibility->get_visit_stats( 30 );
$crawl_health = $plugin->ai_visibility->get_crawl_health_score();
$top_pages    = $plugin->ai_visibility->get_top_pages( 30, 5 );
$daily_visits = $plugin->ai_visibility->get_daily_visits( 30 );

$llms_mode       = get_option( 'ai_seo_pilot_llms_txt_mode', 'auto' );
$schema_enabled  = get_option( 'ai_seo_pilot_schema_enabled', 'yes' );
$sitemap_enabled = get_option( 'ai_seo_pilot_sitemap_ai_enabled', 'yes' );
?>
<div class="wrap ai-seo-pilot-wrap">
	<h1><?php esc_html_e( 'Dashboard', 'ai-seo-pilot' ); ?></h1>

	<!-- Stat Cards -->
	<div class="ai-seo-pilot-stats">
		<div class="ai-seo-pilot-stat-card">
			<h3><?php esc_html_e( 'AI Bot Visits (30d)', 'ai-seo-pilot' ); ?></h3>
			<span class="stat-number"><?php echo esc_html( number_format_i18n( $bot_stats['total_visits'] ) ); ?></span>
			<p class="stat-detail">
				<?php
				printf(
					/* translators: %s: bot name */
					esc_html__( 'Top bot: %s', 'ai-seo-pilot' ),
					esc_html( $bot_stats['top_bot'] ?: '—' )
				);
				?>
			</p>
		</div>

		<div class="ai-seo-pilot-stat-card">
			<h3><?php esc_html_e( 'Unique AI Bots', 'ai-seo-pilot' ); ?></h3>
			<span class="stat-number"><?php echo esc_html( $bot_stats['unique_bots'] ); ?></span>
			<p class="stat-detail"><?php esc_html_e( 'out of 10 tracked', 'ai-seo-pilot' ); ?></p>
		</div>

		<div class="ai-seo-pilot-stat-card">
			<h3><?php esc_html_e( 'Crawl Health', 'ai-seo-pilot' ); ?></h3>
			<div class="score-circle" data-score="<?php echo esc_attr( $crawl_health ); ?>">
				<span><?php echo esc_html( $crawl_health ); ?></span>
			</div>
		</div>

		<div class="ai-seo-pilot-stat-card">
			<h3><?php esc_html_e( 'Daily Average', 'ai-seo-pilot' ); ?></h3>
			<span class="stat-number"><?php echo esc_html( number_format( $bot_stats['avg_daily'], 1 ) ); ?></span>
			<p class="stat-detail"><?php esc_html_e( 'visits per day', 'ai-seo-pilot' ); ?></p>
		</div>
	</div>

	<!-- Bot Visits Chart -->
	<div class="ai-seo-pilot-section">
		<h2><?php esc_html_e( 'AI Bot Visits — Last 30 Days', 'ai-seo-pilot' ); ?></h2>
		<div class="ai-seo-pilot-chart-container">
			<canvas id="ai-seo-pilot-bot-chart"></canvas>
		</div>
		<script>
			window.aiSeoPilotChartData = <?php echo wp_json_encode( array(
				'labels' => array_column( $daily_visits, 'date' ),
				'values' => array_map( 'intval', array_column( $daily_visits, 'count' ) ),
			) ); ?>;
		</script>
	</div>

	<!-- Visits by Bot -->
	<div class="ai-seo-pilot-section">
		<h2><?php esc_html_e( 'Visits by Bot', 'ai-seo-pilot' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Bot', 'ai-seo-pilot' ); ?></th>
					<th><?php esc_html_e( 'Visits', 'ai-seo-pilot' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $bot_stats['visits_by_bot'] ) ) : ?>
					<?php foreach ( $bot_stats['visits_by_bot'] as $bot => $count ) : ?>
						<tr>
							<td><?php echo esc_html( $bot ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="2"><?php esc_html_e( 'No AI bot visits recorded yet.', 'ai-seo-pilot' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- Top Crawled Pages -->
	<div class="ai-seo-pilot-section">
		<h2><?php esc_html_e( 'Top Crawled Pages', 'ai-seo-pilot' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'URL', 'ai-seo-pilot' ); ?></th>
					<th><?php esc_html_e( 'Visits', 'ai-seo-pilot' ); ?></th>
					<th><?php esc_html_e( 'Bots', 'ai-seo-pilot' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $top_pages ) ) : ?>
					<?php foreach ( $top_pages as $page ) : ?>
						<tr>
							<td><code><?php echo esc_html( $page['url'] ); ?></code></td>
							<td><?php echo esc_html( number_format_i18n( $page['visits'] ) ); ?></td>
							<td><?php echo esc_html( $page['bots'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="3"><?php esc_html_e( 'No pages crawled yet.', 'ai-seo-pilot' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- Quick Actions / Getting Started -->
	<div class="ai-seo-pilot-section">
		<h2><?php esc_html_e( 'Getting Started', 'ai-seo-pilot' ); ?></h2>
		<ul class="ai-seo-pilot-checklist">
			<li class="<?php echo 'auto' === $llms_mode || get_option( 'ai_seo_pilot_llms_txt_manual' ) ? 'done' : ''; ?>">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Configure llms.txt', 'ai-seo-pilot' ); ?>
				— <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-seo-pilot-llms-txt' ) ); ?>"><?php esc_html_e( 'Edit', 'ai-seo-pilot' ); ?></a>
			</li>
			<li class="<?php echo 'yes' === $schema_enabled ? 'done' : ''; ?>">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Enable Schema.org JSON-LD', 'ai-seo-pilot' ); ?>
				— <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-seo-pilot-settings#general' ) ); ?>"><?php esc_html_e( 'Settings', 'ai-seo-pilot' ); ?></a>
			</li>
			<li class="<?php echo 'yes' === $sitemap_enabled ? 'done' : ''; ?>">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Enable AI Sitemap', 'ai-seo-pilot' ); ?>
				— <a href="<?php echo esc_url( site_url( '/ai-sitemap.xml' ) ); ?>" target="_blank"><?php esc_html_e( 'View', 'ai-seo-pilot' ); ?></a>
			</li>
			<li>
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Analyze your first post with the Gutenberg sidebar', 'ai-seo-pilot' ); ?>
			</li>
			<li class="<?php echo $bot_stats['total_visits'] > 0 ? 'done' : ''; ?>">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Receive your first AI bot visit', 'ai-seo-pilot' ); ?>
			</li>
		</ul>
	</div>
</div>
