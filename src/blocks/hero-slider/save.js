/**
 * Save callback for `bridge/hero-slider`.
 *
 * The block is dynamic (render.php wraps the inner content with the Swiper
 * scaffold), so save only needs to serialize the inner blocks. Returning
 * `<InnerBlocks.Content />` keeps the authored covers in post HTML and lets
 * the PHP renderer apply the slider markup at output time.
 */

const { InnerBlocks } = window.wp.blockEditor;
const { createElement: el } = window.wp.element;

const Save = () => el(InnerBlocks.Content);

export default Save;
