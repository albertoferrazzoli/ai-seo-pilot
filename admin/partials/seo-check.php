<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$checker = new AI_SEO_Pilot_SEO_Checker();
$results = $checker->run_all();
$score   = $results['score'];

// Grade & color.
if ( $score >= 90 ) {
	$grade       = 'A';
	$grade_color = '#22c55e';
	$grade_desc  = __( 'Excellent! Your site SEO is very strong.', 'ai-seo-pilot' );
} elseif ( $score >= 75 ) {
	$grade       = 'B';
	$grade_color = '#84cc16';
	$grade_desc  = __( 'Good. A few improvements will boost your AI visibility.', 'ai-seo-pilot' );
} elseif ( $score >= 60 ) {
	$grade       = 'C';
	$grade_color = '#eab308';
	$grade_desc  = __( 'Fair. Several areas need attention for better AI discoverability.', 'ai-seo-pilot' );
} elseif ( $score >= 40 ) {
	$grade       = 'D';
	$grade_color = '#f97316';
	$grade_desc  = __( 'Poor. Significant improvements needed for AI search engines.', 'ai-seo-pilot' );
} else {
	$grade       = 'F';
	$grade_color = '#ef4444';
	$grade_desc  = __( 'Critical. Your site is poorly optimized for AI crawlers.', 'ai-seo-pilot' );
}

// SVG ring.
$ring_radius        = 54;
$ring_circumference = 2 * M_PI * $ring_radius;
$ring_offset        = $ring_circumference - ( $ring_circumference * $score / 100 );

// Status icons.
$status_icons = array(
	'pass'    => 'yes-alt',
	'warning' => 'warning',
	'fail'    => 'dismiss',
);

// Sort checks: fail first, then warning, then pass.
$order  = array( 'fail' => 0, 'warning' => 1, 'pass' => 2 );
$checks = $results['checks'];
usort( $checks, function ( $a, $b ) use ( $order ) {
	return ( $order[ $a['status'] ] ?? 3 ) - ( $order[ $b['status'] ] ?? 3 );
} );

// Group by category after sorting.
$grouped = array();
foreach ( $checks as $check ) {
	$grouped[ $check['category'] ][] = $check;
}
?>
<div class="wrap aisp-wrap">
	<h1><?php esc_html_e( 'SEO Check', 'ai-seo-pilot' ); ?></h1>

	<!-- Dashboard -->
	<div class="aisp-dashboard">
		<!-- Ring Card -->
		<div class="aisp-ring-card">
			<div class="aisp-ring-container">
				<svg viewBox="0 0 128 128" class="aisp-ring-svg">
					<circle cx="64" cy="64" r="<?php echo esc_attr( $ring_radius ); ?>" fill="none" stroke="#e5e7eb" stroke-width="10" />
					<circle cx="64" cy="64" r="<?php echo esc_attr( $ring_radius ); ?>" fill="none"
						stroke="<?php echo esc_attr( $grade_color ); ?>"
						stroke-width="10" stroke-linecap="round"
						stroke-dasharray="<?php echo esc_attr( $ring_circumference ); ?>"
						stroke-dashoffset="<?php echo esc_attr( $ring_offset ); ?>"
						transform="rotate(-90 64 64)"
						class="aisp-ring-progress" />
				</svg>
				<div class="aisp-ring-label">
					<span class="aisp-ring-grade" style="color:<?php echo esc_attr( $grade_color ); ?>;"><?php echo esc_html( $grade ); ?></span>
					<span class="aisp-ring-score"><?php echo esc_html( $score ); ?>/100</span>
				</div>
			</div>
			<p class="aisp-ring-desc"><?php echo esc_html( $grade_desc ); ?></p>
		</div>

		<!-- Stats Grid -->
		<div class="aisp-stats">
			<div class="aisp-stat aisp-stat-pass">
				<span class="aisp-stat-num"><?php echo esc_html( $results['passed'] ); ?></span>
				<span class="aisp-stat-label"><?php esc_html_e( 'Passed', 'ai-seo-pilot' ); ?></span>
			</div>
			<div class="aisp-stat aisp-stat-fail">
				<span class="aisp-stat-num"><?php echo esc_html( $results['failed'] ); ?></span>
				<span class="aisp-stat-label"><?php esc_html_e( 'Failed', 'ai-seo-pilot' ); ?></span>
			</div>
			<div class="aisp-stat aisp-stat-warn">
				<span class="aisp-stat-num"><?php echo esc_html( $results['warnings'] ); ?></span>
				<span class="aisp-stat-label"><?php esc_html_e( 'Warnings', 'ai-seo-pilot' ); ?></span>
			</div>
			<div class="aisp-stat aisp-stat-total">
				<span class="aisp-stat-num"><?php echo esc_html( $results['total'] ); ?></span>
				<span class="aisp-stat-label"><?php esc_html_e( 'Total Checks', 'ai-seo-pilot' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Check Results by Category -->
	<?php foreach ( $grouped as $category => $cat_checks ) : ?>
		<h2 style="margin: 24px 0 8px; font-size: 15px;"><?php echo esc_html( $category ); ?></h2>
		<div class="aisp-checks-grid">
			<?php foreach ( $cat_checks as $check ) : ?>
				<div class="aisp-check-card aisp-check-card--<?php echo esc_attr( $check['status'] ); ?>">
					<div class="aisp-check-icon aisp-check-icon--<?php echo esc_attr( $check['status'] ); ?>">
						<span class="dashicons dashicons-<?php echo esc_attr( $status_icons[ $check['status'] ] ); ?>"></span>
					</div>
					<div class="aisp-check-body">
						<div class="aisp-check-top">
							<strong class="aisp-check-title"><?php echo esc_html( $check['label'] ); ?></strong>
							<?php if ( ! empty( $check['severity'] ) ) : ?>
								<span class="aisp-severity aisp-severity-<?php echo esc_attr( $check['severity'] ); ?>">
									<?php echo esc_html( ucfirst( $check['severity'] ) ); ?>
								</span>
							<?php endif; ?>
						</div>
						<p class="aisp-check-msg"><?php echo wp_kses_post( $check['message'] ); ?></p>
						<?php if ( ! empty( $check['fix'] ) ) : ?>
							<div class="aisp-check-fix">
								<span class="dashicons dashicons-lightbulb"></span>
								<span class="aisp-check-fix-text"><?php echo esc_html( $check['fix'] ); ?></span>
								<?php if ( ! empty( $check['fix_action'] ) ) :
									$action = $check['fix_action'];
									if ( 'link' === $action['type'] ) : ?>
										<a href="<?php echo esc_url( $action['url'] ); ?>" class="button button-small aisp-fix-btn"><?php echo esc_html( $action['label'] ); ?></a>
									<?php elseif ( 'option_toggle' === $action['type'] ) : ?>
										<button type="button" class="button button-small aisp-fix-btn" data-fix="option_toggle" data-option="<?php echo esc_attr( $action['option'] ); ?>" data-value="<?php echo esc_attr( $action['value'] ); ?>"><?php echo esc_html( $action['label'] ); ?></button>
									<?php elseif ( 'ajax' === $action['type'] ) : ?>
										<button type="button" class="button button-small aisp-fix-btn" data-fix="ajax" data-action="<?php echo esc_attr( $action['action'] ); ?>"><?php echo esc_html( $action['label'] ); ?></button>
									<?php endif; ?>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>
</div>
