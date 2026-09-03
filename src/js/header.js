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
 * 4. Whether the menu bar still fits. A breakpoint is a number, and the width
 *    a menu actually needs is not a number anyone can write down in advance:
 *    it depends on how many items the site has and how long their labels are.
 *    Between the two, a menu the operator has added one item too many to wraps
 *    onto a second row and stays there for several hundred pixels before any
 *    fixed number finally calls it mobile.
 *
 * The first, second and fourth are reported by an observer rather than by a
 * scroll or resize listener, so nothing of ours runs on the main thread while
 * a visitor is scrolling.
 */

// The header carries its own state, so this asks the element whether it is
// sticky rather than asking the page. Same question, one answer, and it still
// holds anywhere the header is rendered without a document around it.
const HEADER = '.bridge-header--sticky';
const SCROLLED = 'is-scrolled';
const ANY_HEADER = '.bridge-header';
const HEIGHT = '--bridge-header-height';
const NAV = '.bridge-nav--primary';
const PRIMARY_NAV = `${ANY_HEADER} ${NAV}`;
const HAS_CHILD = '.wp-block-navigation-item.has-child';
const DISMISSED = 'is-hover-dismissed';
const SEARCH_TOGGLE = '.bridge-header__search-toggle';
const SEARCH_OPEN = 'is-search-open';
const END = '.bridge-header__end';
const NAV_COLLAPSED = 'is-nav-collapsed';
const NAV_OPEN_BUTTON = '.wp-block-navigation__responsive-container-open';
const NAV_CONTAINER = '.wp-block-navigation__responsive-container';
const NAV_MENU_OPEN = 'is-menu-open';

// Core's own two classes for "this navigation is an overlay at every width" —
// what it prints when the block is set to Always show the hamburger. Borrowed
// rather than reimplemented: every rule core has for an overlay menu keys off
// these, so a collapsed bar is a state the block already knows how to be, and
// the stylesheet does not need a second copy of it that can drift.
const NAV_ALWAYS_SHOWN = 'always-shown';
const NAV_HIDDEN_BY_DEFAULT = 'hidden-by-default';

// The width below which the menu is a panel whatever its contents — the same
// value as `$bar` in src/scss/abstracts/_nav.scss, which is where the reasoning
// for it lives. Below this the stylesheet has already collapsed the nav, in a
// media query, and this file has nothing to decide; above it, nothing else
// can decide, because whether a bar fits is a question about the menu rather
// than about the window.
//
// The two copies cannot be derived from one another — a media query is not
// readable from script, and a custom property would put a number the layout
// depends on somewhere it could be overridden. So they are two, and each says
// where the other is.
const NAV_BREAKPOINT = 1024;

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

/**
 * The search icon opens a field under the header.
 *
 * The panel ships with `hidden` on it, so a visitor without JavaScript never
 * meets a field they cannot see: the attribute comes off here, once there is
 * something able to open and close it. Everything after that is the disclosure
 * pattern — `aria-expanded` on the button is the state, and the class is only
 * how the stylesheet hears about it.
 *
 * The class goes on the header rather than on the panel because the toggle and
 * the panel are no longer siblings: the panel is a full-width band under the
 * whole header, and the header is the one element that contains both.
 *
 * Escape closes it and returns focus to the icon, because the field is the
 * only thing that took focus away; a press outside closes it silently, since
 * the visitor has already moved their attention somewhere else.
 */
function initHeaderSearch() {
	const header = document.querySelector(ANY_HEADER);
	const toggle = header?.querySelector(SEARCH_TOGGLE);
	const panel = toggle
		? document.getElementById(toggle.getAttribute('aria-controls'))
		: null;

	if (!header || !toggle || !panel) {
		return;
	}

	panel.hidden = false;

	const setOpen = (open) => {
		toggle.setAttribute('aria-expanded', String(open));
		header.classList.toggle(SEARCH_OPEN, open);

		if (open) {
			// Without preventScroll the browser scrolls the page to a field
			// that is still a few pixels tall, mid-animation, and the header
			// it belongs to slides out from under the visitor.
			panel
				.querySelector('input[type="search"]')
				?.focus({ preventScroll: true });
		}
	};

	toggle.addEventListener('click', () => {
		setOpen(toggle.getAttribute('aria-expanded') !== 'true');
	});

	document.addEventListener('keydown', (event) => {
		if (event.key !== 'Escape' || !header.classList.contains(SEARCH_OPEN)) {
			return;
		}

		setOpen(false);
		toggle.focus();
	});

	document.addEventListener('pointerdown', (event) => {
		if (!panel.contains(event.target) && !toggle.contains(event.target)) {
			setOpen(false);
		}
	});
}

/**
 * Collapse the menu bar to the panel at the width it stops fitting, rather
 * than at the width core happens to name.
 *
 * The measurement is one comparison, made possible by a stylesheet rule rather
 * than by arithmetic here: in the bar state the header's row is held to a
 * single line, so a menu with nowhere left to go overflows instead of wrapping
 * and `scrollWidth` runs past `clientWidth`. Adding up label widths, gaps,
 * padding and a logo would be the same question asked in a way that goes wrong
 * every time somebody changes the CSS.
 *
 * Collapsing hides the thing being measured, so the width the bar needed is
 * kept: once collapsed, the row's own content proves nothing, and the only
 * honest question left is whether there is now at least as much room as there
 * was when it stopped fitting. When there is, the bar comes back and is
 * measured again in the same frame — so a number that has gone stale, because
 * a font swapped or the menu was edited, corrects itself on the next resize
 * rather than being trusted forever.
 *
 * State goes on with core's own overlay classes, so the panel that opens is
 * the panel core already knows how to open. The class on the header is for the
 * theme's own rules: it tells the menu-bar treatments to stand down, which is
 * what stops a dropdown's tab styling landing on rows inside the panel.
 */
function initNavCollapse() {
	const header = document.querySelector(ANY_HEADER);
	const nav = header?.querySelector(NAV);
	const row = header?.querySelector(END)?.parentElement;
	const button = nav?.querySelector(NAV_OPEN_BUTTON);
	const container = nav?.querySelector(NAV_CONTAINER);

	if (
		!header ||
		!row ||
		!button ||
		!container ||
		typeof ResizeObserver === 'undefined'
	) {
		return;
	}

	// The width the bar was short of, remembered from the frame it ran out.
	let required = 0;
	let scheduled = false;

	const apply = (collapsed) => {
		header.classList.toggle(NAV_COLLAPSED, collapsed);
		button.classList.toggle(NAV_ALWAYS_SHOWN, collapsed);
		container.classList.toggle(NAV_HIDDEN_BY_DEFAULT, collapsed);
	};

	// One pixel of tolerance. Sub-pixel layout rounds a row that fits exactly
	// into one that overflows by a fraction, and a menu that collapses on the
	// width it fits at is the original bug with a smaller number.
	const overflows = () => row.scrollWidth > row.clientWidth + 1;

	const sync = () => {
		scheduled = false;

		// Not while the panel is open: the visitor is inside the menu, and
		// taking it out from under them to swap in a bar is worse than a bar
		// arriving a moment late.
		if (container.classList.contains(NAV_MENU_OPEN)) {
			return;
		}

		if (window.innerWidth < NAV_BREAKPOINT) {
			// Core collapses the nav on its own down here, and it does it in a
			// media query, which is one fewer thing to be wrong.
			apply(false);
			return;
		}

		if (!header.classList.contains(NAV_COLLAPSED)) {
			if (overflows()) {
				required = row.scrollWidth;
				apply(true);
			}

			return;
		}

		if (!required || row.clientWidth < required) {
			return;
		}

		apply(false);

		// Measured again now the bar is back, because `required` is a record
		// of what was true last time rather than a fact about this one.
		if (overflows()) {
			required = row.scrollWidth;
			apply(true);
		}
	};

	const schedule = () => {
		if (scheduled) {
			return;
		}

		scheduled = true;
		requestAnimationFrame(sync);
	};

	new ResizeObserver(schedule).observe(row);

	// A font that swaps late changes what the menu needs without changing the
	// size of anything the observer is watching.
	document.fonts?.ready.then(schedule);

	sync();
}

function initHeader() {
	initStickyHeader();
	publishHeaderHeight();
	initHoverDismiss();
	initHeaderSearch();
	initNavCollapse();
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initHeader);
} else {
	initHeader();
}
