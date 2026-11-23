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

/**
 * The Locations section: add, reorder and remove locations.
 *
 * Each location is a fieldset of ordinary inputs named
 * `…[locations][n][field]`. Order on the page is the order submitted, and the
 * sanitiser re-indexes, so moving a row is only moving its element. A new row
 * is the <template> with a fresh index — any unique number will do.
 */
(() => {
	const list = document.querySelector('[data-bridge-locations]');
	const template = document.querySelector('[data-bridge-location-template]');
	const add = document.querySelector('[data-bridge-location-add]');

	if (!list || !template || !add) {
		return;
	}

	const wire = (row) => {
		const name = row.querySelector('[data-bridge-location-name]');
		const title = row.querySelector('[data-bridge-location-title]');

		if (name && title) {
			name.addEventListener('input', () => {
				title.textContent = name.value || name.placeholder || '…';
			});
		}

		row.querySelector('[data-bridge-location-remove]')?.addEventListener(
			'click',
			() => row.remove()
		);

		row.querySelector('[data-bridge-location-up]')?.addEventListener(
			'click',
			() => {
				const before = row.previousElementSibling;

				if (before) {
					list.insertBefore(row, before);
				}
			}
		);

		row.querySelector('[data-bridge-location-down]')?.addEventListener(
			'click',
			() => {
				const after = row.nextElementSibling;

				if (after) {
					list.insertBefore(after, row);
				}
			}
		);
	};

	list.querySelectorAll('[data-bridge-location]').forEach(wire);

	add.addEventListener('click', () => {
		const html = template.innerHTML.replaceAll(
			'__INDEX__',
			String(Date.now())
		);
		const holder = document.createElement('div');

		holder.innerHTML = html.trim();

		const row = holder.firstElementChild;

		list.appendChild(row);
		wire(row);
		row.querySelector('[data-bridge-location-name]')?.focus();
	});
})();
