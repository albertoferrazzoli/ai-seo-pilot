<?php
/**
 * Pilot Admin UI - Core helper class.
 *
 * Provides a static API for rendering modern, consistent admin settings
 * pages across all Pilot plugins. Every method outputs escaped HTML
 * directly, matching the WordPress template pattern.
 *
 * @version 1.1.0
 * @package PilotAdminUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'Pilot_Admin_UI' ) ) {
	return;
}

class Pilot_Admin_UI {

	/** @var bool Whether assets have been enqueued for this request. */
	private static $enqueued = false;

	/* =============================================
	 *  Asset Management
	 * ============================================= */

	/**
	 * Enqueue CSS + JS assets.
	 *
	 * Called automatically by page_start(), or explicitly if needed.
	 */
	public static function enqueue(): void {
		if ( self::$enqueued ) {
			return;
		}
		self::$enqueued = true;

		$url = PILOT_ADMIN_UI_URL . 'assets/';
		$ver = PILOT_ADMIN_UI_VERSION;

		wp_enqueue_style(
			'pilot-admin-ui',
			$url . 'css/pilot-admin-ui.css',
			[],
			$ver
		);

		wp_enqueue_script(
			'pilot-admin-ui',
			$url . 'js/pilot-admin-ui.js',
			[ 'jquery' ],
			$ver,
			true
		);
	}

	/* =============================================
	 *  Page Layout
	 * ============================================= */

	/**
	 * Open the page wrapper.
	 *
	 * @param string $title Page title.
	 * @param string $badge Optional badge text (e.g. version or "PRO").
	 * @param bool   $wide  If true, removes max-width constraint.
	 */
	public static function page_start( string $title, string $badge = '', bool $wide = false ): void {
		self::enqueue();

		$class = 'wrap pilot-wrap' . ( $wide ? ' pilot-wrap--wide' : '' );

		echo '<div class="' . esc_attr( $class ) . '">';
		echo '<h1 class="pilot-page-title">' . esc_html( $title );
		if ( $badge ) {
			echo ' <span class="pilot-badge pilot-badge-info">' . esc_html( $badge ) . '</span>';
		}
		echo '</h1>';
	}

	/**
	 * Close the page wrapper.
	 */
	public static function page_end(): void {
		echo '</div>';
	}

	/* =============================================
	 *  Tabs
	 * ============================================= */

	/**
	 * Render a pill-style tab navigation bar.
	 *
	 * @param array  $tabs   Associative array ['slug' => 'Label', ...].
	 * @param string $active Currently active tab slug (default: first).
	 * @param string $group  Group name for localStorage persistence key.
	 */
	public static function tabs( array $tabs, string $active = '', string $group = 'default' ): void {
		if ( ! $active ) {
			$active = array_key_first( $tabs );
		}

		echo '<div class="pilot-tabs" data-group="' . esc_attr( $group ) . '">';
		foreach ( $tabs as $slug => $label ) {
			$is_active = ( $slug === $active ) ? ' active' : '';
			echo '<button type="button" class="pilot-tab' . $is_active . '" data-tab="' . esc_attr( $slug ) . '">';
			echo esc_html( $label );
			echo '</button>';
		}
		echo '</div>';
		echo '<div class="pilot-tab-panels">';
	}

	/**
	 * Open a tab panel.
	 *
	 * @param string $slug   Tab slug matching the tabs() array key.
	 * @param string $active Currently active tab slug.
	 */
	public static function tab_panel_start( string $slug, string $active = '' ): void {
		$hidden = ( $slug !== $active ) ? ' style="display:none"' : '';
		echo '<div data-tab-panel="' . esc_attr( $slug ) . '"' . $hidden . '>';
	}

	/**
	 * Close a tab panel.
	 */
	public static function tab_panel_end(): void {
		echo '</div>';
	}

	/**
	 * Close the tab panels container.
	 */
	public static function tabs_end(): void {
		echo '</div>'; // .pilot-tab-panels
	}

	/* =============================================
	 *  Cards
	 * ============================================= */

	/**
	 * Open a settings card.
	 *
	 * @param string $title       Card title.
	 * @param string $description Optional description text.
	 */
	public static function card_start( string $title, string $description = '' ): void {
		echo '<div class="pilot-card">';
		echo '<div class="pilot-card-header">';
		echo '<div class="pilot-card-info">';
		echo '<h3 class="pilot-section-title">' . esc_html( $title ) . '</h3>';
		if ( $description ) {
			echo '<p class="pilot-description">' . esc_html( $description ) . '</p>';
		}
		echo '</div>';
		echo '</div>';
		echo '<div class="pilot-card-body">';
	}

	/**
	 * Close a settings card.
	 */
	public static function card_end(): void {
		echo '</div></div>'; // .pilot-card-body + .pilot-card
	}

	/**
	 * Open a module card with a toggle switch in the header.
	 *
	 * When toggled off, the card body panel hides automatically.
	 *
	 * @param string $option_name Full input name for the toggle.
	 * @param bool   $checked     Current toggle state.
	 * @param string $title       Module title.
	 * @param string $description Optional description.
	 * @param string $module_key  Unique key (used for panel ID).
	 */
	public static function module_card_start(
		string $option_name,
		bool   $checked,
		string $title,
		string $description = '',
		string $module_key = ''
	): void {
		if ( ! $module_key ) {
			$module_key = sanitize_key( $title );
		}
		$panel_id = 'pilot-panel-' . $module_key;

		echo '<div class="pilot-card">';
		echo '<div class="pilot-card-header">';

		// Hidden input for unchecked state.
		echo '<input type="hidden" name="' . esc_attr( $option_name ) . '" value="0">';

		echo '<label class="pilot-toggle">';
		echo '<input type="checkbox" name="' . esc_attr( $option_name ) . '" value="1"';
		echo ' class="pilot-module-toggle" data-target="' . esc_attr( $panel_id ) . '"';
		if ( $checked ) {
			echo ' checked';
		}
		echo '>';
		echo '<span class="pilot-toggle-slider"></span>';
		echo '</label>';

		echo '<div class="pilot-card-info">';
		echo '<h3 class="pilot-section-title">' . esc_html( $title ) . '</h3>';
		if ( $description ) {
			echo '<p class="pilot-description">' . esc_html( $description ) . '</p>';
		}
		echo '</div>';
		echo '</div>'; // .pilot-card-header

		$display = $checked ? '' : ' style="display:none"';
		echo '<div class="pilot-card-body" id="' . esc_attr( $panel_id ) . '"' . $display . '>';
	}

	/**
	 * Close a module card.
	 */
	public static function module_card_end(): void {
		echo '</div></div>'; // .pilot-card-body + .pilot-card
	}

	/* =============================================
	 *  Form Fields
	 * ============================================= */

	/**
	 * Standalone toggle switch (not a module card header).
	 *
	 * @param string $name        Input name.
	 * @param bool   $checked     Current state.
	 * @param string $label       Field label.
	 * @param string $description Optional help text.
	 */
	public static function toggle( string $name, bool $checked, string $label, string $description = '' ): void {
		echo '<div class="pilot-field-row">';
		echo '<div class="pilot-field-label">' . esc_html( $label ) . '</div>';
		echo '<div class="pilot-field-control">';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
		echo '<label class="pilot-toggle">';
		echo '<input type="checkbox" name="' . esc_attr( $name ) . '" value="1"';
		if ( $checked ) {
			echo ' checked';
		}
		echo '>';
		echo '<span class="pilot-toggle-slider"></span>';
		echo '</label>';
		if ( $description ) {
			echo '<p class="pilot-description">' . esc_html( $description ) . '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * Text input field.
	 *
	 * @param string $name  Input name.
	 * @param string $value Current value.
	 * @param string $label Field label.
	 * @param array  $args  Optional: placeholder, description, type, class, disabled, readonly.
	 */
	public static function text( string $name, string $value, string $label, array $args = [] ): void {
		$placeholder = $args['placeholder'] ?? '';
		$description = $args['description'] ?? '';
		$type        = $args['type'] ?? 'text';
		$class       = $args['class'] ?? '';
		$disabled    = $args['disabled'] ?? false;
		$readonly    = $args['readonly'] ?? false;

		echo '<div class="pilot-field-row">';
		echo '<div class="pilot-field-label"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></div>';
		echo '<div class="pilot-field-control">';
		echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"';
		echo ' value="' . esc_attr( $value ) . '" class="pilot-input ' . esc_attr( $class ) . '"';
		if ( $placeholder ) {
			echo ' placeholder="' . esc_attr( $placeholder ) . '"';
		}
		if ( $disabled ) {
			echo ' disabled';
		}
		if ( $readonly ) {
			echo ' readonly';
		}
		echo '>';
		if ( $description ) {
			echo '<p class="pilot-description">' . wp_kses_post( $description ) . '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * Password input field.
	 *
	 * @param string $name  Input name.
	 * @param string $value Current value.
	 * @param string $label Field label.
	 * @param array  $args  Optional: placeholder, description, class, disabled.
	 */
	public static function password( string $name, string $value, string $label, array $args = [] ): void {
		$args['type'] = 'password';
		self::text( $name, $value, $label, $args );
	}

	/**
	 * Number input field.
	 *
	 * @param string     $name  Input name.
	 * @param int|string $value Current value.
	 * @param string     $label Field label.
	 * @param array      $args  Optional: min, max, step, suffix, description.
	 */
	public static function number( string $name, $value, string $label, array $args = [] ): void {
		$min         = $args['min'] ?? '';
		$max         = $args['max'] ?? '';
		$step        = $args['step'] ?? 1;
		$suffix      = $args['suffix'] ?? '';
		$description = $args['description'] ?? '';

		echo '<div class="pilot-field-row">';
		echo '<div class="pilot-field-label"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></div>';
		echo '<div class="pilot-field-control">';
		echo '<input type="number" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"';
		echo ' value="' . esc_attr( $value ) . '" class="pilot-input pilot-input-small"';
		if ( '' !== $min ) {
			echo ' min="' . esc_attr( $min ) . '"';
		}
		if ( '' !== $max ) {
			echo ' max="' . esc_attr( $max ) . '"';
		}
		echo ' step="' . esc_attr( $step ) . '">';
		if ( $suffix ) {
			echo ' <span class="pilot-field-suffix">' . esc_html( $suffix ) . '</span>';
		}
		if ( $description ) {
			echo '<p class="pilot-description">' . wp_kses_post( $description ) . '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * Select dropdown.
	 *
	 * @param string     $name    Input name.
	 * @param string|int $current Currently selected value.
	 * @param string     $label   Field label.
	 * @param array      $options Associative array ['value' => 'Label', ...].
	 * @param array      $args    Optional: description.
	 */
	public static function select( string $name, $current, string $label, array $options, array $args = [] ): void {
		$description = $args['description'] ?? '';

		echo '<div class="pilot-field-row">';
		echo '<div class="pilot-field-label"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></div>';
		echo '<div class="pilot-field-control">';
		echo '<select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" class="pilot-select">';
		foreach ( $options as $val => $lbl ) {
			echo '<option value="' . esc_attr( $val ) . '"' . selected( $current, $val, false ) . '>';
			echo esc_html( $lbl ) . '</option>';
		}
		echo '</select>';
		if ( $description ) {
			echo '<p class="pilot-description">' . wp_kses_post( $description ) . '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * Textarea field.
	 *
	 * @param string $name  Input name.
	 * @param string $value Current value.
	 * @param string $label Field label.
	 * @param array  $args  Optional: rows, class, placeholder, description.
	 */
	public static function textarea( string $name, string $value, string $label, array $args = [] ): void {
		$rows        = $args['rows'] ?? 5;
		$class       = $args['class'] ?? '';
		$placeholder = $args['placeholder'] ?? '';
		$description = $args['description'] ?? '';

		echo '<div class="pilot-field-row">';
		echo '<div class="pilot-field-label"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></div>';
		echo '<div class="pilot-field-control">';
		echo '<textarea id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"';
		echo ' rows="' . esc_attr( $rows ) . '" class="pilot-input pilot-textarea ' . esc_attr( $class ) . '"';
		if ( $placeholder ) {
			echo ' placeholder="' . esc_attr( $placeholder ) . '"';
		}
		echo '>' . esc_textarea( $value ) . '</textarea>';
		if ( $description ) {
			echo '<p class="pilot-description">' . wp_kses_post( $description ) . '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * Checkbox field.
	 *
	 * @param string $name        Input name.
	 * @param bool   $checked     Current state.
	 * @param string $label       Field label (left column).
	 * @param string $description Inline text next to checkbox.
	 */
	public static function checkbox( string $name, bool $checked, string $label, string $description = '', string $on_value = '1', string $off_value = '0' ): void {
		echo '<div class="pilot-field-row">';
		echo '<div class="pilot-field-label">' . esc_html( $label ) . '</div>';
		echo '<div class="pilot-field-control">';
		echo '<label class="pilot-checkbox-label">';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $off_value ) . '">';
		echo '<input type="checkbox" name="' . esc_attr( $name ) . '" value="' . esc_attr( $on_value ) . '"';
		if ( $checked ) {
			echo ' checked';
		}
		echo '>';
		if ( $description ) {
			echo ' <span>' . esc_html( $description ) . '</span>';
		}
		echo '</label>';
		echo '</div></div>';
	}

	/**
	 * Sub-checkbox inside a module card body (no label column).
	 *
	 * @param string $name    Input name.
	 * @param bool   $checked Current state.
	 * @param string $label   Checkbox label text.
	 */
	public static function sub_checkbox( string $name, bool $checked, string $label ): void {
		echo '<label class="pilot-sub-setting">';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
		echo '<input type="checkbox" name="' . esc_attr( $name ) . '" value="1"';
		if ( $checked ) {
			echo ' checked';
		}
		echo '> ' . wp_kses_post( $label );
		echo '</label>';
	}

	/**
	 * Sub-number input inside a module card body.
	 *
	 * @param string     $name  Input name.
	 * @param int|string $value Current value.
	 * @param string     $label Label text.
	 * @param int        $min   Minimum value.
	 * @param int        $max   Maximum value.
	 */
	public static function sub_number( string $name, $value, string $label, int $min = 0, int $max = 999 ): void {
		echo '<div class="pilot-sub-number">';
		echo '<label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
		echo '<input type="number" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"';
		echo ' value="' . esc_attr( $value ) . '" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '"';
		echo ' class="small-text">';
		echo '</div>';
	}

	/**
	 * Color picker input.
	 *
	 * @param string $name        Input name.
	 * @param string $value       Current hex color value.
	 * @param string $label       Field label.
	 * @param string $description Optional help text.
	 */
	public static function color( string $name, string $value, string $label, string $description = '' ): void {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		echo '<div class="pilot-field-row">';
		echo '<div class="pilot-field-label"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></div>';
		echo '<div class="pilot-field-control">';
		echo '<input type="text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"';
		echo ' value="' . esc_attr( $value ) . '" class="pilot-color-picker">';
		if ( $description ) {
			echo '<p class="pilot-description">' . esc_html( $description ) . '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * Code block (read-only display with optional copy button).
	 *
	 * @param string $label    Field label.
	 * @param string $code     Code/URL to display.
	 * @param bool   $copyable Whether to show a copy button.
	 */
	public static function code_block( string $label, string $code, bool $copyable = true ): void {
		echo '<div class="pilot-field-row">';
		echo '<div class="pilot-field-label">' . esc_html( $label ) . '</div>';
		echo '<div class="pilot-field-control">';
		echo '<div class="pilot-code-block">';
		echo '<code>' . esc_html( $code ) . '</code>';
		if ( $copyable ) {
			echo ' <button type="button" class="button button-small pilot-copy-btn" data-copy="' . esc_attr( $code ) . '">';
			echo esc_html__( 'Copy', 'pilot-admin-ui' );
			echo '</button>';
		}
		echo '</div>';
		echo '</div></div>';
	}

	/* =============================================
	 *  Decorative / Structural
	 * ============================================= */

	/**
	 * Section header within a card body.
	 *
	 * @param string $title Section title.
	 */
	public static function section_header( string $title ): void {
		echo '<h4 class="pilot-section-header">' . esc_html( $title ) . '</h4>';
	}

	/**
	 * Inline badge.
	 *
	 * @param string $text    Badge text.
	 * @param string $variant One of: success, warning, error, info.
	 */
	public static function badge( string $text, string $variant = 'info' ): void {
		echo '<span class="pilot-badge pilot-badge-' . esc_attr( $variant ) . '">' . esc_html( $text ) . '</span>';
	}

	/**
	 * Alert notice.
	 *
	 * @param string $message Alert message (may contain HTML).
	 * @param string $variant One of: success, warning, error, info.
	 */
	public static function alert( string $message, string $variant = 'info' ): void {
		echo '<div class="pilot-alert pilot-alert-' . esc_attr( $variant ) . '">';
		echo wp_kses_post( $message );
		echo '</div>';
	}

	/**
	 * Submit button row.
	 *
	 * @param string $text Button text.
	 * @param string $name Button name attribute.
	 */
	public static function submit( string $text = 'Save Settings', string $name = 'submit' ): void {
		echo '<div class="pilot-submit-row">';
		submit_button( $text, 'primary', $name );
		echo '</div>';
	}

	/**
	 * Test connection button with inline result area.
	 *
	 * The JS module handles the AJAX call automatically using
	 * data-action and data-nonce attributes.
	 *
	 * @param string $ajax_action WordPress AJAX action name.
	 * @param string $nonce       Nonce value.
	 * @param string $label       Row label.
	 * @param string $text        Button text.
	 */
	public static function test_connection( string $ajax_action, string $nonce, string $label = 'Connection Test', string $text = 'Test Connection' ): void {
		echo '<div class="pilot-field-row">';
		echo '<div class="pilot-field-label">' . esc_html( $label ) . '</div>';
		echo '<div class="pilot-field-control">';
		echo '<button type="button" class="button pilot-test-connection"';
		echo ' data-action="' . esc_attr( $ajax_action ) . '"';
		echo ' data-nonce="' . esc_attr( $nonce ) . '">';
		echo esc_html( $text );
		echo '</button>';
		echo ' <span class="pilot-test-result"></span>';
		echo '</div></div>';
	}

	/**
	 * Render arbitrary HTML content inside a field row.
	 *
	 * Useful for custom controls like Pilot_Updater license fields.
	 *
	 * @param string   $label    Row label.
	 * @param callable $callback Function that outputs the control HTML.
	 */
	public static function custom_field( string $label, callable $callback ): void {
		echo '<div class="pilot-field-row">';
		echo '<div class="pilot-field-label">' . esc_html( $label ) . '</div>';
		echo '<div class="pilot-field-control">';
		call_user_func( $callback );
		echo '</div></div>';
	}
}
