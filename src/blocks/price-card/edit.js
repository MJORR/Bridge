/**
 * Editor view for `bridge/price-card`.
 */

const {
	useBlockProps,
	RichText,
	MediaUpload,
	MediaUploadCheck,
	InspectorControls,
} = window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const {
	PanelBody,
	Button,
	ToggleControl,
	TextControl,
	TextareaControl,
	SelectControl,
} = window.wp.components;
const { __ } = window.wp.i18n;

const Edit = ({ attributes, setAttributes }) => {
	const {
		title,
		price,
		description,
		imageId,
		imageUrl,
		featured,
		period = '',
		features = '',
		buttonText = '',
		buttonUrl = '',
		buttonStyle = 'fill',
	} = attributes;

	const featureList = features
		.split(/\r?\n/)
		.map((line) => line.trim())
		.filter(Boolean);

	const blockProps = useBlockProps({
		className: 'bridge-price-card' + (featured ? ' is-featured' : ''),
	});

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Card', 'bridge'), initialOpen: true },
				el(ToggleControl, {
					label: __('Highlight this card', 'bridge'),
					help: __(
						'Marks one plan as the recommended one.',
						'bridge'
					),
					checked: !!featured,
					onChange: (value) => setAttributes({ featured: value }),
					__nextHasNoMarginBottom: true,
				}),
				el(
					MediaUploadCheck,
					null,
					el(MediaUpload, {
						allowedTypes: ['image'],
						// So the library opens on the image already chosen.
						value: imageId,
						onSelect: (media) =>
							setAttributes({
								imageId: media.id,
								// The size the front end renders, not the
								// original: a full-size file as a preview is a
								// slow editor and a different crop.
								imageUrl: media.sizes?.medium?.url || media.url,
							}),
						render: ({ open }) =>
							el(
								'div',
								{ className: 'bridge-media-field' },
								el(
									Button,
									{ variant: 'secondary', onClick: open },
									imageUrl
										? __('Replace image', 'bridge')
										: __('Choose image', 'bridge')
								),
								imageUrl &&
									el(
										Button,
										{
											variant: 'link',
											isDestructive: true,
											onClick: () =>
												setAttributes({
													imageId: undefined,
													imageUrl: '',
												}),
										},
										__('Remove', 'bridge')
									)
							),
					})
				)
			),
			el(
				PanelBody,
				{
					title: __('Details and button', 'bridge'),
					initialOpen: true,
				},
				el(TextControl, {
					label: __('After the price', 'bridge'),
					help: __('A short line such as "/ per month".', 'bridge'),
					value: period,
					onChange: (value) => setAttributes({ period: value }),
				}),
				el(TextareaControl, {
					label: __('What is included', 'bridge'),
					help: __('One item per line, shown as a list.', 'bridge'),
					value: features,
					onChange: (value) => setAttributes({ features: value }),
				}),
				el(TextControl, {
					label: __('Button text', 'bridge'),
					value: buttonText,
					onChange: (value) => setAttributes({ buttonText: value }),
				}),
				el(TextControl, {
					label: __('Button link', 'bridge'),
					type: 'url',
					value: buttonUrl,
					onChange: (value) => setAttributes({ buttonUrl: value }),
				}),
				el(SelectControl, {
					label: __('Button style', 'bridge'),
					value: buttonStyle,
					options: [
						{ label: __('Filled', 'bridge'), value: 'fill' },
						{ label: __('Outline', 'bridge'), value: 'outline' },
					],
					onChange: (value) => setAttributes({ buttonStyle: value }),
					__nextHasNoMarginBottom: true,
				})
			)
		),
		el(
			'li',
			blockProps,
			imageUrl &&
				el('img', {
					className: 'bridge-price-card__image',
					src: imageUrl,
					alt: '',
				}),
			el(RichText, {
				tagName: 'p',
				className: 'bridge-price-card__title',
				value: title,
				allowedFormats: [],
				onChange: (value) => setAttributes({ title: value }),
				placeholder: __('Plan name', 'bridge'),
			}),
			el(RichText, {
				tagName: 'p',
				className: 'bridge-price-card__price',
				value: price,
				allowedFormats: [],
				onChange: (value) => setAttributes({ price: value }),
				placeholder: __('£0', 'bridge'),
			}),
			period &&
				el(
					'p',
					{ className: 'bridge-price-card__period-preview' },
					period
				),
			el(RichText, {
				tagName: 'p',
				className: 'bridge-price-card__description',
				value: description,
				allowedFormats: [],
				onChange: (value) => setAttributes({ description: value }),
				placeholder: __('What is included', 'bridge'),
			}),
			featureList.length > 0 &&
				el(
					'ul',
					{ className: 'bridge-price-card__features', role: 'list' },
					featureList.map((line, i) => el('li', { key: i }, line))
				),
			buttonText &&
				buttonUrl &&
				el(
					'span',
					{
						className:
							'bridge-price-card__button wp-element-button' +
							('outline' === buttonStyle
								? ' is-style-outline'
								: ''),
					},
					buttonText
				)
		)
	);
};

export default Edit;
