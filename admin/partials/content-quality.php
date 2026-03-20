<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin        = AI_SEO_Pilot::get_instance();
$ai_configured = $plugin->ai_engine->is_configured();
$scanned_posts = $plugin->content_quality->get_scanned_posts();
$duplicate_meta = $plugin->content_quality->detect_duplicate_meta();

$total_posts = wp_count_posts( 'post' );
$total_pages = wp_count_posts( 'page' );
$total       = ( $total_posts->publish ?? 0 ) + ( $total_pages->publish ?? 0 );
$scanned     = count( $scanned_posts );

// Identify utility pages and calculate averages excluding them.
$avg_quality   = 0;
$thin_count    = 0;
$utility_count = 0;
$utility_ids   = array();
$content_count = 0; // non-utility scanned posts.

if ( $scanned > 0 ) {
	$total_score = 0;
	foreach ( $scanned_posts as $sp ) {
		if ( $plugin->content_quality->is_utility_page( $sp->post_id ) ) {
			$utility_count++;
			$utility_ids[ $sp->post_id ] = true;
			continue;
		}
		$content_count++;
		$total_score += (float) $sp->quality_score;
		if ( (float) $sp->quality_score < 40 ) {
			$thin_count++;
		}
	}
	$avg_quality = $content_count > 0 ? round( $total_score / $content_count, 1 ) : 0;
}
?>
<div class="wrap ai-seo-pilot-wrap">
	<h1><?php esc_html_e( 'Content Quality', 'ai-seo-pilot' ); ?></h1>

	<?php if ( ! $ai_configured ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'AI Engine is not configured. Go to Settings > AI Providers to set up your API key.', 'ai-seo-pilot' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Stats -->
	<div class="ai-seo-pilot-stats">
		<div class="ai-seo-pilot-stat-card">
			<span class="stat-icon stat-icon--blue dashicons dashicons-search"></span>
			<h3><?php esc_html_e( 'Scanned', 'ai-seo-pilot' ); ?></h3>
			<span class="stat-number"><?php echo esc_html( $scanned ); ?>/<?php echo esc_html( $total ); ?></span>
			<p class="stat-detail"><?php esc_html_e( 'posts analyzed', 'ai-seo-pilot' ); ?></p>
		</div>
		<div class="ai-seo-pilot-stat-card">
			<span class="stat-icon stat-icon--green dashicons dashicons-awards"></span>
			<h3><?php esc_html_e( 'Avg Quality', 'ai-seo-pilot' ); ?></h3>
			<?php $avg_color = $avg_quality >= 70 ? '#10b981' : ( $avg_quality >= 40 ? '#f59e0b' : '#f43f5e' ); ?>
			<span class="stat-number" style="color:<?php echo esc_attr( $avg_color ); ?>;"><?php echo esc_html( $avg_quality ); ?></span>
			<p class="stat-detail"><?php esc_html_e( 'out of 100 (excl. utility)', 'ai-seo-pilot' ); ?></p>
		</div>
		<div class="ai-seo-pilot-stat-card">
			<span class="stat-icon stat-icon--red dashicons dashicons-warning"></span>
			<h3><?php esc_html_e( 'Thin Content', 'ai-seo-pilot' ); ?></h3>
			<span class="stat-number" style="color:<?php echo $thin_count > 0 ? '#f43f5e' : '#10b981'; ?>;"><?php echo esc_html( $thin_count ); ?></span>
			<p class="stat-detail"><?php esc_html_e( 'posts below threshold', 'ai-seo-pilot' ); ?></p>
		</div>
		<div class="ai-seo-pilot-stat-card">
			<span class="stat-icon stat-icon--gray dashicons dashicons-admin-page"></span>
			<h3><?php esc_html_e( 'Utility Pages', 'ai-seo-pilot' ); ?></h3>
			<span class="stat-number" style="color:#6b7280;"><?php echo esc_html( $utility_count ); ?></span>
			<p class="stat-detail"><?php esc_html_e( 'excluded from scoring', 'ai-seo-pilot' ); ?></p>
		</div>
	</div>

	<!-- Scan Button -->
	<?php if ( $ai_configured ) : ?>
	<div class="ai-seo-pilot-section" style="margin-bottom:20px;">
		<button type="button" class="button button-primary" id="ai-seo-pilot-quality-scan">
			<?php esc_html_e( 'Scan All Content', 'ai-seo-pilot' ); ?>
		</button>
		<span id="ai-seo-pilot-scan-status" class="ai-seo-pilot-status" style="margin-left:8px;"></span>
		<div id="ai-seo-pilot-scan-progress" style="display:none; margin-top:8px;">
			<div style="background:#e0e0e0; border-radius:4px; height:20px; overflow:hidden;">
				<div id="ai-seo-pilot-progress-bar" style="background:#6366f1; height:100%; width:0%; transition:width 0.3s; border-radius:4px;"></div>
			</div>
			<span id="ai-seo-pilot-progress-text" style="font-size:12px; color:#6b7280; margin-top:4px; display:block;"></span>
		</div>
	</div>
	<?php endif; ?>

	<!-- Scanned Posts Table -->
	<?php if ( ! empty( $scanned_posts ) ) : ?>
	<div class="ai-seo-pilot-section">
		<h2><?php esc_html_e( 'Content Quality Scores', 'ai-seo-pilot' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Post', 'ai-seo-pilot' ); ?></th>
					<th><?php esc_html_e( 'Type', 'ai-seo-pilot' ); ?></th>
					<th style="width:100px"><?php esc_html_e( 'Quality', 'ai-seo-pilot' ); ?></th>
					<th style="width:100px"><?php esc_html_e( 'Status', 'ai-seo-pilot' ); ?></th>
					<th style="width:160px; white-space:nowrap;"><?php esc_html_e( 'Scanned', 'ai-seo-pilot' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $scanned_posts as $sp ) :
					$score      = (float) $sp->quality_score;
					$is_utility = isset( $utility_ids[ $sp->post_id ] );

					if ( $is_utility ) {
						$color        = '#6b7280';
						$status_label = __( 'Utility', 'ai-seo-pilot' );
						$status_bg    = '#e0e6ed';
						$status_color = '#3c434a';
					} elseif ( $score >= 70 ) {
						$color        = '#10b981';
						$status_label = __( 'Good', 'ai-seo-pilot' );
						$status_bg    = '#ecfdf5';
						$status_color = '#059669';
					} elseif ( $score >= 40 ) {
						$color        = '#f59e0b';
						$status_label = __( 'Needs Work', 'ai-seo-pilot' );
						$status_bg    = '#fffbeb';
						$status_color = '#d97706';
					} else {
						$color        = '#f43f5e';
						$status_label = __( 'Thin', 'ai-seo-pilot' );
						$status_bg    = '#fff1f2';
						$status_color = '#e11d48';
					}
				?>
				<tr>
					<td>
						<a href="<?php echo esc_url( get_edit_post_link( $sp->post_id ) ); ?>">
							<?php echo esc_html( $sp->post_title ); ?>
						</a>
					</td>
					<td style="color:#6b7280;"><?php echo esc_html( $sp->post_type ); ?></td>
					<td>
						<strong style="color:<?php echo esc_attr( $color ); ?>;"><?php echo esc_html( round( $score ) ); ?>/100</strong>
					</td>
					<td>
						<span style="background:<?php echo esc_attr( $status_bg ); ?>; color:<?php echo esc_attr( $status_color ); ?>; padding:2px 8px; border-radius:3px; font-size:11px; font-weight:600;">
							<?php echo esc_html( $status_label ); ?>
						</span>
					</td>
					<td style="color:#6b7280; font-size:12px; white-space:nowrap;">
						<?php echo esc_html( mysql2date( 'Y-m-d H:i', $sp->scanned_at ) ); ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<!-- Duplicate Meta Descriptions -->
	<?php if ( ! empty( $duplicate_meta ) ) : ?>
	<div class="ai-seo-pilot-section" style="margin-top:20px;">
		<h2><?php esc_html_e( 'Duplicate Meta Descriptions', 'ai-seo-pilot' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Description', 'ai-seo-pilot' ); ?></th>
					<th><?php esc_html_e( 'Posts', 'ai-seo-pilot' ); ?></th>
					<th style="width:60px"><?php esc_html_e( 'Count', 'ai-seo-pilot' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $duplicate_meta as $dm ) : ?>
				<tr>
					<td style="font-size:12px;"><?php echo esc_html( wp_trim_words( $dm->description, 15 ) ); ?></td>
					<td>
						<?php
						$ids = explode( ',', $dm->post_ids );
						$links = array();
						foreach ( $ids as $id ) {
							$title = get_the_title( (int) $id );
							$links[] = '<a href="' . esc_url( get_edit_post_link( (int) $id ) ) . '">' . esc_html( $title ) . '</a>';
						}
						echo wp_kses_post( implode( ', ', $links ) );
						?>
					</td>
					<td style="text-align:center; font-weight:600; color:#f43f5e;">
						<?php echo esc_html( $dm->count ); ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>
</div>
