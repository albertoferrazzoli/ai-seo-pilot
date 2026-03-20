<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_tab = 'general';

Pilot_Admin_UI::page_start( __( 'AI SEO Pilot', 'ai-seo-pilot' ), 'v' . AI_SEO_PILOT_VERSION );

echo '<form method="post" action="options.php">';
settings_fields( 'ai_seo_pilot_general' );

$tabs = [
	'general'              => __( 'General', 'ai-seo-pilot' ),
	'ai-providers'         => __( 'AI Providers', 'ai-seo-pilot' ),
	'llms-txt'             => __( 'LLMS', 'ai-seo-pilot' ),
	'schema'               => __( 'Schema', 'ai-seo-pilot' ),
	'content-analysis'     => __( 'Content Analysis', 'ai-seo-pilot' ),
	'ai-bots'              => __( 'AI Bots', 'ai-seo-pilot' ),
	'content-optimization' => __( 'Content Optimization', 'ai-seo-pilot' ),
	'advanced'             => __( 'Advanced', 'ai-seo-pilot' ),
];

Pilot_Admin_UI::tabs( $tabs, $active_tab, 'ai-seo-pilot' );

/* =============================================
   General Tab
   ============================================= */

Pilot_Admin_UI::tab_panel_start( 'general', $active_tab );

Pilot_Admin_UI::card_start( __( 'License', 'ai-seo-pilot' ) );
echo '<table class="form-table">';
Pilot_Updater::render_license_field( 'ai-seo-pilot' );
echo '</table>';
Pilot_Admin_UI::card_end();

Pilot_Admin_UI::card_start( __( 'Features', 'ai-seo-pilot' ), __( 'Enable or disable core plugin features.', 'ai-seo-pilot' ) );

Pilot_Admin_UI::checkbox(
	'ai_seo_pilot_schema_enabled',
	get_option( 'ai_seo_pilot_schema_enabled', 'yes' ) === 'yes',
	__( 'Schema.org JSON-LD', 'ai-seo-pilot' ),
	__( 'Enable automatic Schema.org JSON-LD output', 'ai-seo-pilot' ),
	'yes'
);

Pilot_Admin_UI::checkbox(
	'ai_seo_pilot_analyzer_enabled',
	get_option( 'ai_seo_pilot_analyzer_enabled', 'yes' ) === 'yes',
	__( 'Content Analyzer', 'ai-seo-pilot' ),
	__( 'Enable Gutenberg sidebar content analysis', 'ai-seo-pilot' ),
	'yes'
);

Pilot_Admin_UI::checkbox(
	'ai_seo_pilot_ai_visibility_enabled',
	get_option( 'ai_seo_pilot_ai_visibility_enabled', 'yes' ) === 'yes',
	__( 'AI Bot Tracking', 'ai-seo-pilot' ),
	__( 'Track AI bot visits to your site', 'ai-seo-pilot' ),
	'yes'
);

Pilot_Admin_UI::checkbox(
	'ai_seo_pilot_sitemap_ai_enabled',
	get_option( 'ai_seo_pilot_sitemap_ai_enabled', 'yes' ) === 'yes',
	__( 'AI Sitemap', 'ai-seo-pilot' ),
	__( 'Enable AI-optimized sitemap at /ai-sitemap.xml', 'ai-seo-pilot' ),
	'yes'
);

Pilot_Admin_UI::card_end();

Pilot_Admin_UI::tab_panel_end();

/* =============================================
   AI Providers Tab
   ============================================= */

Pilot_Admin_UI::tab_panel_start( 'ai-providers', $active_tab );

$plugin          = AI_SEO_Pilot::get_instance();
$ai_providers    = $plugin->ai_engine->get_providers();
$active_provider = get_option( 'ai_seo_pilot_ai_provider', 'openai' );

$provider_options = [];
foreach ( $ai_providers as $key => $prov ) {
	$provider_options[ $key ] = $prov['label'];
}
Pilot_Admin_UI::card_start(
	__( 'Provider Configuration', 'ai-seo-pilot' ),
	__( 'Connect an AI API to unlock AI-powered SEO generation. The plugin works fully without an API key — this adds AI-assisted content generation on top.', 'ai-seo-pilot' )
);

Pilot_Admin_UI::select(
	'ai_seo_pilot_ai_provider',
	$active_provider,
	__( 'Provider', 'ai-seo-pilot' ),
	$provider_options
);

// Per-provider settings sections.
foreach ( $ai_providers as $prov_key => $prov ) :
	$hidden = $active_provider !== $prov_key ? 'display:none;' : '';
?>
	<div class="aisp-provider-section" data-provider="<?php echo esc_attr( $prov_key ); ?>" style="<?php echo $hidden; ?>">
		<?php if ( 'server_url' === $prov['auth'] ) : ?>
			<?php $server_url = get_option( "ai_seo_pilot_ai_{$prov_key}_server_url", $prov['default_url'] ?? '' ); ?>
			<div class="pilot-field-row">
				<div class="pilot-field-label">
					<label for="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_server_url">
						<?php esc_html_e( 'Server URL', 'ai-seo-pilot' ); ?>
					</label>
				</div>
				<div class="pilot-field-control">
					<input type="url"
						name="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_server_url"
						id="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_server_url"
						class="pilot-input aisp-provider-credential"
						value="<?php echo esc_attr( $server_url ); ?>"
						placeholder="<?php echo esc_attr( $prov['default_url'] ?? 'http://localhost:11434' ); ?>">
					<p class="pilot-description">
						<?php esc_html_e( 'URL of your local Ollama instance. No API key required.', 'ai-seo-pilot' ); ?>
					</p>
				</div>
			</div>
		<?php else : ?>
			<?php $api_key = get_option( "ai_seo_pilot_ai_{$prov_key}_api_key", '' ); ?>
			<div class="pilot-field-row">
				<div class="pilot-field-label">
					<label for="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_api_key">
						<?php esc_html_e( 'API Key', 'ai-seo-pilot' ); ?>
					</label>
				</div>
				<div class="pilot-field-control">
					<input type="password"
						name="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_api_key"
						id="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_api_key"
						class="pilot-input aisp-provider-credential"
						autocomplete="off"
						value="<?php echo esc_attr( $api_key ); ?>">
					<?php if ( $api_key ) : ?>
						<span class="pilot-badge pilot-badge-success" style="margin-left:8px"><?php esc_html_e( 'Configured', 'ai-seo-pilot' ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $prov['key_url'] ) ) : ?>
						<p class="pilot-description">
							<?php
							printf(
								/* translators: %s: provider key URL */
								esc_html__( 'Get your API key from %s', 'ai-seo-pilot' ),
								'<a href="' . esc_url( $prov['key_url'] ) . '" target="_blank">' . esc_html( $prov['label'] ) . '</a>'
							);
							?>
						</p>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php
		// Model select.
		$current_model = get_option( "ai_seo_pilot_ai_{$prov_key}_model", '' );
		$known_models  = array_keys( $prov['models'] );
		$is_custom     = ! empty( $current_model ) && ! in_array( $current_model, $known_models, true );
		?>
		<div class="pilot-field-row">
			<div class="pilot-field-label">
				<label for="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_model_select">
					<?php esc_html_e( 'Model', 'ai-seo-pilot' ); ?>
				</label>
			</div>
			<div class="pilot-field-control">
				<select
					id="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_model_select"
					class="pilot-select aisp-provider-model aisp-model-select"
					data-target="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_model">
					<?php $first = true; ?>
					<?php foreach ( $prov['models'] as $model_id => $model_label ) : ?>
						<option value="<?php echo esc_attr( $model_id ); ?>"
							<?php echo ( ! $is_custom && ( $current_model === $model_id || ( $first && empty( $current_model ) ) ) ) ? 'selected' : ''; ?>>
							<?php echo esc_html( $model_label ); ?>
						</option>
						<?php $first = false; ?>
					<?php endforeach; ?>
					<?php if ( 'server_url' === ( $prov['auth'] ?? '' ) ) : ?>
						<option value="__custom__" <?php selected( $is_custom, true ); ?>>
							<?php esc_html_e( 'Custom…', 'ai-seo-pilot' ); ?>
						</option>
					<?php endif; ?>
				</select>
				<?php if ( 'server_url' === ( $prov['auth'] ?? '' ) ) : ?>
					<input type="text"
						id="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_model_custom"
						class="pilot-input aisp-model-custom"
						value="<?php echo $is_custom ? esc_attr( $current_model ) : ''; ?>"
						placeholder="<?php esc_attr_e( 'e.g. phi3, codellama, solar', 'ai-seo-pilot' ); ?>"
						style="<?php echo $is_custom ? '' : 'display:none;'; ?> margin-top:6px;">
				<?php endif; ?>
				<input type="hidden"
					name="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_model"
					id="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_model"
					value="<?php echo esc_attr( $current_model ); ?>">
			</div>
		</div>
	</div>
<?php endforeach; ?>

<?php // Test connection (custom JS handler). ?>
<div class="pilot-field-row">
	<div class="pilot-field-label"><?php esc_html_e( 'Test Connection', 'ai-seo-pilot' ); ?></div>
	<div class="pilot-field-control">
		<div style="display:flex; align-items:center; gap:10px;">
			<button type="button" class="button" id="ai-seo-pilot-test-connection">
				<?php esc_html_e( 'Test Connection', 'ai-seo-pilot' ); ?>
			</button>
			<span id="ai-seo-pilot-test-status" class="ai-seo-pilot-status"></span>
		</div>
		<div id="ai-seo-pilot-test-result" style="display:none; margin-top:10px;">
			<pre class="ai-seo-pilot-code-preview" style="max-height:100px; font-size:12px;"></pre>
		</div>
	</div>
</div>

<?php Pilot_Admin_UI::card_end(); ?>

<?php Pilot_Admin_UI::card_start( __( 'AI-Powered Features', 'ai-seo-pilot' ), __( 'Once configured, the following AI features become available:', 'ai-seo-pilot' ) ); ?>
<table class="widefat striped">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Feature', 'ai-seo-pilot' ); ?></th>
			<th><?php esc_html_e( 'Location', 'ai-seo-pilot' ); ?></th>
			<th><?php esc_html_e( 'Description', 'ai-seo-pilot' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><strong><?php esc_html_e( 'Generate llms.txt with AI', 'ai-seo-pilot' ); ?></strong></td>
			<td><?php esc_html_e( 'llms.txt page', 'ai-seo-pilot' ); ?></td>
			<td><?php esc_html_e( 'Analyzes all your posts, pages, and products to generate an optimal llms.txt', 'ai-seo-pilot' ); ?></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'AI Meta Descriptions', 'ai-seo-pilot' ); ?></strong></td>
			<td><?php esc_html_e( 'Post edit sidebar', 'ai-seo-pilot' ); ?></td>
			<td><?php esc_html_e( 'Generates AI-optimized meta descriptions for each post/page', 'ai-seo-pilot' ); ?></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'AI SEO Suggestions', 'ai-seo-pilot' ); ?></strong></td>
			<td><?php esc_html_e( 'Post edit sidebar', 'ai-seo-pilot' ); ?></td>
			<td><?php esc_html_e( 'Detailed, content-specific suggestions to improve AI citability', 'ai-seo-pilot' ); ?></td>
		</tr>
	</tbody>
</table>
<?php Pilot_Admin_UI::card_end(); ?>

<?php Pilot_Admin_UI::tab_panel_end(); ?>

<?php
/* =============================================
   LLMS Tab
   ============================================= */

Pilot_Admin_UI::tab_panel_start( 'llms-txt', $active_tab );

Pilot_Admin_UI::card_start( __( 'llms.txt Configuration', 'ai-seo-pilot' ) );

Pilot_Admin_UI::select(
	'ai_seo_pilot_llms_txt_mode',
	get_option( 'ai_seo_pilot_llms_txt_mode', 'auto' ),
	__( 'Generation Mode', 'ai-seo-pilot' ),
	[
		'auto'   => __( 'Auto-generate', 'ai-seo-pilot' ),
		'manual' => __( 'Manual', 'ai-seo-pilot' ),
	],
	[ 'description' => __( 'Auto-generate uses your site title, description, top posts, and key pages.', 'ai-seo-pilot' ) ]
);

echo '<div id="llms-txt-manual-row">';
Pilot_Admin_UI::textarea(
	'ai_seo_pilot_llms_txt_manual',
	get_option( 'ai_seo_pilot_llms_txt_manual', '' ),
	__( 'Manual Content', 'ai-seo-pilot' ),
	[
		'rows'        => 15,
		'class'       => 'code',
		'description' => __( 'Enter your custom llms.txt content. Markdown format recommended.', 'ai-seo-pilot' ),
	]
);
echo '</div>';

Pilot_Admin_UI::card_end();

Pilot_Admin_UI::tab_panel_end();

/* =============================================
   Schema Tab
   ============================================= */

Pilot_Admin_UI::tab_panel_start( 'schema', $active_tab );

Pilot_Admin_UI::card_start( __( 'Schema.org Settings', 'ai-seo-pilot' ) );

Pilot_Admin_UI::checkbox(
	'ai_seo_pilot_schema_organization',
	get_option( 'ai_seo_pilot_schema_organization', 'yes' ) === 'yes',
	__( 'Organization Schema', 'ai-seo-pilot' ),
	__( 'Output Organization schema on front page', 'ai-seo-pilot' ),
	'yes'
);

Pilot_Admin_UI::checkbox(
	'ai_seo_pilot_schema_breadcrumbs',
	get_option( 'ai_seo_pilot_schema_breadcrumbs', 'yes' ) === 'yes',
	__( 'Breadcrumb Schema', 'ai-seo-pilot' ),
	__( 'Output BreadcrumbList schema on all pages', 'ai-seo-pilot' ),
	'yes'
);

Pilot_Admin_UI::checkbox(
	'ai_seo_pilot_schema_website',
	get_option( 'ai_seo_pilot_schema_website', 'yes' ) === 'yes',
	__( 'WebSite Schema', 'ai-seo-pilot' ),
	__( 'Output WebSite schema on front page', 'ai-seo-pilot' ),
	'yes'
);

Pilot_Admin_UI::textarea(
	'ai_seo_pilot_organization_same_as',
	get_option( 'ai_seo_pilot_organization_same_as', '' ),
	__( 'Social Profiles (sameAs)', 'ai-seo-pilot' ),
	[
		'rows'        => 5,
		'description' => __( 'One URL per line. Used in Organization schema sameAs property.', 'ai-seo-pilot' ),
	]
);

Pilot_Admin_UI::card_end();

Pilot_Admin_UI::tab_panel_end();

/* =============================================
   Content Analysis Tab
   ============================================= */

$ca_settings = get_option( 'ai_seo_pilot_content_analysis', [] );
$ca_defaults = AI_SEO_Pilot_Content_Analyzer::get_defaults();
$ca_settings = wp_parse_args( $ca_settings, $ca_defaults );
foreach ( $ca_defaults['checks'] as $key => $def ) {
	$ca_settings['checks'][ $key ] = wp_parse_args(
		isset( $ca_settings['checks'][ $key ] ) ? $ca_settings['checks'][ $key ] : [],
		$def
	);
}

Pilot_Admin_UI::tab_panel_start( 'content-analysis', $active_tab );
Pilot_Admin_UI::card_start(
	__( 'Scoring Configuration', 'ai-seo-pilot' ),
	__( 'Configure which checks run in the Gutenberg editor sidebar and how they are scored.', 'ai-seo-pilot' )
);

Pilot_Admin_UI::number(
	'ai_seo_pilot_content_analysis[ai_ready_threshold]',
	$ca_settings['ai_ready_threshold'],
	__( 'AI-Ready Threshold', 'ai-seo-pilot' ),
	[
		'min'         => 0,
		'max'         => 100,
		'suffix'      => '%',
		'description' => __( 'Percentage of total points required to display the "AI-Ready" badge.', 'ai-seo-pilot' ),
	]
);

Pilot_Admin_UI::card_end();

Pilot_Admin_UI::card_start( __( 'Scoring Checks', 'ai-seo-pilot' ) );

$check_meta = [
	'direct_answer' => [
		'label'       => __( 'Direct Answer', 'ai-seo-pilot' ),
		'description' => __( 'First paragraph provides a clear, concise answer', 'ai-seo-pilot' ),
		'threshold'   => [ 'key' => 'max_chars', 'label' => __( 'Max chars', 'ai-seo-pilot' ), 'min' => 50, 'max' => 500 ],
	],
	'qa_structure' => [
		'label'       => __( 'Q&A Structure', 'ai-seo-pilot' ),
		'description' => __( 'Contains question headings (H2/H3 with ?)', 'ai-seo-pilot' ),
		'threshold'   => [ 'key' => 'min_questions', 'label' => __( 'Min questions', 'ai-seo-pilot' ), 'min' => 1, 'max' => 10 ],
	],
	'definitions' => [
		'label'       => __( 'Definitions', 'ai-seo-pilot' ),
		'description' => __( 'Contains definition patterns ("X is...", "defined as")', 'ai-seo-pilot' ),
		'threshold'   => [ 'key' => 'min_definitions', 'label' => __( 'Min definitions', 'ai-seo-pilot' ), 'min' => 1, 'max' => 10 ],
	],
	'paragraph_length' => [
		'label'       => __( 'Paragraph Length', 'ai-seo-pilot' ),
		'description' => __( 'Average paragraph within target word count', 'ai-seo-pilot' ),
		'threshold'   => [ 'key' => 'max_avg_words', 'label' => __( 'Max avg words', 'ai-seo-pilot' ), 'min' => 50, 'max' => 500 ],
	],
	'list_optimization' => [
		'label'       => __( 'List Optimization', 'ai-seo-pilot' ),
		'description' => __( 'Contains ordered/unordered lists or tables', 'ai-seo-pilot' ),
		'threshold'   => [ 'key' => 'min_lists', 'label' => __( 'Min lists/tables', 'ai-seo-pilot' ), 'min' => 1, 'max' => 10 ],
	],
	'entity_density' => [
		'label'       => __( 'Entity Density', 'ai-seo-pilot' ),
		'description' => __( 'Proper nouns and named entities per 100 words', 'ai-seo-pilot' ),
		'threshold'   => [ 'key' => 'min_density', 'label' => __( 'Min % density', 'ai-seo-pilot' ), 'min' => 1, 'max' => 10 ],
	],
	'citable_statistics' => [
		'label'       => __( 'Citable Statistics', 'ai-seo-pilot' ),
		'description' => __( 'Numbers with context (percentages, amounts)', 'ai-seo-pilot' ),
		'threshold'   => [ 'key' => 'min_stats', 'label' => __( 'Min statistics', 'ai-seo-pilot' ), 'min' => 1, 'max' => 10 ],
	],
	'semantic_completeness' => [
		'label'       => __( 'Semantic Completeness', 'ai-seo-pilot' ),
		'description' => __( 'Sufficient word count with intro and conclusion', 'ai-seo-pilot' ),
		'threshold'   => [ 'key' => 'min_words', 'label' => __( 'Min words', 'ai-seo-pilot' ), 'min' => 100, 'max' => 2000, 'step' => 50 ],
	],
	'snippet_optimization' => [
		'label'       => __( 'Snippet Optimization', 'ai-seo-pilot' ),
		'description' => __( 'Concise summary paragraph with bold formatting', 'ai-seo-pilot' ),
		'threshold'   => [ 'key' => 'max_summary_words', 'label' => __( 'Max summary words', 'ai-seo-pilot' ), 'min' => 20, 'max' => 150 ],
	],
	'freshness_signals' => [
		'label'       => __( 'Freshness Signals', 'ai-seo-pilot' ),
		'description' => __( 'Date references, "updated", "latest" keywords', 'ai-seo-pilot' ),
		'threshold'   => [ 'key' => 'min_signals', 'label' => __( 'Min signals', 'ai-seo-pilot' ), 'min' => 1, 'max' => 10 ],
	],
];
?>

<table class="widefat striped ai-seo-pilot-ca-checks">
	<thead>
		<tr>
			<th style="width:60px"><?php esc_html_e( 'Enabled', 'ai-seo-pilot' ); ?></th>
			<th style="width:200px"><?php esc_html_e( 'Check', 'ai-seo-pilot' ); ?></th>
			<th style="width:80px"><?php esc_html_e( 'Weight', 'ai-seo-pilot' ); ?></th>
			<th style="width:130px;text-align:right"><?php esc_html_e( 'Threshold', 'ai-seo-pilot' ); ?></th>
			<th style="width:70px"></th>
			<th><?php esc_html_e( 'Description', 'ai-seo-pilot' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach ( $check_meta as $key => $meta ) :
			$check   = $ca_settings['checks'][ $key ];
			$enabled = ! empty( $check['enabled'] );
			$weight  = isset( $check['weight'] ) ? (int) $check['weight'] : 10;
			$t_key   = $meta['threshold']['key'];
			$t_val   = isset( $check[ $t_key ] ) ? $check[ $t_key ] : '';
			$t_step  = isset( $meta['threshold']['step'] ) ? $meta['threshold']['step'] : 1;
		?>
		<tr>
			<td class="check-col" style="text-align:center">
				<input type="hidden" name="ai_seo_pilot_content_analysis[checks][<?php echo esc_attr( $key ); ?>][enabled]" value="0">
				<input type="checkbox"
					name="ai_seo_pilot_content_analysis[checks][<?php echo esc_attr( $key ); ?>][enabled]"
					value="1" <?php checked( $enabled ); ?>>
			</td>
			<td><strong><?php echo esc_html( $meta['label'] ); ?></strong></td>
			<td>
				<input type="number"
					name="ai_seo_pilot_content_analysis[checks][<?php echo esc_attr( $key ); ?>][weight]"
					value="<?php echo esc_attr( $weight ); ?>"
					min="0" max="20" class="small-text" style="width:60px">
			</td>
			<td style="text-align:right;font-size:12px;color:#6b7280;white-space:nowrap">
				<?php echo esc_html( $meta['threshold']['label'] ); ?>:
			</td>
			<td>
				<input type="number"
					name="ai_seo_pilot_content_analysis[checks][<?php echo esc_attr( $key ); ?>][<?php echo esc_attr( $t_key ); ?>]"
					value="<?php echo esc_attr( $t_val ); ?>"
					min="<?php echo esc_attr( $meta['threshold']['min'] ); ?>"
					max="<?php echo esc_attr( $meta['threshold']['max'] ); ?>"
					step="<?php echo esc_attr( $t_step ); ?>"
					class="small-text" style="width:60px">
			</td>
			<td><span class="pilot-description"><?php echo esc_html( $meta['description'] ); ?></span></td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<p class="pilot-description" style="margin-top:8px">
	<?php esc_html_e( 'Weight determines the maximum points for each check. The total score is calculated as a percentage of achieved points vs. total possible points.', 'ai-seo-pilot' ); ?>
</p>

<?php
Pilot_Admin_UI::card_end();

Pilot_Admin_UI::tab_panel_end();

/* =============================================
   AI Bots Tab
   ============================================= */

Pilot_Admin_UI::tab_panel_start( 'ai-bots', $active_tab );

Pilot_Admin_UI::card_start( __( 'Bot Tracking Settings', 'ai-seo-pilot' ) );

Pilot_Admin_UI::number(
	'ai_seo_pilot_bot_retention_days',
	get_option( 'ai_seo_pilot_bot_retention_days', 90 ),
	__( 'Data Retention', 'ai-seo-pilot' ),
	[
		'min'         => 7,
		'max'         => 365,
		'suffix'      => __( 'days', 'ai-seo-pilot' ),
		'description' => __( 'Bot visit data older than this will be automatically deleted.', 'ai-seo-pilot' ),
	]
);

Pilot_Admin_UI::card_end();

$all_bots = AI_SEO_Pilot_AI_Visibility::get_all_bots();

Pilot_Admin_UI::card_start( __( 'Tracked AI Bots', 'ai-seo-pilot' ) );
?>

<table class="widefat striped" id="ai-seo-pilot-bots-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'UA Identifier', 'ai-seo-pilot' ); ?></th>
			<th><?php esc_html_e( 'Display Name', 'ai-seo-pilot' ); ?></th>
			<th><?php esc_html_e( 'Service', 'ai-seo-pilot' ); ?></th>
			<th style="width:80px"></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $all_bots as $id => $bot ) : ?>
		<tr>
			<td><code><?php echo esc_html( $id ); ?></code></td>
			<td><?php echo esc_html( $bot['name'] ); ?></td>
			<td><?php echo esc_html( $bot['service'] ); ?></td>
			<td style="text-align:center">
				<?php if ( empty( $bot['builtin'] ) ) : ?>
					<button type="button" class="button-link ai-seo-pilot-custom-badge"
						style="color:#f43f5e"
						title="<?php esc_attr_e( 'Remove', 'ai-seo-pilot' ); ?>"
						data-identifier="<?php echo esc_attr( $id ); ?>"
						onclick="this.closest('tr').remove()">
						<?php esc_html_e( 'Remove', 'ai-seo-pilot' ); ?>
					</button>
				<?php else : ?>
					<span style="color:#8c8f94"><?php esc_html_e( 'built-in', 'ai-seo-pilot' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<?php
$custom_bots = get_option( 'ai_seo_pilot_custom_bots', [] );
?>
<div id="ai-seo-pilot-custom-bots-inputs">
	<?php if ( is_array( $custom_bots ) ) : ?>
		<?php foreach ( $custom_bots as $i => $bot ) : ?>
			<div class="ai-seo-pilot-custom-bot-row" data-identifier="<?php echo esc_attr( $bot['identifier'] ); ?>">
				<input type="hidden" name="ai_seo_pilot_custom_bots[<?php echo (int) $i; ?>][identifier]" value="<?php echo esc_attr( $bot['identifier'] ); ?>">
				<input type="hidden" name="ai_seo_pilot_custom_bots[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $bot['name'] ); ?>">
				<input type="hidden" name="ai_seo_pilot_custom_bots[<?php echo (int) $i; ?>][service]" value="<?php echo esc_attr( $bot['service'] ); ?>">
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

<?php Pilot_Admin_UI::section_header( __( 'Add Custom Bot', 'ai-seo-pilot' ) ); ?>
<div style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
	<div>
		<label style="display:block;font-size:12px;margin-bottom:2px"><?php esc_html_e( 'UA Identifier', 'ai-seo-pilot' ); ?></label>
		<input type="text" id="ai-seo-pilot-new-bot-id" class="pilot-input" style="width:180px;max-width:180px" placeholder="e.g. MetaBot">
	</div>
	<div>
		<label style="display:block;font-size:12px;margin-bottom:2px"><?php esc_html_e( 'Display Name', 'ai-seo-pilot' ); ?></label>
		<input type="text" id="ai-seo-pilot-new-bot-name" class="pilot-input" style="width:180px;max-width:180px" placeholder="e.g. MetaBot">
	</div>
	<div>
		<label style="display:block;font-size:12px;margin-bottom:2px"><?php esc_html_e( 'Service', 'ai-seo-pilot' ); ?></label>
		<input type="text" id="ai-seo-pilot-new-bot-service" class="pilot-input" style="width:180px;max-width:180px" placeholder="e.g. Meta AI">
	</div>
	<button type="button" class="button" id="ai-seo-pilot-add-bot"><?php esc_html_e( 'Add Bot', 'ai-seo-pilot' ); ?></button>
</div>
<p class="pilot-description"><?php esc_html_e( 'Custom bots will be tracked alongside the built-in list. Click Save Changes to apply.', 'ai-seo-pilot' ); ?></p>

<?php
Pilot_Admin_UI::card_end();

Pilot_Admin_UI::tab_panel_end();

/* =============================================
   Content Optimization Tab
   ============================================= */

Pilot_Admin_UI::tab_panel_start( 'content-optimization', $active_tab );

Pilot_Admin_UI::card_start(
	__( 'AI Content Features', 'ai-seo-pilot' ),
	__( 'AI-powered content optimization features. All features require an AI provider to be configured.', 'ai-seo-pilot' )
);

Pilot_Admin_UI::checkbox(
	'ai_seo_pilot_readability_enabled',
	get_option( 'ai_seo_pilot_readability_enabled', 'yes' ) === 'yes',
	__( 'AI Readability Analysis', 'ai-seo-pilot' ),
	__( 'Analyze content readability using AI', 'ai-seo-pilot' ),
	'yes'
);

Pilot_Admin_UI::checkbox(
	'ai_seo_pilot_content_quality_enabled',
	get_option( 'ai_seo_pilot_content_quality_enabled', 'yes' ) === 'yes',
	__( 'AI Content Quality', 'ai-seo-pilot' ),
	__( 'Evaluate content quality, detect thin content and duplicates', 'ai-seo-pilot' ),
	'yes'
);

Pilot_Admin_UI::checkbox(
	'ai_seo_pilot_keyword_tracker_enabled',
	get_option( 'ai_seo_pilot_keyword_tracker_enabled', 'yes' ) === 'yes',
	__( 'AI Keyword Tracker', 'ai-seo-pilot' ),
	__( 'Extract keywords, track density, detect cannibalization', 'ai-seo-pilot' ),
	'yes'
);

Pilot_Admin_UI::checkbox(
	'ai_seo_pilot_internal_linking_enabled',
	get_option( 'ai_seo_pilot_internal_linking_enabled', 'yes' ) === 'yes',
	__( 'AI Internal Linking', 'ai-seo-pilot' ),
	__( 'AI-powered internal link suggestions and orphan detection', 'ai-seo-pilot' ),
	'yes'
);

Pilot_Admin_UI::checkbox(
	'ai_seo_pilot_content_optimizer_enabled',
	get_option( 'ai_seo_pilot_content_optimizer_enabled', 'yes' ) === 'yes',
	__( 'AI Content Optimizer', 'ai-seo-pilot' ),
	__( 'Rewrite paragraphs, adjust tone, generate sections with AI', 'ai-seo-pilot' ),
	'yes'
);

$tones = [
	'authoritative'  => __( 'Authoritative', 'ai-seo-pilot' ),
	'conversational' => __( 'Conversational', 'ai-seo-pilot' ),
	'technical'      => __( 'Technical', 'ai-seo-pilot' ),
	'simplified'     => __( 'Simplified', 'ai-seo-pilot' ),
];

Pilot_Admin_UI::select(
	'ai_seo_pilot_default_tone',
	get_option( 'ai_seo_pilot_default_tone', 'authoritative' ),
	__( 'Default Tone', 'ai-seo-pilot' ),
	$tones,
	[ 'description' => __( 'Default tone for content rewriting and optimization.', 'ai-seo-pilot' ) ]
);

Pilot_Admin_UI::card_end();

Pilot_Admin_UI::alert(
	__( 'Every AI analysis consumes API tokens. Results are cached and only regenerated when you explicitly request it or when content is saved.', 'ai-seo-pilot' ),
	'warning'
);

Pilot_Admin_UI::tab_panel_end();

/* =============================================
   Advanced Tab
   ============================================= */

Pilot_Admin_UI::tab_panel_start( 'advanced', $active_tab );

Pilot_Admin_UI::card_start( __( 'Advanced Settings', 'ai-seo-pilot' ) );

Pilot_Admin_UI::checkbox(
	'ai_seo_pilot_robots_txt_enhance',
	get_option( 'ai_seo_pilot_robots_txt_enhance', 'yes' ) === 'yes',
	__( 'robots.txt Enhancement', 'ai-seo-pilot' ),
	__( 'Add AI bot Allow directives and sitemap URL to robots.txt', 'ai-seo-pilot' ),
	'yes'
);

Pilot_Admin_UI::checkbox(
	'ai_seo_pilot_x_robots_tag',
	get_option( 'ai_seo_pilot_x_robots_tag', 'yes' ) === 'yes',
	__( 'X-Robots-Tag Header', 'ai-seo-pilot' ),
	__( 'Send X-Robots-Tag: all header on frontend pages', 'ai-seo-pilot' ),
	'yes'
);

Pilot_Admin_UI::checkbox(
	'ai_seo_pilot_remove_data_on_uninstall',
	get_option( 'ai_seo_pilot_remove_data_on_uninstall', 'no' ) === 'yes',
	__( 'Remove Data on Uninstall', 'ai-seo-pilot' ),
	__( 'Delete all plugin data (options, database tables, post meta) when the plugin is uninstalled', 'ai-seo-pilot' ),
	'yes'
);

Pilot_Admin_UI::alert(
	__( 'Warning: Enabling "Remove Data on Uninstall" is irreversible. All data will be permanently deleted.', 'ai-seo-pilot' ),
	'error'
);

Pilot_Admin_UI::card_end();

Pilot_Admin_UI::tab_panel_end();

Pilot_Admin_UI::tabs_end();

Pilot_Admin_UI::submit();

echo '</form>';

Pilot_Admin_UI::page_end();
