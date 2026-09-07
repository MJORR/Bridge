/**
 * Bridge — Post Meta block registration entry. Editor only.
 *
 * The canvas draws the shape rather than fetching the real byline, for the
 * same reason the breadcrumb does: a block-renderer request is a REST request
 * with no post in the loop, so `get_the_date()` and `get_the_author()` on the
 * other end would be describing whatever the REST request happened to be
 * about, which on a template is nothing at all. The dates and names below are
 * placeholders and are meant to read as placeholders.
 *
 * What is *not* a placeholder is which of the three appear. They come from
 * `window.bridgePostMeta`, printed beside this script from the token record,
 * so an operator who switched the author off sees a byline without one here
 * too — the one thing about this block that is a decision rather than a
 * sample.
 *
 * Uses window.wp.* globals — Vite externalises @wordpress/* packages and the
 * matching script handles are listed as wp_register_script deps.
 */

const { registerBlockType } = window.wp.blocks;
const { createElement: el } = window.wp.element;
const { useBlockProps } = window.wp.blockEditor;
const { __ } = window.wp.i18n;

// Absent means on, matching bridge_post_meta_parts(): the global is printed
// from the token record, but a script loaded without it should draw the whole
// byline rather than none of it.
const settings = window.bridgePostMeta || {};
const on = (part) => false !== settings[part];

const Edit = () => {
	// Called once and before any branch: the byline has two shapes and a hook
	// may not be reached conditionally, whichever one is drawn.
	const blockProps = useBlockProps({ className: 'bridge-article__meta' });

	const items = [];

	if (on('date')) {
		items.push(__('12 August 2026', 'bridge'));
	}

	if (on('category')) {
		items.push(__('Category', 'bridge'));
	}

	if (on('author')) {
		items.push(__('The author', 'bridge'));
	}

	// All three off is a byline that renders nothing on the front end, so the
	// canvas says so in words rather than showing an empty box the editor
	// would take for a loading state.
	if (!items.length) {
		return el(
			'div',
			blockProps,
			el(
				'span',
				{ className: 'bridge-article__meta-item' },
				__(
					'No byline — the date, category and author are all switched off in Theme Options.',
					'bridge'
				)
			)
		);
	}

	return el(
		'div',
		blockProps,
		items.map((label) =>
			el(
				'span',
				{ key: label, className: 'bridge-article__meta-item' },
				label
			)
		)
	);
};

registerBlockType('bridge/post-meta', {
	edit: Edit,

	// Dynamic: render.php draws the real byline, and nothing is written into
	// post content.
	save() {
		return null;
	},
});
