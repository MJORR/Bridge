/**
 * Editor view for `bridge/tabs` — the Tab Block.
 *
 * The canvas works the way the page does: the intro, then the tab row, then
 * one tab's content at a time. Clicking a tab in the row shows that tab and
 * selects it; selecting anything inside a tab shows that tab too. The active
 * tab's label is typed straight into the row. "Add tab" adds a step at the
 * end, and tabs reorder by dragging them in List View.
 *
 * The children are one flat list, intro blocks and tabs together, as in
 * Feature Blocks. The tab row is drawn inside that list and placed between
 * the two with CSS `order`, and the tabs that are not showing are hidden with
 * a rule naming their block ids.
 *
 * The section settings are the Feature Blocks ones, with the same labels.
 */

const {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	RichText,
	InnerBlocks,
} = window.wp.blockEditor;
const { createElement: el, Fragment, useState, useEffect } = window.wp.element;
const {
	PanelBody,
	SelectControl,
	ToggleControl,
	TextControl,
	BaseControl,
	ColorPalette,
	Button,
} = window.wp.components;
const { __, sprintf } = window.wp.i18n;
const { useSelect, useDispatch } = window.wp.data;
const { createBlock } = window.wp.blocks;

const { MaskPanel, maskStyle, hasMask } = window.bridgeBandMaskUI || {};

const ALLOWED_BLOCKS = [
	'bridge/tab',
	'core/heading',
	'core/paragraph',
	'core/buttons',
];

const TEMPLATE = [
	['core/paragraph', { placeholder: __('Subheading (optional)', 'bridge') }],
	['core/heading', { level: 2, placeholder: __('What to expect', 'bridge') }],
	['bridge/tab', {}],
	['bridge/tab', {}],
	['bridge/tab', {}],
];

const pad = (n) => String(n).padStart(2, '0');

const Edit = ({ attributes, setAttributes, clientId }) => {
	const {
		width,
		textAlignment,
		showNumbers,
		showSteps,
		prevLabel,
		nextLabel,
		imageSide,
		imageCorners,
		panelStyle,
		buttonStyle,
		buttonSize,
		transition,
		roundCorners,
		cornerTop,
		cornerBottom,
	} = attributes;

	const [active, setActive] = useState(0);

	const { tabs, selectedTab, themeColors } = useSelect(
		(select) => {
			const store = select('core/block-editor');
			const children = store.getBlocks(clientId);
			const list = children.filter((b) => b.name === 'bridge/tab');
			const selected = store.getSelectedBlockClientId();
			let owner = null;

			if (selected) {
				const chain = [selected, ...store.getBlockParents(selected)];
				owner = list.find((b) => chain.includes(b.clientId)) || null;
			}

			const settings = store.getSettings();

			return {
				tabs: list,
				selectedTab: owner ? owner.clientId : null,
				themeColors: settings.colors || settings.colorPalette || [],
			};
		},
		[clientId]
	);

	const { updateBlockAttributes, insertBlock, selectBlock } =
		useDispatch('core/block-editor');

	// Follow the selection: editing inside a tab shows that tab.
	useEffect(() => {
		if (!selectedTab) {
			return;
		}
		const index = tabs.findIndex((t) => t.clientId === selectedTab);
		if (index >= 0 && index !== active) {
			setActive(index);
		}
	}, [selectedTab, tabs, active]);

	const current = Math.min(active, Math.max(0, tabs.length - 1));

	const addTab = () => {
		const children = window.wp.data
			.select('core/block-editor')
			.getBlocks(clientId);
		const block = createBlock('bridge/tab');
		insertBlock(block, children.length, clientId);
		setActive(tabs.length);
	};

	const cornerSwatches = (label, help, value, key) =>
		el(
			BaseControl,
			{ label, help, __nextHasNoMarginBottom: true },
			el(ColorPalette, {
				colors: themeColors,
				value: themeColors.find((c) => c.slug === value)?.color,
				onChange: (hex) =>
					setAttributes({
						[key]:
							themeColors.find((c) => c.color === hex)?.slug ||
							'',
					}),
				disableCustomColors: true,
				clearable: true,
			})
		);

	const blockProps = useBlockProps({
		className: [
			'bridge-tabs',
			'bridge-section bridge-band alignfull',
			`bridge-tabs--${width}`,
			`bridge-tabs--text-${textAlignment}`,
			`bridge-tabs--image-${imageSide}`,
			`bridge-tabs--features-${panelStyle}`,
			imageCorners === 'round' ? 'bridge-tabs--image-round' : '',
			buttonSize === 'small' ? 'bridge-tabs--button-small' : '',
			roundCorners ? 'bridge-tabs--corners' : '',
			hasMask && hasMask(attributes) ? 'has-mask' : '',
			'is-editor-preview',
		]
			.filter(Boolean)
			.join(' '),
		style: {
			...(roundCorners
				? {
						'--bridge-corner-top': `var(--wp--preset--color--${cornerTop || 'background'})`,
						'--bridge-corner-bottom': `var(--wp--preset--color--${cornerBottom || 'background'})`,
					}
				: {}),
			...(maskStyle ? maskStyle(attributes) : {}),
		},
	});

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'bridge-tabs__editor-list' },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			templateLock: false,
			renderAppender: false,
		}
	);

	const { children: innerChildren, ...listProps } = innerBlocksProps;

	// Hide every tab but the one showing.
	const hidden = tabs
		.filter((t, i) => i !== current)
		.map((t) => `#block-${t.clientId}`);

	const nav = el(
		'div',
		{
			className: 'bridge-tabs__nav',
			key: 'nav',
			role: 'tablist',
			contentEditable: false,
		},
		tabs.map((tab, i) => {
			const isActive = i === current;
			const label = tab.attributes.label || '';

			return el(
				'div',
				{
					key: tab.clientId,
					className: 'bridge-tabs__tab',
					role: 'tab',
					'aria-selected': isActive ? 'true' : 'false',
					onClick: () => {
						setActive(i);
						if (!isActive) {
							selectBlock(tab.clientId);
						}
					},
				},
				showNumbers &&
					el(
						'span',
						{ className: 'bridge-tabs__number' },
						pad(i + 1)
					),
				isActive
					? el(RichText, {
							tagName: 'span',
							className: 'bridge-tabs__label',
							value: label,
							allowedFormats: [],
							placeholder: sprintf(
								/* translators: %d: tab number. */
								__('Step %d', 'bridge'),
								i + 1
							),
							onChange: (value) =>
								updateBlockAttributes(tab.clientId, {
									label: value,
								}),
						})
					: el(
							'span',
							{ className: 'bridge-tabs__label' },
							label.replace(/<[^>]+>/g, '') ||
								sprintf(
									/* translators: %d: tab number. */
									__('Step %d', 'bridge'),
									i + 1
								)
						)
			);
		}),
		el(
			Button,
			{
				className: 'bridge-tabs__add',
				icon: 'plus',
				variant: 'tertiary',
				onClick: addTab,
				label: __('Add tab', 'bridge'),
			},
			__('Add tab', 'bridge')
		)
	);

	const steps =
		showSteps &&
		tabs.length > 1 &&
		el(
			'div',
			{ className: 'bridge-tabs__steps', key: 'steps' },
			el(
				'button',
				{
					type: 'button',
					className:
						'bridge-tabs__prev wp-element-button is-style-outline',
					disabled: current === 0,
					onClick: () => current > 0 && setActive(current - 1),
				},
				el('span', { className: 'bridge-tabs__arrow' }, '←'),
				el('span', null, prevLabel || __('Previous', 'bridge'))
			),
			el(
				'p',
				{ className: 'bridge-tabs__count' },
				sprintf(
					/* translators: 1: step number, 2: number of steps. */
					__('Step %1$d of %2$d', 'bridge'),
					current + 1,
					tabs.length
				)
			),
			el(
				'button',
				{
					type: 'button',
					className:
						'bridge-tabs__next wp-element-button' +
						(buttonStyle === 'outline' ? ' is-style-outline' : ''),
					disabled: current >= tabs.length - 1,
					onClick: () =>
						current < tabs.length - 1 && setActive(current + 1),
				},
				el('span', null, nextLabel || __('Next step', 'bridge')),
				el('span', { className: 'bridge-tabs__arrow' }, '→')
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
				el(SelectControl, {
					label: __('Text alignment', 'bridge'),
					help: __(
						'For the heading and summary above the tabs.',
						'bridge'
					),
					value: textAlignment,
					options: [
						{ label: __('Left', 'bridge'), value: 'left' },
						{ label: __('Centre', 'bridge'), value: 'center' },
					],
					onChange: (value) =>
						setAttributes({ textAlignment: value }),
				}),
				el(SelectControl, {
					label: __('Image side', 'bridge'),
					help: __(
						'A tab with no image uses the full width for its words.',
						'bridge'
					),
					value: imageSide,
					options: [
						{ label: __('Right', 'bridge'), value: 'right' },
						{ label: __('Left', 'bridge'), value: 'left' },
					],
					onChange: (value) => setAttributes({ imageSide: value }),
				}),
				el(SelectControl, {
					label: __('Switching tabs', 'bridge'),
					value: transition,
					options: [
						{ label: __('Fade', 'bridge'), value: 'fade' },
						{ label: __('No animation', 'bridge'), value: 'none' },
					],
					onChange: (value) => setAttributes({ transition: value }),
				})
			),
			el(
				PanelBody,
				{ title: __('Tabs', 'bridge'), initialOpen: true },
				el(ToggleControl, {
					label: __('Show step numbers', 'bridge'),
					help: __(
						'Puts 01, 02, 03… above each tab label.',
						'bridge'
					),
					checked: !!showNumbers,
					onChange: (value) => setAttributes({ showNumbers: value }),
					__nextHasNoMarginBottom: true,
				}),
				el(ToggleControl, {
					label: __('Show Previous and Next', 'bridge'),
					help: __(
						'Buttons under the tabs to step through them, with “Step 1 of 5” between. Off makes plain tabs.',
						'bridge'
					),
					checked: !!showSteps,
					onChange: (value) => setAttributes({ showSteps: value }),
					__nextHasNoMarginBottom: true,
				}),
				showSteps &&
					el(TextControl, {
						label: __('Previous button', 'bridge'),
						value: prevLabel,
						placeholder: __('Previous', 'bridge'),
						onChange: (value) =>
							setAttributes({ prevLabel: value }),
						__nextHasNoMarginBottom: true,
					}),
				showSteps &&
					el(TextControl, {
						label: __('Next button', 'bridge'),
						value: nextLabel,
						placeholder: __('Next step', 'bridge'),
						onChange: (value) =>
							setAttributes({ nextLabel: value }),
						__nextHasNoMarginBottom: true,
					})
			),
			el(
				PanelBody,
				{ title: __('Panels', 'bridge'), initialOpen: false },
				el(SelectControl, {
					label: __('Panel style', 'bridge'),
					help: __(
						'How the Tab Features in each tab are drawn. Card gives each a tinted ground and a fine border; Outlined keeps only the border; Plain is the words alone.',
						'bridge'
					),
					value: panelStyle,
					options: [
						{ label: __('Card', 'bridge'), value: 'card' },
						{ label: __('Plain', 'bridge'), value: 'plain' },
						{ label: __('Outlined', 'bridge'), value: 'outline' },
					],
					onChange: (value) => setAttributes({ panelStyle: value }),
				}),
				el(SelectControl, {
					label: __('Image corners', 'bridge'),
					help: __(
						'Card matches the corner radius set for cards in Theme Options. Round is twice the large radius.',
						'bridge'
					),
					value: imageCorners,
					options: [
						{ label: __('Card', 'bridge'), value: 'card' },
						{ label: __('Round', 'bridge'), value: 'round' },
					],
					onChange: (value) => setAttributes({ imageCorners: value }),
				}),
				el(SelectControl, {
					label: __('Button style', 'bridge'),
					help: __(
						'For the Next button. Previous is always outlined.',
						'bridge'
					),
					value: buttonStyle,
					options: [
						{ label: __('Filled', 'bridge'), value: 'fill' },
						{ label: __('Outline', 'bridge'), value: 'outline' },
					],
					onChange: (value) => setAttributes({ buttonStyle: value }),
				}),
				el(SelectControl, {
					label: __('Button size', 'bridge'),
					value: buttonSize,
					options: [
						{ label: __('Default', 'bridge'), value: 'default' },
						{ label: __('Small', 'bridge'), value: 'small' },
					],
					onChange: (value) => setAttributes({ buttonSize: value }),
				})
			),
			MaskPanel ? MaskPanel(attributes, setAttributes) : null
		),
		el(
			InspectorControls,
			{ group: 'styles' },
			el(
				PanelBody,
				{ title: __('Corners', 'bridge'), initialOpen: true },
				el(ToggleControl, {
					label: __('Rounded corners', 'bridge'),
					help: __(
						'Rounds the corners of the section at the XL radius.',
						'bridge'
					),
					checked: !!roundCorners,
					onChange: (value) => setAttributes({ roundCorners: value }),
					__nextHasNoMarginBottom: true,
				}),
				roundCorners &&
					cornerSwatches(
						__('Colour behind the top corners', 'bridge'),
						__(
							'Match the section above. Clear for the page colour.',
							'bridge'
						),
						cornerTop,
						'cornerTop'
					),
				roundCorners &&
					cornerSwatches(
						__('Colour behind the bottom corners', 'bridge'),
						__(
							'Match the section below. Clear for the page colour.',
							'bridge'
						),
						cornerBottom,
						'cornerBottom'
					)
			)
		),
		el(
			'section',
			blockProps,
			hidden.length
				? el(
						'style',
						null,
						hidden.join(',') + '{display:none !important}'
					)
				: null,
			el(
				'div',
				{ className: 'bridge-tabs__inner' },
				el('div', listProps, innerChildren, nav),
				steps
			)
		)
	);
};

export const save = () => el(InnerBlocks.Content);

export default Edit;
