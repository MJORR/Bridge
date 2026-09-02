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
	const { width, columns, textAlignment, countUp } = attributes;

	const blockProps = useBlockProps({
		className: [
			'bridge-goals',
			'bridge-section bridge-band alignfull',
			`bridge-goals--${width}`,
			`bridge-goals--text-${textAlignment}`,
		].join(' '),
		style: { '--columns': columns },
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
