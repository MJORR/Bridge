/**
 * Bridge — Theme Options, the Posts tab.
 *
 * How a single post is laid out, for every post on the site.
 *
 * ---- Why one setting rather than a picker on each post ---------------------
 *
 * The three layouts are deliberately *not* registered in theme.json's
 * `customTemplates`, which is what would put a template dropdown in each post's
 * sidebar. A site whose articles are laid out three different ways is a site
 * with no article design — the reader learns the shape of a post once and every
 * page after that is easier to read for it. The decision belongs to whoever
 * owns the design system, made once, which is what this record is.
 *
 * ---- Why a picker of drawings rather than a select -------------------------
 *
 * The same reason the header layouts use one, and the comment on
 * `LayoutPicker` says it better than this one could: the difference between
 * these three is entirely visual, and a dropdown makes you choose before you
 * can look.
 *
 * The drawings are schematic; the sidebar is where the real thing is, drawn
 * from the compiled palette and type scale.
 */

import { LayoutPicker, Section } from '../controls';
import { PostTemplatePreview } from '../preview';

const { __ } = wp.i18n;

export function PostsTab({ draft, payload, setGroup }) {
	const { postTemplates } = payload;

	const current = draft.posts?.template || 'classic';

	return (
		<Section
			title={__('Article layout', 'bridge')}
			description={__(
				'Where the featured image sits relative to the headline. One choice for every post on the site — a reader learns the shape of an article once, and every page after that is easier for it. All three are the same blocks and take the site’s own palette and type scale; only the arrangement changes.',
				'bridge'
			)}
		>
			<LayoutPicker
				label={__('Layout', 'bridge')}
				value={current}
				onChange={(value) => setGroup('posts', 'template', value)}
				options={(postTemplates || []).map((entry) => ({
					value: entry.slug,
					label: entry.name,
				}))}
			/>

			{/*
			 * The chosen layout's own sentence, under the picker rather than as
			 * a hint inside each tile. Each is a paragraph about what the
			 * layout asks of a photograph, which is the part an operator cannot
			 * see in a drawing and the part they will regret not reading.
			 */}
			{(postTemplates || [])
				.filter((entry) => entry.slug === current)
				.map((entry) => (
					<p key={entry.slug} className="bridge-options__subhelp">
						{entry.description}
					</p>
				))}
		</Section>
	);
}

/**
 * The Posts tab's sidebar.
 *
 * @param {Object} props Component props.
 */
export function PostsPreview({ draft, preview }) {
	return (
		<>
			<h3>{__('A post, in this layout', 'bridge')}</h3>
			<PostTemplatePreview
				template={draft.posts?.template || 'classic'}
				palette={preview?.palette}
				fontSizes={preview?.fontSizes}
				fontFamilies={preview?.fontFamilies}
				styles={preview?.styles}
				custom={preview?.custom}
			/>
		</>
	);
}
