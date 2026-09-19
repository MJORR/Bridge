/**
 * Bridge — the media picker on the Site Options screen.
 *
 * That screen is plain WordPress markup rather than the React app the Theme
 * Options screen is, so its one image field is wired up by hand. This is all
 * it takes: open core's media modal, write the chosen attachment's id into the
 * field the form submits, and redraw the preview beside it.
 *
 * Everything it touches is found by `data-` attribute rather than by id, so a
 * second image field on the screen works with no further code — the markup in
 * bridge_site_options_field() is what decides how many there are.
 *
 * Progressive enhancement, deliberately: the id lives in a readonly text input
 * that is on the page whether or not this file runs. With JavaScript
 * unavailable the buttons do nothing, the field still shows what is saved, and
 * an operator can still read the value.
 */
(function () {
	// The media modal arrives via wp_enqueue_media(). If it is missing —
	// another plugin dequeuing it, a script error earlier on the page — the
	// buttons are left inert rather than throwing on every click.
	if (!window.wp || !window.wp.media) {
		return;
	}

	document.querySelectorAll('[data-bridge-media]').forEach((field) => {
		const input = field.querySelector('[data-bridge-media-input]');
		const choose = field.querySelector('[data-bridge-media-choose]');

		if (!input || !choose) {
			return;
		}

		const preview = field.querySelector('[data-bridge-media-preview]');
		const clear = field.querySelector('[data-bridge-media-clear]');

		// One frame per field, created on first use and reused after. Opening
		// a new one each time loses the modal's own state — the tab you were
		// on, the folder you had scrolled to.
		let frame = null;

		const render = (id, markup) => {
			input.value = id ? String(id) : '';

			if (preview) {
				preview.innerHTML = markup;
			}

			if (clear) {
				clear.disabled = !id;
			}
		};

		choose.addEventListener('click', () => {
			if (!frame) {
				frame = window.wp.media({
					title: choose.textContent.trim(),
					library: { type: 'image' },
					multiple: false,
					button: {
						text: window.wp.i18n
							? window.wp.i18n.__('Use this image', 'bridge')
							: 'Use this image',
					},
				});

				frame.on('select', () => {
					const item = frame
						.state()
						.get('selection')
						.first()
						.toJSON();

					// The medium size when the library has one, because this
					// is a thumbnail in a form — falling back to the full file
					// for an image too small to have been resized, and for an
					// SVG, which has no sizes at all.
					const src =
						(item.sizes &&
							item.sizes.medium &&
							item.sizes.medium.url) ||
						item.url;

					render(item.id, '<img src="' + src + '" alt="">');

					choose.textContent = window.wp.i18n
						? window.wp.i18n.__('Replace image', 'bridge')
						: 'Replace image';
				});
			}

			frame.open();
		});

		if (clear) {
			clear.addEventListener('click', () => {
				const none = window.wp.i18n
					? window.wp.i18n.__('No image chosen', 'bridge')
					: 'No image chosen';

				render('', '<em>' + none + '</em>');

				choose.textContent = window.wp.i18n
					? window.wp.i18n.__('Choose image', 'bridge')
					: 'Choose image';
			});
		}
	});
})();
