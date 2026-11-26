/**
 * Bridge — Tab Block, on the page.
 *
 * Every panel is in the HTML; this shows one at a time. The tab row follows
 * the ARIA tabs pattern: arrow keys move between tabs (and open them), Home
 * and End jump to the ends, and only the open tab is in the Tab order.
 * Previous and Next step through the same panels and keep "Step 1 of 5" up to
 * date. When a step is changed from the buttons below and the tab row has
 * scrolled off the top of the screen, the page scrolls back to it, so a
 * reader on a phone lands at the start of the new step rather than the end.
 */

function initTabs(root) {
	const tabs = [...root.querySelectorAll('[role="tab"]')];

	if (!tabs.length) {
		return;
	}

	const panels = tabs.map((tab) =>
		root.querySelector('#' + CSS.escape(tab.getAttribute('aria-controls')))
	);
	const nav = root.querySelector('[data-bridge-tabs-nav]');
	const prev = root.querySelector('[data-bridge-tabs-prev]');
	const next = root.querySelector('[data-bridge-tabs-next]');
	const count = root.querySelector('[data-bridge-tabs-count]');
	const template = count ? count.dataset.template || '' : '';
	let current = Math.max(
		0,
		tabs.findIndex((tab) => tab.getAttribute('aria-selected') === 'true')
	);

	function show(index, { focusTab = false, fromSteps = false } = {}) {
		index = Math.max(0, Math.min(tabs.length - 1, index));

		tabs.forEach((tab, i) => {
			const on = i === index;
			tab.setAttribute('aria-selected', on ? 'true' : 'false');
			tab.tabIndex = on ? 0 : -1;
			if (panels[i]) {
				panels[i].hidden = !on;
			}
		});

		current = index;

		if (focusTab) {
			tabs[index].focus();
		}

		// Keep the open tab in view in a row that scrolls sideways.
		if (nav && nav.scrollWidth > nav.clientWidth) {
			const tab = tabs[index];
			const left =
				tab.offsetLeft - (nav.clientWidth - tab.offsetWidth) / 2;
			nav.scrollTo({ left, behavior: 'smooth' });
		}

		if (prev) {
			prev.disabled = index === 0;
		}
		if (next) {
			next.disabled = index === tabs.length - 1;
		}
		if (count && template) {
			count.textContent = template
				.replace('%1$d', String(index + 1))
				.replace('%2$d', String(tabs.length));
		}

		if (fromSteps && nav && nav.getBoundingClientRect().top < 0) {
			nav.scrollIntoView({ block: 'start', behavior: 'smooth' });
		}
	}

	tabs.forEach((tab, i) => {
		tab.addEventListener('click', () => show(i));
		tab.addEventListener('keydown', (event) => {
			const keys = {
				ArrowRight: current + 1,
				ArrowLeft: current - 1,
				Home: 0,
				End: tabs.length - 1,
			};
			if (event.key in keys) {
				event.preventDefault();
				let target = keys[event.key];
				// Wrap around at the ends, as the pattern expects.
				if (target < 0) {
					target = tabs.length - 1;
				} else if (target >= tabs.length) {
					target = 0;
				}
				show(target, { focusTab: true });
			}
		});
	});

	if (prev) {
		prev.addEventListener('click', () =>
			show(current - 1, { fromSteps: true })
		);
	}
	if (next) {
		next.addEventListener('click', () =>
			show(current + 1, { fromSteps: true })
		);
	}

	root.classList.add('is-ready');
	show(current);
}

function init() {
	document.querySelectorAll('[data-bridge-tabs]').forEach((root) => {
		if (!root.dataset.bridgeTabsReady) {
			root.dataset.bridgeTabsReady = '1';
			initTabs(root);
		}
	});
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}
