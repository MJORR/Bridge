/**
 * Editor view for `bridge/call-to-action`.
 *
 * The band's colour is the Styles tab (the theme's section skins), not a
 * control here — so the Inspector holds only what a skin cannot say: the
 * photograph behind the band and how far it is dimmed. The heading, the copy
 * and both buttons are inner blocks, edited with the controls they have
 * everywhere else.
 */

const {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
} = window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, RangeControl, SelectControl, Button } = window.wp.components;
const { __ } = window.wp.i18n;

const TEMPLATE = [
	[
		'core/heading',
		{
			level: 2,
			textAlign: 'center',
			placeholder: __('Ready to start?', 'bridge'),
		},
	],
	[
		'core/paragraph',
		{
			align: 'center',
			placeholder: __('One line on why now is the moment.', 'bridge'),
		},
	],
	// Two buttons: the action, and the one for the reader who is not ready to
	// take it yet. The second wears core's own `outline` style, which
	// theme.json paints — so it is a design-system choice an editor can change
	// or remove, not a second button control on this block.
	[
		'core/buttons',
		{ layout: { type: 'flex', justifyContent: 'center' } },
		[
			['core/button', { text: __('Get in touch', 'bridge') }],
			[
				'core/button',
				{
					text: __('See our work', 'bridge'),
					className: 'is-style-outline',
				},
			],
		],
	],
];

const Edit = ({ attributes, setAttributes }) => {
	const {
		width,
		backgroundImage,
		backgroundImageUrl,
		dimRatio,
		backgroundColor,
		graphicUrl,
	} = attributes;

	// The same question render.php asks, asked the same way: is there an
	// image. The two used to disagree — this read the URL, that read the id.
	const hasImage = !!backgroundImage;

	const blockProps = useBlockProps({
		className: [
			'bridge-cta',
			`bridge-cta--${width}`,
			'bridge-section bridge-band alignfull',
			hasImage ? 'bridge-cta--image' : '',
		]
			.filter(Boolean)
			.join(' '),
		style: hasImage
			? {
					'--bridge-cta-dim': dimRatio / 100,
					'--bridge-cta-image': backgroundImageUrl
						? `url(${backgroundImageUrl})`
						: undefined,
					// The Background colour an editor picks in the Styles tab
					// is invisible under a photograph, so it names the wash
					// over it instead.
					'--bridge-cta-scrim': backgroundColor
						? `var(--wp--preset--color--${backgroundColor})`
						: undefined,
				}
			: undefined,
	});

	// Merged onto the inner wrapper rather than nested, so the editor's tree
	// matches render.php's and one set of selectors is correct in both.
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'bridge-cta__inner' },
		{ template: TEMPLATE, templateLock: false }
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
				})
			),
			el(
				PanelBody,
				{ title: __('Background', 'bridge'), initialOpen: true },
				el(
					MediaUploadCheck,
					null,
					el(MediaUpload, {
						allowedTypes: ['image'],
						value: backgroundImage,
						onSelect: (media) =>
							setAttributes({
								backgroundImage: media.id,
								backgroundImageUrl: media.url,
							}),
						render: ({ open }) =>
							el(
								'div',
								{ className: 'bridge-media-field' },
								el(
									Button,
									{ variant: 'secondary', onClick: open },
									hasImage
										? __('Replace image', 'bridge')
										: __('Choose image', 'bridge')
								),
								hasImage &&
									el(
										Button,
										{
											variant: 'link',
											isDestructive: true,
											onClick: () =>
												setAttributes({
													backgroundImage: undefined,
													backgroundImageUrl: '',
												}),
										},
										__('Remove', 'bridge')
									)
							),
					})
				),
				hasImage &&
					el(RangeControl, {
						label: __('Darken image', 'bridge'),
						help: __(
							'How much to wash the photograph with the Background colour from the Styles tab, so the text over it stays readable.',
							'bridge'
						),
						value: dimRatio,
						onChange: (value) => setAttributes({ dimRatio: value }),
						min: 0,
						max: 100,
						step: 5,
						__nextHasNoMarginBottom: true,
					})
			),
			// Only for bands that still hold one. A graphic is an Image block
			// now, so there is nothing here to choose a new one with — just a
			// way to clear the old one and a note saying where it went.
			!!graphicUrl &&
				el(
					PanelBody,
					{ title: __('Graphic', 'bridge'), initialOpen: false },
					el(
						'p',
						null,
						__(
							'This band holds a graphic saved by an older version of the block. Remove it and add an Image block above the heading instead — it can be linked, captioned and sized like any other image.',
							'bridge'
						)
					),
					el(
						Button,
						{
							variant: 'secondary',
							isDestructive: true,
							onClick: () =>
								setAttributes({
									graphic: undefined,
									graphicUrl: '',
									graphicAlt: '',
								}),
						},
						__('Remove graphic', 'bridge')
					)
				)
		),
		el(
			'section',
			blockProps,
			hasImage &&
				el('div', {
					className: 'bridge-cta__scrim',
					'aria-hidden': true,
				}),
			!!graphicUrl &&
				el('img', {
					className: 'bridge-cta__graphic',
					src: graphicUrl,
					alt: attributes.graphicAlt || '',
				}),
			el('div', innerBlocksProps)
		)
	);
};

export default Edit;
