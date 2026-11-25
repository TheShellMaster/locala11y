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

	function buildRestUrl(path) {
		const base = String(cfg.restUrl || '').replace(/\/+$/, '');
		const cleanPath = String(path || '').replace(/^\/+/, '');
		if (base.indexOf('?') !== -1) {
			const parts = cleanPath.split('?');
			const endpoint = parts[0];
			const qs = parts[1] ? '&' + parts[1] : '';
			return base + '/' + endpoint + qs;
		}
		return base + '/' + cleanPath;
	}

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
		const res = await fetch(buildRestUrl(path), opts);
		if (!res.ok) {
			throw new Error('HTTP ' + res.status);
		}
		return res.json();
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

	function initGlobalUiHandlers() {
		document.querySelectorAll('.locala11y-print-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				window.print();
			});
		});

		document.querySelectorAll('form[data-locala11y-confirm]').forEach(function (form) {
			form.addEventListener('submit', function (e) {
				const msg = form.getAttribute('data-locala11y-confirm') || 'Are you sure?';
				if (!window.confirm(msg)) {
					e.preventDefault();
				}
			});
		});

		document.querySelectorAll('.locala11y-copy-btn').forEach(function (btn) {
			btn.addEventListener('click', async function () {
				const text = btn.getAttribute('data-copy') || '';
				if (!text) {
					return;
				}
				const originalLabel = btn.textContent;
				try {
					if (navigator.clipboard && navigator.clipboard.writeText) {
						await navigator.clipboard.writeText(text);
					} else {
						const ta = document.createElement('textarea');
						ta.value = text;
						ta.style.position = 'fixed';
						ta.style.left = '-9999px';
						document.body.appendChild(ta);
						ta.select();
						document.execCommand('copy');
						document.body.removeChild(ta);
					}
					btn.textContent = cfg.i18n.copied || 'Copied!';
					window.setTimeout(function () {
						btn.textContent = originalLabel;
					}, 1500);
				} catch (e) {
					// Clipboard denied silently.
				}
			});
		});
	}

	function renderTargets() {
		const listEl = document.getElementById('locala11y-targets-list');
		const countEl = document.getElementById('locala11y-targets-count');
		if (!listEl) {
			return;
		}

		while (listEl.firstChild) {
			listEl.removeChild(listEl.firstChild);
		}

		if (!targets.length) {
			const emptyP = document.createElement('p');
			emptyP.textContent = cfg.i18n.noTargetsFound || 'No published pages found.';
			listEl.appendChild(emptyP);
			if (countEl) {
				countEl.textContent = '';
			}
			return;
		}

		targets.forEach(function (t, idx) {
			const row = document.createElement('label');
			row.className = 'locala11y-target-row';

			const cb = document.createElement('input');
			cb.type = 'checkbox';
			cb.className = 'locala11y-target-cb';
			cb.setAttribute('data-index', String(idx));
			cb.checked = true;

			const textWrap = document.createElement('span');
			const titleEl = document.createElement('strong');
			titleEl.textContent = t.title || t.url;

			const space = document.createTextNode(' ');

			const urlEl = document.createElement('span');
			urlEl.className = 'locala11y-target-url';
			urlEl.textContent = '(' + t.url + ')';

			textWrap.appendChild(titleEl);
			textWrap.appendChild(space);
			textWrap.appendChild(urlEl);

			row.appendChild(cb);
			row.appendChild(textWrap);
			listEl.appendChild(row);
		});

		if (countEl) {
			countEl.textContent = targets.length + ' ' + (cfg.i18n.pagesCount || 'page(s)');
		}
	}

	async function loadTargets() {
		const listEl = document.getElementById('locala11y-targets-list');
		if (!listEl) {
			return;
		}

		try {
			const data = await apiFetch('/targets', { method: 'GET' });
			targets = Array.isArray(data.targets) ? data.targets : [];
			renderTargets();
		} catch (err) {
			listEl.textContent = 'Failed to load targets: ' + err.message;
		}
	}

	function initCustomUrlAdder() {
		const input = document.getElementById('locala11y-custom-url');
		const addBtn = document.getElementById('locala11y-add-url');
		if (!input || !addBtn) {
			return;
		}

		addBtn.addEventListener('click', function () {
			const raw = input.value.trim();
			if (!raw) {
				return;
			}

			let parsed;
			try {
				parsed = new URL(raw, window.location.origin);
			} catch (e) {
				window.alert(cfg.i18n.invalidCustomUrl);
				return;
			}

			const expectedOrigin = cfg.siteOrigin || window.location.origin;
			if (parsed.origin.toLowerCase() !== expectedOrigin.toLowerCase()) {
				window.alert(cfg.i18n.invalidCustomUrl);
				return;
			}

			const normalized = parsed.href;
			const exists = targets.some(function (t) {
				return String(t.url).replace(/\/+$/, '') === normalized.replace(/\/+$/, '');
			});
			if (exists) {
				window.alert(cfg.i18n.duplicateUrl);
				return;
			}

			const limit = parseInt(cfg.batchLimit || 0, 10);
			if (limit > 0 && targets.length >= limit) {
				window.alert(cfg.i18n.limitReached);
				return;
			}

			targets.unshift({
				post_id: 0,
				title: parsed.pathname || '/',
				url: normalized,
				type: 'custom',
			});
			input.value = '';
			renderTargets();
		});
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
						const fontsReady = doc.fonts && doc.fonts.ready
							? doc.fonts.ready.catch(function () { return null; })
							: Promise.resolve();

						fontsReady.then(function () {
							window.setTimeout(function () {
								const tags = wcagTagMap[wcagLevel] || wcagTagMap.wcag22aa;
								win.axe
									.run(
										{ include: [doc], exclude: [['#wpadminbar'], ['#locala11y-drawer']] },
										{
											runOnly: { type: 'tag', values: tags },
											resultTypes: ['violations', 'incomplete'],
										}
									)
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
							}, 250);
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

	function renderSkippedWarnings(skippedPages) {
		const warnBox = document.getElementById('locala11y-scan-warnings');
		if (!warnBox) {
			return;
		}
		while (warnBox.firstChild) {
			warnBox.removeChild(warnBox.firstChild);
		}
		if (!skippedPages.length) {
			warnBox.hidden = true;
			return;
		}

		const intro = document.createElement('p');
		const strong = document.createElement('strong');
		strong.textContent = cfg.i18n.skippedNotice;
		intro.appendChild(strong);
		warnBox.appendChild(intro);

		const ul = document.createElement('ul');
		skippedPages.forEach(function (item) {
			const li = document.createElement('li');
			const a = document.createElement('a');
			a.href = item.url;
			a.target = '_blank';
			a.rel = 'noopener noreferrer';
			a.textContent = item.title || item.url;
			li.appendChild(a);
			li.appendChild(document.createTextNode(' — ' + item.reason));
			ul.appendChild(li);
		});
		warnBox.appendChild(ul);

		const reloadBtn = document.createElement('button');
		reloadBtn.type = 'button';
		reloadBtn.className = 'button button-secondary';
		reloadBtn.style.marginTop = '8px';
		reloadBtn.textContent = cfg.i18n.reloadResults || 'Refresh page to view saved results';
		reloadBtn.addEventListener('click', function () {
			window.location.reload();
		});
		warnBox.appendChild(reloadBtn);

		warnBox.hidden = false;
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
			renderSkippedWarnings([]);

			const skippedPages = [];
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
						skippedPages.push({
							title: target.title,
							url: target.url,
							reason: pageErr.message,
						});
					}

					const pct = Math.round(((i + 1) / selected.length) * 100);
					progressFill.style.width = pct + '%';
				}

				await apiFetch('/scans/' + scanId + '/complete', {
					method: 'POST',
					body: JSON.stringify({ status: cancelRequested ? 'cancelled' : 'completed' }),
				});

				progressText.textContent = cancelRequested ? cfg.i18n.cancelled : cfg.i18n.completed;

				if (skippedPages.length) {
					renderSkippedWarnings(skippedPages);
				} else {
					window.setTimeout(function () {
						window.location.reload();
					}, 800);
				}
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
		initGlobalUiHandlers();
		loadTargets();
		initCustomUrlAdder();
		initScanner();
	});
})();
