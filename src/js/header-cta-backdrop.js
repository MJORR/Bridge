/**
 * Bridge — the header call to action, over a photograph.
 *
 * On the templates that overlay the header on a hero, the button is standing
 * on an image nobody can know server-side — and on a slider, on a different
 * image every few seconds. render.php prepares both answers, the one for a
 * dark backdrop and the one for a light one; this file looks at the pixels
 * actually behind the button and says which.
 *
 * It picks between two schemes. It never mixes a colour of its own: every
 * value it can reach was drawn by bridge_button_scheme() against the palette,
 * so a measurement that lands the wrong way is a button in the wrong one of
 * two design-system answers rather than a button in a colour nobody chose.
 *
 * Loaded only where the header overlays something — render.php enqueues it on
 * that branch rather than declaring it as the block's viewScript, which would
 * ship it on every page in the site.
 */

// The luminance weighting and the threshold are bridge_is_light_color()'s, so
// the question this file asks of a photograph is the question PHP asks of a
// palette colour. Two answers to "is this light?" that disagreed would be
// worse than either.
const LIGHT_AT = 0.6 * 255;

// A small enough draw that the work is trivial and large enough that a
// gradient behind the button is averaged rather than sampled at one point.
const GRID = 8;

/**
 * The image currently behind the header.
 *
 * The active slide's, when there is a slider — the others are still in the
 * document and one of them is usually first in source order. Any cover image
 * otherwise, which covers the single-hero templates.
 *
 * @return {HTMLImageElement|null} The image, or null when there is nothing to measure.
 */
function backdropImage() {
	const active = document.querySelector(
		'[data-bridge-slider-track] > .is-active img, .wp-block-cover.is-active img'
	);

	return (
		active || document.querySelector('.wp-block-cover__image-background')
	);
}

/**
 * The average luminance of the image under a box.
 *
 * The box is in viewport coordinates and the image is painted `object-fit:
 * cover`, so the source rectangle has to be walked back through that scaling:
 * the image is drawn at whichever scale covers its container, centred, and
 * clipped by it. Reading the element's own rect rather than assuming it fills
 * the cover block keeps this correct if the hero is ever laid out differently.
 *
 * @param {HTMLImageElement} img  The image to read.
 * @param {DOMRect}          rect The area to measure, in viewport coordinates.
 * @return {number|null} 0-255, or null when the image cannot be read.
 */
function luminanceUnder(img, rect) {
	const box = img.getBoundingClientRect();
	const nw = img.naturalWidth;
	const nh = img.naturalHeight;

	if (!nw || !nh || !box.width || !box.height) {
		return null;
	}

	// The canvas first, so nothing is computed ahead of a return that would
	// throw the work away.
	const canvas = document.createElement('canvas');
	canvas.width = GRID;
	canvas.height = GRID;

	const ctx = canvas.getContext('2d', { willReadFrequently: true });

	if (!ctx) {
		return null;
	}

	const scale = Math.max(box.width / nw, box.height / nh);
	const offsetX = (box.width - nw * scale) / 2;
	const offsetY = (box.height - nh * scale) / 2;

	// Clamped, because the button can hang past the image on a short hero and
	// drawImage with a source rectangle outside the bitmap reads as empty.
	const sx = Math.max(
		0,
		Math.min(nw, (rect.left - box.left - offsetX) / scale)
	);
	const sy = Math.max(
		0,
		Math.min(nh, (rect.top - box.top - offsetY) / scale)
	);
	const sw = Math.max(1, Math.min(nw - sx, rect.width / scale));
	const sh = Math.max(1, Math.min(nh - sy, rect.height / scale));

	let pixels;

	try {
		ctx.drawImage(img, sx, sy, sw, sh, 0, 0, GRID, GRID);
		pixels = ctx.getImageData(0, 0, GRID, GRID).data;
	} catch (error) {
		// A cross-origin image taints the canvas and getImageData throws. The
		// uploads are same-origin, so this is a site serving them from a CDN
		// without CORS headers — in which case there is nothing to measure and
		// the dark-backdrop default stands.
		return null;
	}

	let total = 0;

	for (let i = 0; i < pixels.length; i += 4) {
		total +=
			0.299 * pixels[i] + 0.587 * pixels[i + 1] + 0.114 * pixels[i + 2];
	}

	return total / (pixels.length / 4);
}

/**
 * Measure, and say which scheme the button should wear.
 *
 * The attribute is removed rather than set to `dark` when the answer is dark
 * or unknown: dark is what the stylesheet already paints, so the absence of an
 * attribute and a measurement that came back dark should look the same.
 *
 * @param {HTMLElement} cta The button's wrapper.
 */
function measure(cta) {
	const img = backdropImage();

	if (!img || !img.complete) {
		return;
	}

	const light = luminanceUnder(img, cta.getBoundingClientRect());

	if (null !== light && light > LIGHT_AT) {
		cta.dataset.bridgeBackdrop = 'light';
	} else {
		delete cta.dataset.bridgeBackdrop;
	}
}

function init() {
	const header = document.querySelector('.bridge-header--transparent');
	const cta = header && header.querySelector('.bridge-header__cta');

	if (!cta) {
		return;
	}

	const run = () => measure(cta);

	run();

	// The slider announces every move it makes; a hero that is one image never
	// fires it and never needs to.
	document.addEventListener('bridge:slide', run);

	// The image behind the button changes with the viewport: `object-fit:
	// cover` re-crops, and a narrow screen shows a different part of the same
	// photograph. Idle-timed rather than debounced by hand, so a drag-resize
	// measures when the browser has a moment rather than on a timer.
	let pending = 0;

	window.addEventListener('resize', () => {
		cancelAnimationFrame(pending);
		pending = requestAnimationFrame(run);
	});

	// A hero image still downloading has no pixels to read. `load` on the
	// window rather than on the image, because the image the slider shows next
	// may not be the one that was decoded first.
	window.addEventListener('load', run);
}

if ('loading' === document.readyState) {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}
