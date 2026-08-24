/**
 * Bridge — Logo Slider block registration entry. Editor only.
 */

import edit from './edit.js';
import save from './save.js';

const { registerBlockType } = window.wp.blocks;

registerBlockType('bridge/logo-slider', { edit, save });
