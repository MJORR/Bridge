/**
 * Bridge — Locations registration entry. Editor only.
 */

import edit from './edit.js';

const { registerBlockType } = window.wp.blocks;

// Dynamic: the markup lives in render.php, and nothing is written into post
// content but the attributes.
registerBlockType('bridge/locations', { edit, save: () => null });
