<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap ai-seo-pilot-wrap">
	<h1><?php esc_html_e( 'Settings', 'ai-seo-pilot' ); ?></h1>

	<form method="post" action="options.php">
		<?php settings_fields( 'ai_seo_pilot_general' ); ?>

		<!-- Tab Navigation -->
		<h2 class="nav-tab-wrapper ai-seo-pilot-tabs">
			<a href="#general" class="nav-tab nav-tab-active" data-tab="general">
				<?php esc_html_e( 'General', 'ai-seo-pilot' ); ?>
			</a>
			<a href="#ai-providers" class="nav-tab" data-tab="ai-providers">
				<?php esc_html_e( 'AI Providers', 'ai-seo-pilot' ); ?>
			</a>
			<a href="#llms-txt" class="nav-tab" data-tab="llms-txt">
				<?php esc_html_e( 'LLMS', 'ai-seo-pilot' ); ?>
			</a>
			<a href="#schema" class="nav-tab" data-tab="schema">
				<?php esc_html_e( 'Schema', 'ai-seo-pilot' ); ?>
			</a>
			<a href="#content-analysis" class="nav-tab" data-tab="content-analysis">
				<?php esc_html_e( 'Content Analysis', 'ai-seo-pilot' ); ?>
			</a>
			<a href="#ai-bots" class="nav-tab" data-tab="ai-bots">
				<?php esc_html_e( 'AI Bots', 'ai-seo-pilot' ); ?>
			</a>
			<a href="#advanced" class="nav-tab" data-tab="advanced">
				<?php esc_html_e( 'Advanced', 'ai-seo-pilot' ); ?>
			</a>
		</h2>

		<!-- General Tab -->
		<div id="tab-general" class="ai-seo-pilot-tab-content active">
			<h2><?php esc_html_e( 'License', 'ai-seo-pilot' ); ?></h2>
			<table class="form-table">
				<?php Pilot_Updater::render_license_field( 'ai-seo-pilot' ); ?>
			</table>

			<h2><?php esc_html_e( 'Features', 'ai-seo-pilot' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="ai_seo_pilot_schema_enabled"><?php esc_html_e( 'Schema.org JSON-LD', 'ai-seo-pilot' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="ai_seo_pilot_schema_enabled" id="ai_seo_pilot_schema_enabled" value="yes"
								<?php checked( get_option( 'ai_seo_pilot_schema_enabled', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Enable automatic Schema.org JSON-LD output', 'ai-seo-pilot' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="ai_seo_pilot_analyzer_enabled"><?php esc_html_e( 'Content Analyzer', 'ai-seo-pilot' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="ai_seo_pilot_analyzer_enabled" id="ai_seo_pilot_analyzer_enabled" value="yes"
								<?php checked( get_option( 'ai_seo_pilot_analyzer_enabled', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Enable Gutenberg sidebar content analysis', 'ai-seo-pilot' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="ai_seo_pilot_ai_visibility_enabled"><?php esc_html_e( 'AI Bot Tracking', 'ai-seo-pilot' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="ai_seo_pilot_ai_visibility_enabled" id="ai_seo_pilot_ai_visibility_enabled" value="yes"
								<?php checked( get_option( 'ai_seo_pilot_ai_visibility_enabled', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Track AI bot visits to your site', 'ai-seo-pilot' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="ai_seo_pilot_sitemap_ai_enabled"><?php esc_html_e( 'AI Sitemap', 'ai-seo-pilot' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="ai_seo_pilot_sitemap_ai_enabled" id="ai_seo_pilot_sitemap_ai_enabled" value="yes"
								<?php checked( get_option( 'ai_seo_pilot_sitemap_ai_enabled', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Enable AI-optimized sitemap at /ai-sitemap.xml', 'ai-seo-pilot' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<!-- AI Providers Tab -->
		<div id="tab-ai-providers" class="ai-seo-pilot-tab-content" style="display:none;">
			<p><?php esc_html_e( 'Connect an AI API to unlock AI-powered SEO generation. The plugin works fully without an API key — this adds AI-assisted content generation on top.', 'ai-seo-pilot' ); ?></p>

			<?php
			$plugin            = AI_SEO_Pilot::get_instance();
			$ai_providers      = $plugin->ai_engine->get_providers();
			$active_provider   = get_option( 'ai_seo_pilot_ai_provider', 'openai' );
			?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="ai_seo_pilot_ai_provider"><?php esc_html_e( 'Provider', 'ai-seo-pilot' ); ?></label>
					</th>
					<td>
						<select name="ai_seo_pilot_ai_provider" id="ai_seo_pilot_ai_provider">
							<?php foreach ( $ai_providers as $key => $prov ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $active_provider, $key ); ?>>
									<?php echo esc_html( $prov['label'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>

			<!-- Per-provider settings sections -->
			<?php foreach ( $ai_providers as $prov_key => $prov ) : ?>
				<div class="aisp-provider-section" data-provider="<?php echo esc_attr( $prov_key ); ?>"
					style="<?php echo $active_provider !== $prov_key ? 'display:none;' : ''; ?>">

					<table class="form-table">
						<?php if ( 'server_url' === $prov['auth'] ) : ?>
							<!-- Server URL (Ollama) -->
							<tr>
								<th scope="row">
									<label for="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_server_url">
										<?php esc_html_e( 'Server URL', 'ai-seo-pilot' ); ?>
									</label>
								</th>
								<td>
									<?php $server_url = get_option( "ai_seo_pilot_ai_{$prov_key}_server_url", $prov['default_url'] ?? '' ); ?>
									<input type="url"
										name="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_server_url"
										id="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_server_url"
										class="regular-text aisp-provider-credential"
										value="<?php echo esc_attr( $server_url ); ?>"
										placeholder="<?php echo esc_attr( $prov['default_url'] ?? 'http://localhost:11434' ); ?>">
									<p class="description">
										<?php esc_html_e( 'URL of your local Ollama instance. No API key required.', 'ai-seo-pilot' ); ?>
									</p>
								</td>
							</tr>
						<?php else : ?>
							<!-- API Key -->
							<tr>
								<th scope="row">
									<label for="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_api_key">
										<?php esc_html_e( 'API Key', 'ai-seo-pilot' ); ?>
									</label>
								</th>
								<td>
									<?php $api_key = get_option( "ai_seo_pilot_ai_{$prov_key}_api_key", '' ); ?>
									<input type="password"
										name="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_api_key"
										id="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_api_key"
										class="regular-text aisp-provider-credential"
										autocomplete="off"
										value="<?php echo esc_attr( $api_key ); ?>">
									<?php if ( $api_key ) : ?>
										<span class="ai-seo-pilot-status success"><?php esc_html_e( 'Configured', 'ai-seo-pilot' ); ?></span>
									<?php endif; ?>
									<?php if ( ! empty( $prov['key_url'] ) ) : ?>
										<p class="description">
											<?php
											printf(
												/* translators: %s: provider key URL */
												esc_html__( 'Get your API key from %s', 'ai-seo-pilot' ),
												'<a href="' . esc_url( $prov['key_url'] ) . '" target="_blank">' . esc_html( $prov['label'] ) . '</a>'
											);
											?>
										</p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endif; ?>

						<!-- Model -->
						<tr>
							<th scope="row">
								<label for="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_model">
									<?php esc_html_e( 'Model', 'ai-seo-pilot' ); ?>
								</label>
							</th>
							<td>
								<?php
								$current_model = get_option( "ai_seo_pilot_ai_{$prov_key}_model", '' );
								$known_models  = array_keys( $prov['models'] );
								$is_custom     = ! empty( $current_model ) && ! in_array( $current_model, $known_models, true );
								?>
								<select
									id="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_model_select"
									class="aisp-provider-model aisp-model-select"
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
										class="regular-text aisp-model-custom"
										value="<?php echo $is_custom ? esc_attr( $current_model ) : ''; ?>"
										placeholder="<?php esc_attr_e( 'e.g. phi3, codellama, solar', 'ai-seo-pilot' ); ?>"
										style="<?php echo $is_custom ? '' : 'display:none;'; ?> margin-top:6px;">
								<?php endif; ?>
								<input type="hidden"
									name="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_model"
									id="ai_seo_pilot_ai_<?php echo esc_attr( $prov_key ); ?>_model"
									value="<?php echo esc_attr( $current_model ); ?>">
							</td>
						</tr>
					</table>
				</div>
			<?php endforeach; ?>

			<!-- Test Connection -->
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Test Connection', 'ai-seo-pilot' ); ?></th>
					<td>
						<div style="display:flex; align-items:center; gap:10px;">
							<button type="button" class="button" id="ai-seo-pilot-test-connection">
								<?php esc_html_e( 'Test Connection', 'ai-seo-pilot' ); ?>
							</button>
							<span id="ai-seo-pilot-test-status" class="ai-seo-pilot-status"></span>
						</div>
						<div id="ai-seo-pilot-test-result" style="display:none; margin-top:10px;">
							<pre class="ai-seo-pilot-code-preview" style="max-height:100px; font-size:12px;"></pre>
						</div>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'AI-Powered Features', 'ai-seo-pilot' ); ?></h3>
			<p><?php esc_html_e( 'Once configured, the following AI features become available:', 'ai-seo-pilot' ); ?></p>
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
		</div>

		<!-- llms.txt Tab -->
		<div id="tab-llms-txt" class="ai-seo-pilot-tab-content" style="display:none;">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="ai_seo_pilot_llms_txt_mode"><?php esc_html_e( 'Generation Mode', 'ai-seo-pilot' ); ?></label>
					</th>
					<td>
						<select name="ai_seo_pilot_llms_txt_mode" id="ai_seo_pilot_llms_txt_mode">
							<option value="auto" <?php selected( get_option( 'ai_seo_pilot_llms_txt_mode', 'auto' ), 'auto' ); ?>>
								<?php esc_html_e( 'Auto-generate', 'ai-seo-pilot' ); ?>
							</option>
							<option value="manual" <?php selected( get_option( 'ai_seo_pilot_llms_txt_mode', 'auto' ), 'manual' ); ?>>
								<?php esc_html_e( 'Manual', 'ai-seo-pilot' ); ?>
							</option>
						</select>
						<p class="description"><?php esc_html_e( 'Auto-generate uses your site title, description, top posts, and key pages.', 'ai-seo-pilot' ); ?></p>
					</td>
				</tr>
				<tr id="llms-txt-manual-row">
					<th scope="row">
						<label for="ai_seo_pilot_llms_txt_manual"><?php esc_html_e( 'Manual Content', 'ai-seo-pilot' ); ?></label>
					</th>
					<td>
						<textarea name="ai_seo_pilot_llms_txt_manual" id="ai_seo_pilot_llms_txt_manual" rows="15" class="large-text code"><?php
							echo esc_textarea( get_option( 'ai_seo_pilot_llms_txt_manual', '' ) );
						?></textarea>
						<p class="description"><?php esc_html_e( 'Enter your custom llms.txt content. Markdown format recommended.', 'ai-seo-pilot' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Schema Tab -->
		<div id="tab-schema" class="ai-seo-pilot-tab-content" style="display:none;">
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Organization Schema', 'ai-seo-pilot' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ai_seo_pilot_schema_organization" value="yes"
								<?php checked( get_option( 'ai_seo_pilot_schema_organization', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Output Organization schema on front page', 'ai-seo-pilot' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Breadcrumb Schema', 'ai-seo-pilot' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ai_seo_pilot_schema_breadcrumbs" value="yes"
								<?php checked( get_option( 'ai_seo_pilot_schema_breadcrumbs', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Output BreadcrumbList schema on all pages', 'ai-seo-pilot' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'WebSite Schema', 'ai-seo-pilot' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ai_seo_pilot_schema_website" value="yes"
								<?php checked( get_option( 'ai_seo_pilot_schema_website', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Output WebSite schema on front page', 'ai-seo-pilot' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="ai_seo_pilot_organization_same_as"><?php esc_html_e( 'Social Profiles (sameAs)', 'ai-seo-pilot' ); ?></label>
					</th>
					<td>
						<textarea name="ai_seo_pilot_organization_same_as" id="ai_seo_pilot_organization_same_as" rows="5" class="large-text"><?php
							echo esc_textarea( get_option( 'ai_seo_pilot_organization_same_as', '' ) );
						?></textarea>
						<p class="description"><?php esc_html_e( 'One URL per line. Used in Organization schema sameAs property.', 'ai-seo-pilot' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Content Analysis Tab -->
		<?php
		$ca_settings = get_option( 'ai_seo_pilot_content_analysis', array() );
		$ca_defaults = AI_SEO_Pilot_Content_Analyzer::get_defaults();
		$ca_settings = wp_parse_args( $ca_settings, $ca_defaults );
		// Merge each check's settings with defaults.
		foreach ( $ca_defaults['checks'] as $key => $def ) {
			$ca_settings['checks'][ $key ] = wp_parse_args(
				isset( $ca_settings['checks'][ $key ] ) ? $ca_settings['checks'][ $key ] : array(),
				$def
			);
		}
		?>
		<div id="tab-content-analysis" class="ai-seo-pilot-tab-content" style="display:none;">
			<p><?php esc_html_e( 'Configure which checks run in the Gutenberg editor sidebar and how they are scored.', 'ai-seo-pilot' ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="ca_ai_ready_threshold"><?php esc_html_e( 'AI-Ready Threshold', 'ai-seo-pilot' ); ?></label>
					</th>
					<td>
						<input type="number" id="ca_ai_ready_threshold"
							name="ai_seo_pilot_content_analysis[ai_ready_threshold]"
							value="<?php echo esc_attr( $ca_settings['ai_ready_threshold'] ); ?>"
							min="0" max="100" class="small-text">
						<span>%</span>
						<p class="description"><?php esc_html_e( 'Percentage of total points required to display the "AI-Ready" badge.', 'ai-seo-pilot' ); ?></p>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Scoring Checks', 'ai-seo-pilot' ); ?></h3>
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
					$check_meta = array(
						'direct_answer' => array(
							'label'       => __( 'Direct Answer', 'ai-seo-pilot' ),
							'description' => __( 'First paragraph provides a clear, concise answer', 'ai-seo-pilot' ),
							'threshold'   => array(
								'key'   => 'max_chars',
								'label' => __( 'Max chars', 'ai-seo-pilot' ),
								'min'   => 50,
								'max'   => 500,
							),
						),
						'qa_structure' => array(
							'label'       => __( 'Q&A Structure', 'ai-seo-pilot' ),
							'description' => __( 'Contains question headings (H2/H3 with ?)', 'ai-seo-pilot' ),
							'threshold'   => array(
								'key'   => 'min_questions',
								'label' => __( 'Min questions', 'ai-seo-pilot' ),
								'min'   => 1,
								'max'   => 10,
							),
						),
						'definitions' => array(
							'label'       => __( 'Definitions', 'ai-seo-pilot' ),
							'description' => __( 'Contains definition patterns ("X is...", "defined as")', 'ai-seo-pilot' ),
							'threshold'   => array(
								'key'   => 'min_definitions',
								'label' => __( 'Min definitions', 'ai-seo-pilot' ),
								'min'   => 1,
								'max'   => 10,
							),
						),
						'paragraph_length' => array(
							'label'       => __( 'Paragraph Length', 'ai-seo-pilot' ),
							'description' => __( 'Average paragraph within target word count', 'ai-seo-pilot' ),
							'threshold'   => array(
								'key'   => 'max_avg_words',
								'label' => __( 'Max avg words', 'ai-seo-pilot' ),
								'min'   => 50,
								'max'   => 500,
							),
						),
						'list_optimization' => array(
							'label'       => __( 'List Optimization', 'ai-seo-pilot' ),
							'description' => __( 'Contains ordered/unordered lists or tables', 'ai-seo-pilot' ),
							'threshold'   => array(
								'key'   => 'min_lists',
								'label' => __( 'Min lists/tables', 'ai-seo-pilot' ),
								'min'   => 1,
								'max'   => 10,
							),
						),
						'entity_density' => array(
							'label'       => __( 'Entity Density', 'ai-seo-pilot' ),
							'description' => __( 'Proper nouns and named entities per 100 words', 'ai-seo-pilot' ),
							'threshold'   => array(
								'key'   => 'min_density',
								'label' => __( 'Min % density', 'ai-seo-pilot' ),
								'min'   => 1,
								'max'   => 10,
							),
						),
						'citable_statistics' => array(
							'label'       => __( 'Citable Statistics', 'ai-seo-pilot' ),
							'description' => __( 'Numbers with context (percentages, amounts)', 'ai-seo-pilot' ),
							'threshold'   => array(
								'key'   => 'min_stats',
								'label' => __( 'Min statistics', 'ai-seo-pilot' ),
								'min'   => 1,
								'max'   => 10,
							),
						),
						'semantic_completeness' => array(
							'label'       => __( 'Semantic Completeness', 'ai-seo-pilot' ),
							'description' => __( 'Sufficient word count with intro and conclusion', 'ai-seo-pilot' ),
							'threshold'   => array(
								'key'   => 'min_words',
								'label' => __( 'Min words', 'ai-seo-pilot' ),
								'min'   => 100,
								'max'   => 2000,
								'step'  => 50,
							),
						),
						'snippet_optimization' => array(
							'label'       => __( 'Snippet Optimization', 'ai-seo-pilot' ),
							'description' => __( 'Concise summary paragraph with bold formatting', 'ai-seo-pilot' ),
							'threshold'   => array(
								'key'   => 'max_summary_words',
								'label' => __( 'Max summary words', 'ai-seo-pilot' ),
								'min'   => 20,
								'max'   => 150,
							),
						),
						'freshness_signals' => array(
							'label'       => __( 'Freshness Signals', 'ai-seo-pilot' ),
							'description' => __( 'Date references, "updated", "latest" keywords', 'ai-seo-pilot' ),
							'threshold'   => array(
								'key'   => 'min_signals',
								'label' => __( 'Min signals', 'ai-seo-pilot' ),
								'min'   => 1,
								'max'   => 10,
							),
						),
					);

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
						<td style="text-align:right;font-size:12px;color:#646970;white-space:nowrap">
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
						<td><span class="description"><?php echo esc_html( $meta['description'] ); ?></span></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description" style="margin-top:8px">
				<?php esc_html_e( 'Weight determines the maximum points for each check. The total score is calculated as a percentage of achieved points vs. total possible points.', 'ai-seo-pilot' ); ?>
			</p>
		</div>

		<!-- AI Bots Tab -->
		<div id="tab-ai-bots" class="ai-seo-pilot-tab-content" style="display:none;">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="ai_seo_pilot_bot_retention_days"><?php esc_html_e( 'Data Retention', 'ai-seo-pilot' ); ?></label>
					</th>
					<td>
						<input type="number" name="ai_seo_pilot_bot_retention_days" id="ai_seo_pilot_bot_retention_days"
							class="small-text" min="7" max="365"
							value="<?php echo esc_attr( get_option( 'ai_seo_pilot_bot_retention_days', 90 ) ); ?>">
						<?php esc_html_e( 'days', 'ai-seo-pilot' ); ?>
						<p class="description"><?php esc_html_e( 'Bot visit data older than this will be automatically deleted.', 'ai-seo-pilot' ); ?></p>
					</td>
				</tr>
			</table>
			<h3><?php esc_html_e( 'Tracked AI Bots', 'ai-seo-pilot' ); ?></h3>
			<?php $all_bots = AI_SEO_Pilot_AI_Visibility::get_all_bots(); ?>
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
									style="color:#d63638"
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
			// Render hidden inputs for existing custom bots.
			$custom_bots = get_option( 'ai_seo_pilot_custom_bots', array() );
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

			<h4><?php esc_html_e( 'Add Custom Bot', 'ai-seo-pilot' ); ?></h4>
			<div style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
				<div>
					<label style="display:block;font-size:12px;margin-bottom:2px"><?php esc_html_e( 'UA Identifier', 'ai-seo-pilot' ); ?></label>
					<input type="text" id="ai-seo-pilot-new-bot-id" class="regular-text" style="width:180px" placeholder="e.g. MetaBot">
				</div>
				<div>
					<label style="display:block;font-size:12px;margin-bottom:2px"><?php esc_html_e( 'Display Name', 'ai-seo-pilot' ); ?></label>
					<input type="text" id="ai-seo-pilot-new-bot-name" class="regular-text" style="width:180px" placeholder="e.g. MetaBot">
				</div>
				<div>
					<label style="display:block;font-size:12px;margin-bottom:2px"><?php esc_html_e( 'Service', 'ai-seo-pilot' ); ?></label>
					<input type="text" id="ai-seo-pilot-new-bot-service" class="regular-text" style="width:180px" placeholder="e.g. Meta AI">
				</div>
				<button type="button" class="button" id="ai-seo-pilot-add-bot"><?php esc_html_e( 'Add Bot', 'ai-seo-pilot' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Custom bots will be tracked alongside the built-in list. Click Save Changes to apply.', 'ai-seo-pilot' ); ?></p>
		</div>

		<!-- Advanced Tab -->
		<div id="tab-advanced" class="ai-seo-pilot-tab-content" style="display:none;">
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'robots.txt Enhancement', 'ai-seo-pilot' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ai_seo_pilot_robots_txt_enhance" value="yes"
								<?php checked( get_option( 'ai_seo_pilot_robots_txt_enhance', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Add AI bot Allow directives and sitemap URL to robots.txt', 'ai-seo-pilot' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'X-Robots-Tag Header', 'ai-seo-pilot' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ai_seo_pilot_x_robots_tag" value="yes"
								<?php checked( get_option( 'ai_seo_pilot_x_robots_tag', 'yes' ), 'yes' ); ?>>
							<?php esc_html_e( 'Send X-Robots-Tag: all header on frontend pages', 'ai-seo-pilot' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Remove Data on Uninstall', 'ai-seo-pilot' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ai_seo_pilot_remove_data_on_uninstall" value="yes"
								<?php checked( get_option( 'ai_seo_pilot_remove_data_on_uninstall', 'no' ), 'yes' ); ?>>
							<?php esc_html_e( 'Delete all plugin data (options, database tables, post meta) when the plugin is uninstalled', 'ai-seo-pilot' ); ?>
						</label>
						<p class="description" style="color:#d63638;"><?php esc_html_e( 'Warning: This action is irreversible.', 'ai-seo-pilot' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<?php submit_button(); ?>
	</form>
</div>
