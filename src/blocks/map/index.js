/**
 * Bridge — Map block registration entry. Editor only.
 */

import edit from './edit.js';

const { registerBlockType } = window.wp.blocks;

// Dynamic block with no inner blocks: render.php owns the output, so there is
// nothing for save to serialize.
registerBlockType('bridge/map', { edit, save: () => null });
