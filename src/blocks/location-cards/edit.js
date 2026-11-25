/**
 * Editor view for `bridge/location-cards`.
 *
 * The intro is ordinary inner blocks. The cards under it are a server render
 * of the real thing, because they are read from Site Options and nothing
 * about them is edited here — the panel links there instead.
 */

const { useBlockProps, useInnerBlocksProps, InspectorControls } =
	window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, RangeControl, SelectControl, TextControl, ExternalLink } =
	window.wp.components;
const { __ } = window.wp.i18n;
const ServerSideRender = window.wp.serverSideRender;

const ALLOWED_BLOCKS = ['core/heading', 'core/paragraph', 'core/buttons'];

const TEMPLATE = [
	[
		'core/paragraph',
		{
			placeholder: __('Eyebrow (optional)', 'bridge'),
			fontSize: 'x-small',
			style: {
				typography: {
					textTransform: 'uppercase',
					letterSpacing: '0.12em',
				},
			},
		},
	],
	['core/heading', { level: 2, placeholder: __('Our locations', 'bridge') }],
];

const Edit = ({ attributes, setAttributes }) => {
	const {
		width = 'wide',
		columns = 2,
		headingLevel = 'h3',
		bookLabel = '',
		bookUrl = '',
		directionsLabel = '',
	} = attributes;

	const blockProps = useBlockProps({
		className: [
			'bridge-location-cards',
			'bridge-section bridge-band alignfull',
			`bridge-location-cards--${width}`,
		].join(' '),
		style: { '--columns': columns },
	});

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'bridge-location-cards__intro' },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			templateLock: false,
		}
	);

	const siteOptions =
		(window.bridgeLocationCards &&
			window.bridgeLocationCards.siteOptionsUrl) ||
		'admin.php';

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Locations', 'bridge') },
				el(
					'p',
					null,
					__(
						'Each card is a location from Site Options: its name, address, opening hours, phone, email, photo and directions link. Edit them there and every card follows.',
						'bridge'
					)
				),
				el(
					ExternalLink,
					{ href: siteOptions },
					__('Edit locations in Site Options', 'bridge')
				)
			),
			el(
				PanelBody,
				{ title: __('Layout', 'bridge') },
				el(SelectControl, {
					label: __('Width', 'bridge'),
					value: width,
					options: [
						{ label: __('Wide', 'bridge'), value: 'wide' },
						{ label: __('Narrow', 'bridge'), value: 'narrow' },
					],
					onChange: (value) => setAttributes({ width: value }),
					__nextHasNoMarginBottom: true,
				}),
				el(RangeControl, {
					label: __('Columns', 'bridge'),
					help: __(
						'The most cards in a row. Narrow screens show one.',
						'bridge'
					),
					value: columns,
					min: 1,
					max: 3,
					onChange: (value) => setAttributes({ columns: value }),
				}),
				el(SelectControl, {
					label: __('Location name heading', 'bridge'),
					value: headingLevel,
					options: [
						{ label: 'H2', value: 'h2' },
						{ label: 'H3', value: 'h3' },
						{ label: 'H4', value: 'h4' },
					],
					onChange: (value) => setAttributes({ headingLevel: value }),
					__nextHasNoMarginBottom: true,
				})
			),
			el(
				PanelBody,
				{ title: __('Buttons', 'bridge') },
				el(TextControl, {
					label: __('Book button label', 'bridge'),
					value: bookLabel,
					placeholder: __('Book appointment', 'bridge'),
					onChange: (value) => setAttributes({ bookLabel: value }),
					__nextHasNoMarginBottom: true,
				}),
				el(TextControl, {
					label: __('Book button link', 'bridge'),
					help: __(
						"Leave empty to link each card to its location's page link.",
						'bridge'
					),
					value: bookUrl,
					placeholder: '/book-online/',
					onChange: (value) => setAttributes({ bookUrl: value }),
					__nextHasNoMarginBottom: true,
				}),
				el(TextControl, {
					label: __('Directions button label', 'bridge'),
					help: __(
						"Links to the location's directions link, or a Google Maps search of its address.",
						'bridge'
					),
					value: directionsLabel,
					placeholder: __('Get directions', 'bridge'),
					onChange: (value) =>
						setAttributes({ directionsLabel: value }),
					__nextHasNoMarginBottom: true,
				})
			)
		),
		el(
			'section',
			blockProps,
			el(
				'div',
				{ className: 'bridge-location-cards__inner' },
				el('div', innerBlocksProps),
				el(ServerSideRender, {
					block: 'bridge/location-cards',
					attributes: { ...attributes, preview: true },
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
								'No locations yet. Add them in Site Options.',
								'bridge'
							)
						),
				})
			)
		)
	);
};

export default Edit;
