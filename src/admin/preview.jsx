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
	const shadow =
		shadows?.find((s) => s.slug === cards.shadow)?.shadow || 'none';
	const radius = `${cards.radius}px`;

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
							background: cards.colors?.[entry.key],
						}}
					>
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
 * Accent, a Portrait's arch *is* the card colour, a Summary's read-more is the
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
	const shadow =
		shadows?.find((s) => s.slug === cards.shadow)?.shadow || 'none';
	const radius = scaled(`${cards.radius}px`);

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

							// Portrait paints its children rather than itself,
							// so the arch and the panel are one colour and the
							// card behind them is nothing. The other two are a
							// single filled box.
							const isPortrait = 'portrait' === style.slug;

							return (
								<div
									key={style.slug}
									className="bridge-preview__cardstyle"
									style={{
										borderRadius: radius,
										boxShadow: isPortrait ? 'none' : shadow,
										background: isPortrait
											? 'transparent'
											: surface,
									}}
								>
									{isPortrait ? (
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
								{__('Primary', 'bridge')}
							</span>
							<span className="bridge-button-sample bridge-button-sample--ghost">
								{__('Secondary', 'bridge')}
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
								label={__('Secondary', 'bridge')}
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
