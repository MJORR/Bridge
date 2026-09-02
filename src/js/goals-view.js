/**
 * Bridge — Goals count-up.
 *
 * Loaded only on pages that render `bridge/goals`, and only when the block's
 * count-up setting is on: render.php prints the `data-bridge-goals` hook this
 * file looks for, or it does not.
 *
 * The figures are already on the page at their true values before this script
 * arrives — nothing here is needed to read them. What it adds is the count
 * from zero, which starts when the row first comes into view and runs once.
 *
 * Formatting is done here rather than through `toLocaleString`, which formats
 * for the *browser's* locale: a German visitor would have watched "1.200"
 * count up and then snap to the "1,200" the editor typed on the last frame.
 * The separators come from the authored figure instead, so every frame is in
 * the format the page was written in.
 */

const BLOCKS = '[data-bridge-goals]';
const NUMBERS = '[data-bridge-goal-number]';

/**
 * How long one figure takes to reach its value, and how far apart the figures
 * in a row start. The stagger is what makes a row read as a row rather than as
 * four numbers that happen to move at once; long enough to see, short enough
 * that the last one is not still counting after the eye has moved on.
 */
const DURATION = 1600;
const STAGGER = 110;

// Fast to begin with and easing off at the end, so the figure spends its last
// moments near the number it is going to land on rather than racing past it.
const easeOut = (progress) => 1 - Math.pow(1 - progress, 3);

/**
 * A value drawn the way the editor wrote it.
 *
 * @param {number}  value    The value at this frame.
 * @param {number}  decimals Digits after the point.
 * @param {boolean} group    Whether thousands are separated.
 * @return {string} The formatted figure.
 */
const format = (value, decimals, group) => {
	const [whole, fraction] = value.toFixed(decimals).split('.');
	const separated = group
		? whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')
		: whole;

	return fraction ? `${separated}.${fraction}` : separated;
};

/**
 * Count one figure from zero to its value.
 *
 * @param {HTMLElement} element The number span.
 * @param {number}      delay   Milliseconds to wait before starting.
 */
const count = (element, delay) => {
	const target = parseFloat(element.dataset.value);

	// render.php only prints the data attributes for a figure it could parse,
	// so this is insurance rather than a branch that is expected to be taken —
	// but the alternative to taking it is a figure counting up to "NaN".
	if (!Number.isFinite(target)) {
		return;
	}

	const decimals = parseInt(element.dataset.decimals, 10) || 0;
	const group = element.dataset.group === '1';
	// The figure exactly as it was authored, kept so the last frame is the
	// page's own text rather than this file's best reconstruction of it.
	const final = element.textContent;

	let start = null;

	const frame = (now) => {
		if (start === null) {
			start = now;
		}

		const elapsed = now - start - delay;

		if (elapsed < 0) {
			window.requestAnimationFrame(frame);
			return;
		}

		if (elapsed >= DURATION) {
			element.textContent = final;
			return;
		}

		element.textContent = format(
			easeOut(elapsed / DURATION) * target,
			decimals,
			group
		);

		window.requestAnimationFrame(frame);
	};

	window.requestAnimationFrame(frame);
};

const init = () => {
	// Reduced motion is answered by doing nothing at all: the numbers are
	// already correct in the markup, so leaving them alone *is* the still
	// version of this. No observer is created and no figure is ever zeroed.
	if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
		return;
	}

	if (typeof window.IntersectionObserver !== 'function') {
		return;
	}

	const blocks = Array.from(document.querySelectorAll(BLOCKS)).filter(
		(block) => block.querySelector(NUMBERS)
	);

	if (!blocks.length) {
		return;
	}

	// `threshold: 0`, deliberately. A larger one would mean the row had to be
	// a quarter or a half on screen before it counted as arrived — and the
	// figures cannot be set to zero until then, because zeroing something the
	// visitor is already looking at is a visible snap backwards. At zero, the
	// first callback for a row below the fold reliably says "not intersecting"
	// while it is genuinely off screen, which is the only safe moment to reset
	// it, and the count begins as its first pixel appears.
	const observer = new window.IntersectionObserver(
		(entries) => {
			entries.forEach((entry) => {
				const numbers = Array.from(
					entry.target.querySelectorAll(NUMBERS)
				);

				if (!entry.isIntersecting) {
					numbers.forEach((number) => {
						number.textContent = format(
							0,
							parseInt(number.dataset.decimals, 10) || 0,
							number.dataset.group === '1'
						);
					});
					return;
				}

				// Once only. A figure that counted again every time it came
				// back into view would turn a page the visitor is scrolling
				// through into something that keeps demanding to be watched.
				observer.unobserve(entry.target);
				numbers.forEach((number, index) =>
					count(number, index * STAGGER)
				);
			});
		},
		{ threshold: 0 }
	);

	blocks.forEach((block) => observer.observe(block));
};

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}
