/**
 * Bridge — filter buttons over a Cards grid.
 *
 * The bar ships `hidden` (src/blocks/cards/render.php) so a visitor without
 * scripts sees every card and no buttons that would do nothing. Here it is
 * shown, and a press shows the cards whose `data-filter-keys` include the
 * button's value; "All" (an empty value) shows every card. The grid reflows
 * on its own, since the hidden cards leave it.
 */

function initFilters(bar) {
	const grid = bar.nextElementSibling;

	if (!grid) {
		return;
	}

	const buttons = [...bar.querySelectorAll('[data-filter]')];
	const cards = [...grid.children].filter((el) =>
		el.hasAttribute('data-filter-keys')
	);

	const empty = document.createElement('p');
	empty.className = 'bridge-cards__filter-empty';
	empty.hidden = true;
	empty.textContent = bar.dataset.emptyText || 'Nothing here yet.';
	grid.after(empty);

	const apply = (value) => {
		let shown = 0;

		cards.forEach((card) => {
			const keys = (card.dataset.filterKeys || '').split(' ');
			const match = !value || keys.includes(value);

			card.hidden = !match;
			card.classList.toggle('is-filter-in', match);
			shown += match ? 1 : 0;
		});

		buttons.forEach((button) =>
			button.setAttribute(
				'aria-pressed',
				button.dataset.filter === value ? 'true' : 'false'
			)
		);

		empty.hidden = shown > 0;
	};

	buttons.forEach((button) =>
		button.addEventListener('click', () => apply(button.dataset.filter))
	);

	bar.hidden = false;
}

function init() {
	document.querySelectorAll('[data-bridge-cards-filters]').forEach((bar) => {
		if (!bar.dataset.ready) {
			bar.dataset.ready = '1';
			initFilters(bar);
		}
	});
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}
