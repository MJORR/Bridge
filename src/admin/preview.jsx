/**
 * Bridge — Theme Options, the previews.
 *
 * What the screen draws rather than what it edits: the type scale, the brand,
 * the cards and the buttons, each rendered from the values the server compiled
 * rather than from a description of them. None of these take an `onChange` —
 * the one exception is ButtonSkinPicker, which is a control drawn out of real
 * buttons and would be a stranger anywhere else.
 *
 * The compiled values matter. wp-admin prints no global styles, so a preview
 * that named `var(--wp--preset--spacing--40)` would resolve to nothing here;
 * every sample sets the properties it needs inline, from the preview response.
 */

import { SPECIMENS, groundVars, skinVars, stackFor } from './lib';

const { __, sprintf } = wp.i18n;

/**
 * Type scale, rendered at the sizes and in the faces the server compiled.
 *
 * @param {Object} props Component props.
 */
export function TypePreview({ fontSizes, fontFamilies, styles }) {
	const body = stackFor(fontFamilies, 'sans');
	// Headings render in the heading family, matching what the site does —
	// showing the whole scale in the body face hid half of a font change.
	const heading = stackFor(fontFamilies, 'heading') || body;
	const headingCase =
		styles?.elements?.heading?.typography?.textTransform || 'none';
	const headingWeight =
		styles?.elements?.heading?.typography?.fontWeight || '600';

	return (
		<div className="bridge-preview__scale">
			{[...fontSizes].reverse().map((size) => {
				const isHeading =
					size.slug !== 'small' && size.slug !== 'medium';

				return (
					<div key={size.slug} className="bridge-preview__step">
						<div className="bridge-preview__step-meta">
							<code>{size.slug}</code>
							<span>
								{size.size}
								{size.fluid &&
									` · ${size.fluid.min}–${size.fluid.max}`}
							</span>
						</div>
						<div
							className="bridge-preview__specimen"
							style={{
								fontFamily: isHeading ? heading : body,
								fontSize: size.size,
								lineHeight: isHeading
									? styles?.elements?.heading?.typography
											?.lineHeight
									: styles?.typography?.lineHeight,
								fontWeight: isHeading ? headingWeight : '400',
								textTransform: isHeading ? headingCase : 'none',
							}}
						>
							{SPECIMENS[size.slug] || size.name}
						</div>
					</div>
				);
			})}
		</div>
	);
}

/**
 * Palette applied to a miniature of the components clients actually see.
 *
 * @param {Object} props Component props.
 */
export function BrandPreview({ palette, fontFamilies }) {
	const color = (slug) => palette?.find((c) => c.slug === slug)?.color;
	const body = stackFor(fontFamilies, 'sans');
	const heading = stackFor(fontFamilies, 'heading') || body;

	return (
		<div className="bridge-preview__brand" style={{ fontFamily: body }}>
			<div className="bridge-preview__swatches">
				{palette?.map((c) => (
					<div key={c.slug} className="bridge-preview__swatch">
						<span style={{ background: c.color }} />
						<code>{c.slug}</code>
					</div>
				))}
			</div>

			<div
				className="bridge-preview__card"
				style={{
					background: color('background'),
					color: color('text'),
				}}
			>
				<strong
					style={{ color: color('primary'), fontFamily: heading }}
				>
					{__('A heading in Primary', 'bridge')}
				</strong>
				<p>
					{__('Body copy in Text, with', 'bridge')}{' '}
					<a
						href="#0"
						style={{ color: color('secondary') }}
						onClick={(e) => e.preventDefault()}
					>
						{__('a link in Secondary', 'bridge')}
					</a>
					.
				</p>
				<div
					className="bridge-preview__panel"
					style={{ background: color('surface') }}
				>
					{__('A Surface panel', 'bridge')}
				</div>
				<span
					className="bridge-preview__button"
					style={{
						background: color('primary'),
						color: color('background'),
					}}
				>
					{__('Primary button', 'bridge')}
				</span>
				<span
					className="bridge-preview__button"
					style={{
						background: color('accent'),
						color: color('text'),
					}}
				>
					{__('Accent button', 'bridge')}
				</span>
			</div>
		</div>
	);
}

/**
 * Both icon weights, side by side, with the active one marked.
 *
 * Shown together rather than one at a time because the choice is comparative —
 * "regular" and "bold" mean nothing in isolation, and a single row would leave
 * the operator toggling back and forth to see the difference.
 *
 * dangerouslySetInnerHTML is the only route for raw SVG path data in React,
 * and it is safe here: the geometry is compiled from files in the theme by the
 * build, never from anything a user typed.
 *
 * @param {Object} props Component props.
 */
export function IconPreview({ icons, weights, active }) {
	const sample = [
		'arrow-right',
		'check',
		'search',
		'mail',
		'calendar',
		'user',
		'star',
		'play',
	].filter((name) => icons && icons[name]);

	if (!sample.length) {
		return (
			<p className="bridge-preview__empty">
				{__('No icons compiled yet — run the build.', 'bridge')}
			</p>
		);
	}

	return (
		<div className="bridge-preview__icons">
			{Object.entries(weights || {}).map(([weight, stroke]) => (
				<div
					key={weight}
					className={`bridge-preview__icon-row${weight === active ? ' is-active' : ''}`}
				>
					<span className="bridge-preview__icon-label">
						{weight === 'regular'
							? __('Regular', 'bridge')
							: __('Bold', 'bridge')}
						{weight === active && <em>{__('in use', 'bridge')}</em>}
					</span>
					<span className="bridge-preview__icon-set">
						{sample.map((name) => (
							<svg
								key={name}
								viewBox="0 0 24 24"
								width="20"
								height="20"
								fill="none"
								stroke="currentColor"
								strokeWidth={stroke}
								strokeLinecap="round"
								strokeLinejoin="round"
								aria-hidden="true"
								focusable="false"
								dangerouslySetInnerHTML={{
									__html: icons[name],
								}}
							/>
						))}
					</span>
				</div>
			))}
		</div>
	);
}

/**
 * Content and wide widths, drawn to scale against each other.
 *
 * @param {Object} props Component props.
 */
export function LayoutPreview({ layout }) {
	const content = parseInt(layout?.contentSize, 10) || 0;
	const wide = parseInt(layout?.wideSize, 10) || 0;
	const max = Math.max(content, wide) || 1;

	return (
		<div className="bridge-preview__layout">
			{[
				{ label: __('Wide', 'bridge'), value: wide },
				{ label: __('Content', 'bridge'), value: content },
			].map(({ label, value }) => (
				<div key={label} className="bridge-preview__bar">
					<span className="bridge-preview__bar-label">{label}</span>
					<span className="bridge-preview__bar-track">
						<span
							className="bridge-preview__bar-fill"
							style={{ width: `${(value / max) * 100}%` }}
						/>
					</span>
					<span className="bridge-preview__bar-value">{value}px</span>
				</div>
			))}
		</div>
	);
}

/**
 * The card surface the compiler is about to publish, in the terms a preview
 * can draw it in.
 *
 * Three places were deciding what a cut corner does to a card — the compiler,
 * and each of the two previews below — and the previews had already drifted
 * from it and from each other: one drew the notch and the other squared the
 * corner and drew nothing, so an operator moving "Corner cut" watched a
 * control that appeared to do nothing. One function now, mirroring the `$cut`
 * branch of bridge_compile_theme_json() and named after it, so the next change
 * to that branch has one place to land on this side.
 *
 * The three answers it carries are the compiler's, for the compiler's reasons:
 * a card cannot be both cut and rounded, so the cut squares it; a `box-shadow`
 * is drawn around the card's *box* and would trace the very corner the cut
 * removed, so a cut card has none.
 *
 * `cqw` rather than a percentage, which is the one part of this that is not
 * obvious. A polygon's percentages resolve per axis, so `12%` is 12% of the
 * width across and 12% of the *height* down — a 45° cut only on a square card,
 * and these samples are not square. `cqw` is 1% of the container's inline size
 * on both axes, which is what makes the angle 45°, and it is what the
 * stylesheet uses. It needs a container to measure, so the card asks to be one
 * — but only while the cut is on, because containment fixes an element's
 * inline size against its contents and is not worth paying for a corner
 * nobody has turned on.
 *
 * @param {Object} cards   The card tokens being previewed.
 * @param {Array}  shadows The shadow presets, from the payload.
 * @return {Object} `cut`, `radius`, `shadow`, `clip` and `container`.
 */
function cardSurface(cards, shadows) {
	const cut = Boolean(cards.cutCorner);
	const preset = shadows?.find((s) => s.slug === cards.shadow);

	return {
		cut,
		radius: cut ? '0px' : `${cards.radius}px`,
		shadow: cut ? 'none' : preset?.shadow || 'none',
		clip: cut
			? `polygon(0 ${cards.cutSize}cqw, ${cards.cutSize}cqw 0, 100% 0, 100% 100%, 0 100%)`
			: 'none',
		container: cut ? 'inline-size' : undefined,
	};
}

/**
 * A card, drawn at the padding, radius and shadow currently chosen.
 *
 * Drawn rather than described, because "Medium" is not a shadow and no
 * operator can picture 10px of corner. The values are the compiled ones — the
 * spacing preset resolved to the clamp it becomes, not the slug — so what is
 * on screen here is what a downloads card looks like on the site.
 *
 * One card per ground, because that is the shape of the decision: the same
 * card is drawn four times, on the page's own background and on each of the
 * three section skins, and each has its own colour. Shown together rather than
 * one at a time so a colour that vanishes into its band — or takes the band's
 * text down with it — is obvious while it is being picked.
 *
 * @param {Object} props Component props.
 */
export function CardPreview({
	cards,
	grounds,
	spacingSizes,
	shadows,
	palette,
}) {
	const color = (slug) => palette?.find((c) => c.slug === slug)?.color;
	const padding =
		spacingSizes?.find((s) => String(s.slug) === String(cards.padding))
			?.size || '1.5rem';
	const { cut, radius, shadow, clip, container } = cardSurface(
		cards,
		shadows
	);

	return (
		<div className="bridge-preview__cards">
			{(grounds || []).map((entry) => (
				<div
					key={entry.key}
					className="bridge-preview__card-band"
					style={{
						background: color(entry.ground),
						// The band's foreground, which the card inherits
						// rather than naming — the same arrangement the site
						// uses, so a card colour that swallows its own text
						// is visible here before it is saved.
						color: color(entry.text),
					}}
				>
					<span className="bridge-preview__card-band-label">
						{entry.label}
					</span>
					<div
						className="bridge-preview__card-sample"
						style={{
							padding,
							borderRadius: radius,
							boxShadow: shadow,
							// Once the corner is cut the colour moves to the
							// layer below, which is the thing that can be
							// clipped. Clipping the card itself would take its
							// words with it, and at the widest cut the notch
							// reaches where the title is.
							background: cut
								? 'transparent'
								: cards.colors?.[entry.key],
							// That layer sits at `z-index: -1`, which only
							// stays inside the card in an element that
							// establishes a stacking context — the same reason
							// abstracts/_card.scss isolates. And the card is
							// the container the cut is measured against.
							position: 'relative',
							isolation: 'isolate',
							containerType: container,
						}}
					>
						{cut && (
							<span
								aria-hidden="true"
								style={{
									position: 'absolute',
									inset: 0,
									zIndex: -1,
									background: cards.colors?.[entry.key],
									clipPath: clip,
								}}
							/>
						)}
						<span
							className="bridge-preview__card-media"
							style={{
								borderRadius: `max(0px, calc(${radius} - 2px))`,
								background:
									'color-mix(in srgb, currentcolor 8%, transparent)',
							}}
						/>
						<strong>{__('Card title', 'bridge')}</strong>
						<p>
							{__(
								'The words a card carries take the colour of the band around them.',
								'bridge'
							)}
						</p>
					</div>
				</div>
			))}
		</div>
	);
}

/**
 * Every card style, on every ground.
 *
 * Twelve cards, which is the number that matters. The three styles differ in
 * the things that collide with a background — a Tile's chip is the palette's
 * Accent, a Team's arch *is* the card colour, a Summary's read-more is the
 * link colour — and each of the four grounds paints a different card. A row of
 * one style on one ground cannot show any of that; a grid can, before it is
 * saved rather than after a client builds the page.
 *
 * Drawn from the draft and the compiled presets together: the slug an operator
 * just chose, resolved through the preset list the server compiled. Nothing
 * here recomputes a scale — a second implementation of the type ratio would be
 * the one on screen and the one that drifted.
 *
 * Schematic rather than a faithful copy of the block's markup. The point is
 * which shapes and colours meet, and a preview that reimplemented the card's
 * stylesheet would be a second stylesheet to keep in step.
 *
 * @param {Object} props Component props.
 */
export function CardStylePreview({
	cards,
	styles,
	grounds,
	ratios,
	avatars,
	shadows,
	spacingSizes,
	fontSizes,
	palette,
	custom,
}) {
	const color = (slug) => palette?.find((c) => c.slug === slug)?.color;

	/**
	 * How far down from life size the drawing is.
	 *
	 * Twelve cards in a four-hundred-pixel sidebar is a hundred pixels each,
	 * and a card heading set at its real 1.95rem in a hundred-pixel box is a
	 * clipped word rather than a preview. Every length below is multiplied by
	 * this — the padding and the corner as well as the type — so what is on
	 * screen is the card reduced rather than the card with its type shrunk and
	 * its padding left alone, which would misreport both.
	 *
	 * It is a comparison, not a measurement: what an operator reads off it is
	 * that a Tile heading is larger than a Summary one and that neither
	 * overruns its card. The Design tab's type preview is where the real sizes
	 * are shown at size.
	 *
	 * 0.75 is calibrated to the Cards tab's half-width column — see
	 * `.bridge-options--split`. A narrower sidebar would want less; this is the
	 * one number to move if that share changes.
	 */
	const zoom = 0.75;
	const scaled = (length) => `calc(${length} * ${zoom})`;

	// The compiled clamp, not the slug: wp-admin prints no global styles, so a
	// `var(--wp--preset--*)` on this screen resolves to nothing.
	const fontSize = (slug) =>
		fontSizes?.find((f) => f.slug === slug)?.size || '1rem';

	const padding = scaled(
		spacingSizes?.find((s) => String(s.slug) === String(cards.padding))
			?.size || '1.5rem'
	);
	// The cut, the radius, the shadow and the polygon, from the one function
	// that mirrors the compiler — see cardSurface() above. Resolved once, at
	// the top, because the radius is spent in three places below and a preview
	// that squared the card but not the panel inside it would be showing a
	// card the site never draws.
	//
	// The radius is the only one this preview scales: these samples are drawn
	// smaller than a real card, and a 24px corner on a card a third of the size
	// is a different shape rather than the same one seen from further away.
	const {
		cut,
		radius: cardRadius,
		shadow,
		clip,
		container,
	} = cardSurface(cards, shadows);
	const radius = scaled(cardRadius);

	const settings = (slug) => cards.styles?.[slug] || {};

	const ratioValue = (slug) =>
		ratios?.find((r) => r.slug === slug)?.value || '16 / 9';
	const avatarWidth = (slug) =>
		avatars?.find((a) => a.slug === slug)?.width || 'min(72%, 14rem)';

	return (
		<div className="bridge-preview__cardstyles">
			{(grounds || []).map((ground) => (
				<div
					key={ground.key}
					className="bridge-preview__card-band"
					style={{
						background: color(ground.ground),
						// The band's foreground, which every card inherits
						// rather than naming — the same arrangement the site
						// uses, so a card colour that swallows its own text is
						// visible here before it is saved.
						color: color(ground.text),
					}}
				>
					<span className="bridge-preview__card-band-label">
						{ground.label}
					</span>

					<div className="bridge-preview__cardstyle-row">
						{(styles || []).map((style) => {
							const set = settings(style.slug);
							const surface = cards.colors?.[ground.key];
							const heading = {
								fontSize: scaled(fontSize(set.heading)),
								lineHeight: 1.2,
								display: 'block',
							};

							// Team paints its children rather than itself,
							// so the arch and the panel are one colour and the
							// card behind them is nothing.
							const isTeam = 'team' === style.slug;

							// Cover paints the photograph across the whole
							// card and washes it in the Inverted colour, so
							// its ground is Primary rather than the card
							// surface this ground would otherwise give it.
							const isCover = 'cover' === style.slug;

							// One lookup rather than a chain of conditionals:
							// three styles, three answers, and a fourth is a
							// line here rather than another nested branch.
							// The chosen wash, and the ink the compiler derived
							// from it — read out of the preview response rather
							// than recomputed here, so the contrast shown is the
							// contrast the server checked.
							const washFor = (slug) =>
								color(slug) || color('primary');
							const coverInk =
								custom?.card?.cover?.ink || color('background');

							const groundFor = (slug) =>
								({
									team: 'transparent',
									cover: washFor(set.wash),
								})[slug] ?? surface;

							return (
								<div
									key={style.slug}
									className="bridge-preview__cardstyle"
									style={{
										borderRadius: radius,
										boxShadow: isTeam ? 'none' : shadow,
										// The surface below is placed against
										// the card, and has to stay inside it:
										// it sits at z-index -1, which only
										// stays put in an element that
										// establishes a stacking context. The
										// card's own stylesheet isolates for
										// the same reason.
										position: 'relative',
										isolation: 'isolate',
										// What the cut is measured against:
										// `cqw` is a share of this element's
										// inline size, on both axes, which is
										// what holds the notch at 45°.
										containerType: container,
										aspectRatio: isCover
											? ratioValue(set.ratio)
											: undefined,
										// Team paints nothing of its own,
										// Cover paints the Inverted ground the
										// wash resolves to, and the rest take
										// this ground's card surface. Once the
										// corner is cut this moves to the
										// layer below, which is the thing that
										// can be clipped.
										background: cut
											? 'transparent'
											: groundFor(style.slug),
									}}
								>
									{/* eslint-disable-next-line no-nested-ternary -- three sibling branches of one switch; a lookup here would mean building three trees eagerly. */}
									{isCover ? (
										<div
											className="bridge-preview__cardstyle-cover"
											style={{ clipPath: clip }}
										>
											{/*
											 * The wash, drawn with the same
											 * stops the stylesheet uses so the
											 * contrast under the title here is
											 * the contrast on the page.
											 */}
											<span
												className="bridge-preview__cardstyle-wash"
												style={{
													background: `linear-gradient(to top, ${washFor(set.wash)} 0%, color-mix(in srgb, ${washFor(set.wash)} 88%, transparent) 28%, color-mix(in srgb, ${washFor(set.wash)} 45%, transparent) 55%, color-mix(in srgb, ${washFor(set.wash)} 12%, transparent) 78%, transparent 100%)`,
												}}
											/>
											<div
												className="bridge-preview__cardstyle-coverbody"
												style={{
													padding,
													// Derived from the wash by
													// the compiler; see the
													// `wash` case in
													// bridge_compile_theme_json().
													color: coverInk,
												}}
											>
												<strong style={heading}>
													{style.name}
												</strong>
												<span
													className="bridge-preview__cardstyle-go"
													aria-hidden="true"
												>
													{'\u2192'}
												</span>
											</div>
										</div>
									) : isTeam ? (
										<>
											{/*
											 * The same arithmetic the card
											 * itself does — the circle pulled
											 * down into the panel by a
											 * fraction of its own width, and
											 * the panel padded by the mirror
											 * of it. Written out here rather
											 * than approximated, so the arch
											 * on screen is the arch the site
											 * draws at whichever size is
											 * chosen.
											 */}
											<span
												className="bridge-preview__cardstyle-avatar"
												style={{
													width: avatarWidth(
														set.avatar
													),
													marginBottom: `calc(${avatarWidth(set.avatar)} * -0.42)`,
													background: surface,
												}}
											/>
											<div
												className="bridge-preview__cardstyle-panel"
												style={{
													background: surface,
													padding: `calc(${avatarWidth(set.avatar)} * 0.42 + ${padding}) ${padding} ${padding}`,
													borderRadius: `0 0 ${radius} ${radius}`,
												}}
											>
												<strong style={heading}>
													{__('Name', 'bridge')}
												</strong>
												<span className="bridge-preview__cardstyle-sub">
													{__('Role', 'bridge')}
												</span>
												<span
													className="bridge-preview__cardstyle-button"
													style={{
														background:
															color('accent'),
														color: color('text'),
													}}
												>
													{__('Button', 'bridge')}
												</span>
											</div>
										</>
									) : (
										<>
											<span
												className="bridge-preview__cardstyle-media"
												style={{
													aspectRatio: ratioValue(
														set.ratio
													),
													// Cut to match the surface
													// under it — the same job
													// `card.cut-corner-child`
													// does on the real card.
													clipPath: clip,
												}}
											>
												{'tile' === style.slug && (
													<span
														className="bridge-preview__cardstyle-badge"
														style={{
															background:
																color('accent'),
															color: color(
																'text'
															),
														}}
													>
														{__('1.3 mi', 'bridge')}
													</span>
												)}
											</span>
											<div
												className="bridge-preview__cardstyle-body"
												style={{ padding }}
											>
												<strong style={heading}>
													{style.name}
												</strong>
												<p>
													{__(
														'The words take the band’s colour.',
														'bridge'
													)}
												</p>
												{'summary' === style.slug && (
													<span
														className="bridge-preview__cardstyle-more"
														style={{
															// What
															// `--post-card-accent`
															// resolves to: the
															// link colour,
															// except on the
															// Inverted ground
															// — keyed `primary`
															// — where the skin
															// repoints it at
															// the band's own
															// foreground.
															color:
																'primary' ===
																ground.key
																	? 'currentcolor'
																	: color(
																			'secondary'
																		),
														}}
													>
														{__(
															'Read more →',
															'bridge'
														)}
													</span>
												)}
											</div>
											{'tile' === style.slug && (
												<span
													className="bridge-preview__cardstyle-rule"
													style={{
														background:
															color('secondary'),
													}}
												/>
											)}
										</>
									)}

									{/*
									 * The surface, clipped to the cut — the
									 * card's `::before` on the site. Behind the
									 * content and in front of the card's own
									 * (now clear) background, which is where a
									 * negative index puts it. Not on Team,
									 * which paints no rectangle for a corner to
									 * be taken off: the same exemption
									 * blocks/cards/_card-team.scss makes.
									 */}
									{cut && !isTeam && (
										<span
											aria-hidden="true"
											style={{
												position: 'absolute',
												inset: 0,
												zIndex: -1,
												background: groundFor(
													style.slug
												),
												clipPath: clip,
											}}
										/>
									)}
								</div>
							);
						})}
					</div>
				</div>
			))}
		</div>
	);
}

/**
 * A single post, in the chosen layout.
 *
 * Drawn from the compiled palette, type scale and faces rather than from a
 * fixed grey mock, so what an operator sees is their own brand's article —
 * the headline in the heading face at the real h1 size, the byline at the
 * `small` preset, the corner at the card radius.
 *
 * The photograph is a flat swatch rather than a stock image. A photograph in a
 * preview is a photograph an operator judges the layout by, and every real one
 * will be different; the block says where the picture goes, which is the whole
 * of the decision. Cover is the exception and has to be: the scrim over it is
 * the thing that makes the layout legible or not, so the swatch is darkened
 * there exactly as the stylesheet darkens the real image.
 *
 * @param {Object} props Component props.
 */
export function PostTemplatePreview({
	template,
	breadcrumb = true,
	meta,
	image = 'rounded',
	palette,
	fontSizes,
	fontFamilies,
	styles,
	custom,
}) {
	const color = (slug) => palette?.find((c) => c.slug === slug)?.color;
	const size = (slug) => fontSizes?.find((f) => f.slug === slug)?.size;

	const body = stackFor(fontFamilies, 'sans');
	const heading = stackFor(fontFamilies, 'heading') || body;
	const radius = custom?.card?.radius || '6px';

	// The h1 is the top of the scale. Scaled down like the card preview's, and
	// for the same reason: a 3.5rem headline in a 30rem sidebar is one word.
	const zoom = 0.42;
	const scaled = (length) => `calc(${length} * ${zoom})`;

	const title = {
		fontFamily: heading,
		fontSize: scaled(size('xx-large') || '3.5rem'),
		fontWeight: styles?.elements?.heading?.typography?.fontWeight || '600',
		textTransform:
			styles?.elements?.heading?.typography?.textTransform || 'none',
		lineHeight: 1.15,
		margin: 0,
	};

	// The type the byline and the trail are both set in — `metaType` rather than
	// `meta`, which is now the prop saying which facts the byline states.
	const metaType = {
		fontSize: scaled(size('small') || '0.875rem'),
		// The same 70% of the band's foreground the stylesheet uses.
		opacity: 0.7,
	};

	// The trail, when it is switched on. Drawn at the byline's size and
	// strength, because on the page it is the same `small` preset at the same
	// reduced foreground — furniture, quieter than the headline under it.
	const crumbs = breadcrumb ? (
		<p
			className="bridge-preview__post-crumbs"
			style={{ ...metaType, margin: 0 }}
		>
			{__('Home / This post', 'bridge')}
		</p>
	) : null;

	// The byline, in the one place it now sits in all three layouts: the top of
	// the article, in the text column, over a rule. The rule is a class rather
	// than an inline border so it can be `currentcolor` at strength, which is
	// what the stylesheet draws and what keeps it correct on a dark palette.
	//
	// The facts, and the slashes between them, are the real ones: which parts
	// are on is the whole of what the Byline switches decide, so a preview that
	// always drew three of them would be showing the operator a byline the site
	// does not have. Absent means on, matching bridge_post_meta_parts().
	const facts = [
		[__('12 August', 'bridge'), false !== meta?.date],
		[__('Category', 'bridge'), false !== meta?.category],
		[__('A. Author', 'bridge'), false !== meta?.author],
	]
		.filter(([, on]) => on)
		.map(([label]) => label);

	// All three off renders nothing at all on the page, rule included — so the
	// preview shows nothing either, rather than an empty line with a rule under
	// it that the site will never draw.
	const byline = facts.length ? (
		<p className="bridge-preview__post-meta" style={metaType}>
			{facts.map((label, i) => (
				<span key={label} className="bridge-preview__post-fact">
					{i > 0 ? <span aria-hidden="true">{' / '}</span> : null}
					{label}
				</span>
			))}
		</p>
	) : null;

	// Stands in for the photograph. Surface rather than a mid grey, so it is a
	// colour from the palette like everything else on screen.
	const photo = {
		background: color('surface'),
		boxShadow: 'inset 0 0 0 1px rgb(0 0 0 / 10%)',
	};

	/**
	 * The corner the photograph is cut to.
	 *
	 * The real declarations, not a schematic: a radius is a radius and a
	 * polygon is a polygon, so the preview can draw the actual shape rather
	 * than an impression of it. The cut is in per-cent of the swatch here
	 * where the page spends a spacing preset — the swatch is a tenth of the
	 * size, and a 2rem bite out of it would be most of the picture.
	 *
	 * Cover takes none of them and passes `photo` straight through: its
	 * photograph is the band, and the stylesheet leaves it square for the
	 * reason its own block gives.
	 */
	const shaped = {
		rounded: { borderRadius: radius },
		square: { borderRadius: 0 },
		cut: {
			borderRadius: 0,
			clipPath: 'polygon(0 14%, 14% 0, 100% 0, 100% 100%, 0 100%)',
		},
	};

	const photoShape = { ...photo, ...(shaped[image] || shaped.rounded) };

	const lines = (
		<div className="bridge-preview__post-lines" aria-hidden="true">
			<span />
			<span />
			<span />
		</div>
	);

	return (
		<div
			className={`bridge-preview__post bridge-preview__post--${template}`}
			style={{
				fontFamily: body,
				background: color('background'),
				color: color('text'),
			}}
		>
			{'cover' === template ? (
				<>
					{/*
					 * The trail is above the band in Cover, not on it: the
					 * photograph is bled and the trail belongs to the page,
					 * not to the picture.
					 */}
					{crumbs && (
						<div className="bridge-preview__post-rail">
							{crumbs}
						</div>
					)}
					<div className="bridge-preview__post-cover">
						<span
							className="bridge-preview__post-photo"
							style={photo}
						/>
						{/* The scrim, matching the stylesheet's gradient. */}
						<span
							className="bridge-preview__post-scrim"
							aria-hidden="true"
						/>
						<div className="bridge-preview__post-over">
							{/*
							 * `background` is the palette's light slug, which is
							 * what `on-dark` resolves the foreground to on the
							 * real page — so a brand whose light colour is not
							 * white shows that here too.
							 */}
							<h4
								style={{
									...title,
									color: color('background'),
								}}
							>
								{__('The headline of a post', 'bridge')}
							</h4>
						</div>
					</div>
					<div className="bridge-preview__post-body">
						{byline}
						{lines}
					</div>
				</>
			) : (
				<div className="bridge-preview__post-body">
					{crumbs}
					<div className="bridge-preview__post-head">
						<div>
							<h4 style={title}>
								{__('The headline of a post', 'bridge')}
							</h4>
						</div>
						<span
							className="bridge-preview__post-photo"
							style={photoShape}
						/>
					</div>
					{byline}
					{lines}
				</div>
			)}
		</div>
	);
}

/**
 * A measured contrast ratio, with the threshold it had to clear.
 *
 * The number is the point. "Accessible" is a claim; 7.4:1 against a 4.5
 * minimum is the evidence for it, and it is what an operator can paste into a
 * reply when a client's auditor asks.
 *
 * @param {Object} props Component props.
 */
export function Ratio({ label, value, min }) {
	const ratio = Number(value) || 0;
	const passes = ratio >= min;

	return (
		<span
			className={`bridge-ratio${passes ? ' is-pass' : ' is-fail'}`}
			title={sprintf(
				/* translators: 1: measured contrast ratio, 2: required minimum. */
				__('%1$s:1, against a minimum of %2$s:1', 'bridge'),
				ratio.toFixed(2),
				min
			)}
		>
			<span className="bridge-ratio__label">{label}</span>
			<strong>{ratio.toFixed(1)}</strong>
		</span>
	);
}

/**
 * The three skins, each drawn as the buttons it actually produces.
 *
 * A radio group of samples rather than a select. The choice is "which of these
 * three do I want", and that is a question no list of adjectives can ask —
 * "Edge" means nothing until the square button with the rule under its label
 * is on screen, hoverable, next to the other two.
 *
 * Every sample is live: the geometry is the skin's own, the colours are the
 * page's ground, and the hover state is the same CSS the site runs, so what is
 * being pointed at here is what a visitor points at.
 *
 * @param {Object} props Component props.
 */
export function ButtonSkinPicker({ skins, active, scheme, onChange }) {
	return (
		<div className="bridge-button-skins">
			{(skins || []).map((skin) => (
				<label
					key={skin.slug}
					htmlFor={`bridge-button-skin-${skin.slug}`}
					className={`bridge-button-skin${skin.slug === active ? ' is-active' : ''}`}
				>
					<span className="bridge-button-skin__head">
						<input
							id={`bridge-button-skin-${skin.slug}`}
							type="radio"
							name="bridge-button-skin"
							value={skin.slug}
							checked={skin.slug === active}
							onChange={() => onChange(skin.slug)}
						/>
						<strong>{skin.name}</strong>
					</span>

					<span
						className="bridge-button-skin__sample"
						style={{ ...skinVars(skin), ...groundVars(scheme) }}
					>
						<span className="bridge-button-sample">
							{__('Get in touch', 'bridge')}
						</span>
						<span className="bridge-button-sample bridge-button-sample--ghost">
							{__('Learn more', 'bridge')}
						</span>
					</span>

					<span className="bridge-button-skin__description">
						{skin.description}
					</span>
				</label>
			))}
		</div>
	);
}

/**
 * The chosen skin drawn on all four grounds, with the contrast behind each.
 *
 * Four bands rather than one, for the reason the card preview gives: the same
 * button is drawn on the page's own background and on each of the three
 * section skins, and each has its own scheme. Shown together so a fill that
 * disappears into its band is obvious while it is being picked rather than
 * after it has shipped.
 *
 * The ratios come from the server with the colours, so the number under a
 * button is the one an audit of the rendered page will produce.
 *
 * @param {Object} props Component props.
 */
export function ButtonPreview({ grounds, schemes, custom, palette }) {
	const color = (slug) => palette?.find((c) => c.slug === slug)?.color;

	return (
		<div className="bridge-preview__buttons" style={skinVars(custom)}>
			{(grounds || []).map((entry) => {
				const scheme = schemes?.[entry.key];

				if (!scheme) {
					return null;
				}

				const audit = scheme.audit || {};

				return (
					<div
						key={entry.key}
						className="bridge-preview__button-band"
						style={{
							background: color(entry.ground),
							color: color(entry.text),
							...groundVars(scheme),
						}}
					>
						<span className="bridge-preview__card-band-label">
							{entry.label}
						</span>

						<span className="bridge-preview__button-row">
							<span className="bridge-button-sample">
								{__('Solid', 'bridge')}
							</span>
							<span className="bridge-button-sample bridge-button-sample--ghost">
								{__('Outline', 'bridge')}
							</span>
						</span>

						<span className="bridge-preview__ratios">
							<Ratio
								label={__('Label', 'bridge')}
								value={audit.label}
								min={4.5}
							/>
							<Ratio
								label={__('Hover', 'bridge')}
								value={audit.hoverLabel}
								min={4.5}
							/>
							<Ratio
								label={
									audit.bordered
										? __('Border', 'bridge')
										: __('Edge', 'bridge')
								}
								value={audit.boundary}
								min={3}
							/>
							<Ratio
								label={__('Outline', 'bridge')}
								value={audit.ghost}
								min={4.5}
							/>
						</span>
					</div>
				);
			})}
		</div>
	);
}

/**
 * What each template renders, after the header/footer swap.
 *
 * Read-only on purpose: templates are a developer artefact. The useful thing
 * to surface here is the *consequence* of the header and footer choices made
 * on the Blocks tab — including which templates opt out of them.
 *
 * @param {Object} props Component props.
 */
export function TemplateList({ templates }) {
	if (!templates?.length) {
		return (
			<p className="bridge-empty">
				{__('No templates found in this build.', 'bridge')}
			</p>
		);
	}

	return (
		<ul className="bridge-inventory">
			{templates.map((template) => (
				<li key={template.slug}>
					<span className="bridge-inventory__head">
						<strong>{template.title}</strong>
						<code>{template.slug}</code>
					</span>
					<span className="bridge-inventory__parts">
						{template.parts.map((part, index) => (
							<span key={index} className="bridge-part">
								<code>{part.resolved}</code>
								{part.fixed && (
									<em>{__('fixed by template', 'bridge')}</em>
								)}
							</span>
						))}
					</span>
				</li>
			))}
		</ul>
	);
}

/**
 * Each section skin rendered at its real palette colours.
 *
 * The skins are implemented in the stylesheet, which the admin screen does not
 * load; the palette slugs each skin resolves to travel in the REST payload so
 * this stays a single declared mapping rather than a second set of rules.
 *
 * @param {Object} props Component props.
 */
export function SkinPreview({ skins, palette, fontFamilies }) {
	const color = (slug) => palette?.find((c) => c.slug === slug)?.color;
	const body = stackFor(fontFamilies, 'sans');
	const heading = stackFor(fontFamilies, 'heading') || body;
	const entries = Object.entries(skins || {});

	if (!entries.length) {
		return (
			<p className="bridge-empty">
				{__('No skins registered.', 'bridge')}
			</p>
		);
	}

	return (
		<div className="bridge-skin-previews">
			{entries.map(([name, skin]) => (
				<div
					key={name}
					className="bridge-skin-preview"
					style={{
						background: color(skin.background),
						color: color(skin.text),
						fontFamily: body,
					}}
				>
					<strong style={{ fontFamily: heading }}>
						{skin.label}
					</strong>
					<span>{__('A section on this ground.', 'bridge')}</span>
					<span
						className="bridge-skin-preview__button"
						style={{
							background: color(skin.text),
							color: color(skin.background),
						}}
					>
						{__('Button', 'bridge')}
					</span>
				</div>
			))}
		</div>
	);
}

/**
 * The contrast of every pair the palette produces.
 *
 * The Buttons tab could always answer "is this button legible", because a
 * button scheme picks its own label and can rescue itself. A palette cannot:
 * both colours in "body text on background" are the operator's, so the only
 * useful thing to do is measure the pairs the theme actually renders and say
 * which one fails and by how much.
 *
 * Each row carries a live sample of the two colours, because a ratio is a
 * number and 4.4 versus 4.6 is not something anyone can picture. The sample is
 * the failure, at the size the failure happens at.
 *
 * @param {Object} props Component props.
 */
export function ContrastAudit({ checks }) {
	if (!checks?.length) {
		return null;
	}

	return (
		<ul className="bridge-audit">
			{checks.map((check) => (
				<li
					key={`${check.label}${check.on}`}
					className={check.pass ? 'is-pass' : 'is-fail'}
				>
					<span
						className="bridge-audit__sample"
						style={{ background: check.bg, color: check.fg }}
						aria-hidden="true"
					>
						{__('Aa', 'bridge')}
					</span>

					<span className="bridge-audit__pair">
						<strong>
							{check.label} {check.on}
						</strong>
						<em>{check.note}</em>
					</span>

					<span
						className={`bridge-ratio${check.pass ? ' is-pass' : ' is-fail'}`}
						title={sprintf(
							/* translators: 1: measured contrast ratio, 2: required minimum. */
							__('%1$s:1, against a minimum of %2$s:1', 'bridge'),
							Number(check.ratio).toFixed(2),
							check.min
						)}
					>
						<strong>{Number(check.ratio).toFixed(1)}</strong>
					</span>
				</li>
			))}
		</ul>
	);
}

/**
 * The same, for the four card surfaces.
 *
 * Two figures per ground and only one of them is graded. The text ratio is
 * WCAG 1.4.3 for every word inside a card, because a card inherits its
 * foreground from the band rather than naming one. The band figure is shown
 * plain: a card is meant to be a quiet step from its ground — the shipped
 * Light card is 1.09:1 — so grading it against the 3:1 a control would owe
 * would fail all four defaults and teach an operator to ignore the column that
 * matters. It is called out only when a card is the same colour as its band
 * with no shadow to lift it, which is a card nobody can see.
 *
 * @param {Object} props Component props.
 */
export function CardAudit({ checks }) {
	if (!checks?.length) {
		return null;
	}

	return (
		<ul className="bridge-audit">
			{checks.map((check) => (
				<li
					key={check.key}
					className={check.text.pass ? 'is-pass' : 'is-fail'}
				>
					<span
						className="bridge-audit__sample"
						style={{ background: check.card }}
						aria-hidden="true"
					/>

					<span className="bridge-audit__pair">
						<strong>{check.label}</strong>
						<em>
							{check.band.flat
								? __(
										'The same colour as its band, with no shadow to lift it — this card is invisible.',
										'bridge'
									)
								: sprintf(
										/* translators: %s: contrast ratio against the band behind the card. */
										__('%s:1 against its band.', 'bridge'),
										Number(check.band.ratio).toFixed(2)
									)}
						</em>
					</span>

					<span
						className={`bridge-ratio${check.text.pass ? ' is-pass' : ' is-fail'}`}
						title={sprintf(
							/* translators: 1: measured contrast ratio, 2: required minimum. */
							__(
								'Text on this card: %1$s:1, against a minimum of %2$s:1',
								'bridge'
							),
							Number(check.text.ratio).toFixed(2),
							check.text.min
						)}
					>
						<span className="bridge-ratio__label">
							{__('Text', 'bridge')}
						</span>
						<strong>{Number(check.text.ratio).toFixed(1)}</strong>
					</span>
				</li>
			))}
		</ul>
	);
}
