/**
 * Bridge — Goal (child) block registration entry. Editor only.
 */

import edit from './edit.js';

const { registerBlockType } = window.wp.blocks;

registerBlockType('bridge/goal', { edit, save: () => null });
