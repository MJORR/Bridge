/**
 * Bridge — the styling controls come off core/button.
 *
 * A button is the one element on a site that most needs to look the same
 * everywhere: it is how a visitor learns what is clickable. Core ships it with
 * the fullest set of controls of any block — its own typeface, size, weight,
 * casing, letter-spacing, background, text colour, border colour, width, style
 * and radius, a shadow, a padding box and a width preset — and every one of
 * them is a way for one button on one page to stop being recognisable as the
 * same control as the button on the page before it.
 *
 * None of it is lost, because none of it was ever the editor's decision here.
 * theme.json paints the button from the Theme Options tokens — colour, radius,
 * border width, padding, size, weight, casing and letter-spacing, for the
 * default and for the outline variation both. Change the token once and every
 * button on the site follows; that is the whole point of having them.
 *
 * What is left on the block is what a button actually is: its text, its link,
 * and the Advanced tab.
 */

const { addFilter } = window.wp.hooks;

const BLOCK = 'core/button';

/**
 * @param {Object} settings Block settings.
 * @param {string} name     Block name.
 * @return {Object} Settings, altered for core/button only.
 */
const lockDown = (settings, name) => {
	if (name !== BLOCK) {
		return settings;
	}

	const supports = { ...settings.supports };

	// Typography and colour go together, not as two decisions. In WordPress 7
	// the Text colour control moved *into* the Typography panel and the
	// Background colour control into a Background panel of its own, so a
	// button that keeps `color` keeps a Typography panel with a colour swatch
	// sitting in it — the panel is only gone once both are.
	delete supports.typography;
	delete supports.color;

	// The Dimensions panel is fed by two supports: `dimensions` puts the
	// 25/50/75/100% width preset in it, `spacing` puts the padding box in it.
	// Deleting one leaves the panel standing with the other still inside.
	delete supports.dimensions;
	delete supports.spacing;

	// Both border keys: core moved the flag from the experimental name to the
	// stable one, and a theme that deletes only one of them still ships the
	// control on whichever WordPress the site happens to be running.
	delete supports.__experimentalBorder;
	delete supports.border;

	delete supports.shadow;

	// Fill and Outline. The two variations stay *painted* — theme.json styles
	// `is-style-outline` and every button already saved as one keeps its look
	// — but they stop being offered, so a new button is the site's button.
	return { ...settings, supports, styles: [] };
};

addFilter('blocks.registerBlockType', 'bridge/button-lock', lockDown);
