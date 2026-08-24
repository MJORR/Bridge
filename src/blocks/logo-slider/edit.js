/**
 * Editor view for `bridge/logo-slider`.
 *
 * Logos carry no per-item content — no title, no link, nothing to write — so
 * they stay an attribute chosen through the media library's own gallery
 * picker rather than becoming a child block each. Reordering happens in that
 * picker, which is where the pictures are.
 */

const {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
} = window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, SelectControl, ToggleControl, Button } =
	window.wp.components;
const { __ } = window.wp.i18n;

const TEMPLATE = [
	[
		'core/heading',
		{
			level: 2,
			textAlign: 'center',
			placeholder: __('Trusted by', 'bridge'),
		},
	],
	[
		'core/paragraph',
		{
			align: 'center',
			placeholder: __('A line of summary (optional)', 'bridge'),
		},
	],
];

// Only the id is rendered — render.php looks the file up again — so the url is
// the editor's preview and nothing else. `medium` is what the front end draws,
// and a row of full-size logos is a slow editor for no gain.
const toImages = (media) =>
	media.map((item) => ({
		id: item.id,
		url: item.sizes?.medium?.url || item.url,
	}));

const RowPicker = ({ label, images, onChange }) =>
	el(
		MediaUploadCheck,
		null,
		el(MediaUpload, {
			multiple: true,
			gallery: true,
			allowedTypes: ['image'],
			value: images.map((image) => image.id),
			onSelect: (media) => onChange(toImages(media)),
			render: ({ open }) =>
				el(
					'div',
					{ className: 'bridge-media-field' },
					el(
						Button,
						{ variant: 'secondary', onClick: open },
						images.length
							? /* translators: %d: number of logos. */
								__('Edit logos', 'bridge') +
									` (${images.length})`
							: label
					),
					images.length > 0 &&
						el(
							Button,
							{
								variant: 'link',
								isDestructive: true,
								onClick: () => onChange([]),
							},
							__('Clear', 'bridge')
						)
				),
		})
	);

const Edit = ({ attributes, setAttributes }) => {
	const { width, imageDisplay, images, secondRow, imagesSecond } = attributes;

	const blockProps = useBlockProps({
		className: `bridge-logos bridge-section bridge-band alignfull bridge-logos--${width}`,
	});

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'bridge-logos__intro bridge-section__intro' },
		{
			allowedBlocks: ['core/heading', 'core/paragraph'],
			template: TEMPLATE,
			templateLock: false,
		}
	);

	const preview = (row, key) =>
		el(
			'ul',
			{ className: 'bridge-logos__track', key },
			row.map((image) =>
				el(
					'li',
					{ className: 'bridge-logos__item', key: image.id },
					el('img', {
						className: `bridge-logos__image is-${imageDisplay}`,
						src: image.url,
						alt: '',
					})
				)
			)
		);

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Logos', 'bridge'), initialOpen: true },
				el(RowPicker, {
					label: __('Choose logos', 'bridge'),
					images,
					onChange: (value) => setAttributes({ images: value }),
				}),
				el(SelectControl, {
					label: __('Width', 'bridge'),
					value: width,
					options: [
						{ label: __('Wide', 'bridge'), value: 'wide' },
						{ label: __('Narrow', 'bridge'), value: 'narrow' },
					],
					onChange: (value) => setAttributes({ width: value }),
				}),
				el(SelectControl, {
					label: __('Image fit', 'bridge'),
					help: __(
						'Contain shows the whole logo. Cover fills the space and crops.',
						'bridge'
					),
					value: imageDisplay,
					options: [
						{ label: __('Contain', 'bridge'), value: 'contain' },
						{ label: __('Cover', 'bridge'), value: 'cover' },
					],
					onChange: (value) => setAttributes({ imageDisplay: value }),
				}),
				el(ToggleControl, {
					label: __('Add a second row', 'bridge'),
					help: __('The second row travels the other way.', 'bridge'),
					checked: !!secondRow,
					onChange: (value) => setAttributes({ secondRow: value }),
					__nextHasNoMarginBottom: true,
				}),
				secondRow &&
					el(RowPicker, {
						label: __('Choose second-row logos', 'bridge'),
						images: imagesSecond,
						onChange: (value) =>
							setAttributes({ imagesSecond: value }),
					})
			)
		),
		el(
			'section',
			blockProps,
			el(
				'div',
				{ className: 'bridge-logos__inner' },
				el('div', innerBlocksProps),
				// Static in the editor: a row sliding under the cursor makes
				// the block hard to select and the intro hard to click into.
				el(
					'div',
					{ className: 'bridge-logos__row is-static' },
					preview(images, 'first')
				),
				secondRow &&
					imagesSecond.length > 0 &&
					el(
						'div',
						{ className: 'bridge-logos__row is-static' },
						preview(imagesSecond, 'second')
					)
			)
		)
	);
};

export default Edit;
