/**
 * Editor view for `bridge/gallery-item`.
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
const { PanelBody, Button, TextControl } = window.wp.components;
const { __ } = window.wp.i18n;

const Edit = ({ attributes, setAttributes }) => {
	const { imageId, imageUrl, alt, label, linkUrl } = attributes;

	const blockProps = useBlockProps({ className: 'bridge-gallery__item' });

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Image', 'bridge'), initialOpen: true },
				el(
					MediaUploadCheck,
					null,
					el(MediaUpload, {
						allowedTypes: ['image'],
						// So the library opens on the image this item already
						// holds rather than making the editor find it again.
						value: imageId,
						onSelect: (media) =>
							setAttributes({
								imageId: media.id,
								// The size the front end renders, not the
								// original: a 4000px file as a preview is a
								// slow editor and a different crop.
								imageUrl: media.sizes?.large?.url || media.url,
								alt: media.alt || '',
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
								!!imageUrl &&
									el(
										Button,
										{
											variant: 'link',
											isDestructive: true,
											onClick: () =>
												setAttributes({
													imageId: undefined,
													imageUrl: '',
													alt: '',
												}),
										},
										__('Remove', 'bridge')
									)
							),
					})
				),
				el(TextControl, {
					label: __('Alt text', 'bridge'),
					help: __(
						'Describes the image. Leave empty if it is purely decorative.',
						'bridge'
					),
					value: alt,
					onChange: (value) => setAttributes({ alt: value }),
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
				})
			)
		),
		el(
			'li',
			blockProps,
			imageUrl
				? el('img', {
						className: 'bridge-gallery__image',
						src: imageUrl,
						alt: alt || '',
					})
				: el(
						'span',
						{ className: 'bridge-gallery__empty' },
						__('No image chosen', 'bridge')
					),
			el(RichText, {
				tagName: 'span',
				className: 'bridge-gallery__label',
				value: label,
				allowedFormats: [],
				onChange: (value) => setAttributes({ label: value }),
				placeholder: __('Label (optional)', 'bridge'),
			})
		)
	);
};

export default Edit;
