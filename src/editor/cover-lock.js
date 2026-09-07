/**
 * Bridge — the spacing controls come off core/cover.
 *
 * The same removal paragraph-lock.js makes, and for the same reason: padding,
 * margin and the gap between blocks are set once in Theme Options and spent by
 * every band on the site. A per-block override is how one page stops matching
 * the rest of them, and no amount of design system further up puts it back.
 *
 * It matters most on a Cover, because a Cover is what a hero slide is. A slide
 * carrying a hand-typed 42px padding is a hero that no longer lines up with the
 * one on the next page, and the controls sat directly under the setting an
 * editor actually wanted — the picture.
 *
 * What is left is everything a Cover is genuinely for: the image, the overlay
 * and its opacity, the focal point, the minimum height and the position of the
 * content within it. None of those is a site-wide decision, and none of them
 * has an answer anywhere else.
 *
 * Global rather than "only inside a hero slider", and deliberately: supports
 * belong to a block type, so scoping them to one parent would mean a Cover that
 * shows different controls depending on where it was dropped — and an editor
 * who learns the controls in one place would find them missing in the other.
 */

const { addFilter } = window.wp.hooks;

const BLOCK = 'core/cover';

/**
 * @param {Object} settings Block settings.
 * @param {string} name     Block name.
 * @return {Object} Settings, altered for core/cover only.
 */
const lockDown = (settings, name) => {
	if (name !== BLOCK) {
		return settings;
	}

	const supports = { ...settings.supports };

	// `spacing` is padding, margin and blockGap together — the three the site
	// answers globally. `dimensions` stays: on a Cover that is the minimum
	// height, which is the block's own shape rather than the site's spacing.
	delete supports.spacing;

	return { ...settings, supports };
};

addFilter('blocks.registerBlockType', 'bridge/cover-lock', lockDown);
