/**
 * Editor view for `bridge/downloads`.
 */

const { useBlockProps, useInnerBlocksProps, InspectorControls } =
	window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, RangeControl, SelectControl } = window.wp.components;
const { __ } = window.wp.i18n;

// The mask shape's controls and preview styling, shared with every other band
// that offers them. Its own script handle, named in this block's dependencies
// — see src/editor/band-mask.js.
const { MaskPanel, maskStyle, hasMask } = window.bridgeBandMaskUI || {};

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
	const { width, columns, cardStyle } = attributes;

	const blockProps = useBlockProps({
		className: [
			'bridge-downloads',
			'bridge-section',
			'bridge-band',
			'alignfull',
			`bridge-downloads--${width}`,
			`bridge-downloads--${cardStyle}`,
			hasMask(attributes) ? 'has-mask' : '',
		]
			.filter(Boolean)
			.join(' '),
		style: { '--columns': columns, ...maskStyle(attributes) },
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
				el(SelectControl, {
					label: __('Card style', 'bridge'),
					help: __(
						'The same card styles the post cards use, so the two match on a page carrying both. Each takes its heading size and image crop from Theme Options → Cards.',
						'bridge'
					),
					value: cardStyle,
					options: [
						{ label: __('Summary', 'bridge'), value: 'summary' },
						{ label: __('Tile', 'bridge'), value: 'tile' },
					],
					onChange: (value) => setAttributes({ cardStyle: value }),
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
			),
			MaskPanel(attributes, setAttributes)
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
