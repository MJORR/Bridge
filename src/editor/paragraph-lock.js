/**
 * Bridge — the spacing controls come off core/paragraph.
 *
 * A paragraph is the block an editor reaches for constantly, and the only one
 * that arrived carrying core's full set of spacing and border controls. That
 * put padding, margins and a border on the block least able to hold them: a
 * run of body copy with a hand-typed 18px top margin is how a page stops
 * matching the rest of the site, and no amount of design system further up
 * puts it back.
 *
 * Nothing is lost by removing them. The space *around* a paragraph is the
 * block gap, set once in Theme Options; the space *inside* a band is the
 * Section block's, which is where a paragraph that wants a background and a
 * full-width stripe now belongs.
 *
 * Colour stays. The palette an editor can reach is already locked to the
 * theme's six, so a coloured paragraph cannot go off-brand — and taking that
 * away would leave no way to mark a note or a caveat at all.
 */

const { addFilter } = window.wp.hooks;

const BLOCK = 'core/paragraph';

/**
 * @param {Object} settings Block settings.
 * @param {string} name     Block name.
 * @return {Object} Settings, altered for core/paragraph only.
 */
const lockDown = (settings, name) => {
	if (name !== BLOCK) {
		return settings;
	}

	const supports = { ...settings.supports };

	// Both border keys: core moved the flag from the experimental name to the
	// stable one, and a theme that deletes only one of them still ships the
	// control on whichever WordPress the site happens to be running.
	delete supports.spacing;
	delete supports.__experimentalBorder;
	delete supports.border;
	delete supports.dimensions;
	delete supports.shadow;

	return { ...settings, supports };
};

addFilter('blocks.registerBlockType', 'bridge/paragraph-lock', lockDown);
