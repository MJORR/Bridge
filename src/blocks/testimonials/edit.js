/**
 * Editor view for `bridge/testimonials`.
 *
 * The old layout had a "Section Intro" field group behind a show/hide toggle,
 * with its own heading, content and alignment fields. All of that is a heading
 * block and a paragraph block, so that is what the template seeds — a group of
 * fields replaced by blocks the editor already knows, and global styles get to
 * own how they look.
 */

const { useBlockProps, useInnerBlocksProps, InspectorControls } =
	window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, RangeControl, SelectControl } = window.wp.components;
const { __ } = window.wp.i18n;

const ALLOWED_BLOCKS = ['bridge/testimonial', 'core/heading'];

// The intro: a headline, and the quotes. The summary line this used to seed
// under the heading is gone — it was left untouched more often than it was
// written, and an empty paragraph spends a row of the section's gap on
// nothing. A heading is still optional: an editor who wants the section to
// open straight onto its cards deletes it.
const TEMPLATE = [
	[
		'core/heading',
		{
			level: 2,
			textAlign: 'center',
			placeholder: __('What our customers say', 'bridge'),
		},
	],
	['bridge/testimonial', {}],
	['bridge/testimonial', {}],
	['bridge/testimonial', {}],
];

const Edit = ({ attributes, setAttributes }) => {
	const { width, cardStyle, columns, overflowStyle } = attributes;

	const blockProps = useBlockProps({
		className: [
			'bridge-testimonials',
			'bridge-section bridge-band alignfull',
			`bridge-testimonials--${overflowStyle}`,
			`bridge-testimonials--cards-${cardStyle}`,
			`bridge-testimonials--${width}`,
			// Opts the preview out of the sideways-scrolling layout — see the
			// note in _testimonials.scss. Authoring inside a scroll container
			// fights the editor; the front end still swipes.
			'is-editor-preview',
		].join(' '),
		style: { '--columns': columns },
	});

	// One flat list in the editor so cards, intro and outro can be dragged
	// past each other; render.php sorts them into containers by position
	// afterwards. The track is nested inside the inner wrapper in both, so
	// the two classes never land on the same element.
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'bridge-testimonials__track' },
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
				}),
				el(SelectControl, {
					label: __('When they do not fit', 'bridge'),
					help: __(
						'Wrap moves extra cards onto the next line. Swipe keeps them on one line that scrolls sideways.',
						'bridge'
					),
					value: overflowStyle,
					options: [
						{
							label: __('Wrap onto new lines', 'bridge'),
							value: 'wrap',
						},
						{
							label: __('Swipe sideways', 'bridge'),
							value: 'carousel',
						},
					],
					onChange: (value) =>
						setAttributes({ overflowStyle: value }),
				}),
				el(SelectControl, {
					label: __('Card style', 'bridge'),
					value: cardStyle,
					options: [
						{ label: __('Solid', 'bridge'), value: 'solid' },
						{ label: __('Outlined', 'bridge'), value: 'outlined' },
					],
					onChange: (value) => setAttributes({ cardStyle: value }),
				})
			)
		),
		el(
			'section',
			blockProps,
			el(
				'div',
				{ className: 'bridge-testimonials__inner' },
				el('div', innerBlocksProps)
			)
		)
	);
};

export default Edit;
