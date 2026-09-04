/**
 * Bridge — Hero Slider runtime.
 *
 * Loaded only on pages that render the `bridge/hero-slider` block (declared as
 * the block's viewScript). Bundles its own CSS by direct ESM import, and reads
 * every per-slider setting from the `data-*` attributes render.php writes.
 *
 * A state machine and nothing else. Which slide is showing is one number; the
 * transition between two of them — a cross-fade, or a step sideways — is a CSS
 * transition on a class and a custom property. That is the whole of what a hero
 * needs, and it is why there is no slider library here: the library's job was
 * the cross-fade, and CSS does the cross-fade.
 *
 * The block renders its slides as ordinary markup and the first one is visible
 * before this file arrives, so a visitor with no JavaScript reads the first
 * slide rather than an empty box. The chevrons ship `hidden` and the dot strip
 * ships empty, in the manner of carousel.js — nothing is on screen that does
 * not yet work.
 */

import '../scss/blocks/_hero-slider.scss';

const SLIDERS = '[data-bridge-slider]';

// How far a pointer has to travel across the slider before the gesture counts
// as a swipe rather than a click on whatever is under it, and how much of that
// travel has to be sideways — a mostly-vertical drag is the page being
// scrolled, and a full-window hero is a large thing to scroll past.
const SWIPE_MIN = 45;
const SWIPE_RATIO = 1.4;

const readBool = (root, key, fallback) => {
	const value = root.dataset[key];

	if (value === undefined) {
		return fallback;
	}

	return value === '1' || value === 'true';
};

const readInt = (root, key, fallback) => {
	const value = parseInt(root.dataset[key], 10);

	return Number.isFinite(value) ? value : fallback;
};

const init = (root) => {
	if (root.classList.contains('is-ready')) {
		return;
	}

	const track = root.querySelector('[data-bridge-slider-track]');

	if (!track) {
		return;
	}

	const slides = Array.from(track.children);

	if (!slides.length) {
		return;
	}

	const prev = root.querySelector('[data-bridge-slider-prev]');
	const next = root.querySelector('[data-bridge-slider-next]');
	const dots = root.querySelector('[data-bridge-slider-dots]');

	// One slide is not a slider: no autoplay to run, nowhere for a chevron to
	// go and no choice for a dot to offer. The block still renders it, because
	// a single Cover in a slider is a perfectly ordinary way to start one.
	const stepping = slides.length > 1;
	const loop = readBool(root, 'loop', true) && stepping;
	const delay = readInt(root, 'autoplayDelay', 6000);

	// Someone who has asked for less motion has asked for this too: a hero that
	// changes itself every six seconds is the largest moving thing on the page.
	// They keep the chevrons and the dots, which move only when asked.
	const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
	const autoplays =
		readBool(root, 'autoplay', true) && stepping && !reduced.matches;

	// Patterns rather than sentences: the strings are translated in PHP, where
	// the theme's text domain is, so this bundle carries no English of its own
	// and needs no wp-i18n.
	const dotLabel = root.dataset.labelBullet || 'Go to slide %d';
	const slideLabel = root.dataset.labelSlide || 'Slide %1$d of %2$d';

	const dotList = [];

	let index = 0;
	let timer = 0;

	// Why autoplay is currently stopped — a pointer resting on the slider, a
	// focused link inside it, a backgrounded tab. A set rather than a flag,
	// because the reasons overlap: a visitor who tabs into the slide while the
	// pointer is over it produces two, and the first one to end must not
	// restart the timer while the other still stands.
	const pauses = new Set();

	const stop = () => {
		window.clearTimeout(timer);
		timer = 0;
	};

	const schedule = () => {
		stop();

		if (autoplays && !pauses.size) {
			timer = window.setTimeout(advance, delay);
		}
	};

	const pause = (reason) => {
		pauses.add(reason);
		stop();
	};

	const resume = (reason) => {
		pauses.delete(reason);
		schedule();
	};

	/**
	 * Publish the current index everywhere it shows.
	 *
	 * The class on the slide drives the cross-fade and the custom property on
	 * the track drives the sideways step, and both are written on every move:
	 * which of the two the visitor sees is the stylesheet's choice to make, and
	 * this way it can change without this file knowing.
	 */
	const paint = () => {
		slides.forEach((slide, i) => {
			const active = i === index;

			slide.classList.toggle('is-active', active);

			// The slides that are not showing are not content. `inert` takes
			// them out of the tab order and off the accessibility tree, which
			// matters most for the sideways effect, where the others are fully
			// visible to a screen reader and merely somewhere else on screen.
			slide.inert = !active;
		});

		track.style.setProperty('--bridge-slider-index', String(index));

		dotList.forEach((dot, i) => {
			dot.classList.toggle('is-active', i === index);

			if (i === index) {
				dot.setAttribute('aria-current', 'true');
			} else {
				dot.removeAttribute('aria-current');
			}
		});

		// Disabled rather than hidden at the ends, so the controls do not move
		// about as the slider reaches a stop. Only when the slider does not
		// loop — when it does, neither end is an end.
		if (prev) {
			prev.disabled = !loop && index === 0;
		}

		if (next) {
			next.disabled = !loop && index === slides.length - 1;
		}

		// Said out loud, because what is behind a slide is somebody else's
		// problem: the header's call to action reads the image under itself
		// and cannot know when it changed. An event rather than a class for
		// that reader to watch — the classes above are this file's own state
		// and should stay free to change.
		root.dispatchEvent(
			new CustomEvent('bridge:slide', {
				bubbles: true,
				detail: { index },
			})
		);
	};

	const goTo = (to) => {
		const last = slides.length - 1;

		index = loop
			? (to + slides.length) % slides.length
			: Math.min(last, Math.max(0, to));

		paint();
	};

	/**
	 * The autoplay step.
	 *
	 * A chain of timeouts rather than an interval, so every manual move
	 * restarts the clock: a visitor who has just clicked to slide three gets
	 * the full delay to read it, not whatever was left of the previous one.
	 */
	function advance() {
		// The end of a slider that does not loop. Nothing is scheduled after
		// this, which is how autoplay stops.
		if (!loop && index === slides.length - 1) {
			return;
		}

		goTo(index + 1);
		schedule();
	}

	// A step the visitor asked for. Autoplay carries on afterwards — the
	// slider is still a slider — but from the top of a fresh delay.
	const step = (to) => {
		goTo(to);
		schedule();
	};

	slides.forEach((slide, i) => {
		slide.classList.add('bridge-hero-slider__slide');

		if (stepping) {
			slide.setAttribute('role', 'group');
			slide.setAttribute(
				'aria-label',
				slideLabel
					.replace('%1$d', String(i + 1))
					.replace('%2$d', String(slides.length))
			);
		}
	});

	if (dots && stepping) {
		slides.forEach((_, i) => {
			const dot = document.createElement('button');

			dot.type = 'button';
			dot.className = 'bridge-hero-slider__dot';
			dot.setAttribute(
				'aria-label',
				dotLabel.replace('%d', String(i + 1))
			);
			dot.addEventListener('click', () => step(i));

			dots.append(dot);
			dotList.push(dot);
		});
	}

	prev?.addEventListener('click', () => step(index - 1));
	next?.addEventListener('click', () => step(index + 1));

	if (prev) {
		prev.hidden = !stepping;
	}

	if (next) {
		next.hidden = !stepping;
	}

	if (stepping) {
		// Announced only when the slides are not already changing by
		// themselves: a live region that fires every six seconds unbidden is
		// noise, and the visitor who is stepping through by hand is the one
		// who needs to hear where they have arrived.
		track.setAttribute('aria-live', autoplays ? 'off' : 'polite');

		root.addEventListener('mouseenter', () => pause('hover'));
		root.addEventListener('mouseleave', () => resume('hover'));
		root.addEventListener('focusin', () => pause('focus'));
		root.addEventListener('focusout', () => resume('focus'));

		// A timer in a backgrounded tab spends the whole time it is away
		// advancing slides nobody is watching, and the visitor comes back to a
		// slider several slides from where they left it.
		document.addEventListener('visibilitychange', () =>
			document.hidden ? pause('hidden') : resume('hidden')
		);

		// Swipe. Recorded on the way down and judged on the way up, rather
		// than tracked frame by frame: a cross-fade has no intermediate
		// position to drag to, so following the finger would promise a
		// movement the transition cannot make.
		let startX = 0;
		let startY = 0;
		let swiping = false;

		track.addEventListener(
			'pointerdown',
			(event) => {
				if (event.button !== 0) {
					return;
				}

				startX = event.clientX;
				startY = event.clientY;
				swiping = true;
			},
			{ passive: true }
		);

		track.addEventListener(
			'pointerup',
			(event) => {
				if (!swiping) {
					return;
				}

				swiping = false;

				const dx = event.clientX - startX;
				const dy = event.clientY - startY;

				if (
					Math.abs(dx) < SWIPE_MIN ||
					Math.abs(dx) < Math.abs(dy) * SWIPE_RATIO
				) {
					return;
				}

				step(dx < 0 ? index + 1 : index - 1);
			},
			{ passive: true }
		);

		track.addEventListener('pointercancel', () => {
			swiping = false;
		});
	}

	// Last, so the stylesheet's no-script arrangement — the first slide
	// showing, the chrome inert — stands until everything above is wired up.
	root.classList.add('is-ready');

	paint();
	schedule();
};

const run = () => document.querySelectorAll(SLIDERS).forEach(init);

if (document.readyState !== 'loading') {
	run();
} else {
	document.addEventListener('DOMContentLoaded', run, { once: true });
}
