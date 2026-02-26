<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin        = AI_SEO_Pilot::get_instance();
$content       = $plugin->llms_txt->get_content();
$mode          = get_option( 'ai_seo_pilot_llms_txt_mode', 'auto' );
$ai_configured = $plugin->ai_engine->is_configured();
?>
<div class="wrap ai-seo-pilot-wrap">
	<h1><?php esc_html_e( 'LLMS', 'ai-seo-pilot' ); ?></h1>

	<p>
		<?php esc_html_e( 'The llms.txt file tells AI search engines about your site. It is served at:', 'ai-seo-pilot' ); ?>
		<a href="<?php echo esc_url( site_url( '/llms.txt' ) ); ?>" target="_blank"><code><?php echo esc_html( site_url( '/llms.txt' ) ); ?></code></a>
	</p>

	<div class="ai-seo-pilot-section">
		<h2><?php esc_html_e( 'Preview', 'ai-seo-pilot' ); ?></h2>
		<p>
			<strong><?php esc_html_e( 'Mode:', 'ai-seo-pilot' ); ?></strong>
			<?php echo 'auto' === $mode ? esc_html__( 'Auto-generated', 'ai-seo-pilot' ) : esc_html__( 'Manual', 'ai-seo-pilot' ); ?>
			— <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-seo-pilot-settings#llms-txt' ) ); ?>"><?php esc_html_e( 'Change', 'ai-seo-pilot' ); ?></a>
		</p>
		<pre class="ai-seo-pilot-code-preview" id="llms-txt-preview"><?php echo esc_html( $content ); ?></pre>
	</div>

	<div class="ai-seo-pilot-section">
		<h2><?php esc_html_e( 'Actions', 'ai-seo-pilot' ); ?></h2>
		<p>
			<button type="button" class="button button-primary" id="ai-seo-pilot-regenerate">
				<?php esc_html_e( 'Regenerate', 'ai-seo-pilot' ); ?>
			</button>
			<?php if ( $ai_configured ) : ?>
				<button type="button" class="button button-primary" id="ai-seo-pilot-ai-generate-llms" style="background:#7c3aed; border-color:#6d28d9;">
					<?php esc_html_e( 'Generate with AI', 'ai-seo-pilot' ); ?>
				</button>
			<?php else : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-seo-pilot-settings#ai-providers' ) ); ?>" class="button" title="<?php esc_attr_e( 'Configure API key first', 'ai-seo-pilot' ); ?>">
					<?php esc_html_e( 'Generate with AI', 'ai-seo-pilot' ); ?>
				</a>
			<?php endif; ?>
			<button type="button" class="button" id="ai-seo-pilot-validate">
				<?php esc_html_e( 'Validate Accessibility', 'ai-seo-pilot' ); ?>
			</button>
			<span id="ai-seo-pilot-llms-status" class="ai-seo-pilot-status"></span>
		</p>
		<?php if ( $ai_configured ) : ?>
			<p class="description">
				<?php esc_html_e( '"Generate with AI" reads all your posts, pages, and products to create an optimized llms.txt. This may take 15-30 seconds.', 'ai-seo-pilot' ); ?>
			</p>
		<?php endif; ?>
	</div>

	<div class="ai-seo-pilot-section" id="ai-seo-pilot-validation-result" style="display:none;">
		<h2><?php esc_html_e( 'Validation Result', 'ai-seo-pilot' ); ?></h2>
		<table class="widefat">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Accessible', 'ai-seo-pilot' ); ?></th>
					<td id="validation-accessible">—</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Status Code', 'ai-seo-pilot' ); ?></th>
					<td id="validation-status-code">—</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Content Length', 'ai-seo-pilot' ); ?></th>
					<td id="validation-content-length">—</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Content Preview', 'ai-seo-pilot' ); ?></th>
					<td><pre id="validation-content-preview">—</pre></td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
