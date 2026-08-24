/**
 * Bridge — Theme Options.
 *
 * The operator-facing editor for the design system. Everything it writes goes
 * through POST /bridge/v1/tokens, which sanitises and clamps server-side; the
 * response is treated as the truth and replaces local state, so an
 * out-of-range value visibly snaps rather than leaving the page disagreeing
 * with the site.
 *
 * The live preview is compiled by POST /bridge/v1/tokens/preview rather than
 * in JavaScript. Reimplementing the modular scale here would give us two
 * implementations to keep in step, and the one the operator is looking at
 * would be the one that drifts.
 *
 * JSX compiles to wp.element.createElement via the esbuild config, so there
 * are no imports and no React runtime — the same window.wp.* approach the
 * editor scripts use.
 */

import '../scss/admin/_options.scss';

const {
	createRoot,
	render,
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} = wp.element;
const {
	Button,
	ColorPicker,
	Dropdown,
	Notice,
	RangeControl,
	SelectControl,
	Spinner,
	TabPanel,
	TextControl,
	ToggleControl,
} = wp.components;
const { __, sprintf } = wp.i18n;
const apiFetch = wp.apiFetch;

const PREVIEW_DEBOUNCE_MS = 250;

/** Sample text for the type-scale preview, per size slug. */
const SPECIMENS = {
	small: __('Captions, meta and form hints', 'bridge'),
	medium: __('Body copy sets the rhythm of every page.', 'bridge'),
	large: __('A section subheading', 'bridge'),
	'x-large': __('Section heading', 'bridge'),
	'xx-large': __('Page title', 'bridge'),
};

/**
 * Normalise whatever ColorPicker hands back into a 6-digit hex.
 *
 * The component has returned both strings and `{ hex }` objects across
 * versions, and can append an alpha pair the server would reject outright.
 *
 * @param {string|Object} value Whatever ColorPicker passed back.
 * @return {string} A hex colour, or an empty string.
 */
function toHex(value) {
	const raw = typeof value === 'string' ? value : value?.hex || '';
	const hex = raw.trim();

	return hex.length === 9 ? hex.slice(0, 7) : hex;
}

/**
 * Is this a hex colour the server will accept?
 *
 * @param {string} value Candidate colour.
 * @return {boolean} True when the server's sanitiser would keep it.
 */
function isValidHex(value) {
	return /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(value);
}

/**
 * A labelled group of controls.
 *
 * @param {Object} props Component props.
 */
function Section({ title, description, children }) {
	return (
		<section className="bridge-options__section">
			<h2 className="bridge-options__section-title">{title}</h2>
			{description && (
				<p className="bridge-options__section-description">
					{description}
				</p>
			)}
			<div className="bridge-options__controls">{children}</div>
		</section>
	);
}

/**
 * One palette slot.
 *
 * The swatch and the hex field are a single joined control on purpose. Two
 * separate ones read as "a colour" and "some text about a colour", and the
 * picker goes unnoticed behind the smaller of them. Joined, the whole thing
 * reads as a colour field: click the swatch to pick, or type into the field —
 * which is what setting up a brand actually looks like, since a hex code is
 * usually pasted from a spec rather than hunted for in a wheel.
 *
 * @param {Object} props Component props.
 */
function ColorSlot({ slug, entry, defaultName, onChange }) {
	const [text, setText] = useState(entry.color);
	const valid = isValidHex(text);

	// Keep the field in step when the value changes elsewhere — a save
	// response, or a reset — without fighting the operator mid-typing.
	useEffect(() => setText(entry.color), [entry.color]);

	const commitText = (next) => {
		// Tolerate a pasted code with no leading hash; brand specs write both.
		const normalised = next && !next.startsWith('#') ? `#${next}` : next;

		setText(normalised);

		if (isValidHex(normalised)) {
			onChange({ color: normalised.toLowerCase() });
		}
	};

	return (
		<div className="bridge-slot">
			<span className="bridge-slot__slug">{slug}</span>

			{/*
			 * Labels are hidden from vision rather than omitted: repeating
			 * "Label shown to editors" six times is noise, but a screen
			 * reader still needs to know which slot each field belongs to.
			 * The slug above and the section description carry the meaning
			 * for sighted users.
			 */}
			<TextControl
				label={sprintf(
					/* translators: %s: palette slot, e.g. "primary". */
					__('Name shown to editors for the %s colour', 'bridge'),
					slug
				)}
				hideLabelFromVision
				value={entry.name}
				placeholder={defaultName}
				onChange={(name) => onChange({ name })}
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>

			<div className={`bridge-slot__field${valid ? '' : ' is-invalid'}`}>
				<Dropdown
					popoverProps={{ placement: 'bottom-start' }}
					renderToggle={({ isOpen, onToggle }) => (
						<Button
							onClick={onToggle}
							aria-expanded={isOpen}
							className="bridge-slot__swatch"
							showTooltip
							label={sprintf(
								/* translators: %s: palette slot, e.g. "primary". */
								__('Pick the %s colour', 'bridge'),
								slug
							)}
						>
							{/*
							 * A plain span rather than <ColorIndicator>: that
							 * component is a small circle with its own border,
							 * which cannot be squared off into a flush well
							 * without a specificity fight every time core
							 * restyles it.
							 */}
							<span
								className="bridge-slot__chip"
								style={{
									background: valid ? text : entry.color,
								}}
							/>
						</Button>
					)}
					renderContent={() => (
						<div className="bridge-slot__picker">
							<ColorPicker
								color={entry.color}
								enableAlpha={false}
								onChange={(value) => {
									const hex = toHex(value);

									if (isValidHex(hex)) {
										onChange({ color: hex.toLowerCase() });
									}
								}}
							/>
						</div>
					)}
				/>

				<input
					type="text"
					className="bridge-slot__hex"
					value={text}
					spellCheck="false"
					autoComplete="off"
					onChange={(event) => commitText(event.target.value)}
					// Snap a half-typed value back to the last good one rather
					// than leaving the field showing something the site isn't.
					onBlur={() => setText(entry.color)}
					aria-label={sprintf(
						/* translators: %s: palette slot, e.g. "primary". */
						__('%s hex code', 'bridge'),
						slug
					)}
					aria-invalid={!valid}
				/>
			</div>
		</div>
	);
}

/**
 * Load the selected Google families into the admin page so the preview can
 * actually render them.
 *
 * This is the one place the theme talks to Google at render time, and it is
 * deliberate: the options screen is behind authentication and only operators
 * reach it, so no visitor's IP is ever handed over. It also has to be the
 * CDN rather than the self-hosted copies — a family is downloaded on *save*,
 * and a preview that could only show fonts you had already committed to would
 * be no preview at all.
 *
 * The front end never touches this path. It serves the downloaded woff2 files
 * and nothing else.
 *
 * @param {string[]} families Family names currently selected.
 */
function useGooglePreviewFonts(families) {
	const key = families.filter(Boolean).sort().join('|');

	useEffect(() => {
		const id = 'bridge-google-preview';
		const wanted = key ? key.split('|') : [];
		let link = document.getElementById(id);

		if (!wanted.length) {
			if (link) {
				link.remove();
			}

			return;
		}

		if (!link) {
			link = document.createElement('link');
			link.id = id;
			link.rel = 'stylesheet';
			document.head.appendChild(link);
		}

		// 400 and 700 only: every family in the catalogue publishes both, and
		// the CSS2 API fails the whole request if any one family lacks a
		// requested weight. Intermediate weights synthesise well enough for a
		// preview.
		const query = wanted
			.map(
				(family) => `family=${encodeURIComponent(family)}:wght@400;700`
			)
			.join('&');

		link.href = `https://fonts.googleapis.com/css2?${query}&display=swap`;
	}, [key]);
}

/**
 * A Google family picker for one role.
 *
 * The option list is flat by necessity: SelectControl maps `options` straight
 * to <option> elements and has no optgroup support, so a grouped structure
 * renders as its group labels and silently drops every family inside.
 *
 * The note under the select carries the family's character, so a face can be
 * chosen without opening a specimen somewhere else.
 *
 * @param {Object} props Component props.
 */
function FontSelect({ label, value, families, installed, onChange }) {
	const list = families || [];
	const current = list.find((f) => f.family === value);
	const isInstalled = (installed || []).some((f) => f.family === value);

	const options = [
		{ label: __('— Use the font set —', 'bridge'), value: '' },
		...list.map((f) => ({ label: f.family, value: f.family })),
	];

	return (
		<SelectControl
			label={label}
			value={value || ''}
			options={options}
			onChange={onChange}
			help={
				current
					? `${current.note}${isInstalled ? '' : ` — ${__('downloads on save', 'bridge')}`}`
					: __('Falls back to the font set above.', 'bridge')
			}
			__nextHasNoMarginBottom
			__next40pxDefaultSize
		/>
	);
}

/**
 * Pull a compiled family's stack out of the preview payload.
 *
 * @param {Array}  fontFamilies Compiled families from the preview response.
 * @param {string} slug         Family slug, e.g. `sans` or `heading`.
 * @return {string|undefined} The CSS font stack, if that family exists.
 */
function stackFor(fontFamilies, slug) {
	return fontFamilies?.find((f) => f.slug === slug)?.fontFamily;
}

/**
 * Type scale, rendered at the sizes and in the faces the server compiled.
 *
 * @param {Object} props Component props.
 */
function TypePreview({ fontSizes, fontFamilies, styles }) {
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
function BrandPreview({ palette, fontFamilies }) {
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
					{__('Body copy in Text, with', 'bridge')}
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
function IconPreview({ icons, weights, active }) {
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
function LayoutPreview({ layout }) {
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
 * What each template renders, after the header/footer swap.
 *
 * Read-only on purpose: templates are a developer artefact. The useful thing
 * to surface here is the *consequence* of the header and footer choices made
 * on the Blocks tab — including which templates opt out of them.
 *
 * @param {Object} props Component props.
 */
function TemplateList({ templates }) {
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
 * The curated pattern library, grouped into families.
 *
 * @param {Object} props Component props.
 */
function PatternList({ families }) {
	const entries = Object.entries(families || {});

	if (!entries.length) {
		return (
			<p className="bridge-empty">
				{__('No patterns registered yet.', 'bridge')}
			</p>
		);
	}

	return (
		<div className="bridge-families">
			{entries.map(([family, patterns]) => (
				<div key={family} className="bridge-family">
					<h3 className="bridge-family__name">{family}</h3>
					<ul className="bridge-inventory">
						{patterns.map((pattern) => (
							<li key={pattern.name}>
								<span className="bridge-inventory__head">
									<strong>{pattern.title}</strong>
									<span
										className={`bridge-lock${pattern.locked ? ' is-locked' : ''}`}
									>
										{pattern.locked
											? __('Content only', 'bridge')
											: __('Unlocked', 'bridge')}
									</span>
								</span>
								<span className="bridge-inventory__desc">
									{pattern.description}
								</span>
							</li>
						))}
					</ul>
				</div>
			))}
		</div>
	);
}

/**
 * Every insertable block, grouped as the inserter groups them, with a switch
 * each.
 *
 * Grouped by the editor's own categories rather than by anything this screen
 * invents: an operator hunting for a block will look where the inserter keeps
 * it. Each row shows the block name under its title, because two blocks can
 * read the same to a human — "Query Loop" and "Post Template" are one concept
 * in the inserter and two rows here — and the name is what the site actually
 * stores.
 *
 * The switch reflects the effective answer, not the stored one: a block is on
 * when the theme ships it on and nothing has switched it off, or when it has
 * been switched on explicitly. What gets saved is only the difference.
 *
 * @param {Object} props Component props.
 */
function BlockLibrary({ library, enabled, disabled, onChange }) {
	const [search, setSearch] = useState('');
	const term = search.trim().toLowerCase();

	const isOn = (block) =>
		(block.default || enabled.includes(block.name)) &&
		!disabled.includes(block.name);

	const groups = (library || [])
		.map((group) => ({
			...group,
			blocks: group.blocks.filter(
				(block) =>
					!term ||
					block.title.toLowerCase().includes(term) ||
					block.name.toLowerCase().includes(term)
			),
		}))
		.filter((group) => group.blocks.length);

	const all = (library || []).flatMap((group) => group.blocks);
	const live = all.filter(isOn).length;

	if (!all.length) {
		return (
			<p className="bridge-empty">
				{__('No blocks are registered.', 'bridge')}
			</p>
		);
	}

	return (
		<div className="bridge-blocks">
			<div className="bridge-blocks__toolbar">
				<TextControl
					label={__('Find a block', 'bridge')}
					value={search}
					onChange={setSearch}
					placeholder={__('Search by name', 'bridge')}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<p className="bridge-blocks__tally">
					{sprintf(
						/* translators: 1: blocks switched on, 2: blocks available. */
						__('%1$d of %2$d switched on', 'bridge'),
						live,
						all.length
					)}
				</p>
			</div>

			{!groups.length && (
				<p className="bridge-empty">
					{__('No block matches that search.', 'bridge')}
				</p>
			)}

			{groups.map((group) => {
				const groupOn = group.blocks.filter(isOn).length;

				return (
					<section key={group.slug} className="bridge-blocks__group">
						<header className="bridge-blocks__group-head">
							<h3 className="bridge-blocks__group-title">
								{group.title}
							</h3>
							<span className="bridge-blocks__group-tally">
								{groupOn}/{group.blocks.length}
							</span>
							<span className="bridge-blocks__group-actions">
								<Button
									variant="link"
									onClick={() => onChange(group.blocks, true)}
								>
									{__('All', 'bridge')}
								</Button>
								<Button
									variant="link"
									onClick={() =>
										onChange(group.blocks, false)
									}
								>
									{__('None', 'bridge')}
								</Button>
							</span>
						</header>

						<ul className="bridge-blocks__grid">
							{group.blocks.map((block) => (
								<li
									key={block.name}
									className={`bridge-block${isOn(block) ? ' is-on' : ''}${
										block.theme ? ' is-theme' : ''
									}`}
								>
									<ToggleControl
										label={block.title}
										checked={isOn(block)}
										disabled={block.required}
										onChange={(on) => onChange([block], on)}
										__nextHasNoMarginBottom
									/>
									<code className="bridge-block__name">
										{block.name}
									</code>
									{block.required && (
										<span className="bridge-block__note">
											{__(
												'Always on — the editor needs it',
												'bridge'
											)}
										</span>
									)}
									{!block.required && !block.default && (
										<span className="bridge-block__note">
											{__(
												'Off in this theme by default',
												'bridge'
											)}
										</span>
									)}
								</li>
							))}
						</ul>
					</section>
				);
			})}
		</div>
	);
}

/**
 * Scale drawings of each header and footer arrangement.
 *
 * Coordinates in a 120x64 box. Every shape is drawn in `currentColor` at a
 * different opacity, so selecting a layout only has to change one colour on
 * the button and the whole drawing follows — no per-state fills to keep in
 * step.
 *
 * [x, y, width, height]
 */
const LAYOUT_ART = {
	left: {
		logo: [[10, 25, 26, 14]],
		nav: [
			[58, 30, 14, 5],
			[78, 30, 14, 5],
			[98, 30, 12, 5],
		],
	},
	centre: {
		logo: [[47, 14, 26, 14]],
		nav: [
			[33, 38, 14, 5],
			[53, 38, 14, 5],
			[73, 38, 14, 5],
		],
	},
	'two-row': {
		logo: [[10, 14, 26, 14]],
		nav: [
			[10, 38, 14, 5],
			[30, 38, 14, 5],
			[50, 38, 14, 5],
		],
	},
	simple: {
		logo: [[10, 28, 26, 10]],
		nav: [[86, 30, 24, 5]],
	},
	columns: {
		logo: [[10, 12, 22, 9]],
		nav: [
			[10, 26, 16, 4],
			[10, 34, 16, 4],
			[48, 12, 16, 4],
			[48, 20, 16, 4],
			[48, 28, 16, 4],
			[86, 12, 20, 4],
			[86, 20, 20, 4],
		],
		rule: [[10, 46, 100, 1]],
		util: [[10, 52, 30, 3]],
	},
};

/**
 * One layout drawing.
 *
 * @param {Object} props Component props.
 */
function LayoutArt({ name }) {
	const art = LAYOUT_ART[name] || {};
	const draw = (rects, opacity, radius) =>
		(rects || []).map(([x, y, w, h], i) => (
			<rect
				key={i}
				x={x}
				y={y}
				width={w}
				height={h}
				rx={radius}
				fill="currentColor"
				opacity={opacity}
			/>
		));

	return (
		<svg
			viewBox="0 0 120 64"
			role="presentation"
			focusable="false"
			className="bridge-layout__art"
		>
			{draw(art.rule, 0.18, 0.5)}
			{draw(art.util, 0.35, 1.5)}
			{draw(art.nav, 0.45, 2.5)}
			{draw(art.logo, 1, 3)}
		</svg>
	);
}

/**
 * A grid of layout choices, each drawn rather than described.
 *
 * Buttons rather than a select because the difference between these options is
 * entirely visual — "two row" and "centre" are the same words to anyone who
 * has not seen them, and a dropdown makes you pick before you can look.
 *
 * @param {Object} props Component props.
 */
function LayoutPicker({ label, help, value, options, onChange }) {
	return (
		<div className="bridge-layouts">
			<span className="bridge-layouts__label">{label}</span>
			<div
				className="bridge-layouts__grid"
				role="radiogroup"
				aria-label={label}
			>
				{options.map((option) => {
					const active = option.value === value;

					return (
						<button
							type="button"
							key={option.value}
							role="radio"
							aria-checked={active}
							className={`bridge-layout${active ? ' is-active' : ''}`}
							onClick={() => onChange(option.value)}
						>
							<LayoutArt name={option.value} />
							<span className="bridge-layout__name">
								{option.label}
							</span>
							{option.hint && (
								<span className="bridge-layout__hint">
									{option.hint}
								</span>
							)}
						</button>
					);
				})}
			</div>
			{help && <p className="bridge-layouts__help">{help}</p>}
		</div>
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
function SkinPreview({ skins, palette, fontFamilies }) {
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
 * A logo slot: thumbnail, choose, remove.
 *
 * Uses the media modal WordPress already prints on this screen rather than a
 * bespoke uploader, so operators get the library they know — search, recent,
 * alt text and all.
 *
 * @param {Object} props Component props.
 */
function LogoPicker({ label, help, value, onChange }) {
	const [url, setUrl] = useState('');

	// Resolve the stored attachment ID to a thumbnail. The media store is
	// already loaded for the modal, so this is a cache hit in practice.
	useEffect(() => {
		if (!value) {
			setUrl('');
			return;
		}

		apiFetch({ path: `/wp/v2/media/${value}` })
			.then((media) => setUrl(media?.source_url || ''))
			.catch(() => setUrl(''));
	}, [value]);

	const open = () => {
		const frame = wp.media({
			title: label,
			library: { type: 'image' },
			multiple: false,
			button: { text: __('Use this image', 'bridge') },
		});

		frame.on('select', () => {
			const item = frame.state().get('selection').first().toJSON();
			onChange(item.id);
		});

		frame.open();
	};

	return (
		<div className="bridge-logo">
			<span className="bridge-logo__label">{label}</span>
			<div className="bridge-logo__row">
				<span className="bridge-logo__preview">
					{url ? (
						<img src={url} alt="" />
					) : (
						<em>{__('None', 'bridge')}</em>
					)}
				</span>
				<Button
					variant="secondary"
					onClick={open}
					__next40pxDefaultSize
				>
					{value ? __('Replace', 'bridge') : __('Choose', 'bridge')}
				</Button>
				{Boolean(value) && (
					<Button
						variant="tertiary"
						isDestructive
						onClick={() => onChange(0)}
						__next40pxDefaultSize
					>
						{__('Remove', 'bridge')}
					</Button>
				)}
			</div>
			{help && <p className="bridge-logo__help">{help}</p>}
		</div>
	);
}

function OptionsApp() {
	const [payload, setPayload] = useState(null);
	const [draft, setDraft] = useState(null);
	const [preview, setPreview] = useState(null);
	const [saving, setSaving] = useState(false);
	const [notice, setNotice] = useState(null);
	const [error, setError] = useState(null);

	// Guards against a slow preview response landing after a newer one.
	const previewToken = useRef(0);

	useEffect(() => {
		apiFetch({ path: '/bridge/v1/tokens' })
			.then((data) => {
				setPayload(data);
				setDraft(data.tokens);
			})
			.catch((e) =>
				setError(
					e.message ||
						__('Could not load the design system.', 'bridge')
				)
			);
	}, []);

	// Recompile the preview whenever the draft settles.
	useEffect(() => {
		if (!draft) {
			return undefined;
		}

		const ticket = ++previewToken.current;
		const timer = setTimeout(() => {
			apiFetch({
				path: '/bridge/v1/tokens/preview',
				method: 'POST',
				data: { tokens: draft },
			})
				.then((data) => {
					if (ticket === previewToken.current) {
						setPreview(data);
					}
				})
				.catch(() => {});
		}, PREVIEW_DEBOUNCE_MS);

		return () => clearTimeout(timer);
	}, [draft]);

	// Pull the chosen faces into the admin page so the preview panel renders
	// them the moment they are picked, rather than only after a save.
	useGooglePreviewFonts(
		draft?.typography?.googleFonts
			? [draft.typography.googleHeading, draft.typography.googleBody]
			: []
	);

	const isDirty = useMemo(
		() =>
			Boolean(payload && draft) &&
			JSON.stringify(payload.tokens) !== JSON.stringify(draft),
		[payload, draft]
	);

	const setGroup = useCallback((group, key, value) => {
		setDraft((current) => ({
			...current,
			[group]: { ...current[group], [key]: value },
		}));
	}, []);

	const setSlot = useCallback((slug, patch) => {
		setDraft((current) => ({
			...current,
			brand: {
				...current.brand,
				palette: {
					...current.brand.palette,
					[slug]: { ...current.brand.palette[slug], ...patch },
				},
			},
		}));
	}, []);

	const setHeader = useCallback((path, value) => {
		setDraft((current) => {
			const header = { ...current.header };
			const [group, key] = path.split('.');

			if (key) {
				header[group] = { ...header[group], [key]: value };
			} else {
				header[group] = value;
			}

			return { ...current, header };
		});
	}, []);

	/**
	 * Switch blocks on or off.
	 *
	 * Stores the difference from what the theme ships rather than the whole
	 * list: switching a default-on block off records it as disabled, switching
	 * it back on simply forgets it again. A theme release that adds a block
	 * then arrives switched on, instead of missing from a list written before
	 * it existed.
	 *
	 * Both lists are kept sorted because the server sorts them too — the save
	 * button reads its enabled state from a comparison of the two records.
	 */
	const setBlocks = useCallback((blocks, on) => {
		setDraft((current) => {
			const enabled = new Set(current.blocks.enabled);
			const disabled = new Set(current.blocks.disabled);

			blocks.forEach((block) => {
				if (block.required) {
					return;
				}

				if (on) {
					disabled.delete(block.name);
					if (block.default) {
						enabled.delete(block.name);
					} else {
						enabled.add(block.name);
					}

					return;
				}

				enabled.delete(block.name);
				if (block.default) {
					disabled.add(block.name);
				} else {
					disabled.delete(block.name);
				}
			});

			return {
				...current,
				blocks: {
					enabled: [...enabled].sort(),
					disabled: [...disabled].sort(),
				},
			};
		});
	}, []);

	const save = () => {
		setSaving(true);
		setNotice(null);

		apiFetch({
			path: '/bridge/v1/tokens',
			method: 'POST',
			data: { tokens: draft },
		})
			.then((data) => {
				const clamped =
					JSON.stringify(data.tokens) !== JSON.stringify(draft);

				setPayload(data);
				setDraft(data.tokens);
				setNotice({
					status: clamped ? 'warning' : 'success',
					message: clamped
						? __(
								'Saved. Some values were outside their allowed range and were adjusted to the nearest permitted value.',
								'bridge'
							)
						: __(
								'Saved. The editor and the front end are already using these values.',
								'bridge'
							),
				});
			})
			.catch((e) =>
				setNotice({
					status: 'error',
					message: e.message || __('Could not save.', 'bridge'),
				})
			)
			.finally(() => setSaving(false));
	};

	if (error) {
		return (
			<Notice status="error" isDismissible={false}>
				{error}
			</Notice>
		);
	}

	if (!draft || !payload) {
		return (
			<div className="bridge-options__loading">
				<Spinner />
				<span>{__('Loading the design system…', 'bridge')}</span>
			</div>
		);
	}

	const {
		constraints,
		fontSets,
		paletteSlugs,
		spacingSlugs,
		spacingSteps,
		googleFonts,
		installedFonts,
		fontErrors,
		icons,
		iconWeights,
		templates,
		patternFamilies,
		sectionSkins,
		menus,
		blockLibrary,
	} = payload;
	const type = draft.typography;
	const layout = draft.layout;

	const range = (group, key, label, help) => {
		const c = constraints[group][key];

		return (
			<RangeControl
				label={label}
				help={help}
				value={draft[group][key]}
				min={c.min}
				max={c.max}
				step={c.step}
				onChange={(value) =>
					setGroup(group, key, value === undefined ? c.min : value)
				}
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
		);
	};

	const header = draft.header;

	// Ids arrive as numbers and SelectControl deals in strings, so the value is
	// stringified going in and parsed coming out — otherwise the current menu
	// never matches an option and the control reads as unset.
	const menuOptions = (auto) => [
		{ label: auto, value: '0' },
		...(menus || []).map((menu) => ({
			label: menu.title,
			value: String(menu.id),
		})),
	];

	// One vocabulary for every control that spends spacing. The slug stays the
	// stored value — it is what post content references — but nothing asks an
	// operator to choose between 60 and 70.
	const spacingOptions = spacingSlugs.map((slug) => ({
		label: spacingSteps?.[slug] || `spacing–${slug}`,
		value: slug,
	}));

	return (
		<TabPanel
			className="bridge-options__tabs"
			tabs={[
				{ name: 'design', title: __('Design', 'bridge') },
				{ name: 'blocks', title: __('Blocks', 'bridge') },
				{ name: 'templates', title: __('Templates', 'bridge') },
			]}
		>
			{(tab) => {
				// Two tabs run full width. Templates, because its layout
				// pickers are drawings and are already the preview — a sidebar
				// repeating them smaller would be the same information twice.
				// Blocks, because the library is seventy-odd switches and a
				// half-width column turns it into a scroll.
				const single =
					'templates' === tab.name || 'blocks' === tab.name;

				const actions = (
					<>
						<div className="bridge-options__actions">
							<Button
								variant="primary"
								onClick={save}
								isBusy={saving}
								disabled={saving || !isDirty}
							>
								{saving
									? __('Saving…', 'bridge')
									: __('Save design system', 'bridge')}
							</Button>
							<Button
								variant="tertiary"
								onClick={() => setDraft(payload.tokens)}
								disabled={saving || !isDirty}
							>
								{__('Discard changes', 'bridge')}
							</Button>
						</div>

						{isDirty && (
							<p className="bridge-options__dirty">
								{__(
									'Unsaved changes — the preview is live, the site is not.',
									'bridge'
								)}
							</p>
						)}
					</>
				);

				return (
					<div
						className={`bridge-options${single ? ' bridge-options--single' : ''}`}
					>
						<div className="bridge-options__main">
							{notice && (
								<Notice
									status={notice.status}
									onRemove={() => setNotice(null)}
								>
									{notice.message}
								</Notice>
							)}

							{single && (
								<div className="bridge-options__bar">
									{actions}
								</div>
							)}

							{tab.name === 'design' && (
								<>
									<Section
										title={__('Brand palette', 'bridge')}
										description={__(
											'These six colours are the only ones the editor offers — custom colours are switched off, so nothing off-brand can reach a page. Each slot takes the name editors see, then its hex value.',
											'bridge'
										)}
									>
										<div className="bridge-options__slots">
											{Object.entries(paletteSlugs).map(
												([slug, defaultName]) => (
													<ColorSlot
														key={slug}
														slug={slug}
														entry={
															draft.brand.palette[
																slug
															]
														}
														defaultName={
															defaultName
														}
														onChange={(patch) =>
															setSlot(slug, patch)
														}
													/>
												)
											)}
										</div>
									</Section>

									<Section
										title={__('Typography', 'bridge')}
										description={__(
											'Every font size on the site is generated from the base size and the scale ratio. Editors choose Small through Huge; they never type a number.',
											'bridge'
										)}
									>
										<SelectControl
											label={__('Font set', 'bridge')}
											value={type.fontSet}
											options={fontSets.map((set) => ({
												label: set.name,
												value: set.slug,
											}))}
											onChange={(value) =>
												setGroup(
													'typography',
													'fontSet',
													value
												)
											}
											help={
												fontSets.find(
													(s) =>
														s.slug === type.fontSet
												)?.description
											}
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>

										<div className="bridge-google">
											<ToggleControl
												label={__(
													'Google Fonts',
													'bridge'
												)}
												checked={Boolean(
													type.googleFonts
												)}
												onChange={(on) =>
													setGroup(
														'typography',
														'googleFonts',
														on
													)
												}
												help={__(
													'Adds well-known Google families to the pickers below. Files are downloaded and served from this site — nothing is ever requested from Google when a visitor loads a page.',
													'bridge'
												)}
												__nextHasNoMarginBottom
											/>

											{type.googleFonts && (
												<div className="bridge-google__pickers">
													<FontSelect
														label={__(
															'Heading font',
															'bridge'
														)}
														value={
															type.googleHeading
														}
														families={
															googleFonts?.heading
														}
														installed={
															installedFonts
														}
														onChange={(value) =>
															setGroup(
																'typography',
																'googleHeading',
																value
															)
														}
													/>
													<FontSelect
														label={__(
															'Body font',
															'bridge'
														)}
														value={type.googleBody}
														families={
															googleFonts?.body
														}
														installed={
															installedFonts
														}
														onChange={(value) =>
															setGroup(
																'typography',
																'googleBody',
																value
															)
														}
													/>
													<p className="bridge-google__note">
														{__(
															'Fonts download when you save, which can take a few seconds the first time.',
															'bridge'
														)}
													</p>
												</div>
											)}

											{Object.entries(
												fontErrors || {}
											).map(([family, message]) => (
												<Notice
													key={family}
													status="error"
													isDismissible={false}
												>
													{`${family}: ${message}`}
												</Notice>
											))}
										</div>

										{range(
											'typography',
											'baseSize',
											__('Base size (rem)', 'bridge'),
											__(
												'The body copy size everything else is measured against.',
												'bridge'
											)
										)}
										{range(
											'typography',
											'scaleRatio',
											__('Scale ratio', 'bridge'),
											__(
												'How sharply headings step up. 1.25 is restrained; 1.6 is dramatic.',
												'bridge'
											)
										)}
										{range(
											'typography',
											'bodyLineHeight',
											__('Body line height', 'bridge')
										)}
										{range(
											'typography',
											'headingLineHeight',
											__('Heading line height', 'bridge')
										)}

										<SelectControl
											label={__(
												'Heading weight',
												'bridge'
											)}
											value={type.headingWeight}
											options={constraints.typography.headingWeight.options.map(
												(w) => ({ label: w, value: w })
											)}
											onChange={(value) =>
												setGroup(
													'typography',
													'headingWeight',
													value
												)
											}
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>

										<ToggleControl
											label={__(
												'Uppercase headings',
												'bridge'
											)}
											checked={
												type.headingCase === 'uppercase'
											}
											onChange={(on) =>
												setGroup(
													'typography',
													'headingCase',
													on ? 'uppercase' : 'none'
												)
											}
											__nextHasNoMarginBottom
										/>
									</Section>

									<Section
										title={__('Icons', 'bridge')}
										description={__(
											'Icons are drawn from the theme\u2019s library and inherit their colour from the text around them. Weight is the only choice — a heavier stroke reads as more confident, a lighter one as more refined.',
											'bridge'
										)}
									>
										<SelectControl
											label={__('Icon weight', 'bridge')}
											value={draft.icons.weight}
											options={constraints.icons.weight.options.map(
												(w) => ({
													label:
														w === 'regular'
															? __(
																	'Regular',
																	'bridge'
																)
															: __(
																	'Bold',
																	'bridge'
																),
													value: w,
												})
											)}
											onChange={(value) =>
												setGroup(
													'icons',
													'weight',
													value
												)
											}
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>
									</Section>

									<Section
										title={__(
											'Layout and spacing',
											'bridge'
										)}
										description={__(
											'Widths and rhythm for every template. The spacing steps editors can pick from are generated from the base and increment below.',
											'bridge'
										)}
									>
										{range(
											'layout',
											'contentSize',
											__('Content width (px)', 'bridge'),
											__(
												'The default column that body copy sits in.',
												'bridge'
											)
										)}
										{range(
											'layout',
											'wideSize',
											__('Wide width (px)', 'bridge'),
											__(
												'Used by wide-aligned blocks. Never narrower than the content width.',
												'bridge'
											)
										)}
										{range(
											'layout',
											'spacingBase',
											__('Spacing base (rem)', 'bridge'),
											__(
												'The middle step of the seven-step spacing scale.',
												'bridge'
											)
										)}
										{range(
											'layout',
											'spacingIncrement',
											__('Spacing increment', 'bridge'),
											__(
												'How quickly the spacing steps grow apart.',
												'bridge'
											)
										)}

										<SelectControl
											label={__(
												'Gap between blocks',
												'bridge'
											)}
											value={layout.blockGap}
											options={spacingOptions}
											onChange={(value) =>
												setGroup(
													'layout',
													'blockGap',
													value
												)
											}
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>

										<SelectControl
											label={__(
												'Page side padding',
												'bridge'
											)}
											value={layout.rootPadding}
											options={spacingOptions}
											onChange={(value) =>
												setGroup(
													'layout',
													'rootPadding',
													value
												)
											}
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>

										<SelectControl
											label={__(
												'Section padding',
												'bridge'
											)}
											value={layout.sectionPadding}
											options={spacingOptions}
											onChange={(value) =>
												setGroup(
													'layout',
													'sectionPadding',
													value
												)
											}
											help={__(
												'The air above and below every full-width section — the section patterns, and any group given a Surface, Inverted or Accent style. Sections already on a page follow the new value; nothing has to be re-inserted.',
												'bridge'
											)}
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>
									</Section>
								</>
							)}

							{tab.name === 'blocks' && (
								<>
									<Section
										title={__('Block library', 'bridge')}
										description={__(
											'Which blocks the editor offers. A block switched off here disappears from the inserter for everyone, including you \u2014 this is a decision about what the site is built from, not about who is editing. Content already using a switched-off block keeps rendering; only inserting a new one stops.',
											'bridge'
										)}
									>
										<BlockLibrary
											library={blockLibrary}
											enabled={draft.blocks.enabled}
											disabled={draft.blocks.disabled}
											onChange={setBlocks}
										/>
									</Section>

									<Section
										title={__('Pattern library', 'bridge')}
										description={__(
											'What editors can insert when building a page. Content-only patterns let them replace words and images inside a fixed structure \u2014 they cannot restructure the section or reach for a colour.',
											'bridge'
										)}
									>
										<PatternList
											families={patternFamilies}
										/>
									</Section>

									<Section
										title={__('Section skins', 'bridge')}
										description={__(
											'Named treatments an editor can apply to a section. Each resolves to palette colours, so a skin restyles itself when the brand changes.',
											'bridge'
										)}
									>
										<SkinPreview
											skins={sectionSkins}
											palette={preview?.palette}
											fontFamilies={preview?.fontFamilies}
										/>
									</Section>
								</>
							)}

							{tab.name === 'templates' && (
								<>
									<Section
										title={__('Header', 'bridge')}
										description={__(
											'One setting drives every template. No layout puts the menu in a fixed centre column — that caps how many items it can hold, and a service list only ever grows.',
											'bridge'
										)}
									>
										<LayoutPicker
											label={__('Layout', 'bridge')}
											value={header.layout}
											onChange={(value) =>
												setHeader('layout', value)
											}
											options={[
												{
													value: 'left',
													label: __('Left', 'bridge'),
													hint: __(
														'Logo left, menu right',
														'bridge'
													),
												},
												{
													value: 'centre',
													label: __(
														'Centre',
														'bridge'
													),
													hint: __(
														'Stacked, centred',
														'bridge'
													),
												},
												{
													value: 'two-row',
													label: __(
														'Two row',
														'bridge'
													),
													hint: __(
														'Stacked, left aligned',
														'bridge'
													),
												},
											]}
										/>

										<SelectControl
											label={__('Main menu', 'bridge')}
											value={String(header.menus.primary)}
											options={menuOptions(
												__(
													'Auto — the newest menu on the site',
													'bridge'
												)
											)}
											onChange={(value) =>
												setHeader(
													'menus.primary',
													Number(value)
												)
											}
											help={__(
												'Which menu the header navigation shows. Menus themselves are still built in the Site Editor.',
												'bridge'
											)}
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>

										<ToggleControl
											label={__('Top bar', 'bridge')}
											checked={Boolean(header.topBar)}
											onChange={(on) =>
												setHeader('topBar', on)
											}
											help={__(
												'A slim strip above the header holding a second, quieter menu. Available on every layout.',
												'bridge'
											)}
											__nextHasNoMarginBottom
										/>

										{header.topBar && (
											<SelectControl
												label={__(
													'Top bar menu',
													'bridge'
												)}
												value={String(
													header.menus.utility
												)}
												options={menuOptions(
													__(
														'None — hide the bar',
														'bridge'
													)
												)}
												onChange={(value) =>
													setHeader(
														'menus.utility',
														Number(value)
													)
												}
												help={__(
													'The bar renders only once it has a menu: an empty strip is worse than no strip.',
													'bridge'
												)}
												__nextHasNoMarginBottom
												__next40pxDefaultSize
											/>
										)}

										<ToggleControl
											label={__('CTA button', 'bridge')}
											checked={Boolean(
												header.cta.enabled
											)}
											onChange={(on) =>
												setHeader('cta.enabled', on)
											}
											help={__(
												'A button to the right of the menu, on every layout.',
												'bridge'
											)}
											__nextHasNoMarginBottom
										/>

										{header.cta.enabled && (
											<>
												<TextControl
													label={__(
														'Button label',
														'bridge'
													)}
													value={header.cta.label}
													onChange={(value) =>
														setHeader(
															'cta.label',
															value
														)
													}
													placeholder={__(
														'Get in touch',
														'bridge'
													)}
													__nextHasNoMarginBottom
													__next40pxDefaultSize
												/>
												<TextControl
													label={__(
														'Button link',
														'bridge'
													)}
													value={header.cta.url}
													onChange={(value) =>
														setHeader(
															'cta.url',
															value
														)
													}
													placeholder="/contact"
													help={__(
														'The button appears once both fields are filled in.',
														'bridge'
													)}
													__nextHasNoMarginBottom
													__next40pxDefaultSize
												/>
											</>
										)}

										<SelectControl
											label={__('Background', 'bridge')}
											value={header.background}
											options={[
												{
													label: __(
														'Solid',
														'bridge'
													),
													value: 'solid',
												},
												{
													label: __(
														'Transparent — overlays what follows',
														'bridge'
													),
													value: 'transparent',
												},
											]}
											onChange={(value) =>
												setHeader('background', value)
											}
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>

										{header.background === 'solid' && (
											<SelectControl
												label={__(
													'Background colour',
													'bridge'
												)}
												value={header.backgroundColor}
												options={Object.entries(
													paletteSlugs
												).map(([slug]) => ({
													label: draft.brand.palette[
														slug
													].name,
													value: slug,
												}))}
												onChange={(value) =>
													setHeader(
														'backgroundColor',
														value
													)
												}
												__nextHasNoMarginBottom
												__next40pxDefaultSize
											/>
										)}

										{header.background ===
											'transparent' && (
											<SelectControl
												label={__(
													'Text contrast',
													'bridge'
												)}
												value={header.contrast}
												options={[
													{
														label: __(
															'Auto',
															'bridge'
														),
														value: 'auto',
													},
													{
														label: __(
															'Light text',
															'bridge'
														),
														value: 'light',
													},
													{
														label: __(
															'Dark text',
															'bridge'
														),
														value: 'dark',
													},
												]}
												onChange={(value) =>
													setHeader('contrast', value)
												}
												help={__(
													'Auto assumes a dark hero. A photograph\u2019s brightness cannot be known in advance, so set it here if auto reads wrong.',
													'bridge'
												)}
												__nextHasNoMarginBottom
												__next40pxDefaultSize
											/>
										)}

										<ToggleControl
											label={__(
												'Stick to the top on scroll',
												'bridge'
											)}
											checked={Boolean(header.sticky)}
											onChange={(on) =>
												setHeader('sticky', on)
											}
											help={
												header.background ===
												'transparent'
													? __(
															'A transparent sticky header turns solid once it leaves the hero, so its links stay readable.',
															'bridge'
														)
													: undefined
											}
											__nextHasNoMarginBottom
										/>

										<ToggleControl
											label={__(
												'Bottom border',
												'bridge'
											)}
											checked={Boolean(header.border)}
											onChange={(on) =>
												setHeader('border', on)
											}
											__nextHasNoMarginBottom
										/>

										<LogoPicker
											label={__('Dark logo', 'bridge')}
											value={header.logo.id}
											onChange={(id) =>
												setHeader('logo.id', id)
											}
											help={__(
												'Used wherever the header sits on a light background.',
												'bridge'
											)}
										/>

										<LogoPicker
											label={__('Light logo', 'bridge')}
											value={header.logo.lightId}
											onChange={(id) =>
												setHeader('logo.lightId', id)
											}
											help={__(
												'Used when the header background is dark, including a transparent header over a hero. Optional — without one the dark logo is used everywhere.',
												'bridge'
											)}
										/>

										<RangeControl
											label={__(
												'Logo height (px)',
												'bridge'
											)}
											value={header.logo.height}
											min={
												constraints.header.logoHeight
													.min
											}
											max={
												constraints.header.logoHeight
													.max
											}
											step={
												constraints.header.logoHeight
													.step
											}
											onChange={(value) =>
												setHeader(
													'logo.height',
													value ?? 40
												)
											}
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>

										<RangeControl
											label={__(
												'Vertical padding (px)',
												'bridge'
											)}
											value={header.paddingBlock}
											min={
												constraints.header.paddingBlock
													.min
											}
											max={
												constraints.header.paddingBlock
													.max
											}
											step={
												constraints.header.paddingBlock
													.step
											}
											onChange={(value) =>
												setHeader(
													'paddingBlock',
													value ?? 12
												)
											}
											help={__(
												'Space above and below the logo on a full-width screen. Narrower viewports scale down to a proportion of it, so the header keeps its shape on a phone.',
												'bridge'
											)}
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>
									</Section>

									<Section
										title={__('Footer', 'bridge')}
										description={__(
											'Which footer layout every template resolves to.',
											'bridge'
										)}
									>
										<LayoutPicker
											label={__('Layout', 'bridge')}
											value={draft.footer.style}
											onChange={(value) =>
												setGroup(
													'footer',
													'style',
													value
												)
											}
											options={[
												{
													value: 'simple',
													label: __(
														'Simple',
														'bridge'
													),
													hint: __(
														'One line',
														'bridge'
													),
												},
												{
													value: 'columns',
													label: __(
														'Columns',
														'bridge'
													),
													hint: __(
														'Links and contact details',
														'bridge'
													),
												},
											]}
										/>
									</Section>

									<Section
										title={__('Templates', 'bridge')}
										description={__(
											'The page structures this build ships, and which header and footer each one resolves to. A template that names its own part keeps it.',
											'bridge'
										)}
									>
										<TemplateList templates={templates} />
									</Section>

									<Section
										title={__('Site output', 'bridge')}
										description={__(
											'What WordPress serves alongside the pages themselves. This theme strips the generator meta, emoji script, XML-RPC and oEmbed discovery links and shortlinks from every response, because nothing here uses them. Feeds are the one piece of that worth deciding per site.',
											'bridge'
										)}
									>
										<ToggleControl
											label={__(
												'Show RSS feed links',
												'bridge'
											)}
											checked={Boolean(draft.site?.feeds)}
											onChange={(on) =>
												setGroup('site', 'feeds', on)
											}
											help={__(
												'Off, no page advertises a feed \u2014 which is right for a brochure site, where the comment and category feeds are crawl surface and nothing else. Turn it on for a build with a blog readers actually subscribe to. Either way /feed/ keeps working for anything pointed straight at it, such as a mailing list.',
												'bridge'
											)}
											__nextHasNoMarginBottom
										/>
									</Section>
								</>
							)}
						</div>

						{!single && (
							<aside className="bridge-options__preview">
								<div className="bridge-options__preview-inner">
									{/*
									 * The actions live in every tab rather than once
									 * above the tabs: a draft spans all three, so Save
									 * has to be reachable wherever the last edit happened.
									 */}
									{actions}

									{tab.name === 'design' &&
										(preview ? (
											<>
												<h3>{__('Brand', 'bridge')}</h3>
												<BrandPreview
													palette={preview.palette}
													fontFamilies={
														preview.fontFamilies
													}
												/>

												<h3>
													{__('Type scale', 'bridge')}
												</h3>
												<TypePreview
													fontSizes={
														preview.fontSizes
													}
													fontFamilies={
														preview.fontFamilies
													}
													styles={preview.styles}
												/>

												<h3>{__('Icons', 'bridge')}</h3>
												<IconPreview
													icons={icons}
													weights={iconWeights}
													active={draft.icons.weight}
												/>

												<h3>
													{__('Widths', 'bridge')}
												</h3>
												<LayoutPreview
													layout={preview.layout}
												/>
											</>
										) : (
											<div className="bridge-options__loading">
												<Spinner />
											</div>
										))}
								</div>
							</aside>
						)}
					</div>
				);
			}}
		</TabPanel>
	);
}

function mount() {
	const root = document.getElementById('bridge-options-root');

	if (!root) {
		return;
	}

	// createRoot landed in WP 6.2; render is the fallback for older cores.
	if (typeof createRoot === 'function') {
		createRoot(root).render(<OptionsApp />);
	} else {
		render(<OptionsApp />, root);
	}
}

// A footer script normally runs before DOMContentLoaded, but anything that
// defers or async-loads it — a performance plugin, a future core strategy —
// would land after the event and leave the screen permanently empty. Checking
// readyState makes the mount independent of when the script arrives.
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', mount);
} else {
	mount();
}
