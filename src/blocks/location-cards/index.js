/**
 * Bridge — Location Cards registration entry. Editor only.
 */

import edit from './edit.js';

const { registerBlockType } = window.wp.blocks;
const { InnerBlocks } = window.wp.blockEditor;
const { createElement: el } = window.wp.element;

// Dynamic: the cards are read from Site Options in render.php. Only the
// intro — the inner blocks — is saved into post content.
registerBlockType('bridge/location-cards', {
	edit,
	save: () => el(InnerBlocks.Content),
});
