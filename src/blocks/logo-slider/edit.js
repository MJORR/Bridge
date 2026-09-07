/**
 * Editor view for `bridge/logo-slider`.
 *
 * Logos carry no per-item content — no title, no link, nothing to write — so
 * they stay an attribute picked from the media library rather than becoming a
 * child block each.
 *
 * The Inspector lists the chosen logos one per line: add one, remove one,
 * nudge one along the row. The media frame's gallery mode used to do the
 * ordering, but it asks the editor to build a gallery on the way to choosing
 * pictures, and every trip through it replaced the whole set — so removing a
 * single logo meant re-picking the rest.
 *
 * Above the logos there is one optional label. It used to be nested blocks —
 * a heading, and whatever an editor added beside it — which made the top of
 * the band a different shape on every page. A logo band does not need a title
 * and a standfirst to say what it is, and most of the time it does not need
 * anything: the field starts empty, and a band with nothing typed into it
 * prints no label at all. Its size and alignment are set from the toolbar,
 * over the label itself, rather than from a panel down the side of the
 * screen. Blocks saved under the old shape are carried over by deprecated.js.
 */

const {
	useBlockProps,
	RichText,
	BlockControls,
	AlignmentControl,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
} = window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const {
	PanelBody,
	SelectControl,
	ToggleControl,
	RangeControl,
	ToolbarGroup,
	ToolbarDropdownMenu,
	Button,
} = window.wp.components;
const { __ } = window.wp.i18n;

/**
 * Where the two sliders sit before anyone touches them.
 *
 * Zero is what the attributes hold until an editor moves a slider, and it
 * means "whatever the stylesheet says" — so the control has to be told what
 * that is, or it would open at its minimum and read as a row that had been set
 * tiny. These are the values in src/scss/blocks/_logo-slider.scss: one height,
 * and a gap that depends on the fit, because logos left at their own widths
 * need more air between them than logos cropped to a tile.
 */
/**
 * What the label can be set to, largest decision first: the letter is what the
 * toolbar shows, and the value is the class render.php prints and the
 * stylesheet answers.
 */
const LABEL_SIZES = [
	{ value: 'small', abbr: __('S', 'bridge'), title: __('Small', 'bridge') },
	{ value: 'medium', abbr: __('M', 'bridge'), title: __('Medium', 'bridge') },
	{ value: 'large', abbr: __('L', 'bridge'), title: __('Large', 'bridge') },
	{
		value: 'x-large',
		abbr: __('XL', 'bridge'),
		title: __('Extra large', 'bridge'),
	},
];

const DEFAULT_SIZE = 3;
const DEFAULT_SPACE = { contain: 2.25, cover: 1.5 };
const DEFAULT_SPEED = 1;

// Only the id is rendered — render.php looks the file up again — so the url is
// the editor's preview and nothing else. `medium` is what the front end draws,
// and a row of full-size logos is a slow editor for no gain.
const toImage = (item) => ({
	id: item.id,
	url: item.sizes?.medium?.url || item.url,
	name: item.title || item.filename || '',
});

// The row of logos, one per line, each with its own controls.
const RowPicker = ({ addLabel, images, onChange }) => {
	const removeAt = (index) =>
		onChange(images.filter((image, at) => at !== index));

	const moveBy = (index, step) => {
		const next = images.slice();

		next.splice(index + step, 0, next.splice(index, 1)[0]);
		onChange(next);
	};

	return el(
		'div',
		{ className: 'bridge-logo-list' },
		images.length > 0 &&
			el(
				'ul',
				{ className: 'bridge-logo-list__items' },
				images.map((image, index) =>
					el(
						'li',
						{
							className: 'bridge-logo-list__item',
							// The same logo may legitimately appear twice in a
							// row, so the id alone is not a key.
							key: `${image.id}-${index}`,
						},
						el('img', {
							className: 'bridge-logo-list__thumb',
							src: image.url,
							alt: '',
						}),
						el(
							'span',
							{ className: 'bridge-logo-list__name' },
							image.name || __('Logo', 'bridge')
						),
						el(
							'div',
							{ className: 'bridge-logo-list__actions' },
							el(Button, {
								icon: 'arrow-up-alt2',
								size: 'small',
								label: __('Move logo earlier', 'bridge'),
								disabled: 0 === index,
								onClick: () => moveBy(index, -1),
							}),
							el(Button, {
								icon: 'arrow-down-alt2',
								size: 'small',
								label: __('Move logo later', 'bridge'),
								disabled: index === images.length - 1,
								onClick: () => moveBy(index, 1),
							}),
							el(Button, {
								icon: 'no-alt',
								size: 'small',
								isDestructive: true,
								label: __('Remove logo', 'bridge'),
								onClick: () => removeAt(index),
							})
						)
					)
				)
			),
		el(
			MediaUploadCheck,
			null,
			el(MediaUpload, {
				// Pick as many as you like in one visit, but nothing is
				// preselected and `gallery` stays off: this picker adds to the
				// row, it does not stand in for the row. Gallery mode would
				// hand back the whole selection instead, which is what made
				// removing a single logo impossible.
				multiple: true,
				gallery: false,
				addToGallery: false,
				allowedTypes: ['image'],
				title: __('Add logos', 'bridge'),
				onSelect: (media) =>
					onChange([
						...images,
						...(Array.isArray(media) ? media : [media]).map(
							toImage
						),
					]),
				render: ({ open }) =>
					el(
						'div',
						{ className: 'bridge-media-field' },
						el(
							Button,
							{ variant: 'secondary', onClick: open },
							addLabel
						)
					),
			})
		)
	);
};

const Edit = ({ attributes, setAttributes }) => {
	const {
		label,
		labelAlign,
		labelSize,
		width,
		imageDisplay,
		images,
		secondRow,
		imagesSecond,
		logoSize,
		logoSpace,
		speed,
	} = attributes;

	// Anything that is not `full` is `wide` — the block used to offer `narrow`
	// too, and a block still carrying it should draw as a width that exists.
	const rowWidth = 'full' === width ? 'full' : 'wide';

	// The sets the toolbar offers, and the same fallbacks render.php makes: a
	// value nobody recognises draws as the block's default rather than as a
	// label with no alignment or no size at all.
	const align = ['left', 'center', 'right'].includes(labelAlign)
		? labelAlign
		: 'left';
	const size =
		LABEL_SIZES.find(({ value }) => value === labelSize) || LABEL_SIZES[1];

	// The same two properties render.php prints, so the preview is the page.
	// An unset slider prints nothing and the stylesheet answers instead.
	const style = {};

	// The plain size, where render.php prints a clamp that falls away on a
	// narrow screen. What a slider holds is the desktop size, and the editor's
	// canvas is a desktop — so this is that size, and the curve it travels
	// down stays in one place, which is bridge_fluid_clamp() in PHP.
	if (logoSize > 0) {
		style['--bridge-logo-size'] = `${logoSize}rem`;
	}

	if (logoSpace > 0) {
		style['--bridge-logo-space'] = `${logoSpace}rem`;
	}

	if (speed > 0) {
		style['--bridge-logo-speed'] = String(speed);
	}

	const blockProps = useBlockProps({
		className: `bridge-logos bridge-section bridge-band alignfull bridge-logos--${rowWidth}`,
		style,
	});

	const preview = (row, key) =>
		el(
			'ul',
			{ className: 'bridge-logos__track', key },
			row.map((image, index) =>
				el(
					'li',
					{
						className: 'bridge-logos__item',
						key: `${image.id}-${index}`,
					},
					el('img', {
						className: `bridge-logos__image is-${imageDisplay}`,
						src: image.url,
						alt: '',
					})
				)
			)
		);

	return el(
		Fragment,
		null,
		el(
			BlockControls,
			{ group: 'block' },
			el(AlignmentControl, {
				value: align,
				// Pressing the active button again clears it, which would
				// leave the label with no alignment at all — so that reads as
				// a return to the left the block starts at.
				onChange: (value) =>
					setAttributes({ labelAlign: value || 'left' }),
			}),
			el(
				ToolbarGroup,
				null,
				el(ToolbarDropdownMenu, {
					icon: 'editor-textcolor',
					label: __('Text size', 'bridge'),
					// The current size sits in the toolbar as its own letter,
					// so the button says what it is set to rather than only
					// what it does.
					text: size.abbr,
					controls: LABEL_SIZES.map((option) => ({
						title: option.title,
						isActive: option.value === size.value,
						onClick: () =>
							setAttributes({ labelSize: option.value }),
					})),
				})
			)
		),
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Logos', 'bridge'), initialOpen: true },
				el(RowPicker, {
					addLabel: __('Add logos', 'bridge'),
					images,
					onChange: (value) => setAttributes({ images: value }),
				}),
				el(SelectControl, {
					label: __('Width', 'bridge'),
					help: __(
						'Full width runs the logos to the edges of the window. The heading stays in the content column either way.',
						'bridge'
					),
					value: rowWidth,
					options: [
						{ label: __('Full width', 'bridge'), value: 'full' },
						{ label: __('Wide', 'bridge'), value: 'wide' },
					],
					onChange: (value) => setAttributes({ width: value }),
				}),
				el(SelectControl, {
					label: __('Image fit', 'bridge'),
					help: __(
						'Contain shows the whole logo. Cover fills the space and crops.',
						'bridge'
					),
					value: imageDisplay,
					options: [
						{ label: __('Contain', 'bridge'), value: 'contain' },
						{ label: __('Cover', 'bridge'), value: 'cover' },
					],
					onChange: (value) => setAttributes({ imageDisplay: value }),
				}),
				el(RangeControl, {
					label: __('Logo size', 'bridge'),
					help: __(
						'The height every logo is drawn at on a desktop. Logos scale down a little on narrow screens.',
						'bridge'
					),
					// The slider always shows a real number, whether it is the
					// editor's or the stylesheet's. Reset hands back
					// `undefined`, which is stored as the zero that means
					// "unset" rather than as a size of nothing.
					value: logoSize || DEFAULT_SIZE,
					min: 1.5,
					max: 10,
					step: 0.25,
					allowReset: true,
					resetFallbackValue: undefined,
					onChange: (value) =>
						setAttributes({ logoSize: value || 0 }),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				}),
				el(RangeControl, {
					label: __('Space between logos', 'bridge'),
					help: __(
						'Applies to both rows, and narrows with the window like the logos do. Wider spacing keeps logos reading as separate marks rather than one strip.',
						'bridge'
					),
					// The minimum is a quarter rem rather than none: nought is
					// the value that means the slider was never moved, and
					// logos touching edge to edge is not the gap anybody is
					// reaching for on the way past it.
					value:
						logoSpace ||
						DEFAULT_SPACE[imageDisplay] ||
						DEFAULT_SPACE.contain,
					min: 0.25,
					max: 8,
					step: 0.25,
					allowReset: true,
					resetFallbackValue: undefined,
					onChange: (value) =>
						setAttributes({ logoSpace: value || 0 }),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				}),
				el(RangeControl, {
					label: __('Speed', 'bridge'),
					help: __(
						'How fast the logos travel. A row with more logos in it still takes longer to come round, so adding one makes the row longer rather than quicker.',
						'bridge'
					),
					value: speed || DEFAULT_SPEED,
					min: 0.25,
					max: 4,
					step: 0.25,
					allowReset: true,
					resetFallbackValue: undefined,
					onChange: (value) => setAttributes({ speed: value || 0 }),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				}),
				el(ToggleControl, {
					label: __('Add a second row', 'bridge'),
					help: __('The second row travels the other way.', 'bridge'),
					checked: !!secondRow,
					onChange: (value) => setAttributes({ secondRow: value }),
					__nextHasNoMarginBottom: true,
				}),
				secondRow &&
					el(RowPicker, {
						addLabel: __('Add second-row logos', 'bridge'),
						images: imagesSecond,
						onChange: (value) =>
							setAttributes({ imagesSecond: value }),
					})
			)
		),
		el(
			'section',
			blockProps,
			el(
				'div',
				{ className: 'bridge-logos__inner' },
				el(
					'div',
					{ className: 'bridge-logos__intro bridge-section__intro' },
					el(RichText, {
						tagName: 'p',
						className: `bridge-logos__label is-align-${align} is-size-${size.value}`,
						value: label,
						// No formatting. The label is set in one size and one
						// weight by the stylesheet, and a bold word inside
						// something already at 600 is a difference nobody can
						// see.
						allowedFormats: [],
						disableLineBreaks: true,
						onChange: (value) => setAttributes({ label: value }),
						// What the field is for rather than an example of
						// what goes in it: the band is as likely to be headed
						// "Our clients" as "As seen in", and a placeholder
						// that names one of them reads as a suggestion to use
						// it.
						placeholder: __('Title or Caption', 'bridge'),
					})
				),
				// Static in the editor: a row sliding under the cursor makes
				// the block hard to select and the intro hard to click into.
				el(
					'div',
					{
						className: `bridge-logos__row is-static is-${imageDisplay}`,
					},
					preview(images, 'first')
				),
				secondRow &&
					imagesSecond.length > 0 &&
					el(
						'div',
						{
							className: `bridge-logos__row is-static is-${imageDisplay}`,
						},
						preview(imagesSecond, 'second')
					)
			)
		)
	);
};

export default Edit;
