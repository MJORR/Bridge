/**
 * Bridge — the two things about the header that CSS cannot work out for itself.
 *
 * 1. Whether the page has been scrolled away from the top, which a transparent
 *    sticky header needs: scroll past the hero and its links otherwise sit
 *    invisibly over body copy.
 * 2. How tall the header is, which the mobile panel needs. The panel hangs off
 *    the header's bottom edge and its close button sits on the toggle in the
 *    middle of the header, and neither position can be written down in advance
 *    — the height depends on whether there is a top bar, how tall the logo is,
 *    and whether the row has wrapped.
 * 3. That Escape has been pressed over an open dropdown, which WCAG 2.2 SC
 *    1.4.13 requires to dismiss it.
 *
 * The first two are reported by an observer rather than by a scroll or resize
 * listener, so nothing of ours runs on the main thread while a visitor is
 * scrolling.
 */

// The header carries its own state, so this asks the element whether it is
// sticky rather than asking the page. Same question, one answer, and it still
// holds anywhere the header is rendered without a document around it.
const HEADER = '.bridge-header--sticky';
const SCROLLED = 'is-scrolled';
const ANY_HEADER = '.bridge-header';
const HEIGHT = '--bridge-header-height';
const PRIMARY_NAV = '.bridge-header .bridge-nav--primary';
const HAS_CHILD = '.wp-block-navigation-item.has-child';
const DISMISSED = 'is-hover-dismissed';

function initStickyHeader() {
	const header = document.querySelector(HEADER);

	if (!header || typeof IntersectionObserver === 'undefined') {
		return;
	}

	// The sentinel sits where the top of the page is. Once it scrolls out of
	// view the header is no longer over the hero.
	const sentinel = document.createElement('div');
	sentinel.setAttribute('aria-hidden', 'true');
	sentinel.style.cssText =
		'position:absolute;top:0;left:0;width:1px;height:1px;pointer-events:none;';
	document.body.prepend(sentinel);

	const observer = new IntersectionObserver(
		([entry]) => {
			header.classList.toggle(SCROLLED, !entry.isIntersecting);
		},
		{ threshold: 0 }
	);

	observer.observe(sentinel);
}

/**
 * Publish the header's own height on the header, as a custom property.
 *
 * On the element rather than on :root, because that is where the rest of the
 * header's measurements already live — the block writes its padding and logo
 * height into the same style attribute — and because a header rendered in the
 * editor canvas has no document root of the site's to write to.
 *
 * A ResizeObserver rather than a resize listener: the height changes when the
 * header changes, which is not the same event as the window changing. A logo
 * that finishes loading, a menu that wraps onto a second line, and a font that
 * swaps late all move it without the window moving at all.
 */
function publishHeaderHeight() {
	const header = document.querySelector(ANY_HEADER);

	if (!header || typeof ResizeObserver === 'undefined') {
		return;
	}

	const observer = new ResizeObserver(([entry]) => {
		// The border box, not the content box: the header's padding is part of
		// how tall it is, and a border on a bordered header is too.
		const height =
			entry.borderBoxSize?.[0]?.blockSize ?? entry.contentRect.height;

		header.style.setProperty(HEIGHT, `${Math.round(height)}px`);
		// A second copy on the root, for the one consumer that is not inside
		// the header: `scroll-padding-top` is a property of the scroll
		// container, and the scroll container is the page. A sticky header
		// otherwise parks focused content underneath itself as a visitor tabs
		// down the page, which is WCAG 2.2 SC 2.4.11.
		document.documentElement.style.setProperty(
			HEIGHT,
			`${Math.round(height)}px`
		);
	});

	observer.observe(header);
}

/**
 * Escape dismisses a dropdown the pointer opened.
 *
 * SC 1.4.13 asks that content shown on hover can be dismissed without moving
 * the pointer. Core's own Escape handler is bound to the menu item, so it only
 * ever hears the key when focus is already inside the menu — press Escape with
 * the pointer resting on an open dropdown and nothing happens at all.
 *
 * Two things are wrong in that moment and each needs its own answer. The
 * block's state still says the submenu is open, which is fixed by handing core
 * the event it would have got if the pointer had left: `pointerleave` runs its
 * own close action, and `aria-expanded` goes back to false. But the panel is
 * on screen because of a CSS `:hover` rule that never consulted that state, so
 * it stays up regardless — hence the class, which the stylesheet uses to hold
 * the panel shut for as long as the pointer stays where it is.
 *
 * The class comes off again at the first sign that the visitor wants the menu
 * back: the pointer leaving the item it was dismissed on, a press, or focus
 * arriving. A dismissal applies to the hover that provoked it, not to the
 * menu for the rest of the visit.
 */
function initHoverDismiss() {
	const nav = document.querySelector(PRIMARY_NAV);

	if (!nav) {
		return;
	}

	const release = () => nav.classList.remove(DISMISSED);

	document.addEventListener('keydown', (event) => {
		if (event.key !== 'Escape') {
			return;
		}

		const open = nav.querySelectorAll(`${HAS_CHILD}:hover`);

		if (!open.length) {
			return;
		}

		// Innermost first, so a nested panel closes before its parent does.
		Array.from(open)
			.reverse()
			.forEach((item) => {
				item.dispatchEvent(new PointerEvent('pointerleave'));
			});

		nav.classList.add(DISMISSED);

		// Added after the dispatch above, or it would hear that synthetic
		// event and take the class straight back off.
		open[0].addEventListener('pointerleave', release, { once: true });
	});

	nav.addEventListener('pointerdown', release);
	nav.addEventListener('focusin', release);
}

function initHeader() {
	initStickyHeader();
	publishHeaderHeight();
	initHoverDismiss();
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initHeader);
} else {
	initHeader();
}
