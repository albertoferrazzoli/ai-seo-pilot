/**
 * Pilot Admin UI - JavaScript Module
 *
 * Handles tab switching, toggle interactivity, clipboard copy,
 * color picker initialization, and AJAX test-connection pattern.
 *
 * @version 1.0.0
 */
(function ($) {
	'use strict';

	var PilotUI = {

		init: function () {
			this.initTabs();
			this.initToggles();
			this.initCopyButtons();
			this.initColorPickers();
			this.initTestConnections();
		},

		/* -----------------------------------------
		 *  Tab Switching
		 * ----------------------------------------- */

		initTabs: function () {
			// Click handler for tab buttons.
			$(document).on('click', '.pilot-tab', function (e) {
				e.preventDefault();

				var $tab    = $(this);
				var tab     = $tab.data('tab');
				var $tabs   = $tab.closest('.pilot-tabs');
				var group   = $tabs.data('group') || 'default';
				var $panels = $tabs.next('.pilot-tab-panels');

				// Update active tab.
				$tabs.find('.pilot-tab').removeClass('active');
				$tab.addClass('active');

				// Show/hide panels.
				if ($panels.length) {
					$panels.children('[data-tab-panel]').hide();
					$panels.children('[data-tab-panel="' + tab + '"]').show();
				}

				// Persist active tab.
				try {
					localStorage.setItem('pilot_tab_' + group, tab);
				} catch (ex) { /* private browsing */ }

				// Update URL hash without scrolling.
				if (history.replaceState) {
					history.replaceState(null, null, '#' + tab);
				}
			});

			// Restore saved tab on page load.
			$('.pilot-tabs').each(function () {
				var $tabs = $(this);
				var group = $tabs.data('group') || 'default';

				// Priority: URL hash > localStorage.
				var hash  = window.location.hash.replace('#', '');
				var saved;

				try {
					saved = localStorage.getItem('pilot_tab_' + group);
				} catch (ex) { /* private browsing */ }

				var target = hash || saved;

				if (target) {
					var $target = $tabs.find('[data-tab="' + target + '"]');
					if ($target.length) {
						$target.trigger('click');
					}
				}
			});
		},

		/* -----------------------------------------
		 *  Toggle Switches (module card show/hide)
		 * ----------------------------------------- */

		initToggles: function () {
			$(document).on('change', '.pilot-module-toggle', function () {
				var target = $(this).data('target');

				if (target) {
					var $panel = $('#' + target);

					if (this.checked) {
						$panel.slideDown(200);
					} else {
						$panel.slideUp(200);
					}
				}
			});
		},

		/* -----------------------------------------
		 *  Copy to Clipboard
		 * ----------------------------------------- */

		initCopyButtons: function () {
			$(document).on('click', '.pilot-copy-btn', function () {
				var $btn = $(this);
				var text = $btn.data('copy') || '';

				if (!text) {
					// Fallback: copy from adjacent <code> element.
					var $code = $btn.siblings('code');
					if ($code.length) {
						text = $code.text();
					}
				}

				if (!text) {
					return;
				}

				var originalText = $btn.text();

				navigator.clipboard.writeText(text).then(function () {
					$btn.text('Copied!');
					setTimeout(function () {
						$btn.text(originalText);
					}, 2000);
				});
			});
		},

		/* -----------------------------------------
		 *  Color Pickers
		 * ----------------------------------------- */

		initColorPickers: function () {
			if ($.fn.wpColorPicker) {
				$('.pilot-color-picker').wpColorPicker();
			}
		},

		/* -----------------------------------------
		 *  Test Connection (AJAX)
		 *
		 *  Usage:
		 *  <button class="pilot-test-connection"
		 *          data-action="my_ajax_action"
		 *          data-nonce="abc123">Test</button>
		 *  <span class="pilot-test-result"></span>
		 * ----------------------------------------- */

		initTestConnections: function () {
			$(document).on('click', '.pilot-test-connection', function () {
				var $btn    = $(this);
				var $result = $btn.siblings('.pilot-test-result');

				if (!$result.length) {
					$result = $btn.next('.pilot-test-result');
				}

				var originalText = $btn.text();

				$btn.prop('disabled', true).text('Testing\u2026');
				$result.removeClass('success error').text('');

				$.post(ajaxurl, {
					action:   $btn.data('action'),
					_wpnonce: $btn.data('nonce')
				})
				.done(function (response) {
					if (response.success) {
						$result.addClass('success').text('\u2713 ' + (response.data || 'OK'));
					} else {
						$result.addClass('error').text('\u2717 ' + (response.data || 'Failed'));
					}
				})
				.fail(function () {
					$result.addClass('error').text('\u2717 Connection error');
				})
				.always(function () {
					$btn.prop('disabled', false).text(originalText);
				});
			});
		}
	};

	$(document).ready(function () {
		PilotUI.init();
	});

	// Expose for plugins that need to extend or re-init.
	window.PilotUI = PilotUI;

})(jQuery);
