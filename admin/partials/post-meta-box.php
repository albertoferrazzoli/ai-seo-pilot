<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_type  = get_post_meta( $post->ID, '_ai_seo_pilot_schema_type', true );
$current_type  = $current_type ?: 'auto';
$meta_desc     = get_post_meta( $post->ID, '_ai_seo_pilot_meta_description', true );

$plugin        = AI_SEO_Pilot::get_instance();
$detected_type = $plugin->schema_manager->get_detected_type( $post->ID );
$schema_json   = $plugin->schema_manager->get_schema_json( $post->ID );
$ai_configured = $plugin->ai_engine->is_configured();

$types = array(
	'auto'        => sprintf(
		/* translators: %s: detected type */
		__( 'Auto-detect (%s)', 'ai-seo-pilot' ),
		$detected_type
	),
	'Article'     => __( 'Article', 'ai-seo-pilot' ),
	'BlogPosting' => __( 'BlogPosting', 'ai-seo-pilot' ),
	'FAQPage'     => __( 'FAQPage', 'ai-seo-pilot' ),
	'HowTo'       => __( 'HowTo', 'ai-seo-pilot' ),
	'NewsArticle' => __( 'NewsArticle', 'ai-seo-pilot' ),
	'none'        => __( 'Disabled', 'ai-seo-pilot' ),
);

wp_nonce_field( 'ai_seo_pilot_save_schema', 'ai_seo_pilot_schema_nonce' );
?>

<!-- Meta Description -->
<p><strong><?php esc_html_e( 'AI Meta Description', 'ai-seo-pilot' ); ?></strong></p>
<textarea name="ai_seo_pilot_meta_description" id="ai_seo_pilot_meta_description"
	rows="3" style="width:100%; box-sizing:border-box; font-size:12px;"
	placeholder="<?php esc_attr_e( 'AI-generated meta description will appear here...', 'ai-seo-pilot' ); ?>"
><?php echo esc_textarea( $meta_desc ); ?></textarea>
<p style="margin:4px 0;">
	<span id="ai-seo-pilot-meta-chars" style="font-size:11px; color:#646970;">
		<?php echo esc_html( mb_strlen( $meta_desc ) ); ?>/160
	</span>
	<?php if ( $ai_configured ) : ?>
		<button type="button" class="button button-small" id="ai-seo-pilot-generate-meta"
			data-post-id="<?php echo esc_attr( $post->ID ); ?>"
			style="float:right; background:#7c3aed; border-color:#6d28d9; color:#fff;">
			<?php esc_html_e( 'Generate with AI', 'ai-seo-pilot' ); ?>
		</button>
	<?php endif; ?>
</p>
<span id="ai-seo-pilot-meta-status" class="ai-seo-pilot-status" style="font-size:11px;"></span>

<hr style="margin:12px 0;">

<!-- Schema Type -->
<p>
	<label for="ai_seo_pilot_schema_type">
		<strong><?php esc_html_e( 'Schema Type', 'ai-seo-pilot' ); ?></strong>
	</label>
</p>
<p>
	<select name="ai_seo_pilot_schema_type" id="ai_seo_pilot_schema_type" style="width:100%; box-sizing:border-box;">
		<?php foreach ( $types as $value => $label ) : ?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_type, $value ); ?>>
				<?php echo esc_html( $label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
</p>

<hr style="margin:12px 0;">

<!-- AI SEO Suggestions -->
<?php if ( $ai_configured ) : ?>
<p>
	<strong><?php esc_html_e( 'AI SEO Suggestions', 'ai-seo-pilot' ); ?></strong>
</p>
<p>
	<button type="button" class="button button-small" id="ai-seo-pilot-generate-suggestions"
		data-post-id="<?php echo esc_attr( $post->ID ); ?>"
		style="background:#7c3aed; border-color:#6d28d9; color:#fff;">
		<?php esc_html_e( 'Analyze with AI', 'ai-seo-pilot' ); ?>
	</button>
	<span id="ai-seo-pilot-suggestions-status" class="ai-seo-pilot-status" style="font-size:11px;"></span>
</p>
<div id="ai-seo-pilot-suggestions-list" style="display:none; margin-top:8px;"></div>

<hr style="margin:12px 0;">
<?php endif; ?>

<!-- JSON-LD Preview -->
<details>
	<summary style="cursor:pointer; font-weight:600; font-size:12px;">
		<?php esc_html_e( 'JSON-LD Preview', 'ai-seo-pilot' ); ?>
	</summary>
	<pre style="max-height:200px; overflow:auto; font-size:11px; background:#f0f0f1; padding:8px; white-space:pre-wrap; margin-top:8px;"><?php echo esc_html( $schema_json ); ?></pre>
</details>
