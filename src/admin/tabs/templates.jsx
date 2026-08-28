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
					</>
				)}

				<SelectControl
					label={__('Background', 'bridge')}
					value={header.background}
					options={[
						{
							label: __('Solid', 'bridge'),
							value: 'solid',
						},
						{
							label: __(
								'Transparent — overlays what follows',
								'bridge'
							),
							value: 'transparent',
						},
					]}
					onChange={(value) => setHeader('background', value)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				{header.background === 'solid' && (
					<SelectControl
						label={__('Background colour', 'bridge')}
						value={header.backgroundColor}
						options={Object.entries(paletteSlugs).map(([slug]) => ({
							label: draft.brand.palette[slug].name,
							value: slug,
						}))}
						onChange={(value) =>
							setHeader('backgroundColor', value)
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				)}

				{header.background === 'transparent' && (
					<SelectControl
						label={__('Text contrast', 'bridge')}
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
							'Auto assumes a dark hero. A photograph\u2019s brightness cannot be known in advance, so set it here if auto reads wrong.',
							'bridge'
						)}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				)}

				<ToggleControl
					label={__('Stick to the top on scroll', 'bridge')}
					checked={Boolean(header.sticky)}
					onChange={(on) => setHeader('sticky', on)}
					help={
						header.background === 'transparent'
							? __(
									'A transparent sticky header turns solid once it leaves the hero, so its links stay readable.',
									'bridge'
								)
							: undefined
					}
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
					'Which footer layout every template resolves to.',
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
							hint: __('Links and contact details', 'bridge'),
						},
					]}
				/>
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
