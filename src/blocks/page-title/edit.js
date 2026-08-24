/**
 * Editor view for `bridge/page-title`.
 *
 * Mirrors the cards/hero-slider pattern: plain createElement (no JSX),
 * window.wp.* globals. The preview is rendered by <ServerSideRender>, which
 * calls render.php via REST — the same code path as the frontend, so the
 * editor shows the banner exactly as visitors will see it.
 *
 * The per-page banner settings (style, alignment, colour) live in post meta
 * and are edited from the "Title Banner" document panel, not on the block
 * itself — this block carries no attributes.
 */

const { useBlockProps } = window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { Placeholder, Spinner } = window.wp.components;
const { useSelect } = window.wp.data;
const { __ } = window.wp.i18n;
const ServerSideRender = window.wp.serverSideRender;

const BLOCK_NAME = 'bridge/page-title';

const Edit = () => {
	const blockProps = useBlockProps();

	// Current post ID — passed to SSR so render.php resolves the real page
	// (and its title) instead of falling back to the sample preview.
	const postId = useSelect(
		(select) => select('core/editor')?.getCurrentPostId() ?? null,
		[]
	);

	// Live banner settings from the edited (unsaved) post meta. Selecting a
	// banner in the "Title Banner" panel updates this immediately, so the
	// preview reflects the choice before the page is saved.
	const meta = useSelect(
		(select) => select('core/editor')?.getEditedPostAttribute('meta') || {},
		[]
	);

	const ssrProps = {
		block: BLOCK_NAME,
		// Forwarded to render.php as a live-preview override of the saved meta.
		attributes: {
			isEditorPreview: true,
			previewStyle: meta.bridge_banner_style || 'none',
			previewAlign: meta.bridge_title_align || 'left',
			previewColor: meta.bridge_banner_color || '',
		},
		LoadingResponsePlaceholder: () =>
			el(Placeholder, { label: __('Page Title', 'bridge') }, el(Spinner)),
		ErrorResponsePlaceholder: ({ response }) =>
			el(
				Placeholder,
				{ label: __('Page Title', 'bridge') },
				response?.errorMsg || __('Could not render preview.', 'bridge')
			),
	};

	if (postId) {
		ssrProps.urlQueryArgs = { post_id: postId };
	}

	return el(
		Fragment,
		null,
		el('div', blockProps, el(ServerSideRender, ssrProps))
	);
};

export default Edit;
