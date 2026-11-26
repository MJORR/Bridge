/**
 * Bridge — Tab Feature (child of a Tab) registration entry. Editor only.
 */

import edit from './edit.js';

const { registerBlockType } = window.wp.blocks;

registerBlockType('bridge/tab-feature', { edit, save: () => null });
