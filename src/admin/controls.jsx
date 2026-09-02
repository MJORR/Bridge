/**
 * Bridge — Theme Options, the controls.
 *
 * Everything an operator touches: the colour fields, the pickers, the block
 * library. Each is a plain component over wp-components, so the screen keeps
 * looking like WordPress across core updates.
 *
 * Kept apart from preview.jsx on one line: a control writes to the draft, a
 * preview only reads it. Anything here takes an `onChange`.
 */

import { isValidHex, toHex } from './lib';

const { useEffect, useState } = wp.element;
const {
	Button,
	ColorPicker,
	Dropdown,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} = wp.components;
const { __, sprintf } = wp.i18n;
const apiFetch = wp.apiFetch;

/**
 * A labelled group of controls.
 *
 * @param {Object} props Component props.
 */
export function Section({ title, description, children }) {
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
 * A colour: a swatch that opens a picker, joined to a hex field.
 *
 * One control rather than two on purpose. Separate, they read as "a colour"
 * and "some text about a colour", and the picker goes unnoticed behind the
 * smaller of them. Joined, the whole thing reads as a colour field — click the
 * swatch to pick, or type into the field, which is what setting up a brand
 * actually looks like, since a hex code is usually pasted from a spec rather
 * than hunted for in a wheel.
 *
 * Shared by the palette slots and the card backgrounds; `name` is only ever
 * spoken to a screen reader.
 *
 * @param {Object} props Component props.
 */
export function ColorField({ color, name, onChange }) {
	const [text, setText] = useState(color);
	const valid = isValidHex(text);

	// Keep the field in step when the value changes elsewhere — a save
	// response, or a reset — without fighting the operator mid-typing.
	useEffect(() => setText(color), [color]);

	const commitText = (next) => {
		// Tolerate a pasted code with no leading hash; brand specs write both.
		const normalised = next && !next.startsWith('#') ? `#${next}` : next;

		setText(normalised);

		if (isValidHex(normalised)) {
			onChange(normalised.toLowerCase());
		}
	};

	return (
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
							/* translators: %s: colour name, e.g. "primary". */
							__('Pick the %s colour', 'bridge'),
							name
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
							style={{ background: valid ? text : color }}
						/>
					</Button>
				)}
				renderContent={() => (
					<div className="bridge-slot__picker">
						<ColorPicker
							color={color}
							enableAlpha={false}
							onChange={(value) => {
								const hex = toHex(value);

								if (isValidHex(hex)) {
									onChange(hex.toLowerCase());
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
				onBlur={() => setText(color)}
				aria-label={sprintf(
					/* translators: %s: colour name, e.g. "primary". */
					__('%s hex code', 'bridge'),
					name
				)}
				aria-invalid={!valid}
			/>
		</div>
	);
}

/**
 * One card background, for one of the grounds a card sits on.
 *
 * No name field, unlike a palette slot: these are not offered to editors and
 * have no label anyone but an operator ever sees, so the label is the theme's
 * and only the colour is the client's.
 *
 * @param {Object} props Component props.
 */
export function CardColorSlot({ label, color, onChange }) {
	return (
		<div className="bridge-slot bridge-slot--fixed">
			<span className="bridge-slot__slug">{label}</span>
			<ColorField color={color} name={label} onChange={onChange} />
		</div>
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
export function ColorSlot({ slug, entry, defaultName, onChange }) {
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

			<ColorField
				color={entry.color}
				name={slug}
				onChange={(color) => onChange({ color })}
			/>
		</div>
	);
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
export function FontSelect({ label, value, families, installed, onChange }) {
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
export function BlockLibrary({ library, enabled, disabled, onChange }) {
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
	/**
	 * The three article layouts.
	 *
	 * Drawn in the same four weights the header layouts use, read here as: the
	 * photograph is `logo` (the solid one), the headline is `nav`, the byline
	 * and body copy are `util`, the rule is `rule`. The point of each drawing
	 * is only where the picture sits relative to the title, because that is the
	 * whole of the choice.
	 */
	classic: {
		nav: [[24, 8, 48, 6]],
		util: [
			[24, 18, 26, 3],
			[10, 46, 100, 3],
			[10, 53, 82, 3],
		],
		logo: [[10, 26, 100, 15]],
	},
	cover: {
		// The photograph is the band: it runs to the edges of the frame and the
		// headline sits inside it, which is the only drawing of the three where
		// the two shapes overlap.
		logo: [[0, 0, 120, 38]],
		nav: [[10, 22, 48, 6]],
		util: [
			[10, 31, 26, 3],
			[10, 46, 100, 3],
			[10, 53, 82, 3],
		],
	},
	feature: {
		nav: [
			[10, 12, 44, 6],
			[10, 21, 32, 6],
		],
		util: [
			[10, 32, 26, 3],
			[10, 48, 100, 3],
			[10, 55, 82, 3],
		],
		logo: [[62, 10, 48, 30]],
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
export function LayoutArt({ name }) {
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
export function LayoutPicker({ label, help, value, options, onChange }) {
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
 * A logo slot: thumbnail, choose, remove.
 *
 * Uses the media modal WordPress already prints on this screen rather than a
 * bespoke uploader, so operators get the library they know — search, recent,
 * alt text and all.
 *
 * @param {Object} props Component props.
 */
export function LogoPicker({ label, help, value, onChange }) {
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

/**
 * A slider bound to one numeric token, with the server's own range.
 *
 * Built as a factory rather than a component so the call sites read as they
 * did when this was a closure inside the app — `range('typography',
 * 'baseSize', …)` — and so the three arguments every one of them shares are
 * named once per tab instead of on every slider.
 *
 * The range itself is never hardcoded: it travels with the payload from
 * bridge_token_constraints(), so a control can never offer a value the server
 * would silently clamp, which reads to an operator as the page ignoring them.
 *
 * @param {Object}   constraints The server's declared ranges.
 * @param {Object}   draft       The working token set.
 * @param {Function} setGroup    Setter, called as (group, key, value).
 * @return {Function} A render function: (group, key, label, help) => element.
 */
export function rangeControl(constraints, draft, setGroup) {
	return (group, key, label, help) => {
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
}
