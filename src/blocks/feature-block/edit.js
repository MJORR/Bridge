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

/*
 * The arrow-in-a-circle, drawn here as the same geometry the front end takes
 * from the icon library (src/icons/arrow-circle-right.svg). `currentColor`,
 * so it is whatever colour the panel's text is.
 */
const ARROW = el(
	'span',
	{ className: 'bridge-feature__arrow', 'aria-hidden': true },
	el(
		'svg',
		{
			className: 'bridge-icon bridge-icon--large',
			viewBox: '0 0 24 24',
			width: '2.25em',
			height: '2.25em',
			fill: 'none',
			stroke: 'currentColor',
			strokeWidth: 1.6,
			strokeLinecap: 'round',
			strokeLinejoin: 'round',
			focusable: 'false',
		},
		el('circle', { cx: 12, cy: 12, r: 10 }),
		el('path', { d: 'M7.5 12h9' }),
		el('path', { d: 'm12.5 8 4 4-4 4' })
	)
);

const Edit = ({ attributes, setAttributes, context, clientId }) => {
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
		cardBackground,
		cardText,
	} = attributes;

	// The number line over the title is only drawn when the band asks for it,
	// and never on the numbered layout, which has badges of its own.
	const showPrefix =
		!!context?.['bridge/featureNumbers'] &&
		context?.['bridge/featureLayout'] !== 'numbered';

	// This panel's position among its sibling panels — the intro heading and
	// paragraph share the list in the editor, so they are not counted.
	const position = useSelect(
		(select) => {
			const store = select('core/block-editor');
			const root = store.getBlockRootClientId(clientId);
			const siblings = store
				.getBlocks(root)
				.filter((block) => block.name === 'bridge/feature-block');

			return (
				siblings.findIndex((block) => block.clientId === clientId) + 1
			);
		},
		[clientId]
	);
	// The arrow at the foot of the panel, switched on for the whole band.
	const showArrow = !!context?.['bridge/featureArrow'];
	// The band's call: the panel is the link, so there is no cue to word or
	// style — only the address is asked for.
	const panelLink = !!context?.['bridge/featurePanelLink'];

	const autoNumber = String(Math.max(1, position)).padStart(2, '0');

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

	// Per-panel colours. The ground is not offered on a cover panel — the
	// photograph is the ground there — but the text colour is.
	if (cardBackground && !isCover) {
		style['--bridge-feature-bg'] =
			`var(--wp--preset--color--${cardBackground})`;
	}

	if (cardText) {
		style['--bridge-feature-text'] =
			`var(--wp--preset--color--${cardText})`;
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
			linkUrl ? 'is-linked' : '',
			highlight ? 'is-highlighted' : '',
			cardBackground && !isCover ? 'has-card-background' : '',
			cardText ? 'has-card-text' : '',
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
			el(
				PanelBody,
				{ title: __('Colours', 'bridge'), initialOpen: true },
				!isCover &&
					el(
						BaseControl,
						{
							label: __('Background colour', 'bridge'),
							help: __(
								'Left empty the panel uses the card colour set in Theme Options.',
								'bridge'
							),
							__nextHasNoMarginBottom: true,
						},
						el(ColorPalette, {
							colors: themeColors,
							value: slugToHex(cardBackground),
							onChange: (hex) =>
								setAttributes({
									cardBackground: hexToSlug(hex),
								}),
							disableCustomColors: true,
							clearable: true,
						})
					),
				el(
					BaseControl,
					{
						label: __('Text colour', 'bridge'),
						help: isCover
							? __(
									'Left empty it is chosen for contrast against the wash.',
									'bridge'
								)
							: __(
									'Left empty it follows the band’s text colour.',
									'bridge'
								),
						__nextHasNoMarginBottom: true,
					},
					el(ColorPalette, {
						colors: themeColors,
						value: slugToHex(cardText),
						onChange: (hex) =>
							setAttributes({ cardText: hexToSlug(hex) }),
						disableCustomColors: true,
						clearable: true,
					})
				)
			),
			// Only when the band has step numbers switched on.
			showPrefix &&
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
				panelLink &&
					el(
						'p',
						{ className: 'components-base-control__help' },
						__(
							'The whole panel links here. Turn off “Make panel the link” on the Feature Blocks band to show a button or text link instead.',
							'bridge'
						)
					),
				!panelLink &&
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
				!panelLink &&
					el(SelectControl, {
						label: __('Show it as', 'bridge'),
						help: __(
							'Either way the whole panel is clickable on the page.',
							'bridge'
						),
						value: linkStyle,
						options: [
							{ label: __('Button', 'bridge'), value: 'button' },
							{ label: __('Text link', 'bridge'), value: 'text' },
						],
						onChange: (value) =>
							setAttributes({ linkStyle: value }),
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
				showPrefix &&
					el(RichText, {
						tagName: 'span',
						className: 'bridge-feature__prefix',
						value: prefix,
						allowedFormats: [],
						onChange: (value) => setAttributes({ prefix: value }),
						// Its position, which is what the page prints when the
						// field is left empty.
						placeholder: autoNumber,
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
					!panelLink &&
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
							)),
				showArrow && ARROW
			)
		)
	);
};

export default Edit;
