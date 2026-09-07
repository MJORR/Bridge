/**
 * Bridge — Logo Slider fit check.
 *
 * The row is a CSS marquee: the track holds every logo twice and travels by
 * half its own width, so the second set is standing where the first began at
 * the moment it loops. That only reads as a marquee when there is more to see
 * than the row can hold. Three logos on a wide screen have nowhere to travel
 * to — the row slides a set of logos out and an identical set in, which reads
 * as a fault rather than as a band.
 *
 * So this file measures one set of logos against the row and hands the answer
 * back to CSS as a class. `is-static` stops the animation, drops the duplicate
 * set and centres what is left; everything about how that looks stays in the
 * stylesheet, which is also where the editor's preview and the reduced-motion
 * version of the same row are already drawn.
 *
 * Nothing here is needed to read the logos. Without the script the row slides,
 * which is what it did before this file existed — so a page that never
 * receives it is behind, not broken.
 */

const ROWS = '[data-bridge-logos-row]';
const TRACK = '.bridge-logos__track';
// The first set only, and the images rather than the items around them. The
// second set is the seam copy, already marked as the thing a screen reader
// should ignore; and an item's own width includes whichever way the row is
// currently spacing its logos, which is the one thing this measurement must
// not depend on.
const LOGOS =
	'.bridge-logos__item:not([aria-hidden="true"]) > .bridge-logos__image';
const STATIC = 'is-static';

/**
 * A pixel of slack.
 *
 * Widths come back fractional, and a row whose logos come to 640.3px inside
 * 640px would otherwise be set travelling three tenths of a pixel — a marquee
 * nobody can see, running for the lifetime of the page.
 */
const SLACK = 1;

/**
 * How wide one set of logos is, laid out in a line.
 *
 * Every part of this has to read the same in both of the row's states, or the
 * answer would depend on the last answer and the row could sit flipping
 * between them. The logos are measured rather than their items, because an
 * item's width includes its trailing padding while the row is sliding and
 * does not while it is standing still; and the space between two logos is
 * read from both places it can live — that padding, or the track's gap —
 * because exactly one of the two is ever set, so the sum is the spacing
 * whichever state asked.
 *
 * @param {HTMLElement} row The row element.
 * @return {number} Width in pixels, the space between logos included.
 */
const setWidth = (row) => {
	const track = row.querySelector(TRACK);

	if (!track) {
		return 0;
	}

	const logos = track.querySelectorAll(LOGOS);

	if (!logos.length) {
		return 0;
	}

	// The spacing is a token, and `contain` rows carry a wider one than
	// `cover` rows do — so it is read off the page rather than assumed.
	// `column-gap` comes back as `normal` on a track that has none, which
	// parses to NaN and falls to nought, as does a padding of `0px`.
	const spacing =
		(parseFloat(window.getComputedStyle(track).columnGap) || 0) +
		(parseFloat(
			window.getComputedStyle(logos[0].parentElement).paddingInlineEnd
		) || 0);

	// One fewer space than logos: the row ends at the last logo, whatever the
	// track is carrying after it.
	let width = spacing * (logos.length - 1);

	logos.forEach((logo) => {
		width += logo.getBoundingClientRect().width;
	});

	return width;
};

/**
 * Decide whether one row travels.
 *
 * @param {HTMLElement} row The row element.
 */
const apply = (row) => {
	// clientWidth, not the bounding rect: the row is what clips the logos, and
	// its transform-free content box is the space they actually have.
	row.classList.toggle(STATIC, setWidth(row) <= row.clientWidth + SLACK);
};

const rows = [];
let queued = false;

// Resizing fires continuously; the measurement only has to be right once the
// window has stopped moving, and a frame is a cheap way to say so.
const schedule = () => {
	if (queued) {
		return;
	}

	queued = true;
	window.requestAnimationFrame(() => {
		queued = false;
		rows.forEach(apply);
	});
};

const init = () => {
	const found = document.querySelectorAll(ROWS);

	if (!found.length) {
		return;
	}

	rows.push(...found);
	rows.forEach(apply);

	if (typeof window.ResizeObserver === 'function') {
		// The row, not the window: a row narrows when the window does, but it
		// also narrows when a sidebar opens or the page's own container
		// changes, and none of those are resize events.
		const observer = new window.ResizeObserver(schedule);

		rows.forEach((row) => observer.observe(row));
	} else {
		window.addEventListener('resize', schedule);
	}

	// The logos ship with width and height attributes, so the row is already
	// the right size before any of them arrive. This is for the one that
	// does not — a broken source collapses to its alt text and takes a
	// different amount of room than the attributes promised.
	if (document.readyState !== 'complete') {
		window.addEventListener('load', schedule, { once: true });
	}
};

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}
