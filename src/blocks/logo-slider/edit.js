/**
 * Editor view for `bridge/logo-slider`.
 *
 * Logos carry no per-item content — no title, no link, nothing to write — so
 * they stay an attribute picked from the media library rather than becoming a
 * child block each.
 *
 * The Inspector lists the chosen logos one per line: add one, remove one,
 * nudge one along the row. The media frame's gallery mode used to do the
 * ordering, but it asks the editor to build a gallery on the way to choosing
 * pictures, and every trip through it replaced the whole set — so removing a
 * single logo meant re-picking the rest.
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

// Heading only. A paragraph is still allowed — it is just not put there
// waiting to be deleted on every insert.
const TEMPLATE = [
	[
		'core/heading',
		{
			level: 2,
			textAlign: 'center',
			placeholder: __('Trusted by', 'bridge'),
		},
	],
];

// Only the id is rendered — render.php looks the file up again — so the url is
// the editor's preview and nothing else. `medium` is what the front end draws,
// and a row of full-size logos is a slow editor for no gain.
const toImage = (item) => ({
	id: item.id,
	url: item.sizes?.medium?.url || item.url,
	name: item.title || item.filename || '',
});

// The row of logos, one per line, each with its own controls.
const RowPicker = ({ addLabel, images, onChange }) => {
	const removeAt = (index) =>
		onChange(images.filter((image, at) => at !== index));

	const moveBy = (index, step) => {
		const next = images.slice();

		next.splice(index + step, 0, next.splice(index, 1)[0]);
		onChange(next);
	};

	return el(
		'div',
		{ className: 'bridge-logo-list' },
		images.length > 0 &&
			el(
				'ul',
				{ className: 'bridge-logo-list__items' },
				images.map((image, index) =>
					el(
						'li',
						{
							className: 'bridge-logo-list__item',
							// The same logo may legitimately appear twice in a
							// row, so the id alone is not a key.
							key: `${image.id}-${index}`,
						},
						el('img', {
							className: 'bridge-logo-list__thumb',
							src: image.url,
							alt: '',
						}),
						el(
							'span',
							{ className: 'bridge-logo-list__name' },
							image.name || __('Logo', 'bridge')
						),
						el(
							'div',
							{ className: 'bridge-logo-list__actions' },
							el(Button, {
								icon: 'arrow-up-alt2',
								size: 'small',
								label: __('Move logo earlier', 'bridge'),
								disabled: 0 === index,
								onClick: () => moveBy(index, -1),
							}),
							el(Button, {
								icon: 'arrow-down-alt2',
								size: 'small',
								label: __('Move logo later', 'bridge'),
								disabled: index === images.length - 1,
								onClick: () => moveBy(index, 1),
							}),
							el(Button, {
								icon: 'no-alt',
								size: 'small',
								isDestructive: true,
								label: __('Remove logo', 'bridge'),
								onClick: () => removeAt(index),
							})
						)
					)
				)
			),
		el(
			MediaUploadCheck,
			null,
			el(MediaUpload, {
				// Pick as many as you like in one visit, but nothing is
				// preselected and `gallery` stays off: this picker adds to the
				// row, it does not stand in for the row. Gallery mode would
				// hand back the whole selection instead, which is what made
				// removing a single logo impossible.
				multiple: true,
				gallery: false,
				addToGallery: false,
				allowedTypes: ['image'],
				title: __('Add logos', 'bridge'),
				onSelect: (media) =>
					onChange([
						...images,
						...(Array.isArray(media) ? media : [media]).map(
							toImage
						),
					]),
				render: ({ open }) =>
					el(
						'div',
						{ className: 'bridge-media-field' },
						el(
							Button,
							{ variant: 'secondary', onClick: open },
							addLabel
						)
					),
			})
		)
	);
};

const Edit = ({ attributes, setAttributes }) => {
	const { width, imageDisplay, images, secondRow, imagesSecond } = attributes;

	// Anything that is not `full` is `wide` — the block used to offer `narrow`
	// too, and a block still carrying it should draw as a width that exists.
	const rowWidth = 'full' === width ? 'full' : 'wide';

	const blockProps = useBlockProps({
		className: `bridge-logos bridge-section bridge-band alignfull bridge-logos--${rowWidth}`,
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
			row.map((image, index) =>
				el(
					'li',
					{
						className: 'bridge-logos__item',
						key: `${image.id}-${index}`,
					},
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
					addLabel: __('Add logos', 'bridge'),
					images,
					onChange: (value) => setAttributes({ images: value }),
				}),
				el(SelectControl, {
					label: __('Width', 'bridge'),
					help: __(
						'Full width runs the logos to the edges of the window. The heading stays in the content column either way.',
						'bridge'
					),
					value: rowWidth,
					options: [
						{ label: __('Full width', 'bridge'), value: 'full' },
						{ label: __('Wide', 'bridge'), value: 'wide' },
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
						addLabel: __('Add second-row logos', 'bridge'),
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
					{
						className: `bridge-logos__row is-static is-${imageDisplay}`,
					},
					preview(images, 'first')
				),
				secondRow &&
					imagesSecond.length > 0 &&
					el(
						'div',
						{
							className: `bridge-logos__row is-static is-${imageDisplay}`,
						},
						preview(imagesSecond, 'second')
					)
			)
		)
	);
};

export default Edit;
