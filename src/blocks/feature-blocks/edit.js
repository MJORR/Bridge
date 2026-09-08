/**
 * Editor view for `bridge/feature-blocks`.
 *
 * Everything the inspector offers is drawn here as well as on the front end —
 * the panel style, the graphic size, the numbering, the alignment. A setting
 * whose effect only appears once the page is saved is a setting an editor
 * cannot use, which is what the "align images to the top" toggle was before
 * the panel picked it up from block context.
 *
 * The swipe layout is the one exception, and deliberately: the preview wears
 * `is-editor-preview`, which opts it out of the sideways-scrolling track.
 * Authoring inside a scroll container fights the editor — a panel dragged to
 * the end of the row scrolls away from the pointer holding it.
 *
 * Three controls that used to be here are not any more — the graphic size, the
 * hover effect and the background crop. All three are card settings, and cards
 * are set once for the whole site in Theme Options: a band that answered them
 * again locally was a second design system, and the panels drifted away from
 * the cards on the same page. What is left here is what is genuinely this
 * band's: how many panels, how they are arranged, and what the panels stand on.
 */

const { useBlockProps, useInnerBlocksProps, InspectorControls } =
	window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, RangeControl, SelectControl, ToggleControl } =
	window.wp.components;
const { __ } = window.wp.i18n;

// The mask shape's controls and preview styling, shared with every other band
// that offers them. Its own script handle, named in this block's dependencies
// — see src/editor/band-mask.js.
const { MaskPanel, maskStyle, hasMask } = window.bridgeBandMaskUI || {};

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
	const {
		width,
		layout,
		columns,
		gutters,
		overflowStyle,
		panelStyle,
		textAlignment,
	} = attributes;

	const isNumbered = layout === 'numbered';

	const blockProps = useBlockProps({
		className: [
			'bridge-features',
			'bridge-section bridge-band alignfull',
			`bridge-features--${width}`,
			`bridge-features--${layout}`,
			`bridge-features--text-${textAlignment}`,
			`bridge-features--panels-${panelStyle}`,
			// The swipe row's class is still worn so the preview looks like
			// the setting is on; `is-editor-preview` is what holds the
			// scrolling back. render.php refuses the pairing outright on a
			// numbered run, and so does this.
			overflowStyle === 'carousel' && !isNumbered
				? 'bridge-features--carousel'
				: '',
			gutters || isNumbered ? '' : 'bridge-features--flush',
			hasMask(attributes) ? 'has-mask' : '',
			'is-editor-preview',
		]
			.filter(Boolean)
			.join(' '),
		style: { '--columns': columns, ...maskStyle(attributes) },
	});

	// The same element render.php prints, for the same reason: a numbered run
	// is an ordered list, and the counter that draws the badges is defined on
	// it. Rendering a <ul> here and an <ol> there would leave the editor's
	// panels unnumbered.
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
					label: __('Arrangement', 'bridge'),
					help: isNumbered
						? __(
								'Steps are numbered by their position, so reordering them renumbers them.',
								'bridge'
							)
						: __(
								'Panels sit side by side, wrapping onto new lines as they run out of room.',
								'bridge'
							),
					value: layout,
					options: [
						{
							label: __('Grid of panels', 'bridge'),
							value: 'grid',
						},
						{
							label: __('Numbered steps', 'bridge'),
							value: 'numbered',
						},
					],
					onChange: (value) => setAttributes({ layout: value }),
				}),
				el(SelectControl, {
					label: __('Width', 'bridge'),
					value: width,
					options: [
						{ label: __('Wide', 'bridge'), value: 'wide' },
						{ label: __('Narrow', 'bridge'), value: 'narrow' },
					],
					onChange: (value) => setAttributes({ width: value }),
				}),
				// A numbered run is a column read top to bottom, so the three
				// settings that only describe a row are not offered against
				// it. Hidden rather than disabled: a control that is visible
				// but inert invites the editor to work out why.
				!isNumbered &&
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
				!isNumbered &&
					el(SelectControl, {
						label: __('When they do not fit', 'bridge'),
						help: __(
							'Wrap moves extra panels onto the next line. Swipe keeps them on one line that scrolls sideways.',
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
				!isNumbered &&
					el(ToggleControl, {
						label: __('Space between panels', 'bridge'),
						help: __(
							'Off, the panels sit flush against each other.',
							'bridge'
						),
						checked: !!gutters,
						onChange: (value) => setAttributes({ gutters: value }),
						__nextHasNoMarginBottom: true,
					})
			),
			el(
				PanelBody,
				{ title: __('Panels', 'bridge'), initialOpen: false },
				el(SelectControl, {
					label: __('Panel style', 'bridge'),
					help: __(
						'Card draws the surface the rest of the site uses. Plain and Outlined let the band show through.',
						'bridge'
					),
					value: panelStyle,
					options: [
						{ label: __('Card', 'bridge'), value: 'card' },
						{ label: __('Plain', 'bridge'), value: 'plain' },
						{ label: __('Outlined', 'bridge'), value: 'outline' },
					],
					onChange: (value) => setAttributes({ panelStyle: value }),
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
				})
			),
			MaskPanel(attributes, setAttributes)
		),
		el(
			'section',
			blockProps,
			el(
				'div',
				{ className: 'bridge-features__inner' },
				el(isNumbered ? 'ol' : 'ul', innerBlocksProps)
			)
		)
	);
};

export default Edit;
