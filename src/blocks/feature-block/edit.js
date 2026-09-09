/**
 * Editor view for `bridge/feature-block`.
 *
 * The panel draws itself here rather than through the block renderer, so the
 * canvas shows the real thing: the photograph in its frame at the crop Theme
 * Options set, the words in the card's padding, the button where it will be.
 * The one thing the editor cannot know is the ink the wash needs — that is a
 * contrast check PHP does against the palette — so a cover panel previews with
 * the site's Cover ink and settles on the exact value when it renders.
 */

const {
	useBlockProps,
	RichText,
	MediaUpload,
	MediaUploadCheck,
	InspectorControls,
	__experimentalLinkControl: LinkControl,
} = window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const {
	PanelBody,
	BaseControl,
	Button,
	ColorPalette,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} = window.wp.components;
const { useSelect } = window.wp.data;
const { __ } = window.wp.i18n;

const MediaField = ({ label, help, id, url, onSelect, onClear }) =>
	el(
		MediaUploadCheck,
		null,
		el(MediaUpload, {
			allowedTypes: ['image'],
			// So the library opens on the image already chosen.
			value: id,
			onSelect,
			render: ({ open }) =>
				el(
					BaseControl,
					{ help, __nextHasNoMarginBottom: true },
					el(
						'div',
						{ className: 'bridge-media-field' },
						el(
							Button,
							{ variant: 'secondary', onClick: open },
							label
						),
						url &&
							el(
								Button,
								{
									variant: 'link',
									isDestructive: true,
									onClick: onClear,
								},
								__('Remove', 'bridge')
							)
					)
				),
		})
	);

const Edit = ({ attributes, setAttributes }) => {
	const {
		prefix,
		prefixColor,
		title,
		text,
		graphicId,
		graphicUrl,
		backgroundId,
		backgroundUrl,
		overlayColor,
		overlayOpacity,
		linkUrl,
		linkText,
		linkStyle,
		highlight,
	} = attributes;

	// The site's palette, which is the only set of colours a wash may take —
	// custom colours are off in theme.json, so this is the whole choice.
	const themeColors = useSelect((select) => {
		const settings = select('core/block-editor').getSettings();

		return settings.colors || settings.colorPalette || [];
	}, []);

	const slugToHex = (slug) =>
		themeColors.find((color) => color.slug === slug)?.color || undefined;

	// The slug, never the hex: a stored colour has to follow a rebrand, and a
	// hex written into a panel would still be the old brand's blue. Cleared
	// back to '' rather than to a colour, which is what lets both of the
	// controls that use this fall back to their inherited default.
	const hexToSlug = (hex) =>
		themeColors.find((color) => color.color === hex)?.slug || '';

	const isCover = !!backgroundUrl;

	/*
	 * The Summary shape: a photograph on the card rather than instead of it.
	 *
	 * The same test render.php makes, and it has to be made here too or the
	 * canvas draws a panel the page will not. It decides one thing — that the
	 * panel keeps the section's card ground even where the band's panel style
	 * is Plain or Outline — and that is visible, so an editor choosing between
	 * those styles has to be looking at the real answer.
	 */
	const isSummary = !isCover && !!graphicUrl;

	/*
	 * The custom properties the panel carries, gathered rather than written in
	 * one place: the wash belongs to the cover shape and the prefix's colour to
	 * any shape, so they cannot be one conditional object.
	 *
	 * A slug in every case, spent through `var()`. See the note on the wash
	 * above — a hex written onto a panel would survive a rebrand it should not.
	 */
	const style = {};

	if (isCover) {
		if (overlayColor) {
			style['--bridge-feature-wash'] =
				`var(--wp--preset--color--${overlayColor})`;
		}

		style['--bridge-feature-wash-opacity'] = overlayOpacity / 100;
	}

	if (prefixColor) {
		style['--bridge-feature-prefix-ink'] =
			`var(--wp--preset--color--${prefixColor})`;
	}

	const blockProps = useBlockProps({
		className: [
			'bridge-feature',
			isCover ? 'bridge-feature--cover' : '',
			isSummary ? 'bridge-feature--summary' : '',
			linkUrl && linkStyle === 'text' ? 'is-linked' : '',
			highlight ? 'is-highlighted' : '',
		]
			.filter(Boolean)
			.join(' '),
		style: Object.keys(style).length ? style : undefined,
	});

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Panel', 'bridge'), initialOpen: true },
				el(MediaField, {
					label: graphicUrl
						? __('Replace image', 'bridge')
						: __('Choose image', 'bridge'),
					help: __(
						'Sits at the top of the panel, cropped to the card shape set in Theme Options.',
						'bridge'
					),
					id: graphicId,
					url: graphicUrl,
					onSelect: (media) =>
						setAttributes({
							graphicId: media.id,
							// The size the front end renders, not the original:
							// a grid of full-size photographs is a slow editor
							// and a different crop.
							graphicUrl: media.sizes?.large?.url || media.url,
						}),
					onClear: () =>
						setAttributes({ graphicId: undefined, graphicUrl: '' }),
				}),
				el(MediaField, {
					label: backgroundUrl
						? __('Replace background', 'bridge')
						: __('Choose background', 'bridge'),
					help: __(
						'Fills the whole panel and takes the place of the image above, with the words laid over it.',
						'bridge'
					),
					id: backgroundId,
					url: backgroundUrl,
					onSelect: (media) =>
						setAttributes({
							backgroundId: media.id,
							backgroundUrl: media.sizes?.large?.url || media.url,
						}),
					onClear: () =>
						setAttributes({
							backgroundId: undefined,
							backgroundUrl: '',
						}),
				}),
				el(ToggleControl, {
					label: __('Pick this one out', 'bridge'),
					help: __(
						'Tints the panel so it stands out from the others in the set.',
						'bridge'
					),
					checked: !!highlight,
					onChange: (value) => setAttributes({ highlight: value }),
					__nextHasNoMarginBottom: true,
				})
			),
			// Only where there is a line to colour. An editor who has not
			// written one has nothing this control could change, and the same
			// test the wash controls below are gated on.
			prefix &&
				el(
					PanelBody,
					{
						title: __('Above the title', 'bridge'),
						initialOpen: true,
					},
					el(
						BaseControl,
						{
							label: __('Prefix colour', 'bridge'),
							help: __(
								'Left empty it is a quieter shade of whatever the band’s own text colour is, which keeps it readable on a light band, a dark one and over a photograph alike.',
								'bridge'
							),
							__nextHasNoMarginBottom: true,
						},
						el(ColorPalette, {
							colors: themeColors,
							value: slugToHex(prefixColor),
							onChange: (hex) =>
								setAttributes({ prefixColor: hexToSlug(hex) }),
							disableCustomColors: true,
							clearable: true,
						})
					)
				),
			// Only where there is a photograph to lay it over. A wash with no
			// background is two controls that change nothing.
			isCover &&
				el(
					PanelBody,
					{
						title: __('Over the photograph', 'bridge'),
						initialOpen: true,
					},
					el(
						BaseControl,
						{
							label: __('Wash colour', 'bridge'),
							help: __(
								'The colour the gradient rises in. Left empty it takes the one the site’s Cover cards use, and the text colour is chosen for contrast against whichever it ends up being.',
								'bridge'
							),
							__nextHasNoMarginBottom: true,
						},
						el(ColorPalette, {
							colors: themeColors,
							value: slugToHex(overlayColor),
							onChange: (hex) =>
								setAttributes({ overlayColor: hexToSlug(hex) }),
							disableCustomColors: true,
							clearable: true,
						})
					),
					el(RangeControl, {
						label: __('Wash strength', 'bridge'),
						help: __(
							'How much of the photograph the wash takes. Lower for a picture that is already dark.',
							'bridge'
						),
						value: overlayOpacity,
						onChange: (value) =>
							setAttributes({ overlayOpacity: value }),
						min: 0,
						max: 100,
						step: 5,
						__nextHasNoMarginBottom: true,
					})
				),
			el(
				PanelBody,
				{ title: __('Link', 'bridge'), initialOpen: false },
				el(LinkControl, {
					value: { url: linkUrl },
					onChange: (value) =>
						setAttributes({ linkUrl: value.url || '' }),
					settings: [],
				}),
				el(TextControl, {
					label: __('Link text', 'bridge'),
					value: linkText,
					placeholder:
						linkStyle === 'button'
							? __('Find out more', 'bridge')
							: __('Read more', 'bridge'),
					onChange: (value) => setAttributes({ linkText: value }),
					__nextHasNoMarginBottom: true,
				}),
				el(SelectControl, {
					label: __('Show it as', 'bridge'),
					help:
						linkStyle === 'button'
							? __(
									'A button is its own target, so the rest of the panel is not clickable.',
									'bridge'
								)
							: __(
									'A quiet line of text, with the whole panel as the click target.',
									'bridge'
								),
					value: linkStyle,
					options: [
						{ label: __('Button', 'bridge'), value: 'button' },
						{ label: __('Text link', 'bridge'), value: 'text' },
					],
					onChange: (value) => setAttributes({ linkStyle: value }),
				})
			)
		),
		el(
			'li',
			blockProps,
			isCover &&
				el('img', {
					className: 'bridge-feature__background',
					src: backgroundUrl,
					alt: '',
				}),
			isCover &&
				el('div', {
					className: 'bridge-feature__wash',
					'aria-hidden': true,
				}),
			!isCover &&
				graphicUrl &&
				el(
					'figure',
					{ className: 'bridge-feature__media' },
					el('img', { src: graphicUrl, alt: '' })
				),
			el(
				'div',
				{ className: 'bridge-feature__body' },
				el(RichText, {
					tagName: 'span',
					className: 'bridge-feature__prefix',
					value: prefix,
					allowedFormats: [],
					onChange: (value) => setAttributes({ prefix: value }),
					placeholder: __('01', 'bridge'),
				}),
				el(RichText, {
					tagName: 'h3',
					className: 'bridge-feature__title',
					value: title,
					allowedFormats: [],
					onChange: (value) => setAttributes({ title: value }),
					placeholder: __('Feature title', 'bridge'),
				}),
				el(RichText, {
					tagName: 'p',
					className: 'bridge-feature__text',
					value: text,
					allowedFormats: [],
					onChange: (value) => setAttributes({ text: value }),
					placeholder: __('A line about it', 'bridge'),
				}),
				linkUrl &&
					(linkStyle === 'button'
						? el(
								'span',
								{
									className:
										'bridge-feature__button wp-element-button',
								},
								linkText || __('Find out more', 'bridge')
							)
						: el(
								'span',
								{ className: 'bridge-feature__link' },
								linkText || __('Read more', 'bridge')
							))
			)
		)
	);
};

export default Edit;
