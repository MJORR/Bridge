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
const { PanelBody, SelectControl, ToggleControl } = window.wp.components;
const { __ } = window.wp.i18n;

const ALLOWED_BLOCKS = [
	'bridge/alternating-row',
	'core/heading',
	'core/paragraph',
];

const TEMPLATE = [
	['core/heading', { level: 2, placeholder: __('How it works', 'bridge') }],
	[
		'core/paragraph',
		{ placeholder: __('A line of summary (optional)', 'bridge') },
	],
	['bridge/alternating-row', {}],
	['bridge/alternating-row', {}],
];

const Edit = ({ attributes, setAttributes }) => {
	const { width, firstImageRight } = attributes;

	const blockProps = useBlockProps({
		className: [
			'bridge-alternating',
			'bridge-section',
			'bridge-band',
			'alignfull',
			width === 'narrow' ? 'bridge-alternating--narrow' : '',
			firstImageRight ? 'is-first-right' : '',
		]
			.filter(Boolean)
			.join(' '),
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
					options: [
						{ label: __('Wide', 'bridge'), value: 'wide' },
						{ label: __('Narrow', 'bridge'), value: 'narrow' },
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
			)
		),
		el('section', blockProps, el('div', innerBlocksProps))
	);
};

export default Edit;
