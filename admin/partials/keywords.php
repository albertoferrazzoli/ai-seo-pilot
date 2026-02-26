<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin       = AI_SEO_Pilot::get_instance();
$ai_configured = $plugin->ai_engine->is_configured();
$focus_keywords = $plugin->keyword_tracker->get_all_focus_keywords();
?>
<div class="wrap ai-seo-pilot-wrap">
	<h1><?php esc_html_e( 'Keywords', 'ai-seo-pilot' ); ?></h1>

	<?php if ( ! $ai_configured ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'AI Engine is not configured. Go to Settings > AI Providers to set up your API key.', 'ai-seo-pilot' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="ai-seo-pilot-section">
		<h2><?php esc_html_e( 'Focus Keywords Overview', 'ai-seo-pilot' ); ?></h2>

		<?php if ( empty( $focus_keywords ) ) : ?>
			<p><?php esc_html_e( 'No focus keywords found. Edit a post and use "Extract with AI" to extract keywords.', 'ai-seo-pilot' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Keyword', 'ai-seo-pilot' ); ?></th>
						<th><?php esc_html_e( 'Post', 'ai-seo-pilot' ); ?></th>
						<th><?php esc_html_e( 'Type', 'ai-seo-pilot' ); ?></th>
						<th><?php esc_html_e( 'Relevance', 'ai-seo-pilot' ); ?></th>
						<th><?php esc_html_e( 'Updated', 'ai-seo-pilot' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $focus_keywords as $kw ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $kw->keyword ); ?></strong></td>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $kw->post_id ) ); ?>">
								<?php echo esc_html( $kw->post_title ); ?>
							</a>
							<span style="color:#646970; font-size:11px;"> (<?php echo esc_html( $kw->post_type ); ?>)</span>
						</td>
						<td>
							<?php if ( $kw->is_focus ) : ?>
								<span style="background:#d4edda; color:#155724; padding:2px 8px; border-radius:3px; font-size:11px; font-weight:600;">
									<?php esc_html_e( 'Focus', 'ai-seo-pilot' ); ?>
								</span>
							<?php else : ?>
								<span style="color:#646970; font-size:11px;"><?php esc_html_e( 'Secondary', 'ai-seo-pilot' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							$score = round( (float) $kw->relevance_score * 100 );
							$color = $score >= 70 ? '#00a32a' : ( $score >= 40 ? '#dba617' : '#d63638' );
							?>
							<span style="color:<?php echo esc_attr( $color ); ?>; font-weight:600;">
								<?php echo esc_html( $score ); ?>%
							</span>
						</td>
						<td style="color:#646970; font-size:12px;">
							<?php echo esc_html( mysql2date( get_option( 'date_format' ), $kw->updated_at ) ); ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<?php if ( $ai_configured && ! empty( $focus_keywords ) ) : ?>
	<div class="ai-seo-pilot-section" style="margin-top:20px;">
		<h2><?php esc_html_e( 'Cannibalization Check', 'ai-seo-pilot' ); ?></h2>
		<p><?php esc_html_e( 'Detect if multiple posts compete for the same keyword.', 'ai-seo-pilot' ); ?></p>
		<button type="button" class="button" id="ai-seo-pilot-check-cannibalization">
			<?php esc_html_e( 'Check Cannibalization', 'ai-seo-pilot' ); ?>
		</button>
		<span id="ai-seo-pilot-cannibal-status" class="ai-seo-pilot-status"></span>
		<div id="ai-seo-pilot-cannibal-results" style="display:none; margin-top:12px;"></div>
	</div>
	<?php endif; ?>
</div>
