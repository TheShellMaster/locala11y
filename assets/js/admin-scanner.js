(function () {
	'use strict';

	if (typeof window.localA11yAdmin === 'undefined') {
		return;
	}

	const cfg = window.localA11yAdmin;
	let targets = [];
	let isRunning = false;
	let cancelRequested = false;

	const wcagTagMap = {
		wcag2a: ['wcag2a'],
		wcag2aa: ['wcag2a', 'wcag2aa'],
		wcag21aa: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'],
		wcag22aa: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'],
	};

	async function apiFetch(path, options) {
		const opts = Object.assign(
			{
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': cfg.nonce,
				},
				credentials: 'same-origin',
			},
			options || {}
		);
		const res = await fetch(cfg.restUrl + path, opts);
		if (!res.ok) {
			throw new Error('HTTP ' + res.status);
		}
		return res.json();
	}

	function escapeText(str) {
		const div = document.createElement('div');
		div.textContent = str || '';
		return div.innerHTML;
	}

	function initStatusSelects() {
		document.querySelectorAll('.locala11y-status-select').forEach(function (select) {
			select.addEventListener('change', async function () {
				const id = select.getAttribute('data-finding-id');
				select.disabled = true;
				try {
					await apiFetch('/findings/' + encodeURIComponent(id), {
						method: 'PATCH',
						body: JSON.stringify({ status: select.value }),
					});
				} catch (err) {
					window.alert('Error updating status: ' + err.message);
				} finally {
					select.disabled = false;
				}
			});
		});
	}

	async function loadTargets() {
		const listEl = document.getElementById('locala11y-targets-list');
		const countEl = document.getElementById('locala11y-targets-count');
		if (!listEl) {
			return;
		}

		try {
			const data = await apiFetch('/targets', { method: 'GET' });
			targets = Array.isArray(data.targets) ? data.targets : [];

			if (!targets.length) {
				listEl.textContent = 'No published pages found.';
				return;
			}

			listEl.innerHTML = '';
			targets.forEach(function (t, idx) {
				const row = document.createElement('label');
				row.className = 'locala11y-target-row';
				row.innerHTML =
					'<input type="checkbox" class="locala11y-target-cb" data-index="' + idx + '" checked> ' +
					'<span><strong>' + escapeText(t.title) + '</strong> <span class="locala11y-target-url">(' + escapeText(t.url) + ')</span></span>';
				listEl.appendChild(row);
			});

			if (countEl) {
				countEl.textContent = targets.length + ' page(s)';
			}
		} catch (err) {
			listEl.textContent = 'Failed to load targets: ' + err.message;
		}
	}

	function runAxeInIframe(url, wcagLevel, viewport) {
		return new Promise(function (resolve, reject) {
			const iframe = document.createElement('iframe');
			const width = viewport === 'mobile' ? 390 : 1280;
			iframe.setAttribute('aria-hidden', 'true');
			iframe.style.cssText = 'position:fixed;left:-99999px;top:0;width:' + width + 'px;height:900px;border:0;visibility:hidden;';

			const cleanup = function () {
				if (iframe.parentNode) {
					iframe.parentNode.removeChild(iframe);
				}
			};

			const timer = window.setTimeout(function () {
				cleanup();
				reject(new Error('Page load timeout'));
			}, 25000);

			iframe.onload = function () {
				try {
					const win = iframe.contentWindow;
					const doc = iframe.contentDocument;
					if (!win || !doc || !doc.documentElement) {
						throw new Error(cfg.i18n.frameError);
					}

					const script = doc.createElement('script');
					script.src = cfg.axeUrl;
					script.onload = function () {
						const tags = wcagTagMap[wcagLevel] || wcagTagMap.wcag22aa;
						win.axe
							.run(doc, {
								runOnly: { type: 'tag', values: tags },
								resultTypes: ['violations', 'incomplete'],
							})
							.then(function (results) {
								window.clearTimeout(timer);
								cleanup();
								resolve(results);
							})
							.catch(function (err) {
								window.clearTimeout(timer);
								cleanup();
								reject(err);
							});
					};
					script.onerror = function () {
						window.clearTimeout(timer);
						cleanup();
						reject(new Error('Could not inject local axe-core into iframe'));
					};
					doc.head.appendChild(script);
				} catch (err) {
					window.clearTimeout(timer);
					cleanup();
					reject(err);
				}
			};

			iframe.onerror = function () {
				window.clearTimeout(timer);
				cleanup();
				reject(new Error(cfg.i18n.frameError));
			};

			iframe.src = url;
			document.body.appendChild(iframe);
		});
	}

	function flattenAxeResults(axeResults) {
		const out = [];
		const pushList = function (list, type) {
			(list || []).forEach(function (rule) {
				const wcagTags = (rule.tags || []).filter(function (tag) {
					return tag.indexOf('wcag') === 0;
				}).join(', ');

				(rule.nodes || []).forEach(function (node) {
					out.push({
						finding_type: type,
						rule_id: rule.id || 'unknown',
						impact: node.impact || rule.impact || 'moderate',
						wcag_tags: wcagTags,
						help_text: rule.help || '',
						description: rule.description || '',
						help_url: rule.helpUrl || '',
						selector: Array.isArray(node.target) ? node.target.join(' > ') : '',
						html_snippet: node.html || '',
						failure_summary: node.failureSummary || '',
					});
				});
			});
		};

		pushList(axeResults.violations, 'violation');
		pushList(axeResults.incomplete, 'incomplete');
		return out;
	}

	function initScanner() {
		const startBtn = document.getElementById('locala11y-start-scan');
		const cancelBtn = document.getElementById('locala11y-cancel-scan');
		const selectAll = document.getElementById('locala11y-select-all');
		const progressWrap = document.getElementById('locala11y-progress-wrap');
		const progressFill = document.getElementById('locala11y-progress-fill');
		const progressText = document.getElementById('locala11y-progress-text');

		if (!startBtn) {
			return;
		}

		if (selectAll) {
			selectAll.addEventListener('change', function () {
				document.querySelectorAll('.locala11y-target-cb').forEach(function (cb) {
					cb.checked = selectAll.checked;
				});
			});
		}

		cancelBtn.addEventListener('click', function () {
			cancelRequested = true;
			cancelBtn.disabled = true;
		});

		startBtn.addEventListener('click', async function () {
			if (isRunning) {
				return;
			}

			const selected = [];
			document.querySelectorAll('.locala11y-target-cb:checked').forEach(function (cb) {
				const idx = parseInt(cb.getAttribute('data-index'), 10);
				if (targets[idx]) {
					selected.push(targets[idx]);
				}
			});

			if (!selected.length) {
				window.alert(cfg.i18n.noTargets);
				return;
			}

			const wcagLevel = document.getElementById('locala11y-wcag-level').value;
			const viewport = document.getElementById('locala11y-viewport').value;

			isRunning = true;
			cancelRequested = false;
			startBtn.disabled = true;
			cancelBtn.disabled = false;
			progressWrap.hidden = false;
			progressFill.style.width = '2%';

			let scanId = 0;

			try {
				const created = await apiFetch('/scans', {
					method: 'POST',
					body: JSON.stringify({
						wcag_level: wcagLevel,
						viewport: viewport,
						total_pages: selected.length,
					}),
				});
				scanId = created.scan_id;

				for (let i = 0; i < selected.length; i++) {
					if (cancelRequested) {
						break;
					}
					const target = selected[i];
					progressText.textContent = cfg.i18n.scanning + ' (' + (i + 1) + '/' + selected.length + '): ' + target.title;

					try {
						const axeRes = await runAxeInIframe(target.url, wcagLevel, viewport);
						const findings = flattenAxeResults(axeRes);
						await apiFetch('/scans/' + scanId + '/results', {
							method: 'POST',
							body: JSON.stringify({
								post_id: target.post_id,
								page_title: target.title,
								page_url: target.url,
								findings: findings,
							}),
						});
					} catch (pageErr) {
						progressText.textContent = 'Skipped ' + target.title + ': ' + pageErr.message;
					}

					const pct = Math.round(((i + 1) / selected.length) * 100);
					progressFill.style.width = pct + '%';
				}

				await apiFetch('/scans/' + scanId + '/complete', {
					method: 'POST',
					body: JSON.stringify({ status: cancelRequested ? 'cancelled' : 'completed' }),
				});

				progressText.textContent = cancelRequested ? cfg.i18n.cancelled : cfg.i18n.completed;
				window.setTimeout(function () {
					window.location.reload();
				}, 800);
			} catch (err) {
				progressText.textContent = 'Error: ' + err.message;
			} finally {
				isRunning = false;
				startBtn.disabled = false;
				cancelBtn.disabled = true;
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		initStatusSelects();
		loadTargets();
		initScanner();
	});
})();
