/**
 * Bridge — Theme Options, the Cards tab.
 *
 * Everything about a card, in the one place. It was two sections of the Design
 * tab two hundred lines apart — "Card backgrounds" near the palette because it
 * is colour, "Card styling" down beside the layout because it is geometry —
 * which is one subject filed under two headings, and a third heading's worth of
 * per-style settings had nowhere to go at all.
 *
 * ---- The two axes ----------------------------------------------------------
 *
 * A card setting is either per *ground* or per *style*, and which one it is
 * follows from what the setting is for:
 *
 *   Per ground — the card's background, and only that. It has to change with
 *   the band because contrast does: the surface that reads on a white page
 *   disappears on a Surface band and turns white-on-white on an Inverted one.
 *
 *   Per style — the heading size, the image crop, the avatar size. None of
 *   these has anything to do with what is behind the card. A heading is the
 *   same size on a dark band as a light one, because size is hierarchy and
 *   ground is contrast.
 *
 * The rule that keeps the two lists from growing into each other is the one
 * abstracts/_card.scss states: a card never names its own text colour, it
 * inherits the band's. So a ground needs exactly one setting and a style never
 * needs a colour.
 *
 * ---- Where the fields come from --------------------------------------------
 *
 * Nothing below hardcodes which style takes which control. `payload.cardStyles`
 * carries each style's `fields`, and this file draws what it is told to — so a
 * fourth style is an entry in `bridge_card_styles()` and no change here.
 */

import { CardColorSlot, Section, rangeControl } from '../controls';
import { CardAudit, CardStylePreview } from '../preview';

const { SelectControl } = wp.components;
const { __, sprintf } = wp.i18n;

/**
 * Human wording for the font-size slugs.
 *
 * The slugs are the site's own scale and are what post content stores
 * (`has-large-font-size`), so they are not renamed — but "x-large" is a class
 * name, not a label, and the Design tab's own type preview already calls these
 * steps by their names.
 */
const SIZE_LABELS = {
	small: __('Small', 'bridge'),
	medium: __('Medium', 'bridge'),
	large: __('Large', 'bridge'),
	'x-large': __('Extra large', 'bridge'),
	'xx-large': __('Huge', 'bridge'),
};

export function CardsTab({
	draft,
	payload,
	preview,
	setGroup,
	setCardColor,
	setCardStyle,
}) {
	const {
		constraints,
		spacingSlugs,
		spacingSteps,
		cardGrounds,
		cardShadows,
		cardStyles,
		cardRatios,
		cardAvatars,
		fontSizeSlugs,
	} = payload;

	const range = rangeControl(constraints, draft, setGroup);

	const spacingOptions = (spacingSlugs || []).map((slug) => ({
		label: spacingSteps?.[slug] || `spacing–${slug}`,
		value: slug,
	}));

	// One option list per field name, so the switch below reads as "draw the
	// control for this field" rather than as three near-identical branches.
	const optionsFor = {
		heading: (fontSizeSlugs || []).map((slug) => ({
			label: SIZE_LABELS[slug] || slug,
			value: slug,
		})),
		ratio: (cardRatios || []).map((entry) => ({
			label: entry.name,
			value: entry.slug,
		})),
		avatar: (cardAvatars || []).map((entry) => ({
			label: entry.name,
			value: entry.slug,
		})),
	};

	const labelFor = {
		heading: __('Heading size', 'bridge'),
		ratio: __('Image crop', 'bridge'),
		avatar: __('Photograph size', 'bridge'),
	};

	const helpFor = {
		heading: __(
			'A step on the site’s own type scale, so a card heading moves with the rest of the typography rather than carrying a size of its own.',
			'bridge'
		),
		ratio: __(
			'How the featured image is cut. Every card in a grid gets the same crop, which is what keeps a row of photographs reading as a row.',
			'bridge'
		),
		avatar: __(
			'How much of the card the circular photograph takes. The panel’s arch is drawn around it, so this moves the whole shape of the card.',
			'bridge'
		),
	};

	return (
		<>
			<Section
				title={__('Card styles', 'bridge')}
				description={__(
					'The three shapes the Cards block can draw. An editor picks one per band; these are what each of them looks like everywhere it is used. Settings are per style rather than per section skin — a heading is the same size whatever colour is behind it.',
					'bridge'
				)}
			>
				{(cardStyles || []).map((style) => (
					<div key={style.slug} className="bridge-options__cardstyle">
						<h3 className="bridge-options__subhead">
							{style.name}
						</h3>
						<p className="bridge-options__subhelp">
							{style.description}
						</p>

						<div className="bridge-options__cardstyle-fields">
							{(style.fields || []).map((field) =>
								optionsFor[field] ? (
									<SelectControl
										key={field}
										label={labelFor[field] || field}
										help={helpFor[field]}
										value={
											draft.cards.styles?.[style.slug]?.[
												field
											] ?? ''
										}
										options={optionsFor[field]}
										onChange={(value) =>
											setCardStyle(
												style.slug,
												field,
												value
											)
										}
										__nextHasNoMarginBottom
										__next40pxDefaultSize
									/>
								) : null
							)}
						</div>
					</div>
				))}
			</Section>

			<Section
				title={__('Card content', 'bridge')}
				description={__(
					'What a card says when nobody has told it otherwise.',
					'bridge'
				)}
			>
				{range(
					'cards',
					'excerpt',
					__('Excerpt length (words)', 'bridge'),
					__(
						'The site’s default. A Cards block that has not been given a length of its own follows this, so a client who decides their cards are too wordy changes one number rather than opening every page. A band with its own length keeps it — this is a default, not an override.',
						'bridge'
					)
				)}
			</Section>

			<Section
				title={__('The card surface', 'bridge')}
				description={__(
					'One card surface, drawn by every block that has cards — the post cards, downloads, feature panels, price cards and testimonials. These three values are what they share, so a change here reaches all of them and none of them can drift.',
					'bridge'
				)}
			>
				<SelectControl
					label={__('Padding inside a card', 'bridge')}
					value={draft.cards.padding}
					options={spacingOptions}
					onChange={(value) => setGroup('cards', 'padding', value)}
					help={__(
						'From the same scale as the page’s spacing, so cards tighten and open up with the rest of the page rather than needing a number of their own.',
						'bridge'
					)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				{range(
					'cards',
					'radius',
					__('Corner radius (px)', 'bridge'),
					__(
						'0 is a square card, which is a design rather than a mistake. Panels set flush against each other keep their square corners whatever this says.',
						'bridge'
					)
				)}

				<SelectControl
					label={__('Shadow', 'bridge')}
					value={draft.cards.shadow}
					options={(cardShadows || []).map((entry) => ({
						label: entry.name,
						value: entry.slug,
					}))}
					onChange={(value) => setGroup('cards', 'shadow', value)}
					help={__(
						'How far a card lifts off the page. Outlined testimonials draw a border instead and are left flat — an outline and a shadow are two answers to the same question.',
						'bridge'
					)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</Section>

			<Section
				title={__('Card backgrounds', 'bridge')}
				description={__(
					'What a card is painted on each of the four grounds this theme puts one on — the page itself, and the three section skins. A card takes its text colour from the band it sits in, so these only have to answer one question: what does a card look like on that background. Not offered to editors; nothing can be painted one of these by hand.',
					'bridge'
				)}
			>
				<div className="bridge-options__slots">
					{(cardGrounds || []).map((entry) => (
						<CardColorSlot
							key={entry.key}
							label={entry.label}
							color={draft.cards.colors[entry.key]}
							onChange={(color) => setCardColor(entry.key, color)}
						/>
					))}
				</div>

				<CardAudit checks={preview?.audits?.cards} />
			</Section>
		</>
	);
}

/**
 * The Cards tab's sidebar.
 *
 * Every style on every ground — twelve cards, which is the number that matters:
 * a Tile badge that works on a white page and vanishes on an Accent band is a
 * bug nobody sees until a client builds that page. Here it is on screen before
 * anything is saved.
 *
 * @param {Object} props Component props.
 */
export function CardsPreview({ draft, payload, preview }) {
	const { cardGrounds, cardShadows, cardStyles, cardRatios, cardAvatars } =
		payload;

	return (
		<>
			<h3>{__('Every style, on every ground', 'bridge')}</h3>
			<p className="bridge-options__subhelp">
				{sprintf(
					/* translators: 1: number of styles. 2: number of grounds. */
					__(
						'%1$d card styles across %2$d grounds. The words take the band’s colour, exactly as they do on the site.',
						'bridge'
					),
					(cardStyles || []).length,
					(cardGrounds || []).length
				)}
			</p>
			<CardStylePreview
				cards={draft.cards}
				styles={cardStyles}
				grounds={cardGrounds}
				ratios={cardRatios}
				avatars={cardAvatars}
				shadows={cardShadows}
				spacingSizes={preview?.spacingSizes}
				fontSizes={preview?.fontSizes}
				palette={preview?.palette}
			/>
		</>
	);
}
