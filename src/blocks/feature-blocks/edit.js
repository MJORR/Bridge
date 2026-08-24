/**
 * Editor view for `bridge/feature-blocks`.
 */

const { useBlockProps, useInnerBlocksProps, InspectorControls } =
	window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, RangeControl, SelectControl, ToggleControl } =
	window.wp.components;
const { __ } = window.wp.i18n;

const ALLOWED_BLOCKS = [
	'bridge/feature-block',
	'core/heading',
	'core/paragraph',
];

const TEMPLATE = [
	['core/heading', { level: 2, placeholder: __('What we do', 'bridge') }],
	[
		'core/paragraph',
		{ placeholder: __('A line of summary (optional)', 'bridge') },
	],
	['bridge/feature-block', {}],
	['bridge/feature-block', {}],
	['bridge/feature-block', {}],
];

const Edit = ({ attributes, setAttributes }) => {
	const { width, columns, gutters, textAlignment, alignImageTop } =
		attributes;

	const blockProps = useBlockProps({
		className: [
			'bridge-features',
			'bridge-section bridge-band alignfull',
			`bridge-features--${width}`,
			`bridge-features--text-${textAlignment}`,
			gutters ? '' : 'bridge-features--flush',
		]
			.filter(Boolean)
			.join(' '),
		style: { '--columns': columns },
	});

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'bridge-features__list' },
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
				el(RangeControl, {
					label: __('Columns', 'bridge'),
					help: __(
						'Narrow screens use fewer automatically.',
						'bridge'
					),
					value: columns,
					onChange: (value) => setAttributes({ columns: value }),
					min: 1,
					max: 4,
					step: 1,
				}),
				el(ToggleControl, {
					label: __('Space between panels', 'bridge'),
					help: __(
						'Off, the panels sit flush against each other.',
						'bridge'
					),
					checked: !!gutters,
					onChange: (value) => setAttributes({ gutters: value }),
					__nextHasNoMarginBottom: true,
				}),
				el(SelectControl, {
					label: __('Text alignment', 'bridge'),
					value: textAlignment,
					options: [
						{ label: __('Left', 'bridge'), value: 'left' },
						{ label: __('Centre', 'bridge'), value: 'center' },
					],
					onChange: (value) =>
						setAttributes({ textAlignment: value }),
				}),
				el(ToggleControl, {
					label: __('Align background images to the top', 'bridge'),
					help: __(
						'Useful when the subject of the photograph sits near the top of the frame.',
						'bridge'
					),
					checked: !!alignImageTop,
					onChange: (value) =>
						setAttributes({ alignImageTop: value }),
					__nextHasNoMarginBottom: true,
				})
			)
		),
		el(
			'section',
			blockProps,
			el(
				'div',
				{ className: 'bridge-features__inner' },
				el('div', innerBlocksProps)
			)
		)
	);
};

export default Edit;
