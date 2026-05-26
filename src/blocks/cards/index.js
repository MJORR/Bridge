/**
 * Bridge — Cards block registration entry.
 *
 * Loaded in the editor only (declared as editorScript in block.json).
 * Uses window.wp.* globals — Vite externalises @wordpress/* packages
 * and the matching script handles are listed as wp_register_script deps.
 */

import edit from './edit.js';
import save from './save.js';

const { registerBlockType } = window.wp.blocks;

registerBlockType( 'bridge/cards', { edit, save } );
