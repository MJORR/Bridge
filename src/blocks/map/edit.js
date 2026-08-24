/**
 * Editor view for `bridge/map`.
 *
 * Renders the same keyless embed the front end does, so the editor shows the
 * real map rather than a placeholder. The address field is in the block itself
 * as well as the sidebar: an empty map's first question is "where?", and
 * making the editor open the Inspector to answer it is a step for nothing.
 */

const { useBlockProps, InspectorControls, BlockControls } =
	window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const {
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	Placeholder,
	Button,
	ToolbarGroup,
	ToolbarButton,
} = window.wp.components;
const { useState } = window.wp.element;
const { __ } = window.wp.i18n;

const embedSrc = (address, zoom) =>
	`https://maps.google.com/maps?q=${encodeURIComponent(
		address
	)}&z=${zoom}&output=embed`;

const Edit = ({ attributes, setAttributes }) => {
	const { address, zoom, height, label, width } = attributes;
	// Held locally until submitted rather than written on every keystroke: a
	// live attribute would reload the iframe once per character typed.
	const [draft, setDraft] = useState(address);
	const [editing, setEditing] = useState(!address);

	const blockProps = useBlockProps({
		className: `bridge-map bridge-section bridge-band alignfull bridge-map--${width}`,
	});

	const inspector = el(
		InspectorControls,
		null,
		el(
			PanelBody,
			{ title: __('Map settings', 'bridge'), initialOpen: true },
			el(TextControl, {
				label: __('Address or coordinates', 'bridge'),
				help: __(
					'A postal address, a place name, or a "latitude,longitude" pair.',
					'bridge'
				),
				value: address,
				onChange: (value) => setAttributes({ address: value }),
				__nextHasNoMarginBottom: true,
			}),
			el(TextControl, {
				label: __('Place name', 'bridge'),
				help: __(
					'Shown under the map, and used to describe it to screen readers.',
					'bridge'
				),
				value: label,
				onChange: (value) => setAttributes({ label: value }),
				__nextHasNoMarginBottom: true,
			}),
			el(RangeControl, {
				label: __('Zoom', 'bridge'),
				value: zoom,
				onChange: (value) => setAttributes({ zoom: value }),
				min: 1,
				max: 21,
				step: 1,
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
			el(RangeControl, {
				label: __('Height (px)', 'bridge'),
				value: height,
				onChange: (value) => setAttributes({ height: value }),
				min: 160,
				max: 1200,
				step: 20,
			})
		)
	);

	if (editing || !address) {
		return el(
			Fragment,
			null,
			inspector,
			el(
				'section',
				blockProps,
				el(
					Placeholder,
					{
						icon: 'location-alt',
						label: __('Map', 'bridge'),
						instructions: __(
							'Enter an address, a place name, or a "latitude,longitude" pair.',
							'bridge'
						),
					},
					el(
						'form',
						{
							className: 'bridge-map__form',
							onSubmit: (event) => {
								event.preventDefault();
								setAttributes({ address: draft });
								setEditing(false);
							},
						},
						el(TextControl, {
							value: draft,
							placeholder: __(
								'e.g. 10 Downing St, London',
								'bridge'
							),
							onChange: setDraft,
							__nextHasNoMarginBottom: true,
						}),
						el(
							Button,
							{ variant: 'primary', type: 'submit' },
							__('Show map', 'bridge')
						)
					)
				)
			)
		);
	}

	return el(
		Fragment,
		null,
		inspector,
		el(
			BlockControls,
			null,
			el(
				ToolbarGroup,
				null,
				el(ToolbarButton, {
					icon: 'edit',
					label: __('Change address', 'bridge'),
					onClick: () => {
						setDraft(address);
						setEditing(true);
					},
				})
			)
		),
		el(
			'section',
			{
				...blockProps,
				style: {
					...blockProps.style,
					'--bridge-map-height': `${height}px`,
				},
			},
			// The overlay swallows pointer events so dragging inside the map
			// pans the editor's block, not Google's tiles — otherwise the
			// block is very hard to select or move once a map is in it.
			el(
				'div',
				{ className: 'bridge-map__inner' },
				el('div', { className: 'bridge-map__shield' }),
				el('iframe', {
					className: 'bridge-map__frame',
					src: embedSrc(address, zoom),
					title: __('Map preview', 'bridge'),
					loading: 'lazy',
				}),
				label && el('p', { className: 'bridge-map__label' }, label)
			)
		)
	);
};

export default Edit;
