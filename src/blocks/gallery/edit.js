/**
 * Editor view for `bridge/gallery`.
 *
 * The "add images" button selects many at once and turns each into its own
 * child block, so building a gallery is one trip to the media library rather
 * than one per picture.
 */

const {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
} = window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, RangeControl, SelectControl, ToggleControl, Button } =
	window.wp.components;
const { useDispatch } = window.wp.data;
const { createBlock } = window.wp.blocks;
const { __ } = window.wp.i18n;

const ALLOWED_BLOCKS = [
	'bridge/gallery-item',
	'core/heading',
	'core/paragraph',
];

const TEMPLATE = [
	['core/heading', { level: 2, placeholder: __('Gallery', 'bridge') }],
	[
		'core/paragraph',
		{ placeholder: __('A line of summary (optional)', 'bridge') },
	],
];

const Edit = ({ attributes, setAttributes, clientId }) => {
	const { width, columns, lightbox } = attributes;
	const { insertBlocks } = useDispatch('core/block-editor');

	const blockProps = useBlockProps({
		className: `bridge-gallery bridge-section bridge-band alignfull bridge-gallery--${width}`,
		style: { '--columns': columns },
	});

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'bridge-gallery__grid' },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			templateLock: false,
		}
	);

	const addImages = (media) =>
		insertBlocks(
			media.map((item) =>
				createBlock('bridge/gallery-item', {
					imageId: item.id,
					// The rendered size, not the original — a gallery built
					// from twenty 4000px files makes the editor crawl.
					imageUrl: item.sizes?.large?.url || item.url,
					alt: item.alt || '',
				})
			),
			undefined,
			clientId
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
				}),
				el(RangeControl, {
					label: __('Columns', 'bridge'),
					help: __(
						'Narrow screens use fewer automatically.',
						'bridge'
					),
					value: columns,
					onChange: (value) => setAttributes({ columns: value }),
					min: 1,
					max: 6,
					step: 1,
				}),
				el(ToggleControl, {
					label: __('Open images full size', 'bridge'),
					help: __(
						'Clicking an image opens it in a viewer. Images that carry a link of their own follow the link instead.',
						'bridge'
					),
					checked: !!lightbox,
					onChange: (value) => setAttributes({ lightbox: value }),
					__nextHasNoMarginBottom: true,
				})
			)
		),
		el(
			'section',
			blockProps,
			el(
				'div',
				{ className: 'bridge-gallery__inner' },
				el('div', innerBlocksProps),
				el(
					MediaUploadCheck,
					null,
					el(MediaUpload, {
						multiple: true,
						gallery: true,
						allowedTypes: ['image'],
						onSelect: addImages,
						render: ({ open }) =>
							el(
								Button,
								{
									variant: 'secondary',
									onClick: open,
									className: 'bridge-gallery__add',
								},
								__('Add images', 'bridge')
							),
					})
				)
			)
		)
	);
};

export default Edit;
