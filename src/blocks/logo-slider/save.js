/**
 * Save for `bridge/logo-slider` — the intro's inner blocks; render.php wraps
 * them and prints the logo rows from attributes.
 */
const { InnerBlocks } = window.wp.blockEditor;
const { createElement: el } = window.wp.element;

export default () => el(InnerBlocks.Content);
