/**
 * Bridge — Site Footer block registration entry.
 *
 * The block has no controls. Everything it renders comes from Theme Options
 * and Site Options, so its edit implementation exists only to draw the real
 * thing: the same render.php the front end uses, fetched through
 * ServerSideRender.
 *
 * Uses window.wp.* globals — Vite externalises @wordpress/* packages and the
 * matching script handles are listed as wp_register_script deps.
 */

const { registerBlockType } = window.wp.blocks;
const { createElement } = window.wp.element;
const { useBlockProps } = window.wp.blockEditor;
const ServerSideRender = window.wp.serverSideRender;

function Edit() {
	return createElement(
		'div',
		// Not `disabled`: the preview holds two real navigation blocks, and a
		// click inside one should select this block rather than fall through
		// to markup the editor cannot edit anyway.
		useBlockProps({ className: 'bridge-footer-preview' }),
		createElement(ServerSideRender, {
			block: 'bridge/footer',
			// The footer carries no per-post state and no attributes, so one
			// preview is always current.
			httpMethod: 'POST',
		})
	);
}

registerBlockType('bridge/footer', {
	edit: Edit,

	// Dynamic: the markup lives in render.php, and nothing is written into
	// post content.
	save() {
		return null;
	},
});
