/**
 * Bridge — Gallery lightbox.
 *
 * Loaded only on pages that render `bridge/gallery`. Drives the native
 * <dialog> printed by render.php: the browser brings the focus trap, the
 * Escape key, the backdrop and the inert page with it, so what is left here is
 * choosing which image to show and stepping between them.
 */

const GALLERIES = '.bridge-gallery';

const init = (gallery) => {
	const dialog = gallery.querySelector('.bridge-gallery__lightbox');
	const grid = gallery.querySelector('[data-bridge-lightbox]');

	if (!dialog || !grid || typeof dialog.showModal !== 'function') {
		return;
	}

	const triggers = Array.from(
		grid.querySelectorAll('.bridge-gallery__trigger')
	);

	if (!triggers.length) {
		return;
	}

	// Looked up after the early return, not before it: a gallery with no
	// triggers leaves without having queried for two elements it will not use.
	const img = dialog.querySelector('.bridge-gallery__full');
	const caption = dialog.querySelector('.bridge-gallery__caption');

	let index = 0;

	const show = (next) => {
		index = (next + triggers.length) % triggers.length;
		const trigger = triggers[index];
		const inner = trigger.querySelector('img');

		img.src = trigger.dataset.full || '';
		// The grid image already carries a description; reusing it keeps the
		// full-size view from being announced as an unlabelled image.
		img.alt = inner ? inner.alt : '';
		caption.textContent = trigger.dataset.caption || '';
		caption.hidden = !caption.textContent;
	};

	triggers.forEach((trigger, i) => {
		trigger.addEventListener('click', () => {
			show(i);
			dialog.showModal();
		});
	});

	dialog
		.querySelector('.bridge-gallery__close')
		?.addEventListener('click', () => dialog.close());
	dialog
		.querySelector('.bridge-gallery__prev')
		?.addEventListener('click', () => show(index - 1));
	dialog
		.querySelector('.bridge-gallery__next')
		?.addEventListener('click', () => show(index + 1));

	dialog.addEventListener('keydown', (event) => {
		if (event.key === 'ArrowLeft') {
			show(index - 1);
		} else if (event.key === 'ArrowRight') {
			show(index + 1);
		}
	});

	// Clicking the backdrop closes. The dialog element itself covers the whole
	// window, so the test is whether the click landed outside the figure.
	dialog.addEventListener('click', (event) => {
		if (event.target === dialog) {
			dialog.close();
		}
	});

	// Returning focus to the thumbnail that opened the viewer, rather than to
	// the top of the document, so a keyboard user carries on where they were.
	dialog.addEventListener('close', () => {
		triggers[index]?.focus();
	});
};

const run = () => document.querySelectorAll(GALLERIES).forEach(init);

if (document.readyState !== 'loading') {
	run();
} else {
	document.addEventListener('DOMContentLoaded', run, { once: true });
}
