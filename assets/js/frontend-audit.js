(function () {
	'use strict';

	if (typeof window.localA11yFront === 'undefined' || typeof window.axe === 'undefined') {
		return;
	}

	const cfg = window.localA11yFront;
	let drawerEl = null;
	let highlightedEl = null;

	function clearHighlight() {
		if (highlightedEl) {
			highlightedEl.classList.remove('locala11y-highlight-outline');
			highlightedEl = null;
		}
	}

	function ensureDrawer() {
		if (drawerEl) {
			return drawerEl;
		}
		drawerEl = document.createElement('aside');
		drawerEl.id = 'locala11y-drawer';
		drawerEl.setAttribute('role', 'dialog');
		drawerEl.setAttribute('aria-label', cfg.i18n.title);
		drawerEl.hidden = true;

		const header = document.createElement('div');
		header.className = 'locala11y-drawer__header';

		const h2 = document.createElement('h2');
		h2.textContent = cfg.i18n.title;

		const actions = document.createElement('div');
		actions.className = 'locala11y-drawer__actions';

		const rerunBtn = document.createElement('button');
		rerunBtn.type = 'button';
		rerunBtn.className = 'locala11y-drawer__btn';
		rerunBtn.textContent = cfg.i18n.rerun;
		rerunBtn.addEventListener('click', runAudit);

		const closeBtn = document.createElement('button');
		closeBtn.type = 'button';
		closeBtn.className = 'locala11y-drawer__btn locala11y-drawer__btn--close';
		closeBtn.textContent = cfg.i18n.close;
		closeBtn.addEventListener('click', function () {
			clearHighlight();
			drawerEl.hidden = true;
		});

		actions.appendChild(rerunBtn);
		actions.appendChild(closeBtn);
		header.appendChild(h2);
		header.appendChild(actions);

		const body = document.createElement('div');
		body.className = 'locala11y-drawer__body';

		drawerEl.appendChild(header);
		drawerEl.appendChild(body);
		document.body.appendChild(drawerEl);
		return drawerEl;
	}

	function highlightSelector(selector) {
		clearHighlight();
		try {
			const el = document.querySelector(selector);
			if (el) {
				highlightedEl = el;
				el.classList.add('locala11y-highlight-outline');
				el.scrollIntoView({ behavior: 'smooth', block: 'center' });
			}
		} catch (e) {
			// Ignore complex shadow/iframe selectors.
		}
	}

	async function runAudit() {
		const drawer = ensureDrawer();
		const body = drawer.querySelector('.locala11y-drawer__body');
		drawer.hidden = false;
		clearHighlight();
		body.textContent = cfg.i18n.running;

		try {
			const results = await window.axe.run(
				{ exclude: [['#locala11y-drawer'], ['#wpadminbar']] },
				{
					runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] },
					resultTypes: ['violations', 'incomplete'],
				}
			);

			body.innerHTML = '';

			const notice = document.createElement('div');
			notice.className = 'locala11y-drawer__notice';
			notice.textContent = cfg.i18n.honestNotice;
			body.appendChild(notice);

			const summary = document.createElement('p');
			const vCount = results.violations.reduce(function (acc, r) { return acc + r.nodes.length; }, 0);
			const iCount = results.incomplete.reduce(function (acc, r) { return acc + r.nodes.length; }, 0);
			summary.innerHTML = '<strong>' + cfg.i18n.violations + ': ' + vCount + '</strong> · ' + cfg.i18n.needsReview + ': ' + iCount;
			body.appendChild(summary);

			if (!results.violations.length) {
				const ok = document.createElement('p');
				ok.textContent = cfg.i18n.noIssues;
				body.appendChild(ok);
				return;
			}

			results.violations.forEach(function (rule) {
				rule.nodes.forEach(function (node) {
					const card = document.createElement('div');
					card.className = 'locala11y-drawer__item';

					const impact = node.impact || rule.impact || 'moderate';
					const badge = document.createElement('span');
					badge.className = 'locala11y-drawer__impact locala11y-drawer__impact--' + impact;
					badge.textContent = impact.toUpperCase();

					const title = document.createElement('strong');
					title.textContent = rule.help;

					const selector = Array.isArray(node.target) ? node.target[0] : '';
					const selCode = document.createElement('div');
					selCode.style.cssText = 'font-family:monospace;font-size:11px;margin:6px 0;word-break:break-all;color:#50575e;';
					selCode.textContent = selector;

					const btn = document.createElement('button');
					btn.type = 'button';
					btn.className = 'locala11y-drawer__btn';
					btn.textContent = cfg.i18n.highlight;
					btn.addEventListener('click', function () {
						highlightSelector(selector);
					});

					card.appendChild(badge);
					card.appendChild(title);
					card.appendChild(selCode);
					card.appendChild(btn);
					body.appendChild(card);
				});
			});
		} catch (err) {
			body.textContent = 'Audit error: ' + err.message;
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		const trigger = document.querySelector('#wp-admin-bar-locala11y-quick-audit a');
		if (trigger) {
			trigger.addEventListener('click', function (e) {
				e.preventDefault();
				runAudit();
			});
		}
	});
})();
