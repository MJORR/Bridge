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
					'Blocks switched off here are hidden from the inserter. Content already using them still displays.',
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
					'Colour styles an editor can apply to a section. They follow the brand palette.',
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
					'Lets editors install new blocks from WordPress.org.',
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
						'Stops the block inserter offering blocks from WordPress.org. Installed blocks are unaffected.',
						'bridge'
					)}
					__nextHasNoMarginBottom
				/>
			</Section>
		</>
	);
}
