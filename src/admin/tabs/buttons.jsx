/**
 * Bridge — Theme Options, the Buttons tab.
 *
 * One shape for the whole site, and one fill per band. Everything else about a
 * button — its label colour, both hover colours, its boundary, its focus ring —
 * is computed from those two answers server-side, which is why this tab is
 * mostly a picker and a list of guarantees rather than a wall of controls.
 */

import { Section } from '../controls';
import { ButtonPreview, ButtonSkinPicker } from '../preview';

const { SelectControl } = wp.components;
const { __ } = wp.i18n;

export function ButtonsTab({
	draft,
	payload,
	preview,
	setGroup,
	setButtonFill,
}) {
	const { paletteSlugs, buttonSkins, buttonGrounds } = payload;

	return (
		<>
			<Section
				title={__('Button skin', 'bridge')}
				description={__(
					'One of three finished designs, applied to every button on the site — the block, the header’s call to action and anything a plugin renders as one. Pick the shape; the colours are answered separately below, per band, so a skin can be swapped without revisiting them.',
					'bridge'
				)}
			>
				<ButtonSkinPicker
					skins={buttonSkins}
					active={draft.buttons.skin}
					scheme={preview?.buttons?.default}
					onChange={(slug) => setGroup('buttons', 'skin', slug)}
				/>
			</Section>

			<Section
				title={__('Colour schemes', 'bridge')}
				description={__(
					'What fills a button on each of the four grounds this theme puts one on — the page itself, and the three section skins. A palette slug rather than a colour, so the buttons follow the brand when it changes.',
					'bridge'
				)}
			>
				<p className="bridge-options__subhelp">
					{__(
						'The fill is the only choice here, and it is the only one there is: the label, both hover colours, the boundary and the focus ring are computed from it and from the band behind it, each with one right answer. The label is whichever palette colour clears 4.5:1 on the fill, falling back to black or white — so a button that fails WCAG 1.4.3 is not reachable from this control. A fill within 3:1 of its own band is given a border, which is 1.4.11 answered. Every ratio is measured and shown in the preview.',
						'bridge'
					)}
				</p>

				<div className="bridge-options__slots">
					{(buttonGrounds || []).map((entry) => (
						<SelectControl
							key={entry.key}
							label={entry.label}
							value={draft.buttons.colors[entry.key]}
							options={Object.entries(paletteSlugs).map(
								([slug, defaultName]) => ({
									label:
										draft.brand.palette[slug]?.name ||
										defaultName,
									value: slug,
								})
							)}
							onChange={(slug) => setButtonFill(entry.key, slug)}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					))}
				</div>
			</Section>

			<Section
				title={__('What every skin guarantees', 'bridge')}
				description={__(
					'These are properties of the button itself rather than settings, so they hold whichever skin is chosen and whatever the palette becomes. They are listed because a client asking whether the site meets WCAG 2.2 deserves the specifics rather than a yes.',
					'bridge'
				)}
			>
				<ul className="bridge-guarantees">
					<li>
						<strong>{__('Target size', 'bridge')}</strong>
						{__(
							'Every button is at least 44×44px — WCAG 2.2 §2.5.8 asks for 24, and 44 is the size a thumb hits. A long label grows the box rather than shrinking the target.',
							'bridge'
						)}
					</li>
					<li>
						<strong>{__('Focus', 'bridge')}</strong>
						{__(
							'A 3px ring in the band’s own foreground, offset clear of the button’s edge so it is measured against the band — §2.4.11 and §2.4.13. Keyboard focus shows every state a pointer does.',
							'bridge'
						)}
					</li>
					<li>
						<strong>{__('Hover', 'bridge')}</strong>
						{__(
							'CSS only, no JavaScript, and never colour alone — §1.4.1. Solid’s fill climbs from the bottom edge and Pill’s sweeps across from the leading one; Edge draws a rule under the label. All three also change fill, and all three answer a keyboard the same way.',
							'bridge'
						)}
					</li>
					<li>
						<strong>{__('Motion', 'bridge')}</strong>
						{__(
							'The button itself never moves — not under the pointer, not on the press. What moves is inside it: the fill arriving from an edge on Solid and Pill, and Edge’s rule drawing itself. All of it stops under prefers-reduced-motion, where the same colours arrive at once instead. Nothing is conveyed by the movement itself.',
							'bridge'
						)}
					</li>
					<li>
						<strong>{__('Mobile', 'bridge')}</strong>
						{__(
							'Labels wrap rather than overflow, a row of buttons wraps rather than scrolls the page sideways, and the tap target holds at 44px on the narrowest screen.',
							'bridge'
						)}
					</li>
				</ul>
			</Section>
		</>
	);
}

/**
 * The Buttons tab's sidebar.
 *
 * @param {Object} props Component props.
 */
export function ButtonsPreview({ payload, preview }) {
	const { buttonGrounds } = payload;

	return (
		<>
			<h3>{__('Buttons', 'bridge')}</h3>
			<p className="bridge-options__subhelp">
				{__(
					'Hover a sample. This is the site\u2019s own stylesheet, not a drawing of it.',
					'bridge'
				)}
			</p>
			<ButtonPreview
				grounds={buttonGrounds}
				schemes={preview.buttons}
				custom={preview.custom?.button}
				palette={preview.palette}
			/>
		</>
	);
}
