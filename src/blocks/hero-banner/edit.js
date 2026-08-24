/**
 * Editor view for `bridge/hero-banner`.
 *
 * One core/cover, laid out in columns. The outer InnerBlocks is locked so the
 * banner cannot become a second slider by accident — but the Cover carries
 * `templateLock: false` of its own, which stops the lock cascading into it.
 * Everything inside the Cover, the columns included, stays freely editable,
 * so column widths, stacking and vertical alignment are core's controls doing
 * their own job rather than a second set of ours.
 */

const { useBlockProps, useInnerBlocksProps, InspectorControls } =
	window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, ToggleControl, RangeControl, SelectControl } =
	window.wp.components;
const { __ } = window.wp.i18n;

/**
 * The chosen height, as a CSS length, for the editor canvas.
 *
 * bridge_hero_metrics() in inc/hero-blocks.php is authoritative — it is what
 * the front end renders — and this is the preview's copy of the same four
 * presets. The two lists have to agree. It lives here rather than in a shared
 * module because every editor bundle is wrapped as a self-contained IIFE, so a
 * module imported by two entries becomes a chunk the wrapper cannot import.
 *
 * The header allowance is deliberately not repeated: the canvas is not the
 * window, and the stylesheet zeroes the inset for the preview for that reason.
 *
 * @param {string} preset One of full, tall, medium, custom.
 * @param {number} size   Custom height, when the preset is custom.
 * @param {string} unit   Custom unit, when the preset is custom.
 * @return {string} A CSS length.
 */
const heroHeight = (preset, size, unit) => {
	if (preset === 'tall') {
		return '80dvh';
	}

	if (preset === 'medium') {
		return '60dvh';
	}

	if (preset === 'custom') {
		const safeUnit = ['vh', 'dvh', 'px'].includes(unit) ? unit : 'vh';
		const safeSize = Math.max(20, Math.min(4000, Number(size) || 80));

		return `${safeSize}${safeUnit}`;
	}

	return '100dvh';
};

const ALLOWED_BLOCKS = ['core/cover'];

// Two columns at wide width: the Cover's inner container runs core's
// constrained layout, where the default is the 720px content size — a split
// hero built at that width is two narrow strips adrift in the middle of the
// window. `alignwide` opts into the 1200px wide size instead.
const TEMPLATE = [
	[
		'core/cover',
		{
			dimRatio: 50,
			isUserOverlayColor: true,
			contentPosition: 'center center',
			templateLock: false,
		},
		[
			[
				'core/columns',
				{
					align: 'wide',
					verticalAlignment: 'center',
					style: {
						spacing: {
							blockGap: { left: 'var:preset|spacing|60' },
						},
					},
				},
				[
					[
						'core/column',
						{ verticalAlignment: 'center' },
						[
							[
								'core/heading',
								{
									level: 1,
									placeholder: __('Banner title…', 'bridge'),
								},
							],
							[
								'core/paragraph',
								{
									placeholder: __(
										'Optional supporting line',
										'bridge'
									),
								},
							],
							['core/buttons', {}, [['core/button']]],
						],
					],
					[
						'core/column',
						{ verticalAlignment: 'center' },
						[['core/image', {}]],
					],
				],
			],
		],
	],
];

const Edit = ({ attributes, setAttributes }) => {
	const { align, heightPreset, customHeight, customHeightUnit } = attributes;

	// The chosen height, published for the preview. Without it the canvas
	// always drew the 100dvh fallback and the height control looked broken.
	const blockProps = useBlockProps({
		className: 'bridge-hero-banner is-editor-preview',
		style: {
			'--bridge-hero-banner-height': heroHeight(
				heightPreset,
				customHeight,
				customHeightUnit
			),
		},
	});

	// useInnerBlocksProps rather than a nested <InnerBlocks />: the latter
	// renders its children inside an extra `block-editor-block-list__layout`
	// div that exists only in the editor, which would put the Cover one level
	// deeper here than render.php puts it on the front end — and every
	// direct-child selector in the stylesheet would match one tree and miss
	// the other. Merging the props onto this element instead makes the Cover
	// a direct child in both, so one set of rules is correct in both.
	const innerBlocksProps = useInnerBlocksProps(blockProps, {
		allowedBlocks: ALLOWED_BLOCKS,
		template: TEMPLATE,
		templateLock: 'all',
	});

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Banner settings', 'bridge'), initialOpen: true },
				// Writes the block's own `align` attribute rather than a
				// second one of ours, so this and the alignment control in
				// the block toolbar are the same switch under two labels
				// instead of two switches that can disagree. Cleared to '',
				// not undefined — undefined would fall back to the "full"
				// default declared in block.json and the toggle would spring
				// back on.
				el(ToggleControl, {
					label: __('Full window width', 'bridge'),
					help: __(
						'On, the banner spans the whole window. Off, it stays inside the page content width.',
						'bridge'
					),
					checked: align === 'full',
					onChange: (value) =>
						setAttributes({ align: value ? 'full' : '' }),
					__nextHasNoMarginBottom: true,
				}),
				el(SelectControl, {
					label: __('Minimum height', 'bridge'),
					help: __(
						'A floor, not a cap — content taller than this makes the banner taller rather than being cut off. Screen heights allow for the header and its top bar, so Full screen fills the window below them.',
						'bridge'
					),
					value: heightPreset,
					options: [
						{ label: __('Full screen', 'bridge'), value: 'full' },
						{ label: __('Tall (80%)', 'bridge'), value: 'tall' },
						{
							label: __('Medium (60%)', 'bridge'),
							value: 'medium',
						},
						{ label: __('Custom', 'bridge'), value: 'custom' },
					],
					onChange: (value) => setAttributes({ heightPreset: value }),
				}),
				heightPreset === 'custom' &&
					el(RangeControl, {
						label: __('Custom height', 'bridge'),
						value: customHeight,
						onChange: (value) =>
							setAttributes({ customHeight: value }),
						min: 20,
						max: customHeightUnit === 'px' ? 1200 : 200,
						step: 1,
					}),
				heightPreset === 'custom' &&
					el(SelectControl, {
						label: __('Unit', 'bridge'),
						value: customHeightUnit,
						options: [
							{ label: 'vh', value: 'vh' },
							{ label: 'dvh', value: 'dvh' },
							{ label: 'px', value: 'px' },
						],
						onChange: (value) =>
							setAttributes({ customHeightUnit: value }),
					})
			)
		),
		el('div', innerBlocksProps)
	);
};

export default Edit;
