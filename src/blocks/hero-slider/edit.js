/**
 * Editor view for `bridge/hero-slider`.
 *
 * Renders InnerBlocks (core/cover slides) plus an InspectorControls panel
 * exposing slider-level settings (width, height, effect, autoplay, loop, UI
 * toggles).
 */

const { useBlockProps, InnerBlocks, InspectorControls } = window.wp.blockEditor;
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

const TEMPLATE = [
	[
		'core/cover',
		{
			dimRatio: 50,
			minHeight: 80,
			minHeightUnit: 'vh',
			isUserOverlayColor: true,
			contentPosition: 'center center',
		},
		[
			[
				'core/heading',
				{
					level: 1,
					textAlign: 'center',
					placeholder: __('Slide title…', 'bridge'),
				},
			],
			[
				'core/paragraph',
				{
					align: 'center',
					placeholder: __('Optional subtitle', 'bridge'),
				},
			],
		],
	],
	[
		'core/cover',
		{
			dimRatio: 50,
			minHeight: 80,
			minHeightUnit: 'vh',
			isUserOverlayColor: true,
			contentPosition: 'center center',
		},
		[
			[
				'core/heading',
				{
					level: 1,
					textAlign: 'center',
					placeholder: __('Slide title…', 'bridge'),
				},
			],
		],
	],
];

const Edit = ({ attributes, setAttributes }) => {
	const {
		align,
		heightPreset,
		customHeight,
		customHeightUnit,
		effect,
		autoplay,
		autoplayDelay,
		loop,
		showPagination,
		showNavigation,
	} = attributes;

	// The chosen height, published for the preview. Without it the canvas
	// always drew the 100dvh fallback and the height control looked broken.
	const blockProps = useBlockProps({
		className: 'bridge-hero-slider is-editor-preview',
		style: {
			'--bridge-slider-height': heroHeight(
				heightPreset,
				customHeight,
				customHeightUnit
			),
		},
	});

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Slider settings', 'bridge'), initialOpen: true },
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
						'On, the slider spans the whole window. Off, it stays inside the page content width.',
						'bridge'
					),
					checked: align === 'full',
					onChange: (value) =>
						setAttributes({ align: value ? 'full' : '' }),
					__nextHasNoMarginBottom: true,
				}),
				el(SelectControl, {
					label: __('Slider height', 'bridge'),
					help: __(
						'Sets the height of the whole slider — individual Cover height settings are ignored. Screen heights allow for the header, so Full screen fills the window below it and slide content stays centred in what is visible.',
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
					}),
				el(SelectControl, {
					label: __('Transition effect', 'bridge'),
					value: effect,
					options: [
						{ label: __('Fade', 'bridge'), value: 'fade' },
						{ label: __('Slide', 'bridge'), value: 'slide' },
					],
					onChange: (value) => setAttributes({ effect: value }),
				}),
				el(ToggleControl, {
					label: __('Autoplay', 'bridge'),
					checked: !!autoplay,
					onChange: (value) => setAttributes({ autoplay: value }),
				}),
				autoplay &&
					el(RangeControl, {
						label: __('Autoplay delay (seconds)', 'bridge'),
						value: autoplayDelay,
						onChange: (value) =>
							setAttributes({ autoplayDelay: value }),
						min: 3,
						max: 15,
						step: 1,
					}),
				el(ToggleControl, {
					label: __('Loop slides', 'bridge'),
					checked: !!loop,
					onChange: (value) => setAttributes({ loop: value }),
				}),
				el(ToggleControl, {
					label: __('Show pagination dots', 'bridge'),
					checked: !!showPagination,
					onChange: (value) =>
						setAttributes({ showPagination: value }),
				}),
				el(ToggleControl, {
					label: __('Show prev/next arrows', 'bridge'),
					checked: !!showNavigation,
					onChange: (value) =>
						setAttributes({ showNavigation: value }),
				})
			)
		),
		el(
			'div',
			blockProps,
			el(InnerBlocks, {
				allowedBlocks: ALLOWED_BLOCKS,
				template: TEMPLATE,
				templateLock: false,
				orientation: 'vertical',
			})
		)
	);
};

export default Edit;
