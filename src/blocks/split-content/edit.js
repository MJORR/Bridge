/**
 * Editor view for `bridge/split-content`.
 *
 * Two core/group columns, locked to two — the block is a split, and a third
 * column would make it something else. What goes inside each is unrestricted:
 * text, images, and the theme's own bands, which split-content.scss sizes to
 * the column instead of to the window — and either column's heading can be
 * deleted.
 */

const { useBlockProps, useInnerBlocksProps, InspectorControls } =
	window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, SelectControl } = window.wp.components;
const { __ } = window.wp.i18n;

const COLUMN = (placeholder) => [
	'core/group',
	{
		className: 'bridge-split__column',
		templateLock: false,
		layout: { type: 'default' },
	},
	[
		['core/heading', { level: 2, placeholder }],
		['core/paragraph', { placeholder: __('Content', 'bridge') }],
	],
];

const TEMPLATE = [
	COLUMN(__('Left heading', 'bridge')),
	COLUMN(__('Right heading', 'bridge')),
];

const Edit = ({ attributes, setAttributes }) => {
	const { width, ratio } = attributes;

	// Only the modifiers the stylesheet has a rule for. `--wide` and `--even`
	// are the defaults and match nothing, so they were two classes on every
	// section that could never do anything.
	const blockProps = useBlockProps({
		className: [
			'bridge-split',
			'bridge-section',
			'bridge-band',
			'alignfull',
			width === 'narrow' ? 'bridge-split--narrow' : '',
			ratio !== 'even' ? `bridge-split--${ratio}` : '',
		]
			.filter(Boolean)
			.join(' '),
	});

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'bridge-split__inner' },
		{
			allowedBlocks: ['core/group'],
			template: TEMPLATE,
			// Two columns, no more and no fewer — and `insert`, not `all`.
			//
			// Under `all` the editor keeps the inner blocks matched to the
			// template, and it compares *recursively*: the heading and the
			// paragraph seeded into each column are part of the template, so
			// deleting a heading, or adding an image, made the columns stop
			// matching and the editor put them back as they were. `insert`
			// stops columns being added, removed or moved without ever
			// re-syncing, so the template only seeds a new block. The groups
			// carry `templateLock: false` of their own, so inside a column
			// everything stays freely editable.
			templateLock: 'insert',
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
					label: __('Column balance', 'bridge'),
					value: ratio,
					options: [
						{ label: __('Even', 'bridge'), value: 'even' },
						{
							label: __('Wider left', 'bridge'),
							value: 'wide-left',
						},
						{
							label: __('Wider right', 'bridge'),
							value: 'wide-right',
						},
					],
					onChange: (value) => setAttributes({ ratio: value }),
				})
			)
		),
		el('section', blockProps, el('div', innerBlocksProps))
	);
};

export default Edit;
