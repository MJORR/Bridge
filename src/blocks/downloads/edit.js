/**
 * Editor view for `bridge/downloads`.
 */

const { useBlockProps, useInnerBlocksProps, InspectorControls } =
	window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, RangeControl, SelectControl } = window.wp.components;
const { __ } = window.wp.i18n;

const ALLOWED_BLOCKS = [
	'bridge/download-item',
	'core/heading',
	'core/paragraph',
];

const TEMPLATE = [
	['core/heading', { level: 2, placeholder: __('Downloads', 'bridge') }],
	[
		'core/paragraph',
		{ placeholder: __('A line of summary (optional)', 'bridge') },
	],
	['bridge/download-item', {}],
];

const Edit = ({ attributes, setAttributes }) => {
	const { width, columns } = attributes;

	const blockProps = useBlockProps({
		className: `bridge-downloads bridge-section bridge-band alignfull bridge-downloads--${width}`,
		style: { '--columns': columns },
	});

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'bridge-downloads__list' },
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
						'The most columns to show. Narrow screens use fewer automatically.',
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
				{ className: 'bridge-downloads__inner' },
				el('div', innerBlocksProps)
			)
		)
	);
};

export default Edit;
