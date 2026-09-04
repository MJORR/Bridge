/**
 * Bridge — Theme Options, the Templates tab.
 *
 * The furniture around the content: the header, the footer, the templates each
 * resolves into, and what WordPress serves alongside the pages. Full width,
 * because the layout pickers are drawings and are already the preview.
 */

import { LayoutPicker, LogoPicker, Section } from '../controls';
import { TemplateList } from '../preview';

const { RangeControl, SelectControl, TextControl, ToggleControl } =
	wp.components;
const { __ } = wp.i18n;

export function TemplatesTab({ draft, payload, setGroup, setHeader }) {
	const { constraints, paletteSlugs, templates, menus } = payload;

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

	return (
		<>
			<Section
				title={__('Header', 'bridge')}
				description={__(
					'One setting drives every template. No layout puts the menu in a fixed centre column — that caps how many items it can hold, and a service list only ever grows.',
					'bridge'
				)}
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
						'Which menu the header navigation shows. Menus themselves are still built in the Site Editor.',
						'bridge'
					)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				<ToggleControl
					label={__('Top bar', 'bridge')}
					checked={Boolean(header.topBar)}
					onChange={(on) => setHeader('topBar', on)}
					help={__(
						'A slim strip above the header holding a second, quieter menu. Available on every layout.',
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
						help={__(
							'The bar renders only once it has a menu: an empty strip is worse than no strip.',
							'bridge'
						)}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				)}

				<ToggleControl
					label={__('Search icon', 'bridge')}
					checked={Boolean(header.search)}
					onChange={(on) => setHeader('search', on)}
					help={__(
						'A search icon at the end of the main menu, opening a field in the header itself.',
						'bridge'
					)}
					__nextHasNoMarginBottom
				/>

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
								'What fills the button. The header is the one ground the Buttons tab does not model \u2014 it is whichever colour you painted it, or a hero photograph on a landing page \u2014 so this fill is chosen rather than computed. The label follows it automatically, and the same colour paints the button in the mobile menu.',
								'bridge'
							)}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					</>
				)}

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
						'The ground the header sits on everywhere except the Landing Page template, whose header overlays the hero instead.',
						'bridge'
					)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

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
						'On the Landing Page template the header sits over the hero rather than on a colour, so its text is chosen against a photograph. Auto assumes a dark one; a photograph\u2019s brightness cannot be known in advance, so set it here if auto reads wrong.',
						'bridge'
					)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				<SelectControl
					label={__('Nav rollover background colour', 'bridge')}
					value={header.nav.rollover}
					options={Object.entries(paletteSlugs).map(([slug]) => ({
						label: draft.brand.palette[slug].name,
						value: slug,
					}))}
					onChange={(value) => setHeader('nav.rollover', value)}
					help={__(
						'The surface behind a main menu item the pointer is on. Its label colour follows automatically, so this stays readable on the Landing Page template where the menu sits over the hero.',
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
					help={__(
						'The dropdown panel, and the item it hangs from \u2014 the two are painted in one colour so the open menu reads as the tab having grown rather than as a slab under it.',
						'bridge'
					)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				<SelectControl
					label={__('Nav accent colour', 'bridge')}
					value={header.nav.accent}
					options={Object.entries(paletteSlugs).map(([slug]) => ({
						label: draft.brand.palette[slug].name,
						value: slug,
					}))}
					onChange={(value) => setHeader('nav.accent', value)}
					help={__(
						'The bar that lights a hovered item and marks the page you are on, and the colour of the current page\u2019s label inside a dropdown.',
						'bridge'
					)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				<ToggleControl
					label={__('Stick to the top on scroll', 'bridge')}
					checked={Boolean(header.sticky)}
					onChange={(on) => setHeader('sticky', on)}
					help={__(
						'On a landing page the sticky header turns solid once it leaves the hero, so its links stay readable.',
						'bridge'
					)}
					__nextHasNoMarginBottom
				/>

				<ToggleControl
					label={__('Bottom border', 'bridge')}
					checked={Boolean(header.border)}
					onChange={(on) => setHeader('border', on)}
					__nextHasNoMarginBottom
				/>

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
						'Used when the header background is dark, including a transparent header over a hero. Optional — without one the dark logo is used everywhere.',
						'bridge'
					)}
				/>

				{/*
				 * Stored on `brand` rather than on `header`, though the control
				 * stands here: it is a shape belonging to the site's identity,
				 * not to the header, and nothing has been built on it yet. A
				 * general home can serve a specific use later; a home under
				 * `header` could not have served a general one.
				 */}
				<LogoPicker
					label={__('Mask shape', 'bridge')}
					value={draft.brand.maskShapeId}
					onChange={(id) => setGroup('brand', 'maskShapeId', id)}
					help={__(
						'A shape imagery can be clipped to. Nothing draws it yet — it is here so the site has one place to set it.',
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

				<RangeControl
					label={__('Vertical padding (px)', 'bridge')}
					value={header.paddingBlock}
					min={constraints.header.paddingBlock.min}
					max={constraints.header.paddingBlock.max}
					step={constraints.header.paddingBlock.step}
					onChange={(value) => setHeader('paddingBlock', value ?? 12)}
					help={__(
						'Space above and below the logo on a full-width screen. Narrower viewports scale down to a proportion of it, so the header keeps its shape on a phone.',
						'bridge'
					)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
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
							hint: __('Logo, social and two menus', 'bridge'),
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
						'Text, links and icons take a light or dark colour to suit it, and the light logo is used on a dark ground.',
						'bridge'
					)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				{draft.footer.style === 'columns' && (
					<>
						<SelectControl
							label={__('Legal menu', 'bridge')}
							value={String(draft.footer.menus.legal)}
							options={menuOptions(
								__('None — hide the column', 'bridge')
							)}
							onChange={(value) =>
								setFooterMenu('legal', Number(value))
							}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>

						<SelectControl
							label={__('Quick Links menu', 'bridge')}
							value={String(draft.footer.menus.quick)}
							options={menuOptions(
								__('None — hide the column', 'bridge')
							)}
							onChange={(value) =>
								setFooterMenu('quick', Number(value))
							}
							help={__(
								'A column with no menu is not drawn: a heading with nothing under it is worse than two columns. Menus themselves are still built in the Site Editor.',
								'bridge'
							)}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					</>
				)}
			</Section>

			<Section
				title={__('Templates', 'bridge')}
				description={__(
					'The page structures this build ships, and which header and footer each one resolves to. A template that names its own part keeps it.',
					'bridge'
				)}
			>
				<TemplateList templates={templates} />
			</Section>

			<Section
				title={__('Site output', 'bridge')}
				description={__(
					'What WordPress serves alongside the pages themselves. This theme strips the generator meta, emoji script, XML-RPC and oEmbed discovery links and shortlinks from every response, because nothing here uses them. Feeds are the one piece of that worth deciding per site.',
					'bridge'
				)}
			>
				<ToggleControl
					label={__('Show RSS feed links', 'bridge')}
					checked={Boolean(draft.site?.feeds)}
					onChange={(on) => setGroup('site', 'feeds', on)}
					help={__(
						'Off, no page advertises a feed \u2014 which is right for a brochure site, where the comment and category feeds are crawl surface and nothing else. Turn it on for a build with a blog readers actually subscribe to. Either way /feed/ keeps working for anything pointed straight at it, such as a mailing list.',
						'bridge'
					)}
					__nextHasNoMarginBottom
				/>
			</Section>
		</>
	);
}
