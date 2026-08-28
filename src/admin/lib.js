/**
 * Bridge — Theme Options, shared helpers.
 *
 * The pure functions: no state, no markup, no WordPress beyond the string
 * table. Everything here is imported by more than one module, which is the
 * only reason it is not sitting beside its single caller.
 */

const { __ } = wp.i18n;

/** Sample text for the type-scale preview, per size slug. */
export const SPECIMENS = {
	small: __('Captions, meta and form hints', 'bridge'),
	medium: __('Body copy sets the rhythm of every page.', 'bridge'),
	large: __('A section subheading', 'bridge'),
	'x-large': __('Section heading', 'bridge'),
	'xx-large': __('Page title', 'bridge'),
};

/**
 * Normalise whatever ColorPicker hands back into a 6-digit hex.
 *
 * The component has returned both strings and `{ hex }` objects across
 * versions, and can append an alpha pair the server would reject outright.
 *
 * @param {string|Object} value Whatever ColorPicker passed back.
 * @return {string} A hex colour, or an empty string.
 */
export function toHex(value) {
	const raw = typeof value === 'string' ? value : value?.hex || '';
	const hex = raw.trim();

	return hex.length === 9 ? hex.slice(0, 7) : hex;
}

/**
 * Is this a hex colour the server will accept?
 *
 * @param {string} value Candidate colour.
 * @return {boolean} True when the server's sanitiser would keep it.
 */
export function isValidHex(value) {
	return /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(value);
}

/**
 * Pull a compiled family's stack out of the preview payload.
 *
 * @param {Array}  fontFamilies Compiled families from the preview response.
 * @param {string} slug         Family slug, e.g. `sans` or `heading`.
 * @return {string|undefined} The CSS font stack, if that family exists.
 */
export function stackFor(fontFamilies, slug) {
	return fontFamilies?.find((f) => f.slug === slug)?.fontFamily;
}

/**
 * A skin's geometry as the custom properties the button stylesheet spends.
 *
 * wp-admin prints no global styles, so `--wp--custom--button--radius` resolves
 * to nothing on this screen. Setting the same properties inline is what lets
 * the preview share abstracts/_button.scss with the front end rather than
 * carry a second description of a button that would drift from it.
 *
 * Takes either a skin from the payload or the compiled `custom.button` from a
 * preview response — the keys are the same by design, so the skin picker can
 * draw three skins at once while the preview draws the chosen one.
 *
 * @param {Object} skin Skin geometry.
 * @return {Object} Inline style object.
 */
export function skinVars(skin) {
	return {
		'--wp--custom--button--radius': skin?.radius,
		'--wp--custom--button--padding-block': skin?.paddingBlock,
		'--wp--custom--button--padding-inline': skin?.paddingInline,
		'--wp--custom--button--weight': skin?.weight,
		'--wp--custom--button--transform': skin?.transform,
		'--wp--custom--button--letter-spacing': skin?.letterSpacing,
		'--wp--custom--button--border-width': skin?.borderWidth,
		'--wp--custom--button--shadow': skin?.shadow,
		'--wp--custom--button--shadow-hover': skin?.shadowHover,
		'--wp--custom--button--lift': skin?.lift,
		'--wp--custom--button--sweep': skin?.sweep,
		// Not a skin value — the tap-target floor is the same on all three.
		'--wp--custom--button--min-size': skin?.minSize || '44px',
	};
}

/**
 * One ground's colour scheme, as the variables a band sets on the site.
 *
 * @param {Object} scheme A scheme from the preview response.
 * @return {Object} Inline style object.
 */
export function groundVars(scheme) {
	return {
		'--bridge-button-bg': scheme?.bg,
		'--bridge-button-fg': scheme?.fg,
		'--bridge-button-hover-bg': scheme?.hoverBg,
		'--bridge-button-hover-fg': scheme?.hoverFg,
		'--bridge-button-border': scheme?.border,
		'--bridge-button-hover-border': scheme?.hoverBorder,
		'--bridge-button-ghost': scheme?.ghost,
		'--bridge-button-ghost-hover': scheme?.ghostHover,
		'--bridge-button-ring': scheme?.ring,
	};
}
