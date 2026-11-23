/**
 * Editor view for `bridge/locations`.
 *
 * The canvas is the server's own render, so it is exactly what the page will
 * show. By default the locations come from Site Options — written once for the
 * whole site. Switch the source to "This block" and the block keeps a list of
 * its own, edited here: one panel each, with buttons to add, reorder and
 * remove them.
 *
 * Both colours are stored as palette slugs, never hex, so a rebrand repaints
 * the band. The swatches are the site's palette and nothing else.
 */

const { useBlockProps, InspectorControls } = window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const {
	PanelBody,
	TextControl,
	TextareaControl,
	SelectControl,
	ToggleControl,
	Button,
	Flex,
	FlexItem,
	BaseControl,
	ColorPalette,
} = window.wp.components;
const { useSelect } = window.wp.data;
const { __, sprintf } = window.wp.i18n;
const ServerSideRender = window.wp.serverSideRender;

const EMPTY = {
	name: '',
	address: '',
	postcode: '',
	phone: '',
	email: '',
	url: '',
};

const Edit = ({ attributes, setAttributes }) => {
	const {
		source = 'site',
		locations = [],
		showAddress = false,
		background = '',
		borderColor = '',
		nameSize = 'x-large',
	} = attributes;

	const custom = source === 'custom';

	const themeColors = useSelect((select) => {
		const settings = select('core/block-editor').getSettings();

		return settings.colors || settings.colorPalette || [];
	}, []);

	const slugToHex = (slug) =>
		themeColors.find((color) => color.slug === slug)?.color || undefined;
	const hexToSlug = (hex) =>
		themeColors.find((color) => color.color === hex)?.slug || '';

	const update = (index, key, value) =>
		setAttributes({
			locations: locations.map((location, i) =>
				i === index ? { ...location, [key]: value } : location
			),
		});

	const move = (index, by) => {
		const next = [...locations];
		const [item] = next.splice(index, 1);
		next.splice(index + by, 0, item);
		setAttributes({ locations: next });
	};

	const remove = (index) =>
		setAttributes({ locations: locations.filter((_, i) => i !== index) });

	const add = () =>
		setAttributes({ locations: [...locations, { ...EMPTY }] });

	// A palette picker: the site's colours only, stored as the slug.
	const swatches = (label, value, key, help) =>
		el(
			BaseControl,
			{ label, help, __nextHasNoMarginBottom: true },
			el(ColorPalette, {
				colors: themeColors,
				value: slugToHex(value),
				onChange: (hex) => setAttributes({ [key]: hexToSlug(hex) }),
				disableCustomColors: true,
				clearable: true,
			})
		);

	const text = (index, location, key, label, extra = {}) =>
		el(TextControl, {
			label,
			value: location[key] || '',
			onChange: (value) => update(index, key, value),
			__nextHasNoMarginBottom: true,
			__next40pxDefaultSize: true,
			...extra,
		});

	const locationPanel = (location, index) =>
		el(
			PanelBody,
			{
				key: index,
				title:
					location.name ||
					sprintf(
						/* translators: %d: location number. */
						__('Location %d', 'bridge'),
						index + 1
					),
				initialOpen: false,
			},
			text(index, location, 'name', __('Location name', 'bridge')),
			el(TextareaControl, {
				label: __('Address', 'bridge'),
				value: location.address || '',
				onChange: (value) => update(index, 'address', value),
				rows: 3,
				__nextHasNoMarginBottom: true,
			}),
			text(index, location, 'postcode', __('Postcode', 'bridge')),
			text(index, location, 'phone', __('Phone', 'bridge')),
			text(index, location, 'email', __('Email', 'bridge'), {
				type: 'email',
			}),
			text(index, location, 'url', __('Page link', 'bridge'), {
				type: 'url',
				help: __('Makes the name a link.', 'bridge'),
			}),
			el(
				Flex,
				{ justify: 'flex-start', style: { marginTop: 12 } },
				el(
					FlexItem,
					null,
					el(Button, {
						icon: 'arrow-up-alt2',
						label: __('Move up', 'bridge'),
						disabled: index === 0,
						onClick: () => move(index, -1),
						size: 'small',
					})
				),
				el(
					FlexItem,
					null,
					el(Button, {
						icon: 'arrow-down-alt2',
						label: __('Move down', 'bridge'),
						disabled: index === locations.length - 1,
						onClick: () => move(index, 1),
						size: 'small',
					})
				),
				el(
					FlexItem,
					null,
					el(
						Button,
						{
							isDestructive: true,
							variant: 'link',
							onClick: () => remove(index),
						},
						__('Remove', 'bridge')
					)
				)
			)
		);

	const hasNamed = locations.some((location) => (location.name || '').trim());

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Locations', 'bridge'), initialOpen: true },
				el(SelectControl, {
					label: __('Source', 'bridge'),
					value: source,
					options: [
						{
							label: __('Site Options (recommended)', 'bridge'),
							value: 'site',
						},
						{
							label: __('This block only', 'bridge'),
							value: 'custom',
						},
					],
					onChange: (value) => setAttributes({ source: value }),
					help: custom
						? __(
								'This block keeps its own list, below. Changes in Site Options do not reach it.',
								'bridge'
							)
						: __(
								'The list from Site Options → Locations, entered once for the whole site.',
								'bridge'
							),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				}),
				el(ToggleControl, {
					label: __('Show address and postcode', 'bridge'),
					checked: showAddress,
					onChange: (value) => setAttributes({ showAddress: value }),
					__nextHasNoMarginBottom: true,
				}),
				custom &&
					el(
						Button,
						{
							variant: 'secondary',
							onClick: add,
							style: { marginTop: 12 },
						},
						__('Add location', 'bridge')
					)
			),
			custom && locations.map(locationPanel),
			el(
				PanelBody,
				{ title: __('Style', 'bridge'), initialOpen: true },
				el(SelectControl, {
					label: __('Name size', 'bridge'),
					value: nameSize,
					options: [
						{
							label: __('Large (as an H2)', 'bridge'),
							value: 'x-large',
						},
						{
							label: __('Medium (as an H3)', 'bridge'),
							value: 'large',
						},
					],
					onChange: (value) => setAttributes({ nameSize: value }),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				}),
				swatches(
					__('Background', 'bridge'),
					background,
					'background',
					__(
						'The band’s colour. Text turns light or dark to suit it.',
						'bridge'
					)
				),
				swatches(
					__('Bottom border', 'bridge'),
					borderColor,
					'borderColor',
					__(
						'A thin line along the band’s bottom edge. Clear it for none.',
						'bridge'
					)
				)
			)
		),
		el(
			'div',
			useBlockProps({ className: 'bridge-locations-preview' }),
			custom && !hasNamed
				? el(
						'p',
						{
							style: {
								padding: 24,
								border: '1px dashed currentColor',
								textAlign: 'center',
							},
						},
						__(
							'Locations: add a location in the sidebar to show this band.',
							'bridge'
						)
					)
				: el(ServerSideRender, {
						block: 'bridge/locations',
						attributes,
						httpMethod: 'POST',
						EmptyResponsePlaceholder: () =>
							el(
								'p',
								{
									style: {
										padding: 24,
										border: '1px dashed currentColor',
										textAlign: 'center',
									},
								},
								__(
									'Locations: no locations in Site Options yet. Add them under Site Options → Locations.',
									'bridge'
								)
							),
					})
		)
	);
};

export default Edit;
