/**
 * Bridge — Breadcrumbs block registration entry. Editor only.
 *
 * The preview is drawn here rather than fetched through ServerSideRender, and
 * that is not the usual choice for a dynamic block in this theme. A trail is
 * built from the main query — `is_singular()`, `is_archive()`, the current
 * post's ancestors — and a block-renderer request is a REST request, where
 * none of those describe the page being edited. The server preview would
 * answer "no trail here" for every page, which is the one thing the block must
 * never look like it does.
 *
 * So the canvas gets the shape rather than the contents: Home, then whatever
 * the post is currently called. The real trail, with the real ancestors, is
 * render.php's on the front end.
 *
 * Uses window.wp.* globals — Vite externalises @wordpress/* packages and the
 * matching script handles are listed as wp_register_script deps.
 */

const { registerBlockType } = window.wp.blocks;
const { createElement: el } = window.wp.element;
const { useBlockProps } = window.wp.blockEditor;
const { useSelect } = window.wp.data;
const { __ } = window.wp.i18n;

const Edit = () => {
	// The template editor edits templates, whose "title" is the template's own
	// name — "Single Posts" is not a breadcrumb. There, and before a page has
	// been named, the last step is a placeholder.
	const current = useSelect((select) => {
		const editor = select('core/editor');

		if (!editor) {
			return '';
		}

		const type = editor.getCurrentPostType();

		if ('string' === typeof type && type.startsWith('wp_template')) {
			return '';
		}

		return editor.getEditedPostAttribute('title') || '';
	}, []);

	return el(
		'nav',
		useBlockProps({ className: 'bridge-breadcrumb' }),
		el(
			'ol',
			{ className: 'bridge-breadcrumb__list' },
			el(
				'li',
				{ className: 'bridge-breadcrumb__item' },
				el('span', null, __('Home', 'bridge'))
			),
			el(
				'li',
				{ className: 'bridge-breadcrumb__item' },
				el(
					'span',
					{ 'aria-current': 'page' },
					current || __('This page', 'bridge')
				)
			)
		)
	);
};

registerBlockType('bridge/breadcrumb', {
	edit: Edit,

	// Dynamic: the markup and the JSON-LD live in render.php, and nothing is
	// written into post content.
	save() {
		return null;
	},
});
