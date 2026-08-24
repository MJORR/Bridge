/**
 * Bridge — Price Card (child) block registration entry. Editor only.
 */

import edit from './edit.js';

const { registerBlockType } = window.wp.blocks;

registerBlockType('bridge/price-card', { edit, save: () => null });
