/**
 * AI SEO Pilot — Gutenberg Sidebar Content Analyzer
 *
 * Uses wp.element.createElement (no JSX/build step required).
 */
(function () {
	'use strict';

	var el           = wp.element.createElement;
	var Fragment     = wp.element.Fragment;
	var useState     = wp.element.useState;
	var useEffect    = wp.element.useEffect;
	var useRef       = wp.element.useRef;
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginSidebar  = wp.editor.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editor.PluginSidebarMoreMenuItem;
	var useSelect    = wp.data.useSelect;
	var PanelBody    = wp.components.PanelBody;
	var Spinner      = wp.components.Spinner;
	var Icon         = wp.components.Icon;

	var REST_URL = (window.aiSeoPilotAnalyzer && window.aiSeoPilotAnalyzer.restUrl) || '/wp-json/ai-seo-pilot/v1/analyze';
	var NONCE    = (window.aiSeoPilotAnalyzer && window.aiSeoPilotAnalyzer.nonce) || '';

	function getStatusColor(status) {
		if (status === 'good') return '#00a32a';
		if (status === 'warning') return '#dba617';
		return '#d63638';
	}

	function ScoreCircle(props) {
		var score = props.score || 0;
		var color;
		if (score >= 75) color = '#00a32a';
		else if (score >= 50) color = '#dba617';
		else color = '#d63638';

		var size = 80;
		var strokeWidth = 6;
		var radius = (size - strokeWidth) / 2;
		var circumference = 2 * Math.PI * radius;
		var offset = circumference - (score / 100) * circumference;

		return el('div', { style: { textAlign: 'center', padding: '16px 0' } },
			el('svg', { width: size, height: size, viewBox: '0 0 ' + size + ' ' + size },
				el('circle', {
					cx: size / 2, cy: size / 2, r: radius,
					fill: 'none', stroke: '#e0e0e0', strokeWidth: strokeWidth
				}),
				el('circle', {
					cx: size / 2, cy: size / 2, r: radius,
					fill: 'none', stroke: color, strokeWidth: strokeWidth,
					strokeDasharray: circumference,
					strokeDashoffset: offset,
					strokeLinecap: 'round',
					transform: 'rotate(-90 ' + (size / 2) + ' ' + (size / 2) + ')',
					style: { transition: 'stroke-dashoffset 0.5s ease' }
				}),
				el('text', {
					x: '50%', y: '50%', textAnchor: 'middle', dominantBaseline: 'central',
					fontSize: '22', fontWeight: '700', fill: '#1d2327'
				}, score)
			),
			el('span', {
				style: { display: 'block', marginTop: 4, color: '#757575', fontSize: 12, textTransform: 'uppercase' }
			}, 'AI Readiness Score')
		);
	}

	function CheckItem(props) {
		var check = props.check;
		return el('div', { style: { padding: '8px 0', borderBottom: '1px solid #e0e0e0' } },
			el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', fontSize: 13 } },
				el('span', null, check.label),
				el('span', {
					style: {
						fontWeight: 600, padding: '2px 6px', borderRadius: 3, fontSize: 11,
						background: check.status === 'good' ? '#d4edda' : check.status === 'warning' ? '#fff3cd' : '#f8d7da',
						color: check.status === 'good' ? '#155724' : check.status === 'warning' ? '#856404' : '#721c24'
					}
				}, check.score + '/' + check.max)
			),
			check.suggestion ? el('div', {
				style: { fontSize: 12, color: '#757575', marginTop: 4 }
			}, check.suggestion) : null
		);
	}

	function AnalyzerPanel() {
		var _useState = useState(null);
		var result = _useState[0];
		var setResult = _useState[1];

		var _useLoading = useState(false);
		var loading = _useLoading[0];
		var setLoading = _useLoading[1];

		var timerRef = useRef(null);

		var postData = useSelect(function (select) {
			var editor = select('core/editor');
			return {
				title: editor.getEditedPostAttribute('title') || '',
				content: editor.getEditedPostContent() || '',
				postId: editor.getCurrentPostId()
			};
		}, []);

		// Debounced analysis on content/title change.
		useEffect(function () {
			if (!postData.content && !postData.title) return;

			if (timerRef.current) clearTimeout(timerRef.current);

			timerRef.current = setTimeout(function () {
				runAnalysis(postData);
			}, 2000);

			return function () {
				if (timerRef.current) clearTimeout(timerRef.current);
			};
		}, [postData.content, postData.title]);

		function runAnalysis(data) {
			setLoading(true);

			wp.apiFetch({
				path: '/ai-seo-pilot/v1/analyze',
				method: 'POST',
				data: {
					title: data.title,
					content: data.content,
					post_id: data.postId
				}
			}).then(function (response) {
				setResult(response);
				setLoading(false);
			}).catch(function () {
				setLoading(false);
			});
		}

		// Build panel content.
		var content;

		if (loading && !result) {
			content = el('div', { style: { textAlign: 'center', padding: 20 } },
				el(Spinner, null),
				el('p', null, 'Analyzing content…')
			);
		} else if (result) {
			var checks = (result.checks || []).map(function (check, i) {
				return el(CheckItem, { key: i, check: check });
			});

			content = el(Fragment, null,
				el(ScoreCircle, { score: result.score }),
				el('div', { style: { textAlign: 'center', marginBottom: 16 } },
					el('span', {
						style: {
							display: 'inline-block', padding: '4px 12px', borderRadius: 12,
							fontSize: 12, fontWeight: 600,
							background: result.ai_ready ? '#d4edda' : '#f8d7da',
							color: result.ai_ready ? '#155724' : '#721c24'
						}
					}, result.ai_ready ? 'AI-Ready' : 'Needs Improvement')
				),
				loading ? el('div', { style: { textAlign: 'center', padding: '4px 0' } }, el(Spinner, null)) : null,
				el(PanelBody, { title: 'Detailed Checks', initialOpen: true }, checks)
			);
		} else {
			content = el('div', { style: { padding: 16, color: '#757575', textAlign: 'center' } },
				el('p', null, 'Start writing to see your AI readiness score.')
			);
		}

		return el(Fragment, null,
			el(PluginSidebarMoreMenuItem, { target: 'ai-seo-pilot-analyzer' }, 'AI SEO Pilot'),
			el(PluginSidebar, {
				name: 'ai-seo-pilot-analyzer',
				title: 'AI SEO Pilot',
				icon: 'superhero-alt'
			}, el('div', { className: 'ai-seo-pilot-sidebar', style: { padding: 12 } }, content))
		);
	}

	registerPlugin('ai-seo-pilot-analyzer', {
		render: AnalyzerPanel,
		icon: 'superhero-alt'
	});

})();
