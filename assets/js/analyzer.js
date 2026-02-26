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
	var Button       = wp.components.Button;
	var TextControl  = wp.components.TextControl;
	var SelectControl = wp.components.SelectControl;

	var REST_URL = (window.aiSeoPilotAnalyzer && window.aiSeoPilotAnalyzer.restUrl) || '/wp-json/ai-seo-pilot/v1/analyze';
	var NONCE    = (window.aiSeoPilotAnalyzer && window.aiSeoPilotAnalyzer.nonce) || '';

	function getStatusColor(status) {
		if (status === 'good') return '#10b981';
		if (status === 'warning') return '#f59e0b';
		return '#f43f5e';
	}

	function ScoreCircle(props) {
		var percentage = props.percentage || 0;
		var score = props.score || 0;
		var maxScore = props.maxScore || 100;
		var color;
		if (percentage >= 75) color = '#10b981';
		else if (percentage >= 50) color = '#f59e0b';
		else color = '#f43f5e';

		var size = 80;
		var strokeWidth = 6;
		var radius = (size - strokeWidth) / 2;
		var circumference = 2 * Math.PI * radius;
		var offset = circumference - (percentage / 100) * circumference;

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
					fontSize: '22', fontWeight: '700', fill: '#111827'
				}, percentage + '%')
			),
			el('span', {
				style: { display: 'block', marginTop: 4, color: '#757575', fontSize: 12 }
			}, score + ' / ' + maxScore + ' pts')
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
						background: check.status === 'good' ? '#ecfdf5' : check.status === 'warning' ? '#fffbeb' : '#fff1f2',
						color: check.status === 'good' ? '#059669' : check.status === 'warning' ? '#d97706' : '#e11d48'
					}
				}, check.score + '/' + check.max)
			),
			check.suggestion ? el('div', {
				style: { fontSize: 12, color: '#757575', marginTop: 4 }
			}, check.suggestion) : null
		);
	}

	/* ── Readability Panel ────────────────────────────────── */
	function ReadabilityPanel(props) {
		var postId = props.postId;
		var _rd = useState(null); var data = _rd[0]; var setData = _rd[1];
		var _rl = useState(false); var loading = _rl[0]; var setLoading = _rl[1];

		function analyze() {
			if (!postId) return;
			setLoading(true);
			wp.apiFetch({
				path: '/ai-seo-pilot/v1/readability/analyze',
				method: 'POST',
				data: { post_id: postId, force: true }
			}).then(function (r) {
				setData(r);
				setLoading(false);
			}).catch(function () { setLoading(false); });
		}

		var inner;
		if (loading) {
			inner = el('div', { style: { textAlign: 'center', padding: 12 } }, el(Spinner));
		} else if (data && data.score !== undefined) {
			var sc = data.score || 0;
			var clr = sc >= 70 ? '#10b981' : (sc >= 40 ? '#f59e0b' : '#f43f5e');
			var items = [];
			items.push(el('div', { key: 'sc', className: 'aisp-panel-score' },
				el('span', { className: 'score-num', style: { color: clr } }, sc + '/100'),
				data.level ? el('div', { style: { fontSize: 12, color: '#6b7280', marginTop: 2 } }, data.level) : null
			));
			if (data.suggestions && data.suggestions.length) {
				data.suggestions.forEach(function (s, i) {
					var text = typeof s === 'string' ? s : (s.text || s.suggestion || JSON.stringify(s));
					items.push(el('div', { key: 'sg' + i, className: 'aisp-suggestion-item' },
						el('span', { style: { color: '#111827' } }, '• '), text
					));
				});
			}
			items.push(el(Button, { key: 're', isLink: true, onClick: analyze, style: { marginTop: 8, fontSize: 11 } }, 'Re-analyze'));
			inner = el(Fragment, null, items);
		} else {
			inner = el(Button, { isSecondary: true, onClick: analyze, style: { width: '100%', justifyContent: 'center' } }, 'Analyze Readability');
		}

		return el(PanelBody, { title: 'Readability', initialOpen: false }, inner);
	}

	/* ── Focus Keyword Panel ──────────────────────────────── */
	function FocusKeywordPanel(props) {
		var postId = props.postId;
		var _kw = useState(''); var keyword = _kw[0]; var setKeyword = _kw[1];
		var _ex = useState(null); var extracted = _ex[0]; var setExtracted = _ex[1];
		var _us = useState(null); var usage = _us[0]; var setUsage = _us[1];
		var _ld = useState(false); var loading = _ld[0]; var setLoading = _ld[1];
		var _st = useState(''); var status = _st[0]; var setStatus = _st[1];

		function extractKeywords() {
			if (!postId) return;
			setLoading(true);
			setStatus('');
			wp.apiFetch({
				path: '/ai-seo-pilot/v1/keywords/extract',
				method: 'POST',
				data: { post_id: postId }
			}).then(function (r) {
				if (r.keywords) {
					setExtracted(r.keywords);
					if (r.keywords.length && !keyword) {
						var fk = r.keywords[0];
						setKeyword(typeof fk === 'string' ? fk : (fk.keyword || ''));
					}
				}
				setLoading(false);
			}).catch(function () { setLoading(false); setStatus('Error extracting.'); });
		}

		function saveKeyword() {
			if (!postId || !keyword) return;
			wp.apiFetch({
				path: '/ai-seo-pilot/v1/keywords/save',
				method: 'POST',
				data: { post_id: postId, keyword: keyword, is_focus: 1 }
			}).then(function () { setStatus('Saved!'); })
			.catch(function () { setStatus('Error saving.'); });
		}

		function analyzeUsage() {
			if (!postId || !keyword) return;
			setLoading(true);
			wp.apiFetch({
				path: '/ai-seo-pilot/v1/keywords/analyze',
				method: 'POST',
				data: { post_id: postId, keyword: keyword }
			}).then(function (r) { setUsage(r); setLoading(false); })
			.catch(function () { setLoading(false); });
		}

		var items = [];

		items.push(el(TextControl, {
			key: 'input',
			label: 'Focus Keyword',
			value: keyword,
			onChange: setKeyword,
			placeholder: 'Enter focus keyword…'
		}));

		items.push(el('div', { key: 'btns', style: { display: 'flex', gap: 6, marginBottom: 12, flexWrap: 'wrap' } },
			el(Button, { isSecondary: true, onClick: extractKeywords, disabled: loading, isSmall: true }, 'Extract with AI'),
			keyword ? el(Button, { isPrimary: true, onClick: saveKeyword, isSmall: true }, 'Save') : null,
			keyword ? el(Button, { isTertiary: true, onClick: analyzeUsage, disabled: loading, isSmall: true }, 'Analyze') : null
		));

		if (status) {
			items.push(el('div', { key: 'st', style: { fontSize: 12, color: status === 'Saved!' ? '#10b981' : '#f43f5e', marginBottom: 8 } }, status));
		}

		if (loading) {
			items.push(el('div', { key: 'ld', style: { textAlign: 'center', padding: 8 } }, el(Spinner)));
		}

		if (extracted && extracted.length) {
			items.push(el('div', { key: 'extr', style: { marginBottom: 8 } },
				el('strong', { style: { fontSize: 12 } }, 'Extracted Keywords:'),
				extracted.map(function (kw, i) {
					var kwText = typeof kw === 'string' ? kw : (kw.keyword || '');
					var score = kw.relevance_score ? ' (' + Math.round(kw.relevance_score * 100) + '%)' : '';
					return el('div', {
						key: 'kw' + i,
						style: { fontSize: 12, padding: '3px 0', cursor: 'pointer', color: '#6366f1' },
						onClick: function () { setKeyword(kwText); },
						title: 'Click to set as focus keyword'
					}, '• ' + kwText + score);
				})
			));
		}

		if (usage) {
			items.push(el('div', { key: 'usage', style: { marginTop: 8, padding: 8, background: '#f3f4f6', borderRadius: 4, fontSize: 12 } },
				el('strong', null, 'Keyword Usage'),
				usage.density !== undefined ? el('div', { style: { marginTop: 4 } }, 'Density: ' + usage.density) : null,
				usage.distribution ? el('div', null, 'Distribution: ' + usage.distribution) : null,
				usage.suggestions ? el('div', { style: { marginTop: 4, color: '#6b7280' } },
					(Array.isArray(usage.suggestions) ? usage.suggestions : [usage.suggestions]).map(function (s, i) {
						return el('div', { key: 'us' + i }, '• ' + (typeof s === 'string' ? s : JSON.stringify(s)));
					})
				) : null
			));
		}

		return el(PanelBody, { title: 'Focus Keyword', initialOpen: false }, items);
	}

	/* ── Internal Links Panel ─────────────────────────────── */
	function InternalLinksPanel(props) {
		var postId = props.postId;
		var _lk = useState(null); var links = _lk[0]; var setLinks = _lk[1];
		var _ld = useState(false); var loading = _ld[0]; var setLoading = _ld[1];
		var _mg = useState(''); var msg = _mg[0]; var setMsg = _mg[1];

		function findLinks() {
			if (!postId) return;
			setLoading(true);
			setMsg('');
			wp.apiFetch({
				path: '/ai-seo-pilot/v1/linking/suggestions',
				method: 'POST',
				data: { post_id: postId }
			}).then(function (r) {
				setLinks(r.suggestions || []);
				setMsg(r.message || '');
				setLoading(false);
			}).catch(function () { setLoading(false); setMsg('Error fetching suggestions.'); });
		}

		var inner;
		if (loading) {
			inner = el('div', { style: { textAlign: 'center', padding: 12 } }, el(Spinner));
		} else if (links !== null) {
			if (links.length === 0) {
				inner = el(Fragment, null,
					el('p', { style: { fontSize: 12, color: '#6b7280' } }, msg || 'No suggestions found.'),
					el(Button, { isLink: true, onClick: findLinks, style: { fontSize: 11 } }, 'Try again')
				);
			} else {
				var items = links.map(function (lk, i) {
					return el('div', { key: 'lk' + i, className: 'aisp-link-item' },
						el('div', null,
							el('a', { href: lk.url, target: '_blank', style: { fontWeight: 600 } }, lk.anchor_text || lk.url)
						),
						lk.reason ? el('div', { style: { color: '#6b7280', marginTop: 2 } }, lk.reason) : null,
						lk.where ? el('div', { style: { color: '#6366f1', marginTop: 2, fontStyle: 'italic' } }, '→ ' + lk.where) : null
					);
				});
				items.push(el(Button, { key: 're', isLink: true, onClick: findLinks, style: { marginTop: 8, fontSize: 11 } }, 'Refresh'));
				inner = el(Fragment, null, items);
			}
		} else {
			inner = el(Button, { isSecondary: true, onClick: findLinks, style: { width: '100%', justifyContent: 'center' } }, 'Find Link Suggestions');
		}

		return el(PanelBody, { title: 'Internal Links', initialOpen: false }, inner);
	}

	/* ── AI Content Tools Panel ───────────────────────────── */
	function ContentToolsPanel(props) {
		var postId = props.postId;
		var _im = useState(null); var improvements = _im[0]; var setImprovements = _im[1];
		var _ld = useState(false); var loading = _ld[0]; var setLoading = _ld[1];
		var _mg = useState(''); var statusMsg = _mg[0]; var setStatusMsg = _mg[1];

		function getImprovements() {
			if (!postId) return;
			setLoading(true);
			setStatusMsg('');
			wp.apiFetch({
				path: '/ai-seo-pilot/v1/optimize/improve-content',
				method: 'POST',
				data: { post_id: postId }
			}).then(function (r) {
				setImprovements(r.suggestions || []);
				if (r.message) setStatusMsg(r.message);
				setLoading(false);
			}).catch(function () { setLoading(false); setStatusMsg('Error getting suggestions.'); });
		}

		function generateSection(type) {
			if (!postId) return;
			setLoading(true);
			setStatusMsg('');
			wp.apiFetch({
				path: '/ai-seo-pilot/v1/optimize/add-section',
				method: 'POST',
				data: { post_id: postId, section_type: type }
			}).then(function (r) {
				if (r.html) {
					var block = wp.blocks.createBlock('core/freeform', { content: r.html });
					wp.data.dispatch('core/block-editor').insertBlocks(block);
					setStatusMsg('Section added!');
				}
				setLoading(false);
			}).catch(function () { setLoading(false); setStatusMsg('Error generating section.'); });
		}

		var items = [];

		items.push(el(Button, {
			key: 'improve',
			isSecondary: true,
			onClick: getImprovements,
			disabled: loading,
			style: { width: '100%', justifyContent: 'center', marginBottom: 12 }
		}, 'Get AI Improvements'));

		if (loading) {
			items.push(el('div', { key: 'ld', style: { textAlign: 'center', padding: 8 } }, el(Spinner)));
		}

		if (statusMsg) {
			items.push(el('div', { key: 'msg', style: { fontSize: 12, color: '#6b7280', marginBottom: 8 } }, statusMsg));
		}

		if (improvements && improvements.length) {
			improvements.forEach(function (imp, i) {
				var pColor = imp.priority === 'high' ? '#f43f5e' : (imp.priority === 'medium' ? '#f59e0b' : '#6366f1');
				items.push(el('div', { key: 'imp' + i, className: 'aisp-improvement' },
					el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
						el('strong', { style: { color: '#111827' } }, imp.title || 'Suggestion ' + (i + 1)),
						imp.priority ? el('span', {
							style: { fontSize: 10, padding: '1px 6px', borderRadius: 3, background: pColor + '20', color: pColor }
						}, imp.priority) : null
					),
					el('div', { style: { color: '#6b7280', marginTop: 2 } }, imp.description || '')
				));
			});
		}

		items.push(el('hr', { key: 'sep', style: { margin: '12px 0', border: 'none', borderTop: '1px solid #e0e0e0' } }));

		items.push(el('div', { key: 'slbl', style: { fontSize: 12, fontWeight: 600, marginBottom: 6, color: '#111827' } }, 'Generate Section'));
		var sectionTypes = [
			{ value: 'faq', label: 'FAQ' },
			{ value: 'statistics', label: 'Statistics' },
			{ value: 'definitions', label: 'Definitions' },
			{ value: 'summary', label: 'Summary' },
			{ value: 'conclusion', label: 'Conclusion' }
		];
		items.push(el('div', { key: 'sbtns', className: 'aisp-section-btns' },
			sectionTypes.map(function (st) {
				return el(Button, {
					key: st.value,
					isSmall: true,
					isTertiary: true,
					onClick: function () { generateSection(st.value); },
					disabled: loading
				}, st.label);
			})
		));

		return el(PanelBody, { title: 'AI Content Tools', initialOpen: false }, items);
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
				el(ScoreCircle, {
					percentage: result.percentage,
					score: result.score,
					maxScore: result.max_score
				}),
				el('div', { style: { textAlign: 'center', marginBottom: 16 } },
					el('span', {
						style: {
							display: 'inline-block', padding: '4px 12px', borderRadius: 12,
							fontSize: 12, fontWeight: 600,
							background: result.ai_ready ? '#ecfdf5' : '#fff1f2',
							color: result.ai_ready ? '#059669' : '#e11d48'
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
			}, el('div', { className: 'ai-seo-pilot-sidebar', style: { padding: 12 } },
				content,
				el(ReadabilityPanel, { postId: postData.postId }),
				el(FocusKeywordPanel, { postId: postData.postId }),
				el(InternalLinksPanel, { postId: postData.postId }),
				el(ContentToolsPanel, { postId: postData.postId })
			))
		);
	}

	registerPlugin('ai-seo-pilot-analyzer', {
		render: AnalyzerPanel,
		icon: 'superhero-alt'
	});

})();
