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

const TEMPLATE = [
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
			width && width !== 'wide' ? `bridge-alternating--${width}` : '',
			// The band insets its own content in this layout, so it opts out
			// of the gutter a skin would give it. Matches render.php.
			width === 'full' ? 'bridge-band--flush' : '',
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
			)
		),
		el('section', blockProps, el('div', innerBlocksProps))
	);
};

export default Edit;
