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

const { RangeControl, ToggleControl } = wp.components;
const { __ } = wp.i18n;

export function PostsTab({ draft, payload, setGroup }) {
	const { postTemplates, postImages, constraints } = payload;
	const coverLimits = constraints?.posts?.coverMaxHeight || {
		min: 20,
		max: 100,
		step: 1,
	};

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
					'Where the featured image sits on posts.',
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

				{/* Cover only: the other two layouts take their height from
				 * the photograph or a fixed ratio, so there is nothing here
				 * for them to cap. */}
				{current === 'cover' && (
					<RangeControl
						label={__('Cover maximum height (vh)', 'bridge')}
						value={draft.posts?.coverMaxHeight ?? 52}
						min={coverLimits.min}
						max={coverLimits.max}
						step={coverLimits.step}
						onChange={(value) =>
							setGroup('posts', 'coverMaxHeight', value ?? 52)
						}
						help={__(
							'Height of the image band, as a percentage of the screen height.',
							'bridge'
						)}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				)}
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
					'Corner style for the featured image. Not used by the Cover layout.',
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
			 * ---- Why a switch and not a colour picker ------------------------
			 *
			 * The question is whether an article is set apart from the rest of
			 * the site, and the palette already has the slug whose job that is:
			 * `surface` is the near-neighbour of the page colour. A free choice
			 * of six would let a long read end up on `primary` with body text
			 * over it, which is not a setting so much as a way to break one
			 * template.
			 */}
			<Section
				title={__('Background', 'bridge')}
				description={__(
					'Background colour for single posts only.',
					'bridge'
				)}
			>
				<ToggleControl
					label={__('Surface background', 'bridge')}
					checked={Boolean(draft.posts?.surface)}
					onChange={(on) => setGroup('posts', 'surface', on)}
					help={__(
						'Uses the Surface colour behind posts instead of the page colour.',
						'bridge'
					)}
					__nextHasNoMarginBottom
				/>
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
					'Shows the breadcrumb trail above the headline on posts.',
					'bridge'
				)}
			>
				<ToggleControl
					label={__('Show breadcrumbs on posts', 'bridge')}
					checked={breadcrumb}
					onChange={(on) => setGroup('posts', 'breadcrumb', on)}
					help={__(
						'Pages and archives always show their breadcrumbs.',
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
					'The line above the article. Switch all three off to hide it.',
					'bridge'
				)}
			>
				<ToggleControl
					label={__('Date', 'bridge')}
					checked={false !== meta.date}
					onChange={(on) => setMeta('date', on)}
					help={__('Shows the publish date.', 'bridge')}
					__nextHasNoMarginBottom
				/>

				<ToggleControl
					label={__('Category', 'bridge')}
					checked={false !== meta.category}
					onChange={(on) => setMeta('category', on)}
					help={__("Shows the post's categories as links.", 'bridge')}
					__nextHasNoMarginBottom
				/>

				<ToggleControl
					label={__('Author', 'bridge')}
					checked={false !== meta.author}
					onChange={(on) => setMeta('author', on)}
					help={__("Shows the author's name.", 'bridge')}
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
				surface={Boolean(draft.posts?.surface)}
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
