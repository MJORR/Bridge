/**
 * Editor view for `bridge/tab` — one tab's content.
 *
 * The words are the tab's own blocks (a heading, text, Tab Features, buttons);
 * the image is optional and sits beside them, on the side the Tab Block sets.
 * The label is typed into the tab row above, or in the sidebar here.
 */

const {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	InnerBlocks,
} = window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, TextControl, Button } = window.wp.components;
const { __ } = window.wp.i18n;
const { useSelect } = window.wp.data;

const ALLOWED_BLOCKS = [
	'core/heading',
	'core/paragraph',
	'core/list',
	'core/buttons',
	'bridge/tab-feature',
];

const TEMPLATE = [
	['core/heading', { level: 3, placeholder: __('Step title', 'bridge') }],
	[
		'core/paragraph',
		{ placeholder: __('What happens at this step.', 'bridge') },
	],
	['bridge/tab-feature', {}],
	['bridge/tab-feature', {}],
];

const Edit = ({ attributes, setAttributes, isSelected }) => {
	const { label, imageId, imageUrl, imageAlt } = attributes;

	// The picture by its id when the stored address is missing (content
	// written outside the editor), so the canvas shows what the page will.
	const mediaUrl = useSelect(
		(select) => {
			if (!imageId || imageUrl) {
				return '';
			}
			const media = select('core').getMedia(imageId);
			return media
				? media.media_details?.sizes?.large?.source_url ||
						media.source_url
				: '';
		},
		[imageId, imageUrl]
	);
	const shownUrl = imageUrl || mediaUrl;
	const hasImage = !!shownUrl;

	const blockProps = useBlockProps({
		className: 'bridge-tab' + (hasImage ? ' bridge-tab--has-image' : ''),
	});

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'bridge-tab__content' },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			templateLock: false,
		}
	);

	const pick = (media) =>
		setAttributes({
			imageId: media.id,
			imageUrl: (media.sizes?.large || media).url,
			imageAlt: media.alt || '',
		});

	const remove = () =>
		setAttributes({ imageId: undefined, imageUrl: '', imageAlt: '' });

	const chooser = (render) =>
		el(
			MediaUploadCheck,
			null,
			el(MediaUpload, {
				onSelect: pick,
				allowedTypes: ['image'],
				value: imageId,
				render,
			})
		);

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Tab', 'bridge'), initialOpen: true },
				el(TextControl, {
					label: __('Tab label', 'bridge'),
					help: __(
						'Shown in the tab row. You can also type it there.',
						'bridge'
					),
					value: (label || '').replace(/<[^>]+>/g, ''),
					onChange: (value) => setAttributes({ label: value }),
					__nextHasNoMarginBottom: true,
				})
			),
			el(
				PanelBody,
				{ title: __('Image', 'bridge'), initialOpen: true },
				hasImage &&
					el('img', {
						src: shownUrl,
						alt: '',
						style: {
							display: 'block',
							width: '100%',
							marginBottom: '8px',
							borderRadius: '4px',
						},
					}),
				chooser(({ open }) =>
					el(
						'div',
						{ style: { display: 'flex', gap: '8px' } },
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
									onClick: remove,
								},
								__('Remove', 'bridge')
							)
					)
				),
				el(
					'p',
					{ className: 'components-base-control__help' },
					__(
						'Optional. Without one, the words use the full width.',
						'bridge'
					)
				),
				hasImage &&
					el(TextControl, {
						label: __('Alt text', 'bridge'),
						help: __(
							'Describe the image for people who can’t see it. Leave empty to use the alt text from the Media Library.',
							'bridge'
						),
						value: imageAlt,
						onChange: (value) => setAttributes({ imageAlt: value }),
						__nextHasNoMarginBottom: true,
					})
			)
		),
		el(
			'div',
			blockProps,
			el('div', innerBlocksProps),
			hasImage &&
				el(
					'figure',
					{ className: 'bridge-tab__media' },
					el('img', {
						className: 'bridge-tab__img',
						src: shownUrl,
						alt: '',
					})
				),
			!hasImage &&
				isSelected &&
				chooser(({ open }) =>
					el(
						Button,
						{
							className: 'bridge-tab__add-image',
							variant: 'secondary',
							icon: 'format-image',
							onClick: open,
						},
						__('Add image (optional)', 'bridge')
					)
				)
		)
	);
};

export const save = () => el(InnerBlocks.Content);

export default Edit;
