/**
 * Editor view for `bridge/alternating-content`.
 *
 * The wrapper carries the same classes render.php writes, `is-first-right`
 * included, because the alternation is one stylesheet rule counting rows —
 * not two implementations that have to be kept in step.
 */

const { useBlockProps, useInnerBlocksProps, InspectorControls } =
	window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, SelectControl, ToggleControl, RangeControl } =
	window.wp.components;
const { __ } = window.wp.i18n;

/*
 * Rows, and nothing else.
 *
 * The heading and summary this block used to accept are gone: the rows carry
 * their own headings, so a section title either said the same thing twice or
 * sat empty and spent a row of the section's gap on nothing. Removing them
 * from the allow list is what stops the inserter offering them inside this
 * block; render.php has no intro to render either.
 */
const ALLOWED_BLOCKS = ['bridge/alternating-row'];

/**
 * The chosen row height, as a CSS length, for the editor canvas.
 *
 * The hero's presets, so the two blocks mean the same thing by "Tall": the
 * front end reads them through bridge_hero_metrics() in inc/hero-blocks.php,
 * and this list has to agree with that one (as heroHeight() in the hero's
 * edit.js does). Undefined for Auto, which leaves the rows to the picture.
 *
 * @param {string} preset One of auto, full, tall, medium, custom.
 * @param {number} size   Custom height, when the preset is custom.
 * @param {string} unit   Custom unit, when the preset is custom.
 * @return {string|undefined} A CSS length.
 */
const rowHeight = (preset, size, unit) => {
	switch (preset) {
		case 'full':
			return '100dvh';
		case 'tall':
			return '80dvh';
		case 'medium':
			return '60dvh';
		case 'custom': {
			const safeUnit = ['vh', 'dvh', 'px'].includes(unit) ? unit : 'vh';
			const safeSize = Math.max(20, Math.min(4000, Number(size) || 80));

			return `${safeSize}${safeUnit}`;
		}
		default:
			return undefined;
	}
};

const TEMPLATE = [
	['bridge/alternating-row', {}],
	['bridge/alternating-row', {}],
];

const Edit = ({ attributes, setAttributes }) => {
	const {
		width,
		firstImageRight,
		sizing = 'ratio',
		ratio = '16-9',
		heightPreset = 'tall',
		customHeight,
		customHeightUnit,
	} = attributes;

	// Set height is a full-window option; a contained band always goes by the
	// picture's shape. Matches render.php.
	const setHeight = width === 'full' && sizing === 'height';
	const height = setHeight
		? rowHeight(heightPreset, customHeight, customHeightUnit)
		: undefined;

	const blockProps = useBlockProps({
		className: [
			'bridge-alternating',
			'bridge-section',
			'bridge-band',
			'alignfull',
			width && width !== 'wide' ? `bridge-alternating--${width}` : '',
			// The band insets its own content in this layout, so it opts out
			// of the gutter a skin would give it. Matches render.php.
			width === 'full' ? 'bridge-band--flush' : '',
			firstImageRight ? 'is-first-right' : '',
			setHeight ? 'has-set-height' : '',
			!setHeight && ratio !== '16-9' ? `is-ratio-${ratio}` : '',
		]
			.filter(Boolean)
			.join(' '),
		style: height ? { '--bridge-alternating-height': height } : undefined,
	});

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'bridge-alternating__inner' },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			templateLock: false,
		}
	);

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Layout', 'bridge'), initialOpen: true },
				el(SelectControl, {
					label: __('Width', 'bridge'),
					value: width,
					help:
						width === 'full'
							? __(
									'Rows run the full width of the window and the media is flush to its edge. The words stay lined up with the wide container.',
									'bridge'
								)
							: __('The container the rows sit in.', 'bridge'),
					options: [
						{ label: __('Wide', 'bridge'), value: 'wide' },
						{ label: __('Narrow', 'bridge'), value: 'narrow' },
						{ label: __('Full window', 'bridge'), value: 'full' },
					],
					onChange: (value) => setAttributes({ width: value }),
					__nextHasNoMarginBottom: true,
				}),
				el(ToggleControl, {
					label: __('First row: media on the right', 'bridge'),
					help: __(
						'Rows alternate from here, so this is the only side you have to choose.',
						'bridge'
					),
					checked: !!firstImageRight,
					onChange: (value) =>
						setAttributes({ firstImageRight: value }),
					__nextHasNoMarginBottom: true,
				})
			),
			el(
				PanelBody,
				{ title: __('Row size', 'bridge'), initialOpen: true },
				// Set height is full window only: in a contained band the
				// picture keeps its shape inside the column, so a taller row
				// would only add empty band above and below it.
				width === 'full' &&
					el(SelectControl, {
						label: __('Size rows by', 'bridge'),
						value: sizing,
						options: [
							{
								label: __('Image shape', 'bridge'),
								value: 'ratio',
							},
							{
								label: __('Set height', 'bridge'),
								value: 'height',
							},
						],
						onChange: (value) => setAttributes({ sizing: value }),
						__nextHasNoMarginBottom: true,
					}),
				!setHeight &&
					el(SelectControl, {
						label: __('Image shape', 'bridge'),
						help:
							width === 'full'
								? __(
										'Each picture is cut to this shape, and the row is as tall as the picture — or as its words, if they need more room.',
										'bridge'
									)
								: __(
										'Each picture is cut to this shape.',
										'bridge'
									),
						value: ratio,
						options: [
							{
								label: __('Landscape (16:9)', 'bridge'),
								value: '16-9',
							},
							{
								label: __('Classic (4:3)', 'bridge'),
								value: '4-3',
							},
							{
								label: __('Square (1:1)', 'bridge'),
								value: '1-1',
							},
							{
								label: __('Portrait (4:5)', 'bridge'),
								value: '4-5',
							},
						],
						onChange: (value) => setAttributes({ ratio: value }),
						__nextHasNoMarginBottom: true,
					}),
				setHeight &&
					el(SelectControl, {
						label: __('Minimum height', 'bridge'),
						help: __(
							'The same presets as the Hero Banner. A floor, not a cap — a row whose words need more room grows taller, and the picture is cropped to fill whatever the row comes to.',
							'bridge'
						),
						value: heightPreset,
						options: [
							{
								label: __('Full screen', 'bridge'),
								value: 'full',
							},
							{
								label: __('Tall (80%)', 'bridge'),
								value: 'tall',
							},
							{
								label: __('Medium (60%)', 'bridge'),
								value: 'medium',
							},
							{ label: __('Custom', 'bridge'), value: 'custom' },
						],
						onChange: (value) =>
							setAttributes({ heightPreset: value }),
						__nextHasNoMarginBottom: true,
					}),
				setHeight &&
					heightPreset === 'custom' &&
					el(RangeControl, {
						label: __('Custom height', 'bridge'),
						value: customHeight,
						onChange: (value) =>
							setAttributes({ customHeight: value }),
						min: 20,
						max: customHeightUnit === 'px' ? 1200 : 200,
						step: 1,
						__nextHasNoMarginBottom: true,
					}),
				setHeight &&
					heightPreset === 'custom' &&
					el(SelectControl, {
						label: __('Unit', 'bridge'),
						value: customHeightUnit,
						options: [
							{ label: 'px', value: 'px' },
							{ label: 'vh', value: 'vh' },
							{ label: 'dvh', value: 'dvh' },
						],
						onChange: (value) =>
							setAttributes({ customHeightUnit: value }),
						__nextHasNoMarginBottom: true,
					})
			)
		),
		el('section', blockProps, el('div', innerBlocksProps))
	);
};

export default Edit;
