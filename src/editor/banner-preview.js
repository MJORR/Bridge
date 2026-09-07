/**
 * Bridge — Title Banner editor preview.
 *
 * The real banner is rendered on the front end by the template's
 * `bridge/page-title` block, which isn't present in the page editor canvas —
 * there the title is WordPress' own post-title field. This watcher mirrors
 * the per-page Title Banner meta onto that native title (background colour,
 * contrasting text, alignment and a full-width vs contained treatment) so the
 * selected option is visible immediately while editing.
 *
 * Mirrors template-watcher.js: a plain wp.data subscribe that re-asserts the
 * canvas body classes/vars on every relevant change (cheap, idempotent), so
 * it survives the editor iframe re-mounting.
 *
 * @param {Object} wp The WordPress globals this file is wrapped around.
 */

(function (wp) {
	const { select, subscribe } = wp.data;

	const STYLE_CLASSES = [
		'bridge-banner-style-contained',
		'bridge-banner-style-fullwidth',
	];

	// Perceived luminance (BT.601). Mirrors bridge_is_light_color() in PHP so
	// the editor and front end pick the same contrasting title colour.
	const isLight = (hex) => {
		if (!hex) {
			return false;
		}
		let h = String(hex).replace('#', '').trim();
		if (h.length === 3) {
			h = h
				.split('')
				.map((c) => c + c)
				.join('');
		}
		if (h.length !== 6) {
			return false;
		}
		const r = parseInt(h.slice(0, 2), 16);
		const g = parseInt(h.slice(2, 4), 16);
		const b = parseInt(h.slice(4, 6), 16);
		return 0.299 * r + 0.587 * g + 0.114 * b > 0.6 * 255;
	};

	const getPalette = () => {
		const settings = select('core/block-editor')?.getSettings?.();
		return (settings && (settings.colors || settings.colorPalette)) || [];
	};

	// The title lives in the editor-canvas iframe on current WP, and directly
	// in the document on legacy/non-iframe editors — target both.
	const canvasBodies = () => {
		const bodies = [document.body];
		document
			.querySelectorAll('iframe[name="editor-canvas"]')
			.forEach((frame) => {
				const body =
					frame.contentDocument && frame.contentDocument.body;
				if (body) {
					bodies.push(body);
				}
			});
		return bodies;
	};

	const apply = (style, align, colorSlug) => {
		const hasBanner = style === 'contained' || style === 'fullwidth';
		const entry = getPalette().find((c) => c.slug === colorSlug);

		const bg = entry
			? `var(--wp--preset--color--${colorSlug})`
			: 'transparent';
		let text = 'inherit';

		if (entry) {
			text = isLight(entry.color)
				? 'var(--wp--preset--color--text)'
				: 'var(--wp--preset--color--background)';
		}

		canvasBodies().forEach((body) => {
			STYLE_CLASSES.forEach((c) => body.classList.remove(c));
			body.classList.toggle('bridge-has-banner', hasBanner);
			body.classList.toggle(
				'bridge-banner-align-center',
				align === 'center'
			);
			// "No title" removes the heading from the page, so it removes the
			// title field from the canvas too — anything left in its place
			// would be air the published page does not have. The page keeps
			// its name; it is edited from the Pages list, or by switching
			// this setting back. Same class name the front end uses.
			body.classList.toggle('bridge-title-hidden', style === 'hidden');
			if (hasBanner) {
				body.classList.add(`bridge-banner-style-${style}`);
			}
			body.style.setProperty('--bridge-editor-banner-bg', bg);
			body.style.setProperty('--bridge-editor-banner-text', text);
		});
	};

	const start = () => {
		if (!select('core/editor') || !select('core/block-editor')) {
			setTimeout(start, 150);
			return;
		}

		subscribe(() => {
			const editor = select('core/editor');
			if (!editor || editor.getCurrentPostType() !== 'page') {
				return;
			}

			/*
			 * A landing page is always "No title", whatever is stored.
			 *
			 * Its template holds no `bridge/page-title` block, so no banner can
			 * draw there — and the canvas has no use for a title field either,
			 * because the hero is the page's heading and two headlines stacked
			 * is one of them being ignored.
			 *
			 * The page still needs a name. That field is in the sidebar, in the
			 * "Page title" panel — see page-banner-panel.js. Hiding it here
			 * without that panel would be hiding the only way to set it, which
			 * is what this file did until the panel existed.
			 */
			const landing =
				'page-landing' === editor.getEditedPostAttribute('template');

			const meta = editor.getEditedPostAttribute('meta') || {};

			apply(
				landing ? 'hidden' : meta.bridge_banner_style || 'none',
				meta.bridge_title_align || 'left',
				landing ? '' : meta.bridge_banner_color || ''
			);
		});
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start, { once: true });
	} else {
		start();
	}
})(window.wp);
