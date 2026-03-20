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
$ai_configured   = $plugin->ai_engine->is_configured();

// Content Quality data.
$scanned_posts  = $plugin->content_quality->get_scanned_posts();
$scanned_count  = count( $scanned_posts );
$total_posts_o  = wp_count_posts( 'post' );
$total_pages_o  = wp_count_posts( 'page' );
$total_content  = ( $total_posts_o->publish ?? 0 ) + ( $total_pages_o->publish ?? 0 );

$avg_quality    = 0;
$thin_count     = 0;
$good_count     = 0;
$utility_count  = 0;
$content_count  = 0; // non-utility scanned posts.
$quality_scores = array(); // for chart.
if ( $scanned_count > 0 ) {
	$total_score = 0;
	foreach ( $scanned_posts as $sp ) {
		$s = (float) $sp->quality_score;

		if ( $plugin->content_quality->is_utility_page( $sp->post_id ) ) {
			$utility_count++;
			$quality_scores[] = array(
				'title'   => $sp->post_title,
				'score'   => round( $s ),
				'utility' => true,
			);
			continue;
		}

		$content_count++;
		$total_score += $s;
		if ( $s < 40 ) {
			$thin_count++;
		} elseif ( $s >= 70 ) {
			$good_count++;
		}
		$quality_scores[] = array(
			'title'   => $sp->post_title,
			'score'   => round( $s ),
			'utility' => false,
		);
	}
	$avg_quality = $content_count > 0 ? round( $total_score / $content_count, 1 ) : 0;
}

// Keyword data.
$all_keywords   = $plugin->keyword_tracker->get_all_focus_keywords();
$keyword_groups = $plugin->keyword_tracker->get_keyword_groups();
$total_keywords = count( $all_keywords );
$posts_with_kw  = 0;
$seen_posts     = array();
foreach ( $all_keywords as $kw ) {
	if ( ! empty( $kw->is_focus ) && ! isset( $seen_posts[ $kw->post_id ] ) ) {
		$posts_with_kw++;
		$seen_posts[ $kw->post_id ] = true;
	}
}
$cannibal_groups = 0;
foreach ( $keyword_groups as $kw => $posts ) {
	if ( count( $posts ) > 1 ) {
		$cannibal_groups++;
	}
}

// Duplicate meta.
$duplicate_meta = $plugin->content_quality->detect_duplicate_meta();

// Filter out utility pages for top/worst lists.
$content_scores = array_values( array_filter( $quality_scores, function ( $item ) {
	return empty( $item['utility'] );
} ) );
$worst_posts = array_slice( $content_scores, 0, 5 );
$best_posts  = array_slice( array_reverse( $content_scores ), 0, 5 );

// Quality distribution for doughnut chart (utility pages excluded).
$needs_work_count = $content_count - $thin_count - $good_count;
?>
<div class="wrap ai-seo-pilot-wrap">
	<h1><?php esc_html_e( 'Dashboard', 'ai-seo-pilot' ); ?></h1>

	<!-- Row 1: AI Visibility -->
	<div class="ai-seo-pilot-stats">
		<div class="ai-seo-pilot-stat-card">
			<span class="stat-icon stat-icon--blue dashicons dashicons-chart-area"></span>
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
			<span class="stat-icon stat-icon--green dashicons dashicons-heart"></span>
			<h3><?php esc_html_e( 'Crawl Health', 'ai-seo-pilot' ); ?></h3>
			<div class="score-circle" data-score="<?php echo esc_attr( $crawl_health ); ?>">
				<span><?php echo esc_html( $crawl_health ); ?></span>
			</div>
		</div>

		<div class="ai-seo-pilot-stat-card">
			<span class="stat-icon stat-icon--purple dashicons dashicons-groups"></span>
			<h3><?php esc_html_e( 'Unique AI Bots', 'ai-seo-pilot' ); ?></h3>
			<span class="stat-number"><?php echo esc_html( $bot_stats['unique_bots'] ); ?></span>
			<p class="stat-detail"><?php esc_html_e( 'detected in 30 days', 'ai-seo-pilot' ); ?></p>
		</div>

		<div class="ai-seo-pilot-stat-card">
			<span class="stat-icon dashicons dashicons-clock"></span>
			<h3><?php esc_html_e( 'Daily Average', 'ai-seo-pilot' ); ?></h3>
			<span class="stat-number"><?php echo esc_html( number_format( $bot_stats['avg_daily'], 1 ) ); ?></span>
			<p class="stat-detail"><?php esc_html_e( 'visits per day', 'ai-seo-pilot' ); ?></p>
		</div>
	</div>

	<!-- Row 2: Content Optimization -->
	<div class="ai-seo-pilot-stats">
		<div class="ai-seo-pilot-stat-card">
			<span class="stat-icon stat-icon--blue dashicons dashicons-awards"></span>
			<h3><?php esc_html_e( 'Avg Content Quality', 'ai-seo-pilot' ); ?></h3>
			<?php if ( $scanned_count > 0 ) : ?>
				<div class="score-circle" data-score="<?php echo esc_attr( round( $avg_quality ) ); ?>">
					<span><?php echo esc_html( round( $avg_quality ) ); ?></span>
				</div>
				<p class="stat-detail">
					<?php
					printf(
						/* translators: %d: scanned, %d: total */
						esc_html__( '%1$d of %2$d scanned', 'ai-seo-pilot' ),
						$scanned_count,
						$total_content
					);
					?>
				</p>
			<?php else : ?>
				<span class="stat-number" style="color:#6b7280;">—</span>
				<p class="stat-detail">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-seo-pilot-content-quality' ) ); ?>"><?php esc_html_e( 'Run a scan', 'ai-seo-pilot' ); ?></a>
				</p>
			<?php endif; ?>
		</div>

		<div class="ai-seo-pilot-stat-card">
			<span class="stat-icon stat-icon--green dashicons dashicons-yes-alt"></span>
			<h3><?php esc_html_e( 'Good Content', 'ai-seo-pilot' ); ?></h3>
			<span class="stat-number" style="color:#10b981;"><?php echo esc_html( $good_count ); ?></span>
			<p class="stat-detail"><?php esc_html_e( 'score 70+', 'ai-seo-pilot' ); ?></p>
		</div>

		<div class="ai-seo-pilot-stat-card">
			<span class="stat-icon stat-icon--red dashicons dashicons-warning"></span>
			<h3><?php esc_html_e( 'Thin Content', 'ai-seo-pilot' ); ?></h3>
			<span class="stat-number" style="color:<?php echo $thin_count > 0 ? '#f43f5e' : '#10b981'; ?>;"><?php echo esc_html( $thin_count ); ?></span>
			<p class="stat-detail"><?php esc_html_e( 'needs attention', 'ai-seo-pilot' ); ?></p>
		</div>

		<div class="ai-seo-pilot-stat-card">
			<span class="stat-icon stat-icon--orange dashicons dashicons-media-text"></span>
			<h3><?php esc_html_e( 'Duplicate Meta', 'ai-seo-pilot' ); ?></h3>
			<span class="stat-number" style="color:<?php echo count( $duplicate_meta ) > 0 ? '#f59e0b' : '#10b981'; ?>;"><?php echo esc_html( count( $duplicate_meta ) ); ?></span>
			<p class="stat-detail"><?php esc_html_e( 'duplicated descriptions', 'ai-seo-pilot' ); ?></p>
		</div>
	</div>

	<!-- Row 3: Keywords -->
	<div class="ai-seo-pilot-stats">
		<div class="ai-seo-pilot-stat-card">
			<span class="stat-icon stat-icon--blue dashicons dashicons-tag"></span>
			<h3><?php esc_html_e( 'Focus Keywords', 'ai-seo-pilot' ); ?></h3>
			<span class="stat-number"><?php echo esc_html( $posts_with_kw ); ?></span>
			<p class="stat-detail">
				<?php
				printf(
					/* translators: %d: total */
					esc_html__( 'of %d posts tracked', 'ai-seo-pilot' ),
					$total_content
				);
				?>
			</p>
		</div>

		<div class="ai-seo-pilot-stat-card">
			<span class="stat-icon stat-icon--purple dashicons dashicons-admin-generic"></span>
			<h3><?php esc_html_e( 'Total Keywords', 'ai-seo-pilot' ); ?></h3>
			<span class="stat-number"><?php echo esc_html( $total_keywords ); ?></span>
			<p class="stat-detail"><?php esc_html_e( 'extracted by AI', 'ai-seo-pilot' ); ?></p>
		</div>

		<div class="ai-seo-pilot-stat-card">
			<span class="stat-icon stat-icon--orange dashicons dashicons-controls-repeat"></span>
			<h3><?php esc_html_e( 'Cannibalization', 'ai-seo-pilot' ); ?></h3>
			<span class="stat-number" style="color:<?php echo $cannibal_groups > 0 ? '#f43f5e' : '#10b981'; ?>;"><?php echo esc_html( $cannibal_groups ); ?></span>
			<p class="stat-detail">
				<?php echo $cannibal_groups > 0
					? esc_html__( 'keyword conflicts', 'ai-seo-pilot' )
					: esc_html__( 'no conflicts', 'ai-seo-pilot' );
				?>
			</p>
		</div>

		<div class="ai-seo-pilot-stat-card">
			<span class="stat-icon stat-icon--green dashicons dashicons-cloud"></span>
			<h3><?php esc_html_e( 'AI Provider', 'ai-seo-pilot' ); ?></h3>
			<?php if ( $ai_configured ) :
				$provider_key = get_option( 'ai_seo_pilot_ai_provider', 'openai' );
				$providers    = $plugin->ai_engine->get_providers();
				$provider_lbl = isset( $providers[ $provider_key ] ) ? $providers[ $provider_key ]['label'] : $provider_key;
			?>
				<span class="stat-number" style="font-size:20px; color:#10b981;">
					<span class="dashicons dashicons-yes-alt" style="font-size:20px; width:20px; height:20px; vertical-align:middle;"></span>
				</span>
				<p class="stat-detail"><?php echo esc_html( $provider_lbl ); ?></p>
			<?php else : ?>
				<span class="stat-number" style="font-size:20px; color:#f43f5e;">
					<span class="dashicons dashicons-warning" style="font-size:20px; width:20px; height:20px; vertical-align:middle;"></span>
				</span>
				<p class="stat-detail">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-seo-pilot-settings#ai-api' ) ); ?>"><?php esc_html_e( 'Configure', 'ai-seo-pilot' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Bot Visits Chart (full width) -->
	<div class="ai-seo-pilot-section" style="margin: 20px 0;">
		<h2><?php esc_html_e( 'AI Bot Visits — 30 Days', 'ai-seo-pilot' ); ?></h2>
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

	<!-- Content Scores — Best & Worst -->
	<?php if ( $scanned_count > 0 ) : ?>
	<div class="aisp-dashboard-charts">
		<!-- Worst content -->
		<div class="ai-seo-pilot-section aisp-chart-half">
			<h2><?php esc_html_e( 'Lowest Quality Posts', 'ai-seo-pilot' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Post', 'ai-seo-pilot' ); ?></th>
						<th style="width:80px; text-align:right;"><?php esc_html_e( 'Score', 'ai-seo-pilot' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $worst_posts as $wp_item ) :
						$c = $wp_item['score'] >= 70 ? '#10b981' : ( $wp_item['score'] >= 40 ? '#f59e0b' : '#f43f5e' );
					?>
					<tr>
						<td><?php echo esc_html( $wp_item['title'] ); ?></td>
						<td style="text-align:right;"><strong style="color:<?php echo esc_attr( $c ); ?>;"><?php echo esc_html( $wp_item['score'] ); ?></strong></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Best content -->
		<div class="ai-seo-pilot-section aisp-chart-half">
			<h2><?php esc_html_e( 'Highest Quality Posts', 'ai-seo-pilot' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Post', 'ai-seo-pilot' ); ?></th>
						<th style="width:80px; text-align:right;"><?php esc_html_e( 'Score', 'ai-seo-pilot' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $best_posts as $bp_item ) :
						$c = $bp_item['score'] >= 70 ? '#10b981' : ( $bp_item['score'] >= 40 ? '#f59e0b' : '#f43f5e' );
					?>
					<tr>
						<td><?php echo esc_html( $bp_item['title'] ); ?></td>
						<td style="text-align:right;"><strong style="color:<?php echo esc_attr( $c ); ?>;"><?php echo esc_html( $bp_item['score'] ); ?></strong></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php endif; ?>

	<!-- Quality Distribution + Visits by Bot -->
	<div class="aisp-dashboard-charts">
		<div class="ai-seo-pilot-section aisp-chart-half">
			<h2><?php esc_html_e( 'Content Quality Distribution', 'ai-seo-pilot' ); ?></h2>
			<?php if ( $scanned_count > 0 ) : ?>
				<div class="ai-seo-pilot-chart-container" style="height:250px; display:flex; align-items:center; justify-content:center;">
					<canvas id="ai-seo-pilot-quality-chart"></canvas>
				</div>
				<script>
					window.aiSeoPilotQualityData = <?php echo wp_json_encode( array(
						'good'       => $good_count,
						'needs_work' => $needs_work_count,
						'thin'       => $thin_count,
					) ); ?>;
				</script>
			<?php else : ?>
				<p style="text-align:center; color:#6b7280; padding:40px 0;">
					<?php esc_html_e( 'No content scanned yet.', 'ai-seo-pilot' ); ?>
					<br>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-seo-pilot-content-quality' ) ); ?>"><?php esc_html_e( 'Run Content Quality scan', 'ai-seo-pilot' ); ?></a>
				</p>
			<?php endif; ?>
		</div>

		<div class="ai-seo-pilot-section aisp-chart-half">
			<h2><?php esc_html_e( 'Visits by Bot', 'ai-seo-pilot' ); ?></h2>
			<?php if ( ! empty( $bot_stats['visits_by_bot'] ) ) : ?>
				<div class="ai-seo-pilot-chart-container" style="height:250px; display:flex; align-items:center; justify-content:center;">
					<canvas id="ai-seo-pilot-bots-pie-chart"></canvas>
				</div>
				<script>
					window.aiSeoPilotBotsData = <?php echo wp_json_encode( array(
						'labels' => array_keys( $bot_stats['visits_by_bot'] ),
						'values' => array_values( $bot_stats['visits_by_bot'] ),
					) ); ?>;
				</script>
			<?php else : ?>
				<p style="text-align:center; color:#6b7280; padding:40px 0;">
					<?php esc_html_e( 'No AI bot visits recorded yet.', 'ai-seo-pilot' ); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Top Crawled Pages (full width) -->
	<div class="ai-seo-pilot-section" style="margin: 0 0 20px;">
		<h2><?php esc_html_e( 'Top Crawled Pages', 'ai-seo-pilot' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'URL', 'ai-seo-pilot' ); ?></th>
						<th style="width:60px;"><?php esc_html_e( 'Visits', 'ai-seo-pilot' ); ?></th>
						<th style="width:180px;"><?php esc_html_e( 'Bots', 'ai-seo-pilot' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $top_pages ) ) : ?>
						<?php foreach ( $top_pages as $page ) : ?>
							<tr>
								<td><code style="font-size:11px;"><?php echo esc_html( $page['url'] ); ?></code></td>
								<td><?php echo esc_html( number_format_i18n( $page['visits'] ) ); ?></td>
								<td><?php echo esc_html( $page['bots'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="3" style="color:#6b7280;"><?php esc_html_e( 'No pages crawled yet.', 'ai-seo-pilot' ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
	</div>

	<!-- Quick Actions / Getting Started -->
	<div class="ai-seo-pilot-section">
		<h2><?php esc_html_e( 'Getting Started', 'ai-seo-pilot' ); ?></h2>
		<ul class="ai-seo-pilot-checklist">
			<li class="<?php echo $ai_configured ? 'done' : ''; ?>">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Configure an AI Provider', 'ai-seo-pilot' ); ?>
				— <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-seo-pilot-settings#ai-api' ) ); ?>"><?php esc_html_e( 'Settings', 'ai-seo-pilot' ); ?></a>
			</li>
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
			<li class="<?php echo $scanned_count > 0 ? 'done' : ''; ?>">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Run your first Content Quality scan', 'ai-seo-pilot' ); ?>
				— <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-seo-pilot-content-quality' ) ); ?>"><?php esc_html_e( 'Scan', 'ai-seo-pilot' ); ?></a>
			</li>
			<li class="<?php echo $bot_stats['total_visits'] > 0 ? 'done' : ''; ?>">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Receive your first AI bot visit', 'ai-seo-pilot' ); ?>
			</li>
		</ul>
	</div>
</div>
