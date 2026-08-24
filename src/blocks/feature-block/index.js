/**
 * Bridge — Feature (child) block registration entry. Editor only.
 */

import edit from './edit.js';

const { registerBlockType } = window.wp.blocks;

registerBlockType('bridge/feature-block', { edit, save: () => null });
