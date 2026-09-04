/**
 * Save callback for `bridge/cards`.
 *
 * The cards are queried rather than authored, so render.php draws all of them
 * — but the band's intro is not queried. The heading and the optional summary
 * line above the grid are inner blocks, edited in place like every other
 * heading on the site, and inner blocks are only written into post content at
 * the point `InnerBlocks.Content` puts them.
 *
 * This returned `null` while the intro was still drawn from attributes. It
 * stayed at `null` after the intro became inner blocks, which meant the
 * serialiser wrote the block as a void comment and dropped the heading with
 * it: a title typed into the band was gone on save, every time. render.php has
 * been reading it from `$content` throughout — see the `bridge_section_intro()`
 * call there — so the front end was asking for something the editor was never
 * storing.
 *
 * The same one line every other section block saves. Nothing else is
 * serialised: the grid is render.php's, in the editor through
 * <ServerSideRender> and on the front end directly.
 */

const { InnerBlocks } = window.wp.blockEditor;
const { createElement: el } = window.wp.element;

export default () => el(InnerBlocks.Content);
