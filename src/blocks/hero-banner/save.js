/**
 * Save callback for `bridge/hero-banner`.
 *
 * The block is dynamic — render.php wraps the authored Cover with the banner
 * scaffold and computes its height — so save only serializes the inner
 * blocks. Mirrors the Hero Slider's save for the same reason.
 */

const { InnerBlocks } = window.wp.blockEditor;
const { createElement: el } = window.wp.element;

const Save = () => el(InnerBlocks.Content);

export default Save;
