/**
 * Bridge — the decorative mask shape a band can carry, in the editor.
 *
 * The controls and the preview styling for `bridge_band_mask()`. Imported by
 * every block that offers the shape, so the four settings read the same, mean
 * the same and write the same four custom properties the front end does.
 *
 * The data — the shape's URL, the palette, the address of the screen that sets
 * them — is printed against this script by functions.php, because it is the
 * same answer for every block that asks.
 */

const { createElement: el } = window.wp.element;
const {
	PanelBody,
	ToggleControl,
	RangeControl,
	ColorPalette,
	BaseControl,
	ExternalLink,
} = window.wp.components;
const { __ } = window.wp.i18n;

const DATA = window.bridgeBandMask || {};

const MASK_URL = DATA.maskUrl || '';
const OPTIONS_URL = DATA.optionsUrl || '';

/**
 * The site's global colours, straight from the token record.
 *
 * Not the editor's `colors` setting: these are the only colours a mask may
 * take, and reading them from where Theme Options sets them is what keeps that
 * true when core changes how it assembles its own list.
 */
const PALETTE = DATA.palette || [];

/**
 * The four custom properties the band draws the shape from.
 *
 * The same four bridge_band_mask() writes, so the editor and the front end
 * share one stylesheet rule rather than two implementations.
 *
 * @param {Object} attributes The block's mask attributes.
 * @return {Object|undefined} A style object, or undefined when there is no shape.
 */
function maskStyle({ mask, maskColor, maskSize, maskInset }) {
	if (!mask || !MASK_URL) {
		return undefined;
	}

	return {
		'--bridge-band-mask-image': `url(${MASK_URL})`,
		'--bridge-band-mask-color': `var(--wp--preset--color--${maskColor})`,
		'--bridge-band-mask-size': `${maskSize}%`,
		'--bridge-band-mask-inset': `${maskInset}%`,
	};
}

/**
 * Whether the band is showing a shape.
 *
 * Both halves matter: a block can ask for one on a site that has not set one,
 * and the answer then is no shape rather than a coloured rectangle.
 *
 * @param {Object} attributes The block's mask attributes.
 * @return {boolean} True when a shape will be drawn.
 */
function hasMask({ mask }) {
	return !!mask && !!MASK_URL;
}

/**
 * The inspector panel.
 *
 * @param {Object}   attributes    The block's mask attributes.
 * @param {Function} setAttributes The block's setter.
 * @return {Object} A PanelBody element.
 */
function MaskPanel(attributes, setAttributes) {
	const { mask, maskColor, maskSize, maskInset } = attributes;
	const hex = PALETTE.find((entry) => entry.slug === maskColor)?.color;

	return el(
		PanelBody,
		{ title: __('Mask shape', 'bridge'), initialOpen: false },
		!MASK_URL &&
			el(
				'p',
				{ className: 'bridge-options__hint' },
				__('No mask shape has been set for this site yet.', 'bridge'),
				OPTIONS_URL && ' ',
				OPTIONS_URL &&
					el(
						ExternalLink,
						{ href: OPTIONS_URL },
						__('Theme Options', 'bridge')
					)
			),
		!!MASK_URL &&
			el(ToggleControl, {
				label: __('Show the mask shape', 'bridge'),
				help: __(
					'Paints the site’s mask shape flat behind this band, in one palette colour.',
					'bridge'
				),
				checked: !!mask,
				onChange: (value) => setAttributes({ mask: value }),
				__nextHasNoMarginBottom: true,
			}),
		// The three below only exist while there is a shape to place —
		// controls for something switched off are three ways to change
		// nothing.
		!!MASK_URL &&
			!!mask &&
			el(
				BaseControl,
				{
					label: __('Mask colour', 'bridge'),
					help: __(
						'One of the site’s global colours, set in Theme Options.',
						'bridge'
					),
					__nextHasNoMarginBottom: true,
				},
				el(ColorPalette, {
					colors: PALETTE,
					value: hex,
					// The global palette and nothing else: the shape is meant
					// to move when the palette moves, which a typed hex would
					// not. The toggle above is what turns the shape off, so
					// there is nothing to clear here.
					disableCustomColors: true,
					clearable: false,
					onChange: (value) =>
						setAttributes({
							maskColor:
								PALETTE.find((entry) => entry.color === value)
									?.slug || 'accent',
						}),
				})
			),
		!!MASK_URL &&
			!!mask &&
			el(RangeControl, {
				label: __('Mask size (percent)', 'bridge'),
				help: __(
					'How wide the shape is as a share of the band. At 100 it covers.',
					'bridge'
				),
				value: maskSize,
				min: 10,
				max: 200,
				step: 5,
				onChange: (value) => setAttributes({ maskSize: value || 60 }),
				__nextHasNoMarginBottom: true,
			}),
		!!MASK_URL &&
			!!mask &&
			el(RangeControl, {
				label: __('Mask position from the right (percent)', 'bridge'),
				help: __(
					'How far the shape is held off the right edge of the band.',
					'bridge'
				),
				value: maskInset,
				min: 0,
				max: 90,
				step: 1,
				onChange: (value) => setAttributes({ maskInset: value || 0 }),
				__nextHasNoMarginBottom: true,
			})
	);
}

/*
 * Published on a global rather than exported.
 *
 * Every bundle here is a self-contained IIFE that WordPress loads as a classic
 * script — see the banner in vite.config.js. A module two entries import is
 * hoisted into a chunk nothing enqueues, so shared editor code ships the way
 * the carousel runtime does: its own handle, named in the deps of the blocks
 * that use it, loaded once however many of them are on the page.
 */
window.bridgeBandMaskUI = { MaskPanel, maskStyle, hasMask };
