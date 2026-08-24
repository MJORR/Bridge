/**
 * Editor view for `bridge/price-table`.
 */

const { useBlockProps, useInnerBlocksProps, InspectorControls } =
	window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, RangeControl, SelectControl } = window.wp.components;
const { __ } = window.wp.i18n;

const ALLOWED_BLOCKS = [
	'bridge/price-card',
	'core/heading',
	'core/paragraph',
	'core/buttons',
];

const TEMPLATE = [
	[
		'core/heading',
		{ level: 2, textAlign: 'center', placeholder: __('Pricing', 'bridge') },
	],
	[
		'core/paragraph',
		{
			align: 'center',
			placeholder: __('A line of summary (optional)', 'bridge'),
		},
	],
	['bridge/price-card', {}],
	['bridge/price-card', {}],
	['bridge/price-card', {}],
];

const Edit = ({ attributes, setAttributes }) => {
	const { width, columns } = attributes;

	const blockProps = useBlockProps({
		className: `bridge-price-table bridge-section bridge-band alignfull bridge-price-table--${width}`,
		style: { '--columns': columns },
	});

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'bridge-price-table__list' },
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
				})
			)
		),
		el(
			'section',
			blockProps,
			el(
				'div',
				{ className: 'bridge-price-table__inner' },
				el('div', innerBlocksProps)
			)
		)
	);
};

export default Edit;
