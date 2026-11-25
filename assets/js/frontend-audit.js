(function () {
	'use strict';

	if (typeof window.localA11yFront === 'undefined' || typeof window.axe === 'undefined') {
		return;
	}

	const cfg = window.localA11yFront;
	let drawerEl = null;
	let closeBtnEl = null;
	let triggerEl = null;
	let highlightedEl = null;

	function clearHighlight() {
		if (highlightedEl) {
			highlightedEl.classList.remove('locala11y-highlight-outline');
			highlightedEl = null;
		}
	}

	function closeDrawer() {
		if (!drawerEl) {
			return;
		}
		clearHighlight();
		drawerEl.hidden = true;
		if (triggerEl && typeof triggerEl.focus === 'function') {
			triggerEl.focus();
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

		closeBtnEl = document.createElement('button');
		closeBtnEl.type = 'button';
		closeBtnEl.className = 'locala11y-drawer__btn locala11y-drawer__btn--close';
		closeBtnEl.textContent = cfg.i18n.close;
		closeBtnEl.addEventListener('click', closeDrawer);

		actions.appendChild(rerunBtn);
		actions.appendChild(closeBtnEl);
		header.appendChild(h2);
		header.appendChild(actions);

		const body = document.createElement('div');
		body.className = 'locala11y-drawer__body';

		drawerEl.appendChild(header);
		drawerEl.appendChild(body);
		document.body.appendChild(drawerEl);

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && drawerEl && !drawerEl.hidden) {
				closeDrawer();
			}
		});

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

	function renderRuleNodeCard(rule, node) {
		const card = document.createElement('div');
		card.className = 'locala11y-drawer__item';

		const impact = node.impact || rule.impact || 'moderate';
		const badge = document.createElement('span');
		badge.className = 'locala11y-drawer__impact locala11y-drawer__impact--' + impact;
		badge.textContent = impact.toUpperCase();

		const title = document.createElement('strong');
		title.textContent = rule.help;

		card.appendChild(badge);
		card.appendChild(title);

		if (node.failureSummary) {
			const summaryEl = document.createElement('p');
			summaryEl.className = 'locala11y-drawer__summary';
			summaryEl.textContent = node.failureSummary;
			card.appendChild(summaryEl);
		}

		const selector = Array.isArray(node.target) ? node.target[0] : '';
		if (selector) {
			const selCode = document.createElement('div');
			selCode.className = 'locala11y-drawer__selector';
			selCode.textContent = selector;
			card.appendChild(selCode);
		}

		const cardActions = document.createElement('div');
		cardActions.className = 'locala11y-drawer__card-actions';

		if (selector) {
			const btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'locala11y-drawer__btn';
			btn.textContent = cfg.i18n.highlight;
			btn.addEventListener('click', function () {
				highlightSelector(selector);
			});
			cardActions.appendChild(btn);
		}

		if (rule.helpUrl) {
			const guideLink = document.createElement('a');
			guideLink.href = rule.helpUrl;
			guideLink.target = '_blank';
			guideLink.rel = 'noopener noreferrer';
			guideLink.className = 'locala11y-drawer__link';
			guideLink.textContent = cfg.i18n.guideLink || 'WCAG guide ↗';
			cardActions.appendChild(guideLink);
		}

		if (cardActions.firstChild) {
			card.appendChild(cardActions);
		}

		return card;
	}

	async function runAudit() {
		const drawer = ensureDrawer();
		const body = drawer.querySelector('.locala11y-drawer__body');
		drawer.hidden = false;
		clearHighlight();
		body.textContent = cfg.i18n.running;
		if (closeBtnEl) {
			closeBtnEl.focus();
		}

		try {
			const results = await window.axe.run(
				{ exclude: [['#locala11y-drawer'], ['#wpadminbar']] },
				{
					runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] },
					resultTypes: ['violations', 'incomplete'],
				}
			);

			while (body.firstChild) {
				body.removeChild(body.firstChild);
			}

			const notice = document.createElement('div');
			notice.className = 'locala11y-drawer__notice';
			notice.textContent = cfg.i18n.honestNotice;
			body.appendChild(notice);

			const summary = document.createElement('p');
			const vCount = results.violations.reduce(function (acc, r) { return acc + r.nodes.length; }, 0);
			const iCount = results.incomplete.reduce(function (acc, r) { return acc + r.nodes.length; }, 0);

			const strongCount = document.createElement('strong');
			strongCount.textContent = cfg.i18n.violations + ': ' + vCount;
			summary.appendChild(strongCount);
			summary.appendChild(document.createTextNode(' · ' + cfg.i18n.needsReview + ': ' + iCount));
			body.appendChild(summary);

			if (!results.violations.length) {
				const ok = document.createElement('p');
				ok.textContent = cfg.i18n.noIssues;
				body.appendChild(ok);
			} else {
				results.violations.forEach(function (rule) {
					rule.nodes.forEach(function (node) {
						body.appendChild(renderRuleNodeCard(rule, node));
					});
				});
			}

			if (results.incomplete.length) {
				const details = document.createElement('details');
				details.className = 'locala11y-drawer__details';
				const sum = document.createElement('summary');
				sum.textContent = (cfg.i18n.manualReviewTitle || cfg.i18n.needsReview) + ' (' + iCount + ')';
				details.appendChild(sum);

				results.incomplete.forEach(function (rule) {
					rule.nodes.forEach(function (node) {
						details.appendChild(renderRuleNodeCard(rule, node));
					});
				});
				body.appendChild(details);
			}
		} catch (err) {
			body.textContent = 'Audit error: ' + err.message;
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		triggerEl = document.querySelector('#wp-admin-bar-locala11y-quick-audit a');
		if (triggerEl) {
			triggerEl.addEventListener('click', function (e) {
				e.preventDefault();
				runAudit();
			});
		}
	});
})();
