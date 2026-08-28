/**
 * Bridge — Theme Options, the Design tab.
 *
 * The brand itself: the palette every other choice resolves against, the type
 * scale generated from two numbers, and the page's widths and spacing.
 *
 * Cards used to be here too, in two sections two hundred lines apart — their
 * colours up beside the palette, their geometry down beside the layout. They
 * are one subject and they now have one tab; see tabs/cards.jsx.
 */

import { ColorSlot, FontSelect, Section, rangeControl } from '../controls';
import {
	BrandPreview,
	ContrastAudit,
	IconPreview,
	LayoutPreview,
	TypePreview,
} from '../preview';

const { Notice, SelectControl, ToggleControl } = wp.components;
const { __ } = wp.i18n;

export function DesignTab({ draft, payload, preview, setGroup, setSlot }) {
	const {
		constraints,
		fontSets,
		paletteSlugs,
		spacingSlugs,
		spacingSteps,
		googleFonts,
		installedFonts,
		fontErrors,
	} = payload;

	const type = draft.typography;
	const layout = draft.layout;
	const range = rangeControl(constraints, draft, setGroup);

	// One vocabulary for every control that spends spacing. The slug stays the
	// stored value — it is what post content references — but nothing asks an
	// operator to choose between 60 and 70.
	const spacingOptions = spacingSlugs.map((slug) => ({
		label: spacingSteps?.[slug] || `spacing–${slug}`,
		value: slug,
	}));

	return (
		<>
			<Section
				title={__('Brand palette', 'bridge')}
				description={__(
					'These six colours are the only ones the editor offers — custom colours are switched off, so nothing off-brand can reach a page. Each slot takes the name editors see, then its hex value.',
					'bridge'
				)}
			>
				<div className="bridge-options__slots">
					{Object.entries(paletteSlugs).map(([slug, defaultName]) => (
						<ColorSlot
							key={slug}
							slug={slug}
							entry={draft.brand.palette[slug]}
							defaultName={defaultName}
							onChange={(patch) => setSlot(slug, patch)}
						/>
					))}
				</div>

				<h3 className="bridge-options__subhead">
					{__('Contrast', 'bridge')}
				</h3>
				<p className="bridge-options__subhelp">
					{__(
						'Every pair of these six colours the theme actually draws, measured. WCAG 2.2 §1.4.3 asks for 4.5:1 on body-sized text, which all of these are. Nothing here is adjusted for you — both colours in a pair are the brand’s, and a design system that quietly darkened one would be a design system nobody could trust.',
						'bridge'
					)}
				</p>
				<ContrastAudit checks={preview?.audits?.palette} />
			</Section>

			<Section
				title={__('Typography', 'bridge')}
				description={__(
					'Every font size on the site is generated from the base size and the scale ratio. Editors choose Small through Huge; they never type a number.',
					'bridge'
				)}
			>
				<SelectControl
					label={__('Font set', 'bridge')}
					value={type.fontSet}
					options={fontSets.map((set) => ({
						label: set.name,
						value: set.slug,
					}))}
					onChange={(value) =>
						setGroup('typography', 'fontSet', value)
					}
					help={
						fontSets.find((s) => s.slug === type.fontSet)
							?.description
					}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				<div className="bridge-google">
					<ToggleControl
						label={__('Google Fonts', 'bridge')}
						checked={Boolean(type.googleFonts)}
						onChange={(on) =>
							setGroup('typography', 'googleFonts', on)
						}
						help={__(
							'Adds well-known Google families to the pickers below. Files are downloaded and served from this site — nothing is ever requested from Google when a visitor loads a page.',
							'bridge'
						)}
						__nextHasNoMarginBottom
					/>

					{type.googleFonts && (
						<div className="bridge-google__pickers">
							<FontSelect
								label={__('Heading font', 'bridge')}
								value={type.googleHeading}
								families={googleFonts?.heading}
								installed={installedFonts}
								onChange={(value) =>
									setGroup(
										'typography',
										'googleHeading',
										value
									)
								}
							/>
							<FontSelect
								label={__('Body font', 'bridge')}
								value={type.googleBody}
								families={googleFonts?.body}
								installed={installedFonts}
								onChange={(value) =>
									setGroup('typography', 'googleBody', value)
								}
							/>
							<p className="bridge-google__note">
								{__(
									'Fonts download when you save, which can take a few seconds the first time.',
									'bridge'
								)}
							</p>
						</div>
					)}

					{Object.entries(fontErrors || {}).map(
						([family, message]) => (
							<Notice
								key={family}
								status="error"
								isDismissible={false}
							>
								{`${family}: ${message}`}
							</Notice>
						)
					)}
				</div>

				{range(
					'typography',
					'baseSize',
					__('Base size (rem)', 'bridge'),
					__(
						'The body copy size everything else is measured against.',
						'bridge'
					)
				)}
				{range(
					'typography',
					'scaleRatio',
					__('Scale ratio', 'bridge'),
					__(
						'How sharply headings step up. 1.25 is restrained; 1.6 is dramatic.',
						'bridge'
					)
				)}
				{range(
					'typography',
					'bodyLineHeight',
					__('Body line height', 'bridge')
				)}
				{range(
					'typography',
					'headingLineHeight',
					__('Heading line height', 'bridge')
				)}

				<SelectControl
					label={__('Heading weight', 'bridge')}
					value={type.headingWeight}
					options={constraints.typography.headingWeight.options.map(
						(w) => ({ label: w, value: w })
					)}
					onChange={(value) =>
						setGroup('typography', 'headingWeight', value)
					}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				<ToggleControl
					label={__('Uppercase headings', 'bridge')}
					checked={type.headingCase === 'uppercase'}
					onChange={(on) =>
						setGroup(
							'typography',
							'headingCase',
							on ? 'uppercase' : 'none'
						)
					}
					__nextHasNoMarginBottom
				/>
			</Section>

			<Section
				title={__('Icons', 'bridge')}
				description={__(
					'Icons are drawn from the theme\u2019s library and inherit their colour from the text around them. Weight is the only choice — a heavier stroke reads as more confident, a lighter one as more refined.',
					'bridge'
				)}
			>
				<SelectControl
					label={__('Icon weight', 'bridge')}
					value={draft.icons.weight}
					options={constraints.icons.weight.options.map((w) => ({
						label:
							w === 'regular'
								? __('Regular', 'bridge')
								: __('Bold', 'bridge'),
						value: w,
					}))}
					onChange={(value) => setGroup('icons', 'weight', value)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</Section>

			<Section
				title={__('Layout and spacing', 'bridge')}
				description={__(
					'Widths and rhythm for every template. The spacing steps editors can pick from are generated from the base and increment below.',
					'bridge'
				)}
			>
				{range(
					'layout',
					'contentSize',
					__('Content width (px)', 'bridge'),
					__('The default column that body copy sits in.', 'bridge')
				)}
				{range(
					'layout',
					'wideSize',
					__('Wide width (px)', 'bridge'),
					__(
						'Used by wide-aligned blocks. Never narrower than the content width.',
						'bridge'
					)
				)}
				{range(
					'layout',
					'spacingBase',
					__('Spacing base (rem)', 'bridge'),
					__(
						'The middle step of the seven-step spacing scale.',
						'bridge'
					)
				)}
				{range(
					'layout',
					'spacingIncrement',
					__('Spacing increment', 'bridge'),
					__('How quickly the spacing steps grow apart.', 'bridge')
				)}

				<SelectControl
					label={__('Gap between blocks', 'bridge')}
					value={layout.blockGap}
					options={spacingOptions}
					onChange={(value) => setGroup('layout', 'blockGap', value)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				<SelectControl
					label={__('Page side padding', 'bridge')}
					value={layout.rootPadding}
					options={spacingOptions}
					onChange={(value) =>
						setGroup('layout', 'rootPadding', value)
					}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				<SelectControl
					label={__('Section padding', 'bridge')}
					value={layout.sectionPadding}
					options={spacingOptions}
					onChange={(value) =>
						setGroup('layout', 'sectionPadding', value)
					}
					help={__(
						'The air above and below every full-width section — the section patterns, and any group given a Surface, Inverted or Accent style. Sections already on a page follow the new value; nothing has to be re-inserted.',
						'bridge'
					)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</Section>
		</>
	);
}

/**
 * The Design tab's sidebar.
 *
 * @param {Object} props Component props.
 */
export function DesignPreview({ draft, payload, preview }) {
	const { icons, iconWeights } = payload;

	return (
		<>
			<h3>{__('Brand', 'bridge')}</h3>
			<BrandPreview
				palette={preview.palette}
				fontFamilies={preview.fontFamilies}
			/>

			<h3>{__('Type scale', 'bridge')}</h3>
			<TypePreview
				fontSizes={preview.fontSizes}
				fontFamilies={preview.fontFamilies}
				styles={preview.styles}
			/>

			<h3>{__('Icons', 'bridge')}</h3>
			<IconPreview
				icons={icons}
				weights={iconWeights}
				active={draft.icons.weight}
			/>

			<h3>{__('Widths', 'bridge')}</h3>
			<LayoutPreview layout={preview.layout} />
		</>
	);
}
