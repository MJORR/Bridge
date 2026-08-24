/**
 * Bridge — Site Header block registration entry.
 *
 * The block has no controls. Everything it renders comes from Theme Options,
 * so its edit implementation exists only to draw the real thing: the same
 * render.php the front end uses, fetched through ServerSideRender. An operator
 * who changes the header layout sees the change here, in the page they were
 * already editing, rather than discovering it on the front end afterwards.
 *
 * Uses window.wp.* globals — Vite externalises @wordpress/* packages and the
 * matching script handles are listed as wp_register_script deps.
 */

const { registerBlockType } = window.wp.blocks;
const { createElement } = window.wp.element;
const { useSelect } = window.wp.data;
const { useBlockProps } = window.wp.blockEditor;
const ServerSideRender = window.wp.serverSideRender;

/**
 * Template slugs whose header overlays what follows it, handed over by PHP so
 * this list is stated in exactly one place.
 */
const OVERLAY_TEMPLATES = window.bridgeOverlayTemplates || [];

/**
 * The background the template currently open in the editor asks for.
 *
 * On the front end the block reads WordPress's resolved-template global, but
 * the canvas draws itself through a REST preview that resolves no template at
 * all. So the editor answers the same question from the other side — what is
 * being edited — and sends the answer along with the preview request.
 *
 * Empty for anything that is not one of those templates, which includes the
 * post editor and the header part opened on its own. The block then falls
 * back to Theme Options, exactly as it does when a visitor loads a page.
 *
 * @return {string} `transparent`, or an empty string for "no opinion".
 */
function useTemplateBackground() {
	return useSelect((select) => {
		// core/editor is the store both editors share on current WP;
		// core/edit-site is the older home of the same two answers.
		const editor = select('core/editor');
		const site = select('core/edit-site');

		const type =
			editor?.getCurrentPostType?.() ?? site?.getEditedPostType?.();
		const id = editor?.getCurrentPostId?.() ?? site?.getEditedPostId?.();

		if ('wp_template' !== type || typeof id !== 'string') {
			return '';
		}

		// Ids are `theme//slug`.
		const slug = id.split('//').pop();

		return OVERLAY_TEMPLATES.includes(slug) ? 'transparent' : '';
	}, []);
}

/**
 * A capitalised component rather than an inline `edit` method: hooks are only
 * legal inside something React recognises as a component, and the linter
 * enforces that by name.
 *
 * @param {Object} props Component props.
 */
function Edit({ attributes }) {
	const templateBackground = useTemplateBackground();

	return createElement(
		'div',
		// Not `disabled`: the preview holds a real navigation block, and
		// clicks inside it should select this block rather than fall
		// through to markup the editor cannot edit anyway.
		useBlockProps({ className: 'bridge-header-preview' }),
		createElement(ServerSideRender, {
			block: 'bridge/header',
			// Sent with the preview, never written to the block: the header
			// part is shared, and a background saved into it would follow the
			// landing page's decision onto every other template.
			attributes: {
				...attributes,
				background: attributes.background || templateBackground,
			},
			// The header carries no per-post state, so a preview fetched
			// once per attribute change is always current — and the template
			// background is one of those attributes, so switching templates
			// redraws it.
			httpMethod: 'POST',
		})
	);
}

registerBlockType('bridge/header', {
	edit: Edit,

	// Dynamic: the markup lives in render.php, and nothing is written into
	// post content.
	save() {
		return null;
	},
});
