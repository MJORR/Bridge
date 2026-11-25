/**
 * Bridge — Theme Options, the Templates tab.
 *
 * The furniture around the content: the header, the footer, the templates each
 * resolves into, and what WordPress serves alongside the pages. Full width,
 * because the layout pickers are drawings and are already the preview.
 */

import { LayoutPicker, LogoPicker, Section } from '../controls';
import { TemplateList } from '../preview';

const {
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
	__experimentalToggleGroupControl: ToggleGroupControl,
	__experimentalToggleGroupControlOption: ToggleGroupControlOption,
} = wp.components;
const { __, sprintf } = wp.i18n;

export function TemplatesTab({ draft, payload, setGroup, setHeader }) {
	const { constraints, paletteSlugs, templates, menus, sitePhone } = payload;

	const header = draft.header;

	// Ids arrive as numbers and SelectControl deals in strings, so the value is
	// stringified going in and parsed coming out — otherwise the current menu
	// never matches an option and the control reads as unset.
	const menuOptions = (auto) => [
		{ label: auto, value: '0' },
		...(menus || []).map((menu) => ({
			label: menu.title,
			value: String(menu.id),
		})),
	];

	// The footer's two slots live one level deeper than setGroup() reaches, so
	// the map is rebuilt here rather than given a writer of its own in the
	// shell — two call sites in one file is not a setter.
	const setFooterMenu = (slot, id) =>
		setGroup('footer', 'menus', { ...draft.footer.menus, [slot]: id });

	// Empty is a real value here, not a missing one: it means "the theme's own
	// wording", which is what the placeholder shows.
	const footerLabels = draft.footer.labels ?? {};
	const setFooterLabel = (slot, value) =>
		setGroup('footer', 'labels', { ...footerLabels, [slot]: value });

	// Hybrid is Columns with a different last column, so the column controls
	// are shared and only the last column's differ.
	const footerHasColumns = ['columns', 'hybrid'].includes(draft.footer.style);
	const footerHybrid = draft.footer.style === 'hybrid';
	const footerCta = {
		enabled: false,
		label: '',
		url: '',
		style: 'outline',
		...(draft.footer.cta ?? {}),
	};
	const setFooterCta = (key, value) =>
		setGroup('footer', 'cta', { ...footerCta, [key]: value });

	// The Hybrid footer's locations bar. The list itself is in Site Options.
	const footerLocations = {
		enabled: false,
		background: '',
		borderColor: '',
		nameSize: 'large',
		showAddress: false,
		...(draft.footer.locations ?? {}),
	};
	const setFooterLocations = (key, value) =>
		setGroup('footer', 'locations', { ...footerLocations, [key]: value });
	const paletteOptions = (none) => [
		{ label: none, value: '' },
		...Object.keys(paletteSlugs).map((slug) => ({
			label: draft.brand.palette[slug].name,
			value: slug,
		})),
	];

	return (
		<>
			<Section
				title={__('Header', 'bridge')}
				description={__('The header layout and its menus.', 'bridge')}
			>
				<LayoutPicker
					label={__('Layout', 'bridge')}
					value={header.layout}
					onChange={(value) => setHeader('layout', value)}
					options={[
						{
							value: 'left',
							label: __('Left', 'bridge'),
							hint: __('Logo left, menu right', 'bridge'),
						},
						{
							value: 'centre',
							label: __('Centre', 'bridge'),
							hint: __('Stacked, centred', 'bridge'),
						},
						{
							value: 'two-row',
							label: __('Two row', 'bridge'),
							hint: __('Stacked, left aligned', 'bridge'),
						},
						{
							value: 'hybrid',
							label: __('Hybrid', 'bridge'),
							hint: __('Menu button, logo, menu', 'bridge'),
						},
					]}
				/>

				<SelectControl
					label={__('Main menu', 'bridge')}
					value={String(header.menus.primary)}
					options={menuOptions(
						__('Auto — the newest menu on the site', 'bridge')
					)}
					onChange={(value) =>
						setHeader('menus.primary', Number(value))
					}
					help={__(
						'The menu shown in the header. Menus are built in the Site Editor.',
						'bridge'
					)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				{/* Hybrid only: the one layout with a menu button beside a
				    menu bar, so the only one where the two can differ. */}
				{header.layout === 'hybrid' && (
					<SelectControl
						label={__('Menu button opens', 'bridge')}
						value={String(header.menus.burger ?? 0)}
						options={menuOptions(__('The main menu', 'bridge'))}
						onChange={(value) =>
							setHeader('menus.burger', Number(value))
						}
						help={__(
							'The menu shown in the slide-out panel. On phones this is the only menu, so include everything.',
							'bridge'
						)}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				)}

				<ToggleControl
					label={__('Top bar', 'bridge')}
					checked={Boolean(header.topBar)}
					onChange={(on) => setHeader('topBar', on)}
					help={__(
						'A slim strip above the header with a second menu.',
						'bridge'
					)}
					__nextHasNoMarginBottom
				/>

				{header.topBar && (
					<SelectControl
						label={__('Top bar menu', 'bridge')}
						value={String(header.menus.utility)}
						options={menuOptions(
							__('None — hide the bar', 'bridge')
						)}
						onChange={(value) =>
							setHeader('menus.utility', Number(value))
						}
						help={__('Only shows once a menu is chosen.', 'bridge')}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				)}

				{/* Only with a number to show. A switch that adds nothing is
				    not a setting, so rather than offering it and explaining
				    that it does nothing, the control appears once Site Options
				    holds a phone number — and says which one it means. */}
				{header.topBar && Boolean(sitePhone) && (
					<ToggleControl
						label={__('Phone in the top bar', 'bridge')}
						checked={Boolean(header.topBarPhone)}
						onChange={(on) => setHeader('topBarPhone', on)}
						help={sprintf(
							/* translators: %s: the site's phone number. */
							__(
								'%s, shown at the end of the top bar and in the mobile menu. Set the number in Site Options.',
								'bridge'
							),
							sitePhone
						)}
						__nextHasNoMarginBottom
					/>
				)}

				<ToggleControl
					label={__('Search icon', 'bridge')}
					checked={Boolean(header.search)}
					onChange={(on) => setHeader('search', on)}
					help={__('Adds a search icon to the main menu.', 'bridge')}
					__nextHasNoMarginBottom
				/>

				<ToggleControl
					label={__('Locations dropdown', 'bridge')}
					checked={Boolean(header.locations?.enabled)}
					onChange={(on) => setHeader('locations.enabled', on)}
					help={__(
						'A dropdown of your locations beside the header button. On phones it moves into the menu.',
						'bridge'
					)}
					__nextHasNoMarginBottom
				/>

				{header.locations?.enabled && (
					<>
						<TextControl
							label={__('Dropdown label', 'bridge')}
							value={header.locations?.label ?? ''}
							onChange={(value) =>
								setHeader('locations.label', value)
							}
							placeholder={__('Locations', 'bridge')}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						<SelectControl
							label={__('Links from', 'bridge')}
							value={header.locations?.source ?? 'menu'}
							options={[
								{
									label: __('A menu', 'bridge'),
									value: 'menu',
								},
								{
									label: __(
										'Site Options locations',
										'bridge'
									),
									value: 'site',
								},
							]}
							onChange={(value) =>
								setHeader('locations.source', value)
							}
							help={
								header.locations?.source === 'site'
									? __(
											'Lists each location from Site Options that has a page link.',
											'bridge'
										)
									: __(
											'The links in a menu built in the Site Editor.',
											'bridge'
										)
							}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						{header.locations?.source !== 'site' && (
							<SelectControl
								label={__('Dropdown menu', 'bridge')}
								value={String(header.menus.locations ?? 0)}
								options={menuOptions(
									__('None — hide the dropdown', 'bridge')
								)}
								onChange={(value) =>
									setHeader('menus.locations', Number(value))
								}
								help={__(
									'Lists each link in this menu. Menus are built in the Site Editor.',
									'bridge'
								)}
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
						)}
					</>
				)}

				<ToggleControl
					label={__('CTA button', 'bridge')}
					checked={Boolean(header.cta.enabled)}
					onChange={(on) => setHeader('cta.enabled', on)}
					help={__(
						'A button to the right of the menu, on every layout.',
						'bridge'
					)}
					__nextHasNoMarginBottom
				/>

				{header.cta.enabled && (
					<>
						<TextControl
							label={__('Button label', 'bridge')}
							value={header.cta.label}
							onChange={(value) => setHeader('cta.label', value)}
							placeholder={__('Get in touch', 'bridge')}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						<TextControl
							label={__('Button link', 'bridge')}
							value={header.cta.url}
							onChange={(value) => setHeader('cta.url', value)}
							placeholder="/contact"
							help={__(
								'The button appears once both fields are filled in.',
								'bridge'
							)}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						<SelectControl
							label={__('Button colour', 'bridge')}
							value={header.cta.color}
							options={Object.entries(paletteSlugs).map(
								([slug]) => ({
									label: draft.brand.palette[slug].name,
									value: slug,
								})
							)}
							onChange={(value) => setHeader('cta.color', value)}
							help={__(
								'The header button colour. The text colour is set automatically.',
								'bridge'
							)}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						<SelectControl
							label={__('Button text colour', 'bridge')}
							value={header.cta.textColor ?? ''}
							options={[
								{ label: __('Auto', 'bridge'), value: '' },
								...Object.entries(paletteSlugs).map(
									([slug]) => ({
										label: draft.brand.palette[slug].name,
										value: slug,
									})
								),
							]}
							onChange={(value) =>
								setHeader('cta.textColor', value)
							}
							help={__(
								'The header button text colour. Auto picks the most readable.',
								'bridge'
							)}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					</>
				)}
			</Section>

			<Section
				title={__('Header logo', 'bridge')}
				description={__('The logo and its height.', 'bridge')}
			>
				<LogoPicker
					label={__('Dark logo', 'bridge')}
					value={header.logo.id}
					onChange={(id) => setHeader('logo.id', id)}
					help={__(
						'Used wherever the header sits on a light background.',
						'bridge'
					)}
				/>

				<LogoPicker
					label={__('Light logo', 'bridge')}
					value={header.logo.lightId}
					onChange={(id) => setHeader('logo.lightId', id)}
					help={__(
						'Used on dark header backgrounds. Optional.',
						'bridge'
					)}
				/>

				<LogoPicker
					label={__('Favicon', 'bridge')}
					value={header.logo.faviconId}
					onChange={(id) => setHeader('logo.faviconId', id)}
					types={['image/png', 'image/svg+xml']}
					help={__(
						'The browser tab icon, as a square PNG or SVG of 512px or more. Optional.',
						'bridge'
					)}
				/>

				<RangeControl
					label={__('Logo height (px)', 'bridge')}
					value={header.logo.height}
					min={constraints.header.logoHeight.min}
					max={constraints.header.logoHeight.max}
					step={constraints.header.logoHeight.step}
					onChange={(value) => setHeader('logo.height', value ?? 40)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</Section>

			<Section
				title={__('Header colours', 'bridge')}
				description={__(
					'Header and menu colours. Text colours are set automatically to stay readable.',
					'bridge'
				)}
			>
				{/*
				 * There is no Solid/Transparent choice here any more. A transparent
				 * header is a fact about a template rather than about a site: the
				 * Landing Page template overlays its header on the hero it opens
				 * with, and it does that whatever this panel said. Every other
				 * template opens on ordinary content, which a transparent header
				 * slid underneath. There was nothing the setting could usefully be
				 * set to: the one template it would have helped never read it.
				 */}

				<SelectControl
					label={__('Header background', 'bridge')}
					value={header.backgroundColor}
					options={Object.entries(paletteSlugs).map(([slug]) => ({
						label: draft.brand.palette[slug].name,
						value: slug,
					}))}
					onChange={(value) => setHeader('backgroundColor', value)}
					help={__(
						'The header background. Not used on the Landing Page template.',
						'bridge'
					)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				{header.topBar && (
					<SelectControl
						label={__('Top bar background', 'bridge')}
						value={header.topBarColor}
						options={Object.entries(paletteSlugs).map(([slug]) => ({
							label: draft.brand.palette[slug].name,
							value: slug,
						}))}
						onChange={(value) => setHeader('topBarColor', value)}
						help={__(
							'The top bar background. Not used on the Landing Page template.',
							'bridge'
						)}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				)}

				<SelectControl
					label={__('Nav rollover background colour', 'bridge')}
					value={header.nav.rollover}
					options={Object.entries(paletteSlugs).map(([slug]) => ({
						label: draft.brand.palette[slug].name,
						value: slug,
					}))}
					onChange={(value) => setHeader('nav.rollover', value)}
					help={__(
						'The background behind a menu item on hover.',
						'bridge'
					)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				<SelectControl
					label={__('Nav child background colour', 'bridge')}
					value={header.nav.child}
					options={Object.entries(paletteSlugs).map(([slug]) => ({
						label: draft.brand.palette[slug].name,
						value: slug,
					}))}
					onChange={(value) => setHeader('nav.child', value)}
					help={__('The dropdown menu background.', 'bridge')}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				<SelectControl
					label={__('Menu font size', 'bridge')}
					value={header.nav.fontSize ?? 'small'}
					options={[
						{
							label: __('Extra Small', 'bridge'),
							value: 'x-small',
						},
						{ label: __('Small', 'bridge'), value: 'small' },
						{ label: __('Medium', 'bridge'), value: 'medium' },
					]}
					onChange={(value) => setHeader('nav.fontSize', value)}
					help={__('The main menu text size.', 'bridge')}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				<SelectControl
					label={__('Menu font weight', 'bridge')}
					value={String(header.nav.weight ?? '500')}
					options={[
						{ label: __('Regular (400)', 'bridge'), value: '400' },
						{ label: __('Medium (500)', 'bridge'), value: '500' },
						{
							label: __('Semibold (600)', 'bridge'),
							value: '600',
						},
						{ label: __('Bold (700)', 'bridge'), value: '700' },
					]}
					onChange={(value) => setHeader('nav.weight', value)}
					help={__('The main menu text weight.', 'bridge')}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				<ToggleControl
					label={__('Nav accent', 'bridge')}
					checked={Boolean(header.nav.accentEnabled)}
					onChange={(on) => setHeader('nav.accentEnabled', on)}
					help={__(
						'Shows a bar under the hovered item and the current page.',
						'bridge'
					)}
					__nextHasNoMarginBottom
				/>

				{header.nav.accentEnabled && (
					<SelectControl
						label={__('Nav accent colour', 'bridge')}
						value={header.nav.accent}
						options={Object.entries(paletteSlugs).map(([slug]) => ({
							label: draft.brand.palette[slug].name,
							value: slug,
						}))}
						onChange={(value) => setHeader('nav.accent', value)}
						help={__(
							'The colour of the hover bar and the current page in dropdowns.',
							'bridge'
						)}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				)}

				<SelectControl
					label={__('Landing page header text', 'bridge')}
					value={header.contrast}
					options={[
						{
							label: __('Auto', 'bridge'),
							value: 'auto',
						},
						{
							label: __('Light text', 'bridge'),
							value: 'light',
						},
						{
							label: __('Dark text', 'bridge'),
							value: 'dark',
						},
					]}
					onChange={(value) => setHeader('contrast', value)}
					help={__(
						"Header text colour on the Landing Page template. Change it if Auto doesn't read well over the photo.",
						'bridge'
					)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</Section>

			<Section
				title={__('Header shape and behaviour', 'bridge')}
				description={__(
					'Header height, width and sticky behaviour.',
					'bridge'
				)}
			>
				<RangeControl
					label={__('Vertical padding (px)', 'bridge')}
					value={header.paddingBlock}
					min={constraints.header.paddingBlock.min}
					max={constraints.header.paddingBlock.max}
					step={constraints.header.paddingBlock.step}
					onChange={(value) => setHeader('paddingBlock', value ?? 12)}
					help={__('Space above and below the logo.', 'bridge')}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				<ToggleControl
					label={__('Bottom border', 'bridge')}
					checked={Boolean(header.border)}
					onChange={(on) => setHeader('border', on)}
					__nextHasNoMarginBottom
				/>

				<ToggleControl
					label={__('Bottom shadow', 'bridge')}
					checked={Boolean(header.shadow)}
					onChange={(on) => setHeader('shadow', on)}
					help={__('Adds a soft shadow under the header.', 'bridge')}
					__nextHasNoMarginBottom
				/>

				<ToggleControl
					label={__('Stick to the top on scroll', 'bridge')}
					checked={Boolean(header.sticky)}
					onChange={(on) => setHeader('sticky', on)}
					help={__(
						'On landing pages, the sticky header turns solid once it scrolls past the hero.',
						'bridge'
					)}
					__nextHasNoMarginBottom
				/>
			</Section>

			<Section
				title={__('Footer', 'bridge')}
				description={__(
					'Which footer layout every template resolves to, and what goes in it.',
					'bridge'
				)}
			>
				<LayoutPicker
					label={__('Layout', 'bridge')}
					value={draft.footer.style}
					onChange={(value) => setGroup('footer', 'style', value)}
					options={[
						{
							value: 'simple',
							label: __('Simple', 'bridge'),
							hint: __('One line', 'bridge'),
						},
						{
							value: 'columns',
							label: __('Columns', 'bridge'),
							hint: __('Logo, menus and contact', 'bridge'),
						},
						{
							value: 'hybrid',
							label: __('Hybrid', 'bridge'),
							hint: __(
								'Logo, menus, social and button',
								'bridge'
							),
						},
					]}
				/>

				<SelectControl
					label={__('Background', 'bridge')}
					value={draft.footer.backgroundColor}
					options={Object.entries(paletteSlugs).map(([slug]) => ({
						label: draft.brand.palette[slug].name,
						value: slug,
					}))}
					onChange={(value) =>
						setGroup('footer', 'backgroundColor', value)
					}
					help={__(
						'Text and logo colours adjust to suit it.',
						'bridge'
					)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				<ToggleControl
					label={__('Gradient on/off', 'bridge')}
					checked={Boolean(draft.footer.gradient)}
					onChange={(on) => setGroup('footer', 'gradient', on)}
					help={__(
						'Adds a subtle left-to-right gradient to the footer.',
						'bridge'
					)}
					__nextHasNoMarginBottom
				/>

				<SelectControl
					label={__('Copyright row colour', 'bridge')}
					value={draft.footer.legalColor ?? ''}
					options={[
						{
							label: __('Same as the footer', 'bridge'),
							value: '',
						},
						...Object.entries(paletteSlugs).map(([slug]) => ({
							label: draft.brand.palette[slug].name,
							value: slug,
						})),
					]}
					onChange={(value) =>
						setGroup('footer', 'legalColor', value)
					}
					help={__('The bottom row colour.', 'bridge')}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				<ToggleControl
					label={__('Copyright row top border', 'bridge')}
					checked={draft.footer.legalBorder !== false}
					onChange={(on) => setGroup('footer', 'legalBorder', on)}
					help={__(
						'A thin line along the top of the bottom row.',
						'bridge'
					)}
					__nextHasNoMarginBottom
				/>

				<RangeControl
					label={__('Copyright row shade', 'bridge')}
					value={draft.footer.legalShade}
					min={constraints.footer.legalShade.min}
					max={constraints.footer.legalShade.max}
					step={constraints.footer.legalShade.step}
					onChange={(value) =>
						setGroup('footer', 'legalShade', value ?? 0)
					}
					help={__(
						'Lightens or darkens the bottom row. 0 adds nothing.',
						'bridge'
					)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				{/*
				 * The mask shape, with the four controls a section block's
				 * Mask shape panel has — same scales, same wording — so the
				 * footer and the bands read as one system. The shape itself
				 * is picked further down, under Brand shapes.
				 */}
				<ToggleControl
					label={__('Show the mask shape', 'bridge')}
					checked={Boolean(draft.footer.mask)}
					onChange={(on) => setGroup('footer', 'mask', on)}
					help={
						draft.brand.maskShapeId
							? __(
									"Adds the site's mask shape behind the footer.",
									'bridge'
								)
							: __(
									'No mask shape has been set yet — choose one under Brand shapes below.',
									'bridge'
								)
					}
					__nextHasNoMarginBottom
				/>

				{draft.footer.mask && (
					<>
						<RangeControl
							label={__('Mask shade', 'bridge')}
							value={draft.footer.maskShade}
							min={constraints.footer.maskShade.min}
							max={constraints.footer.maskShade.max}
							step={constraints.footer.maskShade.step}
							onChange={(value) =>
								setGroup('footer', 'maskShade', value ?? -20)
							}
							help={__(
								'How much lighter or darker the shape is. 0 hides it.',
								'bridge'
							)}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>

						<RangeControl
							label={__('Mask size', 'bridge')}
							value={draft.footer.maskSize}
							min={constraints.footer.maskSize.min}
							max={constraints.footer.maskSize.max}
							step={constraints.footer.maskSize.step}
							onChange={(value) =>
								setGroup('footer', 'maskSize', value || 60)
							}
							help={__('How large the shape is.', 'bridge')}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>

						<ToggleGroupControl
							label={__('Held against', 'bridge')}
							help={__(
								'Which edge of the footer the shape sits against.',
								'bridge'
							)}
							value={draft.footer.maskEdge || 'right'}
							isBlock
							onChange={(value) =>
								setGroup('footer', 'maskEdge', value || 'right')
							}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						>
							<ToggleGroupControlOption
								value="left"
								label={__('Left', 'bridge')}
							/>
							<ToggleGroupControlOption
								value="right"
								label={__('Right', 'bridge')}
							/>
						</ToggleGroupControl>

						<RangeControl
							label={__('Run past the edge (percent)', 'bridge')}
							value={draft.footer.maskBleed}
							min={constraints.footer.maskBleed.min}
							max={constraints.footer.maskBleed.max}
							step={constraints.footer.maskBleed.step}
							onChange={(value) =>
								setGroup('footer', 'maskBleed', value ?? 0)
							}
							help={__(
								'How much of the shape is cropped off the edge.',
								'bridge'
							)}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					</>
				)}

				{footerHasColumns && (
					<>
						<ToggleControl
							label={__('Accent under menu headings', 'bridge')}
							checked={draft.footer.headingAccent !== false}
							onChange={(on) =>
								setGroup('footer', 'headingAccent', on)
							}
							help={__(
								'The short rule in the accent colour under each column heading.',
								'bridge'
							)}
							__nextHasNoMarginBottom
						/>

						<TextControl
							label={__('Explore heading', 'bridge')}
							value={footerLabels.explore ?? ''}
							onChange={(value) =>
								setFooterLabel('explore', value)
							}
							placeholder={__('Explore', 'bridge')}
							help={__(
								'Leave blank to use the default shown.',
								'bridge'
							)}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>

						<SelectControl
							label={__('Explore menu', 'bridge')}
							value={String(draft.footer.menus.explore)}
							options={menuOptions(
								__('None — hide the column', 'bridge')
							)}
							onChange={(value) =>
								setFooterMenu('explore', Number(value))
							}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>

						<TextControl
							label={__('Services heading', 'bridge')}
							value={footerLabels.services ?? ''}
							onChange={(value) =>
								setFooterLabel('services', value)
							}
							placeholder={__('Services', 'bridge')}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>

						<SelectControl
							label={__('Services menu', 'bridge')}
							value={String(draft.footer.menus.services)}
							options={menuOptions(
								__('None — hide the column', 'bridge')
							)}
							onChange={(value) =>
								setFooterMenu('services', Number(value))
							}
							help={__(
								'The two menu columns. A column without a menu is hidden. Menus are built in the Site Editor.',
								'bridge'
							)}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>

						{!footerHybrid && (
							<TextControl
								label={__('Contact heading', 'bridge')}
								value={footerLabels.contact ?? ''}
								onChange={(value) =>
									setFooterLabel('contact', value)
								}
								placeholder={__('Get in touch', 'bridge')}
								help={__(
									'The heading over the address, phone and social column.',
									'bridge'
								)}
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
						)}

						{footerHybrid && (
							<>
								<ToggleControl
									label={__('Locations bar', 'bridge')}
									checked={Boolean(footerLocations.enabled)}
									onChange={(on) =>
										setFooterLocations('enabled', on)
									}
									help={__(
										'A row of your locations across the top of the footer. Locations are set in Site Options.',
										'bridge'
									)}
									__nextHasNoMarginBottom
								/>

								{footerLocations.enabled && (
									<>
										<SelectControl
											label={__(
												'Locations bar background',
												'bridge'
											)}
											value={footerLocations.background}
											options={paletteOptions(
												__(
													'Same as the footer',
													'bridge'
												)
											)}
											onChange={(value) =>
												setFooterLocations(
													'background',
													value
												)
											}
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>

										<SelectControl
											label={__(
												'Locations bar bottom border',
												'bridge'
											)}
											value={footerLocations.borderColor}
											options={paletteOptions(
												__('None', 'bridge')
											)}
											onChange={(value) =>
												setFooterLocations(
													'borderColor',
													value
												)
											}
											help={__(
												'A thin line between the locations and the footer’s columns.',
												'bridge'
											)}
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>

										<SelectControl
											label={__(
												'Location name size',
												'bridge'
											)}
											value={footerLocations.nameSize}
											options={[
												{
													label: __(
														'Medium (as an H3)',
														'bridge'
													),
													value: 'large',
												},
												{
													label: __(
														'Large (as an H2)',
														'bridge'
													),
													value: 'x-large',
												},
											]}
											onChange={(value) =>
												setFooterLocations(
													'nameSize',
													value
												)
											}
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>

										<ToggleControl
											label={__(
												'Show address and postcode',
												'bridge'
											)}
											checked={Boolean(
												footerLocations.showAddress
											)}
											onChange={(on) =>
												setFooterLocations(
													'showAddress',
													on
												)
											}
											__nextHasNoMarginBottom
										/>
									</>
								)}

								<ToggleControl
									label={__('Show button', 'bridge')}
									checked={Boolean(footerCta.enabled)}
									onChange={(on) =>
										setFooterCta('enabled', on)
									}
									help={__(
										'A button under the social icons in the last column.',
										'bridge'
									)}
									__nextHasNoMarginBottom
								/>

								{footerCta.enabled && (
									<>
										<TextControl
											label={__('Button text', 'bridge')}
											value={footerCta.label}
											onChange={(value) =>
												setFooterCta('label', value)
											}
											placeholder={__(
												'Book online',
												'bridge'
											)}
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>

										<TextControl
											label={__('Button link', 'bridge')}
											type="url"
											value={footerCta.url}
											onChange={(value) =>
												setFooterCta('url', value)
											}
											help={__(
												'The button is shown only when it has both text and a link.',
												'bridge'
											)}
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>

										<SelectControl
											label={__('Button style', 'bridge')}
											value={footerCta.style}
											options={[
												{
													label: __(
														'Outline',
														'bridge'
													),
													value: 'outline',
												},
												{
													label: __('Fill', 'bridge'),
													value: 'fill',
												},
											]}
											onChange={(value) =>
												setFooterCta('style', value)
											}
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>
									</>
								)}
							</>
						)}

						<TextControl
							label={__('Legal menu name', 'bridge')}
							value={footerLabels.legal ?? ''}
							onChange={(value) => setFooterLabel('legal', value)}
							placeholder={__('Legal', 'bridge')}
							help={__(
								'Not shown on screen. Read out by screen readers.',
								'bridge'
							)}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>

						<SelectControl
							label={__('Legal menu', 'bridge')}
							value={String(draft.footer.menus.legal)}
							options={menuOptions(
								__('None — hide the links', 'bridge')
							)}
							onChange={(value) =>
								setFooterMenu('legal', Number(value))
							}
							help={__(
								'Shown in the bottom row beside the copyright.',
								'bridge'
							)}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>

						<p className="bridge-options__hint">
							{footerHybrid
								? __(
										'Social links, description and strapline come from Site Options.',
										'bridge'
									)
								: __(
										'Address, phone, social links, description and strapline come from Site Options.',
										'bridge'
									)}
						</p>
					</>
				)}
			</Section>

			<Section
				title={__('Templates', 'bridge')}
				description={__(
					'Each page template and the header and footer it uses.',
					'bridge'
				)}
			>
				<TemplateList templates={templates} />
			</Section>

			<Section
				title={__('Brand shapes', 'bridge')}
				description={__(
					'An image that section blocks can use.',
					'bridge'
				)}
			>
				{/*
				 * Stored on `brand` rather than on `header`, which is why it
				 * is no longer filed under one: the shape belongs to the site's
				 * identity and four bands draw it. It stood in the Header
				 * section only because that is where it was added.
				 */}

				<LogoPicker
					label={__('Strapline swoosh', 'bridge')}
					value={draft.brand.swooshId}
					onChange={(id) => setGroup('brand', 'swooshId', id)}
					help={__(
						'The swoosh under the footer strapline. Optional.',
						'bridge'
					)}
				/>

				<LogoPicker
					label={__('Mask shape', 'bridge')}
					value={draft.brand.maskShapeId}
					onChange={(id) => setGroup('brand', 'maskShapeId', id)}
					help={__(
						'A background shape that section blocks and the footer can show.',
						'bridge'
					)}
				/>
			</Section>

			<Section
				title={__('Site output', 'bridge')}
				description={__(
					'Extra things WordPress adds to each page.',
					'bridge'
				)}
			>
				<ToggleControl
					label={__('Show RSS feed links', 'bridge')}
					checked={Boolean(draft.site?.feeds)}
					onChange={(on) => setGroup('site', 'feeds', on)}
					help={__(
						'Adds feed links to every page. Turn on for a blog people subscribe to.',
						'bridge'
					)}
					__nextHasNoMarginBottom
				/>
			</Section>
		</>
	);
}
