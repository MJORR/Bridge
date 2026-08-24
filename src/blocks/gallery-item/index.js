/**
 * Bridge — Gallery Image (child) block registration entry. Editor only.
 */

import edit from './edit.js';

const { registerBlockType } = window.wp.blocks;

registerBlockType('bridge/gallery-item', { edit, save: () => null });
