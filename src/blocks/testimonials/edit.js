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
const { PanelBody, RangeControl, SelectControl, BaseControl, ColorPalette } =
	window.wp.components;
const { useSelect } = window.wp.data;
const { __ } = window.wp.i18n;

// The mask shape's controls and preview styling, shared with every other band
// that offers it — see src/editor/band-mask.js.
const { MaskPanel, maskStyle, hasMask } = window.bridgeBandMaskUI || {};

// Paragraphs added later, for an eyebrow line or a summary in the intro.
// Existing bands are unaffected: allowing a block adds nothing to one.
const ALLOWED_BLOCKS = ['bridge/testimonial', 'core/heading', 'core/paragraph'];

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
	const {
		width,
		cardStyle,
		columns,
		overflowStyle,
		cardBackground = '',
		cardLayout = 'standard',
		starColor = '',
	} = attributes;

	// The site's palette, so the card colour is stored as a slug.
	const themeColors = useSelect((select) => {
		const settings = select('core/block-editor').getSettings();

		return settings.colors || settings.colorPalette || [];
	}, []);
	const cardHex =
		cardStyle !== 'outlined' && cardBackground
			? themeColors.find((c) => c.slug === cardBackground)?.color
			: undefined;
	// The same light-or-dark question render.php asks, for the preview.
	const isLight = (hex) => {
		const v = (hex || '').replace('#', '');
		if (v.length < 6) {
			return true;
		}
		const [r, g, b] = [0, 2, 4].map((i) => parseInt(v.slice(i, i + 2), 16));

		return 0.299 * r + 0.587 * g + 0.114 * b > 0.6 * 255;
	};

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
			hasMask(attributes) ? 'has-mask' : '',
			cardHex ? 'bridge-testimonials--card-bg' : '',
			cardLayout === 'review' ? 'bridge-testimonials--layout-review' : '',
			starColor ? 'bridge-testimonials--star-color' : '',
		]
			.filter(Boolean)
			.join(' '),
		style: {
			'--columns': columns,
			...(cardHex
				? {
						'--bridge-testimonials-card-bg': `var(--wp--preset--color--${cardBackground})`,
						'--bridge-testimonials-card-text': isLight(cardHex)
							? 'var(--wp--preset--color--text, #1f2937)'
							: 'var(--wp--preset--color--background, #fff)',
					}
				: {}),
			...(starColor
				? {
						'--bridge-testimonials-star': `var(--wp--preset--color--${starColor})`,
					}
				: {}),
			...maskStyle(attributes),
		},
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
				}),
				cardStyle !== 'outlined' &&
					el(
						BaseControl,
						{
							label: __('Card background', 'bridge'),
							help: __(
								"Clear it to use the band's own card colour. The text colour is chosen to read on it.",
								'bridge'
							),
							__nextHasNoMarginBottom: true,
						},
						el(ColorPalette, {
							colors: themeColors,
							value: cardHex,
							onChange: (hex) =>
								setAttributes({
									cardBackground:
										themeColors.find((c) => c.color === hex)
											?.slug || '',
								}),
							disableCustomColors: true,
							clearable: true,
						})
					),
				el(
					BaseControl,
					{
						label: __('Star colour', 'bridge'),
						help: __(
							'Clear it to use the Accent colour.',
							'bridge'
						),
						__nextHasNoMarginBottom: true,
					},
					el(ColorPalette, {
						colors: themeColors,
						value: themeColors.find((c) => c.slug === starColor)
							?.color,
						onChange: (hex) =>
							setAttributes({
								starColor:
									themeColors.find((c) => c.color === hex)
										?.slug || '',
							}),
						disableCustomColors: true,
						clearable: true,
					})
				),
				el(SelectControl, {
					label: __('Card layout', 'bridge'),
					help: __(
						'Review puts the name at the top, and the stars and source along the bottom.',
						'bridge'
					),
					value: cardLayout,
					options: [
						{ label: __('Standard', 'bridge'), value: 'standard' },
						{ label: __('Review', 'bridge'), value: 'review' },
					],
					onChange: (value) => setAttributes({ cardLayout: value }),
				})
			),
			MaskPanel(attributes, setAttributes)
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
