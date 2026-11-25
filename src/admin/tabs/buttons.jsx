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
					'The button design used across the whole site. Colours are set below.',
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
					'The button colour on the page background and on each section style.',
					'bridge'
				)}
			>
				<p className="bridge-options__subhelp">
					{__(
						'Pick the fill colour. The text, hover and border colours are worked out automatically so every button stays readable.',
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
					'Built into every button, whatever the settings.',
					'bridge'
				)}
			>
				<ul className="bridge-guarantees">
					<li>
						<strong>{__('Target size', 'bridge')}</strong>
						{__(
							'At least 44×44px, so buttons are easy to tap.',
							'bridge'
						)}
					</li>
					<li>
						<strong>{__('Focus', 'bridge')}</strong>
						{__(
							'A clear focus ring shows when using a keyboard.',
							'bridge'
						)}
					</li>
					<li>
						<strong>{__('Hover', 'bridge')}</strong>
						{__(
							'Hover effects never rely on colour alone and work the same with a keyboard.',
							'bridge'
						)}
					</li>
					<li>
						<strong>{__('Motion', 'bridge')}</strong>
						{__(
							'Animations stop for visitors who prefer reduced motion.',
							'bridge'
						)}
					</li>
					<li>
						<strong>{__('Mobile', 'bridge')}</strong>
						{__(
							'Long labels wrap, and buttons fit on the narrowest screens.',
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
export function ButtonsPreview({ payload, preview, skin }) {
	const { buttonGrounds } = payload;

	return (
		<>
			<h3>{__('Buttons', 'bridge')}</h3>
			<p className="bridge-options__subhelp">
				{__('Hover a sample to preview it.', 'bridge')}
			</p>
			<ButtonPreview
				grounds={buttonGrounds}
				schemes={preview.buttons}
				custom={preview.custom?.button}
				palette={preview.palette}
				skin={skin}
			/>
		</>
	);
}
