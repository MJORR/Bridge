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

const { ToggleControl } = wp.components;
const { __ } = wp.i18n;

export function PostsTab({ draft, payload, setGroup }) {
	const { postTemplates, postImages } = payload;

	const current = draft.posts?.template || 'classic';
	const image = draft.posts?.image || 'rounded';
	// Absent means on: a token record written before these switches existed
	// should keep the trail and the byline it has been drawing, not lose them
	// on the next save. bridge_post_meta_parts() reads the same way.
	const breadcrumb = false !== draft.posts?.breadcrumb;
	const meta = draft.posts?.meta || {};
	const setMeta = (part, on) =>
		setGroup('posts', 'meta', { ...meta, [part]: on });

	return (
		<>
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

			{/*
			 * ---- Why the corner is its own picker ----------------------------
			 *
			 * A second decision about the same head, and one that is true of
			 * all three layouts at once: any of these corners is correct on
			 * any of those arrangements. Folded together they would have been
			 * nine tiles answering two questions, and an operator changing
			 * the corner would have had to find their layout again first.
			 *
			 * Drawings rather than a select, for the reason `LayoutPicker`'s
			 * own comment gives and which is even more true here: "cut
			 * corner" is not a thing anyone can picture from the words.
			 */}
			<Section
				title={__('Lead image', 'bridge')}
				description={__(
					'The corner the featured image is cut to, on every post on the site. It applies to the Classic and Feature layouts, where the photograph is a picture on a page. Cover is the exception and takes none of them: there the photograph is the band itself, bled to both edges of the window, and a band has no corners to shape.',
					'bridge'
				)}
			>
				<LayoutPicker
					label={__('Corner', 'bridge')}
					value={image}
					onChange={(value) => setGroup('posts', 'image', value)}
					options={(postImages || []).map((entry) => ({
						value: entry.slug,
						label: entry.name,
					}))}
				/>

				{/* The chosen corner's own sentence, under the picker, exactly
				 * as the layout picker above does it — and for the same
				 * reason: what a shape asks of a photograph is the part that
				 * cannot be drawn. */}
				{(postImages || [])
					.filter((entry) => entry.slug === image)
					.map((entry) => (
						<p key={entry.slug} className="bridge-options__subhelp">
							{entry.description}
						</p>
					))}
			</Section>

			{/*
			 * ---- Why the trail is a switch and not a layout ------------------
			 *
			 * It is a second decision about the head of an article, not a fourth
			 * arrangement of it: every layout draws the trail the same way, above
			 * the headline and lined up with it, and the only question is whether
			 * it is there. Six tiles would have been the same three layouts
			 * twice.
			 *
			 * Posts only. A page or an archive keeps its trail whatever this says
			 * — see bridge_breadcrumb_enabled() — because those are pages reached
			 * from inside the site, where the trail is the visitor's position.
			 */}
			<Section
				title={__('Breadcrumbs', 'bridge')}
				description={__(
					'The trail from the front page down to the article, above the headline. It is also the structured data that turns the URL line of a Google result into the path — so switching it off costs the trail in both places at once, which is deliberate: a site should not tell a search engine about a path it does not show.',
					'bridge'
				)}
			>
				<ToggleControl
					label={__('Show breadcrumbs on posts', 'bridge')}
					checked={breadcrumb}
					onChange={(on) => setGroup('posts', 'breadcrumb', on)}
					help={__(
						'Pages and archives keep their trail either way. A post is the page most often arrived at cold from a search result, which is why it is the one with a choice.',
						'bridge'
					)}
					__nextHasNoMarginBottom
				/>
			</Section>

			{/*
			 * ---- Why three switches and not a text field ---------------------
			 *
			 * The byline is not a sentence an operator writes; it is a set of
			 * facts the site either states or does not. Each of the three is a
			 * real editorial decision — an evergreen guide is worse for carrying
			 * a date, a site with one category says nothing by naming it, a
			 * single-author site has no byline worth printing — and none of them
			 * is a phrasing decision, so none of them wants a field.
			 *
			 * All three off is allowed and renders nothing at all, rule
			 * included. A byline with no facts in it is not a byline, and a rule
			 * across the top of an article separating it from nothing is worse
			 * than no rule.
			 */}
			<Section
				title={__('Byline', 'bridge')}
				description={__(
					'The line above the article, in the text column, over a rule. The parts that are on are separated by slashes, in the order below; a post with no categories contributes none rather than an empty one. Switch all three off and the line goes away entirely, rule and all.',
					'bridge'
				)}
			>
				<ToggleControl
					label={__('Date', 'bridge')}
					checked={false !== meta.date}
					onChange={(on) => setMeta('date', on)}
					help={__(
						'The day it was published. Worth switching off for writing meant to stay useful — a date is the first thing that makes a guide look stale.',
						'bridge'
					)}
					__nextHasNoMarginBottom
				/>

				<ToggleControl
					label={__('Category', 'bridge')}
					checked={false !== meta.category}
					onChange={(on) => setMeta('category', on)}
					help={__(
						'The categories the post is in, as links to their archives. Says nothing on a site where every post is in the same one.',
						'bridge'
					)}
					__nextHasNoMarginBottom
				/>

				<ToggleControl
					label={__('Author', 'bridge')}
					checked={false !== meta.author}
					onChange={(on) => setMeta('author', on)}
					help={__(
						'Who wrote it. A site that publishes as an organisation rather than as people is usually better without it.',
						'bridge'
					)}
					__nextHasNoMarginBottom
				/>
			</Section>
		</>
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
				breadcrumb={false !== draft.posts?.breadcrumb}
				meta={draft.posts?.meta}
				image={draft.posts?.image || 'rounded'}
				palette={preview?.palette}
				fontSizes={preview?.fontSizes}
				fontFamilies={preview?.fontFamilies}
				styles={preview?.styles}
				custom={preview?.custom}
			/>
		</>
	);
}
