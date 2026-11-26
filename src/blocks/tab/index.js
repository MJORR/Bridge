/**
 * Bridge — Tab (child of the Tab Block) registration entry. Editor only.
 */

import edit, { save } from './edit.js';

const { registerBlockType } = window.wp.blocks;

registerBlockType('bridge/tab', { edit, save });
