/**
 * Editor view for `bridge/alternating-row`.
 *
 * The side the media sits on is decided by the parent, not here — so this
 * block asks only what the media is and lets the copy be ordinary blocks.
 */

const {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
} = window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, SelectControl, Button, TextControl } = window.wp.components;
const { __, sprintf } = window.wp.i18n;

const TEMPLATE = [
	['core/heading', { level: 3, placeholder: __('Row heading', 'bridge') }],
	[
		'core/paragraph',
		{ placeholder: __('What this row is about.', 'bridge') },
	],
];

/**
 * The video's id, whatever the editor happened to paste.
 *
 * The field asks for the eleven characters after `v=`, but the thing on the
 * clipboard is a whole URL — from the address bar, from Share, from an embed
 * snippet. Taking the id out of it here is the difference between the block
 * working and a silently broken embed nobody sees until the page is published.
 *
 * @param {string} value Raw field value.
 * @return {string} The video id.
 */
const toYouTubeId = (value) => {
	const trimmed = (value || '').trim();

	if (!trimmed.includes('/')) {
		return trimmed;
	}

	const patterns = [
		/[?&]v=([\w-]{6,})/, // watch?v=ID
		/youtu\.be\/([\w-]{6,})/, // youtu.be/ID
		/\/(?:embed|shorts|live|v)\/([\w-]{6,})/, // /embed/ID and friends
	];

	for (const pattern of patterns) {
		const match = trimmed.match(pattern);

		if (match) {
			return match[1];
		}
	}

	return trimmed;
};

/**
 * What the canvas shows when there is no picture to show.
 *
 * A row whose media is a video used to read "No media chosen" even once the
 * video was chosen, because only an image ever drew anything. The label says
 * what the row actually holds now, so an editor can tell a finished row from
 * an unfinished one without opening the sidebar.
 *
 * @param {Object} attributes Block attributes.
 * @return {string} Placeholder label.
 */
const placeholderLabel = ({ mediaType, videoUrl, youtubeId }) => {
	if (mediaType === 'youtube') {
		return youtubeId
			? sprintf(
					/* translators: %s: YouTube video id. */
					__('YouTube video %s — add a poster image', 'bridge'),
					youtubeId
				)
			: __('Add a YouTube video ID', 'bridge');
	}

	if (mediaType === 'video') {
		return videoUrl
			? __('Video file — add a poster image', 'bridge')
			: __('Choose a video file', 'bridge');
	}

	return __('No image chosen', 'bridge');
};

const Edit = ({ attributes, setAttributes }) => {
	const { mediaType, imageId, imageUrl, alt, videoUrl, youtubeId } =
		attributes;

	const blockProps = useBlockProps({ className: 'bridge-alternating__row' });

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'bridge-alternating__copy' },
		{ template: TEMPLATE, templateLock: false }
	);

	const imageLabel =
		mediaType === 'image'
			? __('Choose image', 'bridge')
			: __('Choose poster image', 'bridge');

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Media', 'bridge'), initialOpen: true },
				el(SelectControl, {
					label: __('Media type', 'bridge'),
					value: mediaType,
					options: [
						{ label: __('Image', 'bridge'), value: 'image' },
						{ label: __('Video file', 'bridge'), value: 'video' },
						{ label: __('YouTube', 'bridge'), value: 'youtube' },
					],
					onChange: (value) => setAttributes({ mediaType: value }),
					__nextHasNoMarginBottom: true,
				}),
				mediaType === 'youtube' &&
					el(TextControl, {
						label: __('YouTube video ID', 'bridge'),
						help: __(
							'The part of the URL after "v=" — or paste the whole link. Loaded from youtube-nocookie.com, and only once someone presses play.',
							'bridge'
						),
						value: youtubeId,
						onChange: (value) =>
							setAttributes({ youtubeId: toYouTubeId(value) }),
						__nextHasNoMarginBottom: true,
					}),
				mediaType === 'video' &&
					el(
						MediaUploadCheck,
						null,
						el(MediaUpload, {
							allowedTypes: ['video'],
							onSelect: (media) =>
								setAttributes({ videoUrl: media.url }),
							render: ({ open }) =>
								el(
									Button,
									{ variant: 'secondary', onClick: open },
									videoUrl
										? __('Replace video', 'bridge')
										: __('Choose video', 'bridge')
								),
						})
					),
				mediaType === 'video' &&
					!!videoUrl &&
					el(
						Button,
						{
							variant: 'link',
							isDestructive: true,
							onClick: () => setAttributes({ videoUrl: '' }),
						},
						__('Remove video', 'bridge')
					),
				el(
					MediaUploadCheck,
					null,
					el(MediaUpload, {
						allowedTypes: ['image'],
						// So the library opens on the image the row already
						// has, rather than making the editor find it again.
						value: imageId,
						onSelect: (media) =>
							setAttributes({
								imageId: media.id,
								// The size the front end renders, not the
								// original: a 4000px original as a preview is
								// a slow editor and a different crop.
								imageUrl:
									media.sizes?.large?.url ||
									media.sizes?.full?.url ||
									media.url,
								alt: media.alt || '',
							}),
						render: ({ open }) =>
							el(
								Button,
								{ variant: 'secondary', onClick: open },
								imageUrl
									? __('Replace image', 'bridge')
									: imageLabel
							),
					})
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
						mediaType === 'image'
							? __('Remove image', 'bridge')
							: __('Remove poster image', 'bridge')
					),
				mediaType === 'image' &&
					el(TextControl, {
						label: __('Alt text', 'bridge'),
						help: __(
							'Describes the image. Leave empty if it is decorative.',
							'bridge'
						),
						value: alt,
						onChange: (value) => setAttributes({ alt: value }),
						__nextHasNoMarginBottom: true,
					})
			)
		),
		el(
			'div',
			blockProps,
			el(
				'div',
				{ className: 'bridge-alternating__media' },
				imageUrl
					? el('img', {
							className: 'bridge-alternating__image',
							src: imageUrl,
							alt: alt || '',
						})
					: el(
							'span',
							{ className: 'bridge-alternating__placeholder' },
							placeholderLabel(attributes)
						)
			),
			el('div', innerBlocksProps)
		)
	);
};

export default Edit;
