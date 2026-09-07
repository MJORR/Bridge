/**
 * Bridge — carousel controls, for any band that offers a swipe layout.
 *
 * One script for every block with a carousel — cards, testimonials and feature
 * panels today — registered under a single handle, so a page carrying two of
 * them loads it once. It knows nothing about any of them: it finds its work
 * through `data-bridge-carousel-*` attributes and styles it through classes the
 * shared `abstracts/carousel` mixin defines. A fourth block joins by emitting
 * the same markup, with no edit here.
 *
 * Loaded only on pages that render a block whose overflow style is carousel.
 *
 * The row itself is CSS: `scroll-snap-type` on a grid of columns, which
 * scrolls with a trackpad, a swipe, a shift-wheel and the keyboard before this
 * file has arrived, and keeps working if it never does. What is left here is
 * the furniture the old block had — a chevron at each end and a dot per page —
 * which cannot be done in CSS because none of it exists until we know how many
 * pages the row makes at this width.
 *
 * So render.php ships the buttons `hidden` and the dot strip empty, and this
 * script reveals and fills them. A visitor with no JavaScript sees a scrollable
 * row and no controls, rather than controls that do nothing.
 *
 * A page rather than a card is the unit throughout. The row shows one, two,
 * three or four cards depending on how much room it has, so a dot per card
 * would mean four dots doing nothing on a desktop and a strip of twenty on a
 * phone. A dot per screenful is the honest count, and it is what the scroll
 * position can actually be measured against.
 */

const BLOCKS = '[data-bridge-carousel]';

// Half a card's slack, so a row scrolled to within a pixel or two of the end
// still counts as being at the end — scroll positions are fractional, and
// `scrollLeft + clientWidth === scrollWidth` is a comparison that never quite
// comes true.
const EPSILON = 2;

/**
 * How long a step between pages takes.
 *
 * The browser's own `behavior: 'smooth'` has no duration — not in CSS, not in
 * `scrollTo`, not anywhere — so a slower step means animating the scroll
 * position by hand, which is what `glide()` below does.
 *
 * Distance-aware rather than one fixed number: a step across four cards that
 * took the same time as a step across one would feel hurried at one end and
 * sluggish at the other. FLOOR is what the shortest step costs, PER_PX what
 * each pixel of travel adds, and CEILING the point past which longer stops
 * feeling deliberate and starts feeling broken.
 *
 * These three numbers are the speed control. Raise FLOOR and CEILING together
 * to slow every step down.
 */
const GLIDE_FLOOR = 450;
const GLIDE_PER_PX = 0.45;
const GLIDE_CEILING = 1100;

/**
 * Mouse dragging.
 *
 * Touch and pen are left alone: they scroll this row natively, with momentum
 * and rubber-banding no script can match, and taking that over would make the
 * carousel worse on the devices where swiping matters most. A mouse has no
 * such gesture, which is the gap this fills.
 *
 * THRESHOLD is how far the pointer travels before a press counts as a drag
 * rather than a click — without it, the small movement in an ordinary click
 * would start a drag and the card's link would never fire. FLICK_MS and
 * FLICK_PX are what separates a short sharp throw, which should advance a
 * page, from a slow reposition, which should settle where it was left.
 */
const DRAG_THRESHOLD = 5;
const FLICK_MS = 300;
const FLICK_PX = 40;

// Symmetrical ease: slow to leave, quick through the middle, slow to arrive.
// A linear scroll reads as a machine moving the page rather than as the page
// being pushed.
const ease = (t) => (t < 0.5 ? 4 * t * t * t : 1 - (-2 * t + 2) ** 3 / 2);

const init = (block) => {
	const track = block.querySelector('[data-bridge-carousel-track]');
	const controls = block.querySelector('[data-bridge-carousel-controls]');

	if (!track || !controls) {
		return;
	}

	const prev = controls.querySelector('[data-bridge-carousel-prev]');
	const next = controls.querySelector('[data-bridge-carousel-next]');
	const dots = controls.querySelector('[data-bridge-carousel-dots]');

	if (!prev || !next || !dots) {
		return;
	}

	// Honoured for the scrolling as well as for CSS transitions: an animated
	// scroll is motion, and someone who has asked for less of it means this
	// too. They get the jump.
	const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');

	let gliding = 0;

	const stopGliding = () => {
		if (gliding) {
			cancelAnimationFrame(gliding);
			gliding = 0;
			// Back to whatever the stylesheet says, which is `x mandatory`.
			track.style.scrollSnapType = '';
		}
	};

	/**
	 * Scroll to a position over time.
	 *
	 * Snapping is switched off for the duration. Mandatory snap and a
	 * script writing `scrollLeft` every frame are two things steering the same
	 * scroller: the browser drags each frame back to the nearest snap point
	 * and the row judders instead of gliding. It goes back on at the end,
	 * where the position is a card boundary anyway.
	 *
	 * @param {number} left Scroll position to land on, in pixels.
	 */
	const glide = (left) => {
		stopGliding();

		const from = track.scrollLeft;
		const distance = left - from;

		if (Math.abs(distance) < 1) {
			return;
		}

		if (reduced.matches) {
			track.scrollLeft = left;

			return;
		}

		const ms = Math.min(
			GLIDE_CEILING,
			GLIDE_FLOOR + Math.abs(distance) * GLIDE_PER_PX
		);
		const started = performance.now();

		track.style.scrollSnapType = 'none';

		const frame = (now) => {
			const t = Math.min(1, (now - started) / ms);

			track.scrollLeft = from + distance * ease(t);

			if (t < 1) {
				gliding = requestAnimationFrame(frame);

				return;
			}

			gliding = 0;
			track.style.scrollSnapType = '';
		};

		gliding = requestAnimationFrame(frame);
	};

	let pages = 1;

	// How far the row can actually travel. Not `scrollWidth` — the last screen
	// is almost never a whole one.
	const maxScroll = () => Math.max(0, track.scrollWidth - track.clientWidth);

	// Rounded *up*. Four cards in a three-card row is 1.34 screens, which is
	// two pages — one of them a short one. Rounding to the nearest whole
	// screen called that a single page, so the row was declared to fit, the
	// chevrons stayed hidden and no dots were built, on exactly the card
	// counts a carousel is most often given.
	const pageCount = () =>
		Math.max(
			1,
			Math.ceil((track.scrollWidth - EPSILON) / track.clientWidth)
		);

	// Pages are mapped across the distance the row can travel rather than laid
	// out at one screen each, because that short last page has to be reachable:
	// stepping a whole screen from the second-to-last stop overshoots the end,
	// the browser clamps it, and the dot for the last page would never light.
	const offsetFor = (page) =>
		pages < 2 ? 0 : (page / (pages - 1)) * maxScroll();

	// Which page a given scroll position sits nearest. Takes the position
	// rather than reading it, so a drag can ask about where it *started* as
	// well as where it ended.
	const pageAt = (left) => {
		const max = maxScroll();

		if (pages < 2 || max <= EPSILON) {
			return 0;
		}

		const page = Math.round((left / max) * (pages - 1));

		return Math.min(pages - 1, Math.max(0, page));
	};

	const currentPage = () => pageAt(track.scrollLeft);

	const goTo = (page) => {
		const target = Math.min(pages - 1, Math.max(0, page));

		glide(offsetFor(target));
	};

	const label = dots.dataset.dotLabel || 'Page %d';

	const buildDots = () => {
		dots.textContent = '';

		// One page is no choice, so there is nothing to show. The chevrons go
		// with it: a row that fits has nowhere to go.
		if (pages < 2) {
			return;
		}

		for (let page = 0; page < pages; page++) {
			const dot = document.createElement('button');

			dot.type = 'button';
			dot.className = 'bridge-carousel__dot';
			// Named from the pattern PHP put on the strip, so the only
			// translated string here came from a .po file rather than from a
			// literal typed into a front-end bundle.
			dot.setAttribute('aria-label', label.replace('%d', page + 1));
			dot.addEventListener('click', () => goTo(page));

			dots.append(dot);
		}
	};

	const sync = () => {
		const active = currentPage();
		const atStart = track.scrollLeft <= EPSILON;
		const atEnd =
			track.scrollLeft + track.clientWidth >= track.scrollWidth - EPSILON;

		// Disabled rather than hidden at the ends: a control that disappears
		// takes its own space with it and shuffles everything beside it.
		prev.disabled = atStart;
		next.disabled = atEnd;

		Array.from(dots.children).forEach((dot, page) => {
			const on = page === active;

			dot.classList.toggle('is-active', on);

			// `aria-current` rather than `aria-selected`, which belongs to a
			// tab in a tab list. Every dot stays in the tab order: they are
			// ordinary buttons, and a keyboard user should be able to reach
			// the third page without visiting the second.
			if (on) {
				dot.setAttribute('aria-current', 'true');
			} else {
				dot.removeAttribute('aria-current');
			}
		});
	};

	const measure = () => {
		const before = pages;

		pages = pageCount();

		if (pages !== before) {
			buildDots();
		}

		const usable = pages > 1;

		prev.hidden = !usable;
		next.hidden = !usable;
		controls.classList.toggle('is-usable', usable);

		sync();
	};

	// Stepping by page rather than by `scrollBy(clientWidth)`, so a chevron and
	// a dot mean the same thing — and so the last, short page is a stop the
	// chevron can actually land on.
	prev.addEventListener('click', () => goTo(currentPage() - 1));
	next.addEventListener('click', () => goTo(currentPage() + 1));

	// Passive, and cheap: `sync` reads three scroll properties and toggles a
	// class, so it can run on every frame of a drag without a rAF wrapper. It
	// also runs on the frames `glide()` writes, which is what keeps the dots
	// moving with the row rather than jumping when it arrives.
	track.addEventListener('scroll', sync, { passive: true });

	// A hand on the row wins. Touching, dragging or wheeling mid-step stops
	// the animation where it is and hands the scroller back — a script that
	// kept animating over someone's swipe would be fighting them for it.
	['pointerdown', 'wheel', 'touchstart', 'keydown'].forEach((type) =>
		track.addEventListener(type, stopGliding, { passive: true })
	);

	// ---- Mouse drag -------------------------------------------------------
	let drag = null;
	// Set for one tick after a drag ends, to swallow the click that a mouse
	// release fires. Without it, letting go over a card follows its link, and
	// every drag ends on a card.
	let dragged = false;

	track.addEventListener('pointerdown', (event) => {
		// Left button, mouse only.
		if (event.pointerType !== 'mouse' || event.button !== 0) {
			return;
		}

		drag = {
			id: event.pointerId,
			x: event.clientX,
			lastX: event.clientX,
			from: track.scrollLeft,
			at: performance.now(),
			moved: false,
		};
	});

	track.addEventListener('pointermove', (event) => {
		if (!drag || event.pointerId !== drag.id) {
			return;
		}

		const dx = event.clientX - drag.x;

		if (!drag.moved) {
			if (Math.abs(dx) < DRAG_THRESHOLD) {
				return;
			}

			drag.moved = true;
			// The drag carries on outside the row, which is what makes a throw
			// past the edge of the block behave.
			track.setPointerCapture(drag.id);
			track.classList.add('is-dragging');
			// Same reason as `glide()`: mandatory snap and a script writing
			// `scrollLeft` are two hands on one scroller.
			track.style.scrollSnapType = 'none';
		}

		drag.lastX = event.clientX;
		track.scrollLeft = drag.from - dx;
	});

	const endDrag = (event) => {
		if (!drag || (event && event.pointerId !== drag.id)) {
			return;
		}

		const { moved, from, at } = drag;
		const dx = drag.lastX - drag.x;

		if (track.hasPointerCapture?.(drag.id)) {
			track.releasePointerCapture(drag.id);
		}

		drag = null;

		if (!moved) {
			return;
		}

		track.classList.remove('is-dragging');
		track.style.scrollSnapType = '';
		dragged = true;
		// One tick, not a timer: the click a mouse release fires arrives in
		// the same task queue turn, and anything longer would swallow a real
		// click that came after.
		setTimeout(() => {
			dragged = false;
		}, 0);

		// A short sharp throw advances a page from wherever it began; anything
		// slower settles on whichever page it was left nearest.
		const flick =
			performance.now() - at < FLICK_MS && Math.abs(dx) > FLICK_PX;

		goTo(flick ? pageAt(from) + (dx < 0 ? 1 : -1) : currentPage());
	};

	track.addEventListener('pointerup', endDrag);
	track.addEventListener('pointercancel', endDrag);

	// Capture, so it runs before the link's own handler and before navigation.
	track.addEventListener(
		'click',
		(event) => {
			if (dragged) {
				event.preventDefault();
				event.stopPropagation();
			}
		},
		true
	);

	// The browser's native drag — of an image, or of a link — starts on the
	// same gesture and fights it, leaving a ghost image stuck to the pointer.
	track.addEventListener('dragstart', (event) => event.preventDefault());

	// A resize changes how many cards fit, which changes how many pages there
	// are. Observed on the track rather than the window, because a card grid
	// inside a column can change width without the window moving at all.
	if (typeof ResizeObserver === 'function') {
		new ResizeObserver(measure).observe(track);
	} else {
		window.addEventListener('resize', measure);
	}

	measure();
};

const run = () => document.querySelectorAll(BLOCKS).forEach(init);

if (document.readyState !== 'loading') {
	run();
} else {
	document.addEventListener('DOMContentLoaded', run, { once: true });
}
