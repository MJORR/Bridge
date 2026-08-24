/**
 * Bridge — download-item block registration entry. Editor only.
 */

import edit from './edit.js';

const { registerBlockType } = window.wp.blocks;

registerBlockType('bridge/download-item', { edit, save: () => null });
