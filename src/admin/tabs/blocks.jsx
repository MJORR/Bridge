/**
 * Bridge — Theme Options, the Blocks tab.
 *
 * What the editor is allowed to contain. Full width rather than half, because
 * the library is seventy-odd switches and a sidebar would turn it into a
 * scroll.
 */

import { BlockLibrary, Section } from '../controls';
import { SkinPreview } from '../preview';

const { __ } = wp.i18n;
const { ToggleControl } = wp.components;

export function BlocksTab({ draft, payload, preview, setBlocks, setGroup }) {
	const { blockLibrary, sectionSkins } = payload;

	return (
		<>
			<Section
				title={__('Block library', 'bridge')}
				description={__(
					'Which blocks the editor offers. A block switched off here disappears from the inserter for everyone, including you \u2014 this is a decision about what the site is built from, not about who is editing. Content already using a switched-off block keeps rendering; only inserting a new one stops.',
					'bridge'
				)}
			>
				<BlockLibrary
					library={blockLibrary}
					enabled={draft.blocks.enabled}
					disabled={draft.blocks.disabled}
					onChange={setBlocks}
				/>
			</Section>

			<Section
				title={__('Section skins', 'bridge')}
				description={__(
					'Named treatments an editor can apply to a section. Each resolves to palette colours, so a skin restyles itself when the brand changes.',
					'bridge'
				)}
			>
				<SkinPreview
					skins={sectionSkins}
					palette={preview?.palette}
					fontFamilies={preview?.fontFamilies}
				/>
			</Section>

			<Section
				title={__('Third-party blocks', 'bridge')}
				description={__(
					'Whether the editor can install new blocks from the WordPress.org Block Directory.',
					'bridge'
				)}
			>
				<ToggleControl
					label={__('Disable third-party blocks', 'bridge')}
					checked={Boolean(draft.blocks.disableDirectory)}
					onChange={(on) =>
						setGroup('blocks', 'disableDirectory', on)
					}
					help={__(
						'When on, searching the block inserter no longer offers blocks from WordPress.org to install, so the library above stays the whole library. Blocks already installed by plugins are not affected.',
						'bridge'
					)}
					__nextHasNoMarginBottom
				/>
			</Section>
		</>
	);
}
