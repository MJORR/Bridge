/**
 * Bridge — Testimonial (child) block registration entry. Editor only.
 */

import edit from './edit.js';

const { registerBlockType } = window.wp.blocks;

// Dynamic: render.php owns the markup, so nothing is serialized to post HTML
// beyond the attributes in the block comment.
registerBlockType('bridge/testimonial', { edit, save: () => null });
