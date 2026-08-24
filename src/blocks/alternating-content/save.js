/**
 * Save — inner blocks only; render.php wraps them.
 */
const { InnerBlocks } = window.wp.blockEditor;
const { createElement: el } = window.wp.element;

export default () => el(InnerBlocks.Content);
