/**
 * AI SEO Pilot — Admin JS
 */
(function ($) {
	'use strict';

	/* ── Tab Switching ─────────────────────────────────────── */
	var STORAGE_KEY = 'ai_seo_pilot_active_tab';

	function activateTab(tab) {
		var $tab = $('.ai-seo-pilot-tabs .nav-tab[data-tab="' + tab + '"]');
		if (!$tab.length) return false;

		$('.ai-seo-pilot-tabs .nav-tab').removeClass('nav-tab-active');
		$tab.addClass('nav-tab-active');

		$('.ai-seo-pilot-tab-content').removeClass('active').hide();
		$('#tab-' + tab).addClass('active').show();

		try { localStorage.setItem(STORAGE_KEY, tab); } catch (e) {}
		if (history.replaceState) {
			history.replaceState(null, null, '#' + tab);
		}
		return true;
	}

	$(document).on('click', '.ai-seo-pilot-tabs .nav-tab', function (e) {
		e.preventDefault();
		activateTab($(this).data('tab'));
	});

	$(function () {
		if (!$('.ai-seo-pilot-tabs').length) return;

		var hash = window.location.hash.replace('#', '');
		var saved = '';
		try { saved = localStorage.getItem(STORAGE_KEY) || ''; } catch (e) {}

		var target = hash || saved;
		if (target) {
			activateTab(target);
		}
	});

	/* ── Model Select + Custom Input Sync ─────────────────── */
	$(document).on('change', '.aisp-model-select', function () {
		var $select = $(this);
		var targetId = $select.data('target');
		var $hidden = $('#' + targetId);
		var $custom = $select.closest('td').find('.aisp-model-custom');
		var val = $select.val();

		if (val === '__custom__') {
			$custom.show().focus();
			$hidden.val($custom.val());
		} else {
			$custom.hide();
			$hidden.val(val);
		}
	});

	$(document).on('input', '.aisp-model-custom', function () {
		var $custom = $(this);
		var $hidden = $custom.closest('td').find('input[type="hidden"]');
		$hidden.val($custom.val());
	});

	/* ── llms.txt Mode Toggle ──────────────────────────────── */
	$(function () {
		var $mode = $('#ai_seo_pilot_llms_txt_mode');
		var $manualRow = $('#llms-txt-manual-row');

		function toggleManual() {
			if ($mode.val() === 'manual') {
				$manualRow.show();
			} else {
				$manualRow.hide();
			}
		}

		if ($mode.length) {
			toggleManual();
			$mode.on('change', toggleManual);
		}
	});

	/* ── llms.txt Validate ─────────────────────────────────── */
	$(document).on('click', '#ai-seo-pilot-validate', function () {
		var $btn = $(this);
		var $status = $('#ai-seo-pilot-llms-status');
		var $result = $('#ai-seo-pilot-validation-result');

		$btn.prop('disabled', true);
		$status.text('Validating…').attr('class', 'ai-seo-pilot-status loading');

		$.post(aiSeoPilot.ajaxUrl, {
			action: 'ai_seo_pilot_validate_llms_txt',
			nonce: aiSeoPilot.nonce
		}, function (response) {
			$btn.prop('disabled', false);

			if (response.success) {
				var d = response.data;
				$status
					.text(d.accessible ? 'Accessible' : 'Not accessible')
					.attr('class', 'ai-seo-pilot-status ' + (d.accessible ? 'success' : 'error'));

				$('#validation-accessible').text(d.accessible ? 'Yes' : 'No');
				$('#validation-status-code').text(d.status_code);
				$('#validation-content-length').text(d.content_length + ' bytes');
				$('#validation-content-preview').text(d.content_preview);
				$result.show();
			} else {
				$status.text('Error').attr('class', 'ai-seo-pilot-status error');
			}
		}).fail(function () {
			$btn.prop('disabled', false);
			$status.text('Request failed').attr('class', 'ai-seo-pilot-status error');
		});
	});

	/* ── llms.txt Regenerate ───────────────────────────────── */
	$(document).on('click', '#ai-seo-pilot-regenerate', function () {
		var $btn = $(this);
		var $status = $('#ai-seo-pilot-llms-status');

		$btn.prop('disabled', true);
		$status.text('Regenerating…').attr('class', 'ai-seo-pilot-status loading');

		$.post(aiSeoPilot.ajaxUrl, {
			action: 'ai_seo_pilot_regenerate_llms_txt',
			nonce: aiSeoPilot.nonce
		}, function (response) {
			$btn.prop('disabled', false);

			if (response.success) {
				$('#llms-txt-preview').text(response.data.content);
				$status.text('Regenerated').attr('class', 'ai-seo-pilot-status success');
			} else {
				$status.text('Error').attr('class', 'ai-seo-pilot-status error');
			}
		}).fail(function () {
			$btn.prop('disabled', false);
			$status.text('Request failed').attr('class', 'ai-seo-pilot-status error');
		});
	});

	/* ── llms.txt Generate with AI ─────────────────────────── */
	$(document).on('click', '#ai-seo-pilot-ai-generate-llms', function () {
		var $btn = $(this);
		var $status = $('#ai-seo-pilot-llms-status');

		$btn.prop('disabled', true);
		$status.text('AI is analyzing your site…').attr('class', 'ai-seo-pilot-status loading');

		$.post(aiSeoPilot.ajaxUrl, {
			action: 'ai_seo_pilot_ai_generate_llms_txt',
			nonce: aiSeoPilot.nonce
		}, function (response) {
			$btn.prop('disabled', false);

			if (response.success) {
				$('#llms-txt-preview').text(response.data.content);
				$status.text('Generated with AI').attr('class', 'ai-seo-pilot-status success');
			} else {
				$status.text(response.data || 'Error').attr('class', 'ai-seo-pilot-status error');
			}
		}).fail(function () {
			$btn.prop('disabled', false);
			$status.text('Request failed').attr('class', 'ai-seo-pilot-status error');
		});
	});

	/* ── AI Meta Description ───────────────────────────────── */
	$(document).on('click', '#ai-seo-pilot-generate-meta', function () {
		var $btn = $(this);
		var postId = $btn.data('post-id');
		var $status = $('#ai-seo-pilot-meta-status');
		var $textarea = $('#ai_seo_pilot_meta_description');

		$btn.prop('disabled', true);
		$status.text('Generating…').attr('class', 'ai-seo-pilot-status loading');

		$.post(aiSeoPilot.ajaxUrl, {
			action: 'ai_seo_pilot_ai_generate_meta',
			nonce: aiSeoPilot.nonce,
			post_id: postId
		}, function (response) {
			$btn.prop('disabled', false);

			if (response.success) {
				$textarea.val(response.data.description);
				$('#ai-seo-pilot-meta-chars').text(response.data.description.length + '/160');
				$status.text('Generated').attr('class', 'ai-seo-pilot-status success');
			} else {
				$status.text(response.data || 'Error').attr('class', 'ai-seo-pilot-status error');
			}
		}).fail(function () {
			$btn.prop('disabled', false);
			$status.text('Request failed').attr('class', 'ai-seo-pilot-status error');
		});
	});

	// Live character counter for meta description.
	$(document).on('input', '#ai_seo_pilot_meta_description', function () {
		$('#ai-seo-pilot-meta-chars').text($(this).val().length + '/160');
	});

	/* ── AI SEO Suggestions ────────────────────────────────── */
	$(document).on('click', '#ai-seo-pilot-generate-suggestions', function () {
		var $btn = $(this);
		var postId = $btn.data('post-id');
		var $status = $('#ai-seo-pilot-suggestions-status');
		var $list = $('#ai-seo-pilot-suggestions-list');

		$btn.prop('disabled', true);
		$status.text('AI is analyzing…').attr('class', 'ai-seo-pilot-status loading');
		$list.hide().empty();

		$.post(aiSeoPilot.ajaxUrl, {
			action: 'ai_seo_pilot_ai_generate_suggestions',
			nonce: aiSeoPilot.nonce,
			post_id: postId
		}, function (response) {
			$btn.prop('disabled', false);

			if (response.success) {
				var suggestions = response.data.suggestions;

				if (!suggestions || !suggestions.length) {
					if (response.data.raw) {
						$list.html('<div style="font-size:12px; white-space:pre-wrap; background:#f0f0f1; padding:8px; border-radius:3px;">' +
							$('<span>').text(response.data.raw).html() + '</div>');
					} else {
						$list.html('<p style="font-size:12px; color:#646970;">No suggestions generated.</p>');
					}
					$list.show();
					$status.text('Done').attr('class', 'ai-seo-pilot-status success');
					return;
				}

				var html = '';
				var priorityColors = { high: '#d63638', medium: '#dba617', low: '#2271b1' };

				for (var i = 0; i < suggestions.length; i++) {
					var s = suggestions[i];
					var color = priorityColors[s.priority] || '#646970';
					html += '<div style="padding:6px 0; border-bottom:1px solid #e0e0e0; font-size:12px;">';
					html += '<div style="display:flex; justify-content:space-between; align-items:center;">';
					html += '<strong>' + $('<span>').text(s.title).html() + '</strong>';
					html += '<span style="font-size:10px; padding:1px 6px; border-radius:3px; background:' + color + '20; color:' + color + ';">' +
						$('<span>').text(s.priority).html() + '</span>';
					html += '</div>';
					html += '<div style="color:#646970; margin-top:2px;">' + $('<span>').text(s.description).html() + '</div>';
					html += '</div>';
				}

				$list.html(html).show();
				$status.text(suggestions.length + ' suggestions').attr('class', 'ai-seo-pilot-status success');
			} else {
				$status.text(response.data || 'Error').attr('class', 'ai-seo-pilot-status error');
			}
		}).fail(function () {
			$btn.prop('disabled', false);
			$status.text('Request failed').attr('class', 'ai-seo-pilot-status error');
		});
	});

	/* ── AI Provider Toggle ────────────────────────────────── */
	$(function () {
		var $providerSelect = $('#ai_seo_pilot_ai_provider');
		if (!$providerSelect.length) return;

		function toggleProviderSections() {
			var provider = $providerSelect.val();
			$('.aisp-provider-section').hide();
			$('.aisp-provider-section[data-provider="' + provider + '"]').show();
		}

		toggleProviderSections();
		$providerSelect.on('change', toggleProviderSections);
	});

	/* ── AI Test Connection ────────────────────────────────── */
	$(document).on('click', '#ai-seo-pilot-test-connection', function () {
		var $btn = $(this);
		var $status = $('#ai-seo-pilot-test-status');
		var $result = $('#ai-seo-pilot-test-result');

		// Read current form values (not saved options) so user can test before saving.
		var provider = $('#ai_seo_pilot_ai_provider').val();
		var $section = $('.aisp-provider-section[data-provider="' + provider + '"]');
		var credential = $section.find('.aisp-provider-credential').val() || '';
		var model = $section.find('.aisp-provider-model').val() || '';

		if (!credential) {
			$status.text('Please enter credentials first.').attr('class', 'ai-seo-pilot-status error');
			return;
		}

		$btn.prop('disabled', true);
		$status.text('Testing…').attr('class', 'ai-seo-pilot-status loading');
		$result.hide();

		$.post(aiSeoPilot.ajaxUrl, {
			action: 'ai_seo_pilot_ai_test_connection',
			nonce: aiSeoPilot.nonce,
			provider: provider,
			credential: credential,
			model: model
		}, function (response) {
			$btn.prop('disabled', false);

			if (response.success) {
				$status.text(response.data.message).attr('class', 'ai-seo-pilot-status success');
				if (response.data.response) {
					$result.find('pre').text(response.data.response);
					$result.show();
				}
			} else {
				$status.text(response.data || 'Error').attr('class', 'ai-seo-pilot-status error');
			}
		}).fail(function () {
			$btn.prop('disabled', false);
			$status.text('Request failed').attr('class', 'ai-seo-pilot-status error');
		});
	});

	/* ── SEO Check Fix Buttons ─────────────────────────────── */
	$(document).on('click', '.aisp-fix-btn[data-fix]', function () {
		var $btn = $(this);
		var fixType = $btn.data('fix');
		var $card = $btn.closest('.aisp-check-card');

		if ($btn.prop('disabled')) return;

		if (fixType === 'option_toggle') {
			$btn.prop('disabled', true).text('Saving…');

			$.post(aiSeoPilot.ajaxUrl, {
				action: 'ai_seo_pilot_seo_fix',
				nonce: aiSeoPilot.nonce,
				option: $btn.data('option'),
				value: $btn.data('value')
			}, function (response) {
				if (response.success) {
					$btn.text('Done').addClass('disabled');
					$card
						.removeClass('aisp-check-card--fail aisp-check-card--warning')
						.addClass('aisp-check-card--pass');
					$card.find('.aisp-check-icon')
						.removeClass('aisp-check-icon--fail aisp-check-icon--warning')
						.addClass('aisp-check-icon--pass')
						.find('.dashicons')
						.attr('class', 'dashicons dashicons-yes-alt');
				} else {
					$btn.prop('disabled', false).text('Retry');
				}
			}).fail(function () {
				$btn.prop('disabled', false).text('Retry');
			});
		} else if (fixType === 'ajax') {
			$btn.prop('disabled', true);
			var originalText = $btn.text();
			$btn.text('Processing…');

			$.post(aiSeoPilot.ajaxUrl, {
				action: $btn.data('action'),
				nonce: aiSeoPilot.nonce
			}, function (response) {
				if (response.success) {
					var msg = 'Done';
					if (response.data && response.data.count) {
						msg = 'Done (' + response.data.count + ' generated)';
					} else if (response.data && response.data.tagline) {
						msg = 'Done';
						$card.find('.aisp-check-msg').text('Tagline: "' + response.data.tagline + '"');
					}
					$btn.text(msg).addClass('disabled');
					$card
						.removeClass('aisp-check-card--fail aisp-check-card--warning')
						.addClass('aisp-check-card--pass');
					$card.find('.aisp-check-icon')
						.removeClass('aisp-check-icon--fail aisp-check-icon--warning')
						.addClass('aisp-check-icon--pass')
						.find('.dashicons')
						.attr('class', 'dashicons dashicons-yes-alt');
				} else {
					$btn.prop('disabled', false).text(originalText);
				}
			}).fail(function () {
				$btn.prop('disabled', false).text(originalText);
			});
		}
	});

	/* ── Score Circle Init ─────────────────────────────────── */
	$(function () {
		$('.score-circle').each(function () {
			var score = parseInt($(this).data('score'), 10) || 0;
			var color;

			if (score >= 75) {
				color = '#00a32a';
			} else if (score >= 50) {
				color = '#dba617';
			} else {
				color = '#d63638';
			}

			this.style.setProperty('--score-pct', score);
			this.style.setProperty('--score-color', color);
		});
	});

	/* ── Bot Visits Chart (Chart.js) ───────────────────────── */
	$(function () {
		var canvas = document.getElementById('ai-seo-pilot-bot-chart');
		if (!canvas || typeof Chart === 'undefined') {
			if (canvas && typeof Chart === 'undefined') {
				var script = document.createElement('script');
				script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js';
				script.onload = initBotChart;
				document.head.appendChild(script);
			}
			return;
		}
		initBotChart();
	});

	function initBotChart() {
		var canvas = document.getElementById('ai-seo-pilot-bot-chart');
		if (!canvas || !window.aiSeoPilotChartData) return;

		var data = window.aiSeoPilotChartData;

		new Chart(canvas, {
			type: 'line',
			data: {
				labels: data.labels,
				datasets: [{
					label: 'AI Bot Visits',
					data: data.values,
					borderColor: '#2271b1',
					backgroundColor: 'rgba(34, 113, 177, 0.1)',
					fill: true,
					tension: 0.3,
					pointRadius: 2,
					pointHoverRadius: 5
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: false }
				},
				scales: {
					x: {
						grid: { display: false },
						ticks: { maxTicksLimit: 10 }
					},
					y: {
						beginAtZero: true,
						ticks: { precision: 0 }
					}
				}
			}
		});
	}

	/* ── Custom Bots Management ───────────────────────────── */

	$('#ai-seo-pilot-add-bot').on('click', function () {
		var id      = $.trim($('#ai-seo-pilot-new-bot-id').val());
		var name    = $.trim($('#ai-seo-pilot-new-bot-name').val()) || id;
		var service = $.trim($('#ai-seo-pilot-new-bot-service').val());

		if (!id) {
			$('#ai-seo-pilot-new-bot-id').focus();
			return;
		}

		// Prevent duplicates.
		var exists = false;
		$('#ai-seo-pilot-bots-table tbody tr td:first-child code').each(function () {
			if ($(this).text() === id) { exists = true; }
		});
		if (exists) {
			alert('Bot "' + id + '" already exists.');
			return;
		}

		var idx = $('#ai-seo-pilot-custom-bots-inputs .ai-seo-pilot-custom-bot-row').length;

		// Add table row.
		var safeId = $('<span>').text(id).html();
		$('#ai-seo-pilot-bots-table tbody').append(
			'<tr>' +
				'<td><code>' + safeId + '</code></td>' +
				'<td>' + $('<span>').text(name).html() + '</td>' +
				'<td>' + $('<span>').text(service).html() + '</td>' +
				'<td><button type="button" class="button-link ai-seo-pilot-custom-badge" ' +
					'style="color:#d63638;padding:2px 6px" ' +
					'data-identifier="' + safeId + '" ' +
					'onclick="this.closest(\'tr\').remove();' +
					'document.querySelector(\'.ai-seo-pilot-custom-bot-row[data-identifier=\\x22' + id.replace(/"/g, '') + '\\x22]\')?.remove();">Remove</button></td>' +
			'</tr>'
		);

		// Add hidden inputs.
		$('#ai-seo-pilot-custom-bots-inputs').append(
			'<div class="ai-seo-pilot-custom-bot-row" data-identifier="' + $('<span>').text(id).html() + '">' +
				'<input type="hidden" name="ai_seo_pilot_custom_bots[' + idx + '][identifier]" value="' + $('<span>').text(id).html() + '">' +
				'<input type="hidden" name="ai_seo_pilot_custom_bots[' + idx + '][name]" value="' + $('<span>').text(name).html() + '">' +
				'<input type="hidden" name="ai_seo_pilot_custom_bots[' + idx + '][service]" value="' + $('<span>').text(service).html() + '">' +
			'</div>'
		);

		// Clear inputs.
		$('#ai-seo-pilot-new-bot-id, #ai-seo-pilot-new-bot-name, #ai-seo-pilot-new-bot-service').val('');
		$('#ai-seo-pilot-new-bot-id').focus();
	});

	// Remove custom bot: also remove its hidden inputs.
	$(document).on('click', '.ai-seo-pilot-custom-badge', function () {
		var identifier = $(this).data('identifier');
		$('.ai-seo-pilot-custom-bot-row[data-identifier="' + identifier + '"]').remove();
	});

})(jQuery);
