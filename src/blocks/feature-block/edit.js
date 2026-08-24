/**
 * Editor view for `bridge/feature-block`.
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

const MediaField = ({ label, id, url, onSelect, onClear }) =>
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
					'div',
					{ className: 'bridge-media-field' },
					el(Button, { variant: 'secondary', onClick: open }, label),
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
				),
		})
	);

const Edit = ({ attributes, setAttributes, context }) => {
	const {
		title,
		text,
		graphicId,
		graphicUrl,
		backgroundId,
		backgroundUrl,
		linkUrl,
		linkText,
	} = attributes;

	// The section's setting, handed down as block context. It was read here
	// and then never used, so the toggle did nothing an editor could see until
	// the page was saved — the class it drives on the front end had no
	// counterpart in the preview.
	const alignTop = !!context['bridge/alignImageTop'];

	const blockProps = useBlockProps({
		className: [
			'bridge-feature',
			backgroundUrl ? 'has-background-image' : '',
			backgroundUrl && alignTop ? 'is-image-top' : '',
			linkUrl ? 'is-linked' : '',
		]
			.filter(Boolean)
			.join(' '),
		style: backgroundUrl
			? { '--bridge-feature-image': `url(${backgroundUrl})` }
			: undefined,
	});

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Images', 'bridge'), initialOpen: true },
				el(MediaField, {
					label: graphicUrl
						? __('Replace graphic', 'bridge')
						: __('Choose graphic', 'bridge'),
					id: graphicId,
					url: graphicUrl,
					onSelect: (media) =>
						setAttributes({
							graphicId: media.id,
							// The sizes the front end renders, not the
							// originals: a grid of full-size photographs is a
							// slow editor and a different crop.
							graphicUrl: media.sizes?.medium?.url || media.url,
						}),
					onClear: () =>
						setAttributes({ graphicId: undefined, graphicUrl: '' }),
				}),
				el(MediaField, {
					label: backgroundUrl
						? __('Replace background', 'bridge')
						: __('Choose background', 'bridge'),
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
					placeholder: __('Read more', 'bridge'),
					onChange: (value) => setAttributes({ linkText: value }),
					__nextHasNoMarginBottom: true,
				})
			)
		),
		el(
			'li',
			blockProps,
			backgroundUrl &&
				el('div', {
					className: 'bridge-feature__scrim',
					'aria-hidden': true,
				}),
			el(
				'div',
				{ className: 'bridge-feature__body' },
				graphicUrl &&
					el('img', {
						className: 'bridge-feature__graphic',
						src: graphicUrl,
						alt: '',
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
					el(
						'span',
						{ className: 'bridge-feature__link' },
						linkText || __('Read more', 'bridge')
					)
			)
		)
	);
};

export default Edit;
