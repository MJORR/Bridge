/**
 * Editor view for `bridge/goals`.
 */

const { useBlockProps, useInnerBlocksProps, InspectorControls } =
	window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, SelectControl, ToggleControl } = window.wp.components;
const { __ } = window.wp.i18n;

const ALLOWED_BLOCKS = ['bridge/goal', 'core/heading', 'core/paragraph'];

const TEMPLATE = [
	['core/heading', { level: 2, placeholder: __('Our goals', 'bridge') }],
	[
		'core/paragraph',
		{ placeholder: __('A line of summary (optional)', 'bridge') },
	],
	['bridge/goal', {}],
	['bridge/goal', {}],
	['bridge/goal', {}],
];

const Edit = ({ attributes, setAttributes }) => {
	const {
		width,
		columns,
		textAlignment,
		countUp,
		figureStyle = 'bold',
		labelStyle = 'default',
		dividers = false,
		bottomRule = false,
		spacing = 'default',
	} = attributes;

	// The heading weight, for the Heading figure style. render.php reads the
	// same token; see the inline script beside this block's registration.
	const headingWeight = (window.bridgeGoals || {}).headingWeight;

	const blockProps = useBlockProps({
		className: [
			'bridge-goals',
			'bridge-section bridge-band alignfull',
			`bridge-goals--${width}`,
			`bridge-goals--text-${textAlignment}`,
			'heading' === figureStyle && 'bridge-goals--figure-heading',
			'eyebrow' === labelStyle && 'bridge-goals--label-eyebrow',
			dividers && 'bridge-goals--dividers',
			bottomRule && 'bridge-goals--bottom-rule',
			'compact' === spacing && 'bridge-goals--compact',
		]
			.filter(Boolean)
			.join(' '),
		style: {
			'--columns': columns,
			...('heading' === figureStyle && headingWeight
				? { '--bridge-goal-figure-weight': headingWeight }
				: {}),
		},
	});

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'bridge-goals__list' },
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
					options: [
						{ label: __('Wide', 'bridge'), value: 'wide' },
						{ label: __('Narrow', 'bridge'), value: 'narrow' },
					],
					onChange: (value) => setAttributes({ width: value }),
				}),
				// Three or four, and nothing else. A figure this large in two
				// columns is a headline, and in five it is unreadable on a
				// laptop — so this is a choice between the two counts that
				// work rather than a range that mostly does not.
				el(SelectControl, {
					label: __('Columns', 'bridge'),
					help: __(
						'Narrow screens use fewer automatically.',
						'bridge'
					),
					value: String(columns),
					options: [
						{ label: __('Three', 'bridge'), value: '3' },
						{ label: __('Four', 'bridge'), value: '4' },
					],
					onChange: (value) =>
						setAttributes({ columns: parseInt(value, 10) }),
				}),
				el(SelectControl, {
					label: __('Text alignment', 'bridge'),
					value: textAlignment,
					options: [
						{ label: __('Centre', 'bridge'), value: 'center' },
						{ label: __('Left', 'bridge'), value: 'left' },
					],
					onChange: (value) =>
						setAttributes({ textAlignment: value }),
				})
			),
			el(
				PanelBody,
				{ title: __('Style', 'bridge'), initialOpen: true },
				el(SelectControl, {
					label: __('Figure style', 'bridge'),
					value: figureStyle,
					options: [
						{ label: __('Bold', 'bridge'), value: 'bold' },
						{
							label: __('Heading font', 'bridge'),
							value: 'heading',
						},
					],
					onChange: (value) => setAttributes({ figureStyle: value }),
				}),
				el(SelectControl, {
					label: __('Label style', 'bridge'),
					value: labelStyle,
					options: [
						{ label: __('Default', 'bridge'), value: 'default' },
						{
							label: __('Small capitals', 'bridge'),
							value: 'eyebrow',
						},
					],
					onChange: (value) => setAttributes({ labelStyle: value }),
				}),
				el(ToggleControl, {
					label: __('Dividers between figures', 'bridge'),
					checked: !!dividers,
					onChange: (value) => setAttributes({ dividers: value }),
				}),
				el(ToggleControl, {
					label: __('Line along the bottom', 'bridge'),
					checked: !!bottomRule,
					onChange: (value) => setAttributes({ bottomRule: value }),
				}),
				el(SelectControl, {
					label: __('Spacing', 'bridge'),
					help: __(
						'Compact suits a strip of figures directly under a banner.',
						'bridge'
					),
					value: spacing,
					options: [
						{ label: __('Default', 'bridge'), value: 'default' },
						{ label: __('Compact', 'bridge'), value: 'compact' },
					],
					onChange: (value) => setAttributes({ spacing: value }),
					__nextHasNoMarginBottom: true,
				})
			),
			el(
				PanelBody,
				{ title: __('Animation', 'bridge'), initialOpen: true },
				el(ToggleControl, {
					label: __('Count up on scroll', 'bridge'),
					help: __(
						'Each figure counts from zero when the band first comes into view. A visitor who has asked for reduced motion always sees the final number.',
						'bridge'
					),
					checked: !!countUp,
					onChange: (value) => setAttributes({ countUp: value }),
					__nextHasNoMarginBottom: true,
				})
			)
		),
		el(
			'section',
			blockProps,
			el(
				'div',
				{ className: 'bridge-goals__inner' },
				el('div', innerBlocksProps)
			)
		)
	);
};

export default Edit;
