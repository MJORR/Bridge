/**
 * Editor view for `bridge/download-item`.
 *
 * The file's type and size are not editable — render.php reads them from the
 * attachment — so the block shows what it will say rather than asking.
 */

const {
	useBlockProps,
	RichText,
	MediaUpload,
	MediaUploadCheck,
	InspectorControls,
} = window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, Button } = window.wp.components;
const { __ } = window.wp.i18n;

const Edit = ({ attributes, setAttributes }) => {
	const {
		title,
		linkText,
		fileId,
		fileUrl,
		fileName,
		previewId,
		previewUrl,
	} = attributes;

	const blockProps = useBlockProps({ className: 'bridge-download' });

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('File', 'bridge'), initialOpen: true },
				el(
					MediaUploadCheck,
					null,
					el(MediaUpload, {
						// So the library opens on the file already attached.
						value: fileId,
						onSelect: (media) =>
							setAttributes({
								fileId: media.id,
								fileUrl: media.url,
								fileName: media.filename || media.title || '',
								title: title || media.title || '',
							}),
						render: ({ open }) =>
							el(
								'div',
								{ className: 'bridge-media-field' },
								el(
									Button,
									{ variant: 'secondary', onClick: open },
									fileUrl
										? __('Replace file', 'bridge')
										: __('Choose file', 'bridge')
								),
								fileName &&
									el(
										'span',
										{
											className:
												'bridge-media-field__name',
										},
										fileName
									),
								!!fileUrl &&
									el(
										Button,
										{
											variant: 'link',
											isDestructive: true,
											onClick: () =>
												setAttributes({
													fileId: undefined,
													fileUrl: '',
													fileName: '',
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
				{ title: __('Preview image', 'bridge'), initialOpen: false },
				el(
					MediaUploadCheck,
					null,
					el(MediaUpload, {
						allowedTypes: ['image'],
						value: previewId,
						onSelect: (media) =>
							setAttributes({
								previewId: media.id,
								// The size the front end renders.
								previewUrl:
									media.sizes?.medium?.url || media.url,
							}),
						render: ({ open }) =>
							el(
								'div',
								{ className: 'bridge-media-field' },
								el(
									Button,
									{ variant: 'secondary', onClick: open },
									previewUrl
										? __('Replace image', 'bridge')
										: __('Choose image', 'bridge')
								),
								previewUrl &&
									el(
										Button,
										{
											variant: 'link',
											isDestructive: true,
											onClick: () =>
												setAttributes({
													previewId: undefined,
													previewUrl: '',
												}),
										},
										__('Remove', 'bridge')
									)
							),
					})
				)
			)
		),
		el(
			'li',
			blockProps,
			previewUrl &&
				el('img', {
					className: 'bridge-download__preview',
					src: previewUrl,
					alt: '',
				}),
			el(
				'div',
				{ className: 'bridge-download__body' },
				el(RichText, {
					tagName: 'p',
					className: 'bridge-download__title',
					value: title,
					allowedFormats: [],
					onChange: (value) => setAttributes({ title: value }),
					placeholder: __('Document title', 'bridge'),
				}),
				el(
					'p',
					{ className: 'bridge-download__meta' },
					fileUrl
						? __('Type and size are read from the file', 'bridge')
						: __('No file chosen yet', 'bridge')
				),
				el(RichText, {
					tagName: 'span',
					className: 'bridge-download__link',
					value: linkText,
					allowedFormats: [],
					onChange: (value) => setAttributes({ linkText: value }),
					placeholder: __('Download', 'bridge'),
				})
			)
		)
	);
};

export default Edit;
