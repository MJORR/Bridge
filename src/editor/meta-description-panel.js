/**
 * Bridge — "Search description" document panel.
 *
 * One field on the Post and Page editors, saved to the `bridge_meta_description`
 * post meta that inc/meta-description.php registers and prints as
 * `<meta name="description">` in the head of that entry.
 *
 * Plain createElement (no JSX) + window.wp.* globals, matching the rest of the
 * theme's editor scripts. The post types are the two the PHP registers the meta
 * for; a panel on a type with no meta behind it would save nothing.
 *
 * @param {Object} wp The WordPress globals this file is wrapped around.
 */

(function (wp) {
	const { registerPlugin } = wp.plugins;
	const { createElement: el, Fragment } = wp.element;
	const { useSelect } = wp.data;
	const { useEntityProp } = wp.coreData;
	const { TextareaControl } = wp.components;
	const VStack = wp.components.__experimentalVStack || Fragment;
	const { __, sprintf, _n } = wp.i18n;

	// PluginDocumentSettingPanel lives in @wordpress/editor on current WP and
	// in @wordpress/edit-post on older releases — support whichever is present.
	// The same guard the Title Banner panel makes, for the same reason.
	const PluginDocumentSettingPanel =
		(wp.editor && wp.editor.PluginDocumentSettingPanel) ||
		(wp.editPost && wp.editPost.PluginDocumentSettingPanel);

	if (!registerPlugin || !PluginDocumentSettingPanel) {
		return;
	}

	const META_KEY = 'bridge_meta_description';

	// The same number bridge_sanitize_meta_description() cuts at, and the same
	// reason: Google truncates a snippet at around this on desktop and less on
	// a phone. Written twice because a JS constant cannot be read from PHP
	// without printing it into the page, which is a request per screen to save
	// one line — but the PHP is the authority, and it is what actually holds.
	const MAX = 160;

	const Panel = () => {
		const postType = useSelect(
			(select) => select('core/editor')?.getCurrentPostType(),
			[]
		);

		const [meta, setMeta] = useEntityProp('postType', postType, 'meta');

		// Undefined until the entity has loaded, and on a post type registered
		// with no meta at all. Nothing else is checked here: the script is
		// enqueued only on the screens whose post type carries the field —
		// bridge_enqueue_meta_description_panel() reads the same list the meta
		// is registered from — so a second guess at it here would be a second
		// place to keep in step.
		if (!meta) {
			return null;
		}

		const value = meta[META_KEY] || '';
		const left = MAX - value.length;

		/*
		 * What the counter says, and why it is three sentences rather than a
		 * number.
		 *
		 * Empty is the state worth explaining: the field does nothing until it
		 * is filled in, and an editor who leaves it empty should know that is a
		 * decision rather than an oversight. Past the limit is the other one —
		 * the value is cut on save, and saying so beforehand is the difference
		 * between a rule and a surprise.
		 */
		let help;

		if (value === '') {
			help = __(
				'The sentence a search engine shows under this page’s title. Left empty, the search engine writes its own from the page — which is usually the first thing on it.',
				'bridge'
			);
		} else if (left < 0) {
			help = sprintf(
				/* translators: %d: number of characters over the limit. */
				_n(
					'%d character too long — it will be trimmed to the last whole word when you save.',
					'%d characters too long — it will be trimmed to the last whole word when you save.',
					Math.abs(left),
					'bridge'
				),
				Math.abs(left)
			);
		} else {
			help = sprintf(
				/* translators: %d: number of characters still available. */
				_n('%d character left.', '%d characters left.', left, 'bridge'),
				left
			);
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'bridge-meta-description',
				title: __('Search description', 'bridge'),
				className: 'bridge-meta-description-panel',
			},
			el(
				VStack,
				{ spacing: 4 },
				el(TextareaControl, {
					label: __('Meta description', 'bridge'),
					value,
					rows: 4,
					help,
					// No `maxLength`. A hard stop in the middle of a word an
					// editor is still typing is a field that fights them; the
					// counter says where the line is and the sanitiser holds it
					// on save, at the last whole word.
					onChange: (next) => setMeta({ ...meta, [META_KEY]: next }),
					__nextHasNoMarginBottom: true,
				})
			)
		);
	};

	registerPlugin('bridge-meta-description-panel', { render: Panel });
})(window.wp);
