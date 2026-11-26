/**
 * Bridge — a team member's profile pop-up.
 *
 * Each person's card carries a <dialog> (src/blocks/cards/partials/
 * person-dialog.php) and a button over the whole card that opens it.
 * showModal() gives the backdrop, the focus trap, Escape to close and focus
 * back on the card. This adds the rest:
 *
 *   - the pop-up takes the card's own background and text colour, whatever
 *     the card style or band set them to;
 *   - a click on the dimmed backdrop closes it, as does the close button;
 *   - the page behind does not scroll while it is open.
 */

/**
 * The colour the card is painted in: the card itself, or (Design 1, where
 * the card is transparent and its panel is painted) the card's body.
 *
 * @param {Element} card The card.
 * @return {{bg: string, fg: string}} Computed colours.
 */
function cardColours(card) {
	const candidates = [card, card.querySelector('.post-card__body')];

	for (const el of candidates) {
		if (!el) {
			continue;
		}
		const style = getComputedStyle(el);
		const bg = style.backgroundColor;
		const clear = bg === 'transparent' || /rgba?\([^)]*,\s*0\)$/.test(bg);

		if (!clear) {
			return { bg, fg: style.color };
		}
	}

	return { bg: '', fg: '' };
}

function open(button) {
	const dialog = document.getElementById(
		button.getAttribute('aria-controls')
	);

	if (!dialog || typeof dialog.showModal !== 'function') {
		return;
	}

	const { bg, fg } = cardColours(button.closest('.post-card'));

	if (bg) {
		dialog.style.setProperty('--bridge-person-bg', bg);
		dialog.style.setProperty('--bridge-person-fg', fg);
	}

	dialog.showModal();
	document.documentElement.classList.add('has-open-dialog');
	// Start at the top of the profile every time it opens.
	dialog.scrollTop = 0;
	const body = dialog.querySelector('.bridge-person__body');
	if (body) {
		body.scrollTop = 0;
	}
}

document.addEventListener('click', (event) => {
	const opener = event.target.closest('[data-bridge-person-open]');

	if (opener) {
		event.preventDefault();
		open(opener);

		return;
	}

	const closer = event.target.closest('[data-bridge-person-close]');

	if (closer) {
		closer.closest('dialog')?.close();

		return;
	}

	// A click on the backdrop lands on the <dialog> itself, outside its
	// panel.
	if (
		event.target instanceof HTMLDialogElement &&
		event.target.classList.contains('bridge-person')
	) {
		event.target.close();
	}
});

document.addEventListener(
	'close',
	(event) => {
		if (event.target.classList?.contains('bridge-person')) {
			document.documentElement.classList.remove('has-open-dialog');
		}
	},
	true
);
