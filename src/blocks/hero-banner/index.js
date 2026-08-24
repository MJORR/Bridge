/**
 * Bridge — Hero Banner block registration entry.
 *
 * Loaded in the editor only (declared as the block's editorScript).
 * Uses the `window.wp.*` globals WordPress already prints so Vite can keep
 * the bundle dependency-free; the matching wp_register_script() call lists
 * `wp-blocks`, `wp-block-editor`, `wp-element` and friends as dependencies.
 */

import edit from './edit.js';
import save from './save.js';

const { registerBlockType } = window.wp.blocks;

registerBlockType('bridge/hero-banner', {
	edit,
	save,
});
