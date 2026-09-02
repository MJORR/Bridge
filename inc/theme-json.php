<?php

/**
 * Bridge design tokens — compilation into theme.json.
 *
 * This is the control plane. Theme options are not a parallel styling system
 * that fights theme.json; they are its input. The token record is compiled
 * into a theme.json fragment and merged into the theme origin before
 * WordPress generates its CSS custom properties — so a colour changed in the
 * admin becomes `--wp--preset--color--primary` everywhere, in the editor and
 * on the front end, with no extra stylesheet and no build step.
 *
 * The static theme.json still ships every value this file injects. It is the
 * structural contract and the fallback if the filter is ever removed; keep
 * the two in sync when adding a preset. Presets merge by replacement within
 * an origin, so the fragment below wholly supersedes the file's palette,
 * font families and font sizes rather than appending to them.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * How far the fluid clamp reaches below and above each font size.
 *
 * Applied as a taper: the smallest step barely moves between viewports, the
 * largest moves most. Utility text should stay legible on a phone while a
 * display heading has room to shrink. Not exposed as a token — these are
 * typographic craft, not brand identity, and every client wants the same
 * answer.
 */
define('BRIDGE_FLUID_MIN_SPREAD', 0.30);
define('BRIDGE_FLUID_MAX_SPREAD', 0.15);

/**
 * Where every fluid value in the theme starts moving.
 *
 * One number, because the theme should have one fluid curve rather than a
 * family of them. WordPress's fluid typography interpolates from 320px up to
 * `layout.wideSize`, and it is not configurable from theme.json in this
 * release — so rather than run a second, differently-shaped curve alongside
 * core's, everything else here interpolates across the same span. A page then
 * grows in one motion: type, spacing and the header's own padding all reach
 * their full size at the width the site was designed for.
 */
define('BRIDGE_FLUID_MIN_VW', 320);

/**
 * How much of a spacing step is given up at the narrow end of that span.
 *
 * Applied as the same taper the type scale uses: the smallest step barely
 * moves, the largest moves most. A hairline gap is a hairline gap on any
 * screen, while 5rem of section padding is a third of a phone.
 */
define('BRIDGE_FLUID_SPACING_SPREAD', 0.45);

/**
 * Build one fluid length.
 *
 * The single implementation of "interpolate between two sizes". Callers decide
 * the bounds — a spacing step tapers by its position on the scale, the header's
 * padding by a flat fraction — and this decides how the value gets from one to
 * the other, so there is one curve to reason about and one place to change it.
 *
 * Static bounds are expressed in rem rather than px so a visitor who raises
 * their browser's font size scales the whole layout, not just the type.
 *
 * @param float $min_rem Value at BRIDGE_FLUID_MIN_VW and below.
 * @param float $max_rem Value at $max_vw and above.
 * @param float $max_vw  Viewport width, in px, where the value tops out.
 */
function bridge_fluid_clamp(float $min_rem, float $max_rem, float $max_vw): string
{
	$span = $max_vw - BRIDGE_FLUID_MIN_VW;

	// Nothing to interpolate: equal bounds, an inverted pair, or a viewport
	// range that collapsed. A clamp would be three ways of writing one value.
	if ($span <= 0 || $min_rem >= $max_rem) {
		return bridge_rem($max_rem);
	}

	// Worked in px, then handed back in rem. The slope is per 100vw; the
	// intercept is routinely negative, which is the line crossing zero to the
	// left of the range rather than an error.
	$min_px    = $min_rem * 16;
	$max_px    = $max_rem * 16;
	$slope     = (($max_px - $min_px) / $span) * 100;
	$intercept = $min_px - (($slope * BRIDGE_FLUID_MIN_VW) / 100);

	return sprintf(
		'clamp(%s, calc(%s + %svw), %s)',
		bridge_rem($min_rem),
		bridge_rem($intercept / 16),
		bridge_css_number($slope, 4),
		bridge_rem($max_rem)
	);
}

/**
 * Build the spacing presets from the base size and increment.
 *
 * The theme generates these itself rather than handing WordPress a
 * `spacingScale` to expand, because a generated scale is a list of fixed
 * lengths and these have to be fluid. The arithmetic is the same one core
 * would have done — each step is the base multiplied by the increment, that
 * many times, either side of the middle — so the value at full width is what
 * the site had before; what is new is everything below full width.
 *
 * Making the presets themselves fluid is what keeps this to one system. Every
 * setting that spends spacing spends it through these slugs — block gap, page
 * side padding, section padding, every padding an editor picks in the block
 * sidebar, every pattern — so none of them needs fluid rules of its own.
 *
 * @param array<string, mixed> $layout Sanitised layout tokens.
 * @return array<int, array<string, string>>
 */
function bridge_compile_spacing_sizes(array $layout): array
{
	$base      = (float) $layout['spacingBase'];
	$increment = (float) $layout['spacingIncrement'];
	$max_vw    = (float) $layout['wideSize'];
	$steps     = bridge_spacing_steps();
	$count     = count($steps);

	// The middle step is the base itself; the scale climbs and falls from it.
	$medium = (int) floor($count / 2);
	$sizes  = array();
	$index  = 0;

	foreach ($steps as $slug => $name) {
		$size = $base * ($increment ** ($index - $medium));

		// Half-step offset so even the smallest step is fluid rather than a
		// clamp with identical bounds.
		$taper = ($index + 0.5) / $count;

		$sizes[] = array(
			'slug' => (string) $slug,
			'name' => $name,
			'size' => bridge_fluid_clamp(
				$size * (1 - (BRIDGE_FLUID_SPACING_SPREAD * $taper)),
				$size,
				$max_vw
			),
		);

		$index++;
	}

	return $sizes;
}

/**
 * Format a float for CSS without trailing-zero noise.
 */
function bridge_css_number(float $value, int $precision = 4): string
{
	$formatted = number_format($value, $precision, '.', '');

	if (str_contains($formatted, '.')) {
		$formatted = rtrim(rtrim($formatted, '0'), '.');
	}

	return '' === $formatted ? '0' : $formatted;
}

/**
 * Format a float as a rem length.
 */
function bridge_rem(float $value): string
{
	return bridge_css_number($value) . 'rem';
}

/**
 * Build the font-size presets from the base size and scale ratio.
 *
 * A modular scale: `medium` is the body size, the three display steps climb
 * by the ratio, and `small` sits at a fixed fraction below the body. Small is
 * deliberately outside the ratio — it is utility text (captions, meta, form
 * hints), and tying it to a display ratio makes it illegible as soon as a
 * client picks a dramatic scale.
 *
 * Slugs are fixed. They are written into post content as `has-large-font-size`.
 *
 * @param array<string, mixed> $typography Sanitised typography tokens.
 * @return array<int, array<string, mixed>>
 */
function bridge_compile_font_sizes(array $typography): array
{
	$base  = (float) $typography['baseSize'];
	$ratio = (float) $typography['scaleRatio'];

	$steps = array(
		// Names are plain strings, not __() calls: theme.json can be resolved
		// before init, and translating here would trigger just-in-time
		// textdomain loading. WordPress translates the static theme.json's
		// own strings through the resolver instead.
		array('slug' => 'small', 'name' => 'Small', 'size' => $base * 0.875),
		array('slug' => 'medium', 'name' => 'Medium', 'size' => $base),
		array('slug' => 'large', 'name' => 'Large', 'size' => $base * $ratio),
		array('slug' => 'x-large', 'name' => 'Extra Large', 'size' => $base * ($ratio ** 2)),
		array('slug' => 'xx-large', 'name' => 'Huge', 'size' => $base * ($ratio ** 3)),
	);

	$count     = count($steps);
	$font_sizes = array();

	foreach ($steps as $index => $step) {
		// Half-step offset so even the smallest size gets a little fluidity
		// rather than a clamp with identical bounds.
		$taper = ($index + 0.5) / $count;
		$size  = (float) $step['size'];

		$font_sizes[] = array(
			'slug'  => $step['slug'],
			'name'  => $step['name'],
			'size'  => bridge_rem($size),
			'fluid' => array(
				'min' => bridge_rem($size * (1 - (BRIDGE_FLUID_MIN_SPREAD * $taper))),
				'max' => bridge_rem($size * (1 + (BRIDGE_FLUID_MAX_SPREAD * $taper))),
			),
		);
	}

	return $font_sizes;
}

/**
 * Build the font-family presets from the selected font set.
 *
 * The three slugs are fixed; only the stacks behind them change. That is what
 * makes a font set switchable on a live site — post content referencing
 * `has-sans-font-family` keeps working and simply renders in the new face.
 *
 * @param array<string, mixed> $typography Sanitised typography tokens.
 * @return array<int, array<string, mixed>>
 */
function bridge_compile_font_families(array $typography): array
{
	$sets = bridge_font_sets();
	$set  = $sets[$typography['fontSet']] ?? $sets['system'];

	$roles = array(
		'sans'  => 'Sans',
		'serif' => 'Serif',
		'mono'  => 'Mono',
	);

	$families = array();

	foreach ($roles as $slug => $name) {
		$stack = $set['families'][$slug] ?? $sets['system']['families'][$slug];

		$family = array(
			'slug'       => $slug,
			'name'       => $name,
			'fontFamily' => (string) $stack,
		);

		// Self-hosted sets carry their own @font-face declarations; pass them
		// through untouched when present.
		if (! empty($set['fontFace'][$slug]) && is_array($set['fontFace'][$slug])) {
			$family['fontFace'] = $set['fontFace'][$slug];
		}

		$families[] = $family;
	}

	$google_on = ! empty($typography['googleFonts']);
	$fallback  = $sets['system']['families']['sans'];

	// The chosen family is named in the stack whether or not its files have
	// been downloaded yet. Gating the *stack* on installation as well as the
	// @font-face would mean a selection could never be previewed before it was
	// saved — and if a download later failed, the browser simply falls through
	// to the next name in the stack, which is the correct behaviour anyway.
	$apply = static function (string $family, array $base) use ($fallback): array {
		if ('' === $family) {
			return $base;
		}

		$base['name']       = $family;
		$base['fontFamily'] = sprintf('"%s", %s', $family, $fallback);

		$faces = bridge_google_font_faces($family);

		if ($faces) {
			$base['fontFace'] = $faces;
		}

		return $base;
	};

	// A Google selection replaces the stack behind an existing role rather
	// than adding a slug, so switching typeface never orphans content: a
	// paragraph carrying `has-sans-font-family` renders in the new face.
	$body = $google_on ? (string) ($typography['googleBody'] ?? '') : '';

	foreach ($families as $index => $family) {
		if ('sans' === $family['slug']) {
			$families[$index] = $apply($body, $family);
		}
	}

	// `heading` is always present so the heading role is a first-class part of
	// the design system rather than something that appears and disappears with
	// the Google toggle — a slug that vanishes breaks any content using it.
	// With no Google heading chosen it mirrors the body face.
	$heading = $google_on ? (string) ($typography['googleHeading'] ?? '') : '';

	$families[] = $apply(
		$heading,
		array(
			'slug'       => 'heading',
			'name'       => 'Heading',
			'fontFamily' => $families[0]['fontFamily'],
		)
	);

	return $families;
}

/**
 * The button half of `settings.custom`.
 *
 * Split out because it is two things joined: the geometry of the chosen skin,
 * which is one set of values for the whole site, and the colour scheme of each
 * of the four grounds, which is four. Published as custom properties for the
 * reason the card values are — every surface that draws a button, the front
 * end, the editor and the options preview, reads the same numbers, and a
 * skin change is a change of eleven properties rather than a rebuild.
 *
 * `minSize` is not a skin value and not negotiable: WCAG 2.2 §2.5.8 puts the
 * floor for a pointer target at 24×24 CSS pixels, and 44 is the size a thumb
 * actually hits. It is a floor, not a height — a button with a long label or a
 * large font size grows past it.
 *
 * @param array<string, mixed> $tokens Sanitised tokens.
 * @return array<string, mixed>
 */
function bridge_compile_button_custom(array $tokens): array
{
	$skins = bridge_button_skins();
	// The sanitiser guarantees the slug is one of these; a filter that removed
	// a skin after a site had saved it would not, and a missing skin should be
	// the first skin rather than a PHP notice.
	$skin = $skins[$tokens['buttons']['skin']] ?? reset($skins);

	$button = array(
		'radius'        => (string) $skin['radius'],
		'paddingBlock'  => (string) $skin['paddingBlock'],
		'paddingInline' => (string) $skin['paddingInline'],
		'weight'        => (string) $skin['weight'],
		'transform'     => (string) $skin['transform'],
		'letterSpacing' => (string) $skin['letterSpacing'],
		'borderWidth'   => (string) $skin['borderWidth'],
		'shadow'        => (string) $skin['shadow'],
		'shadowHover'   => (string) $skin['shadowHover'],
		'lift'          => (string) $skin['lift'],
		'sweep'         => (string) $skin['sweep'],
		'minSize'       => '44px',
	);

	// `default` => `onDefault` => `--wp--custom--button--on-default--bg`, the
	// same shape the card grounds take. The audit travels with the scheme
	// inside PHP but is dropped here: it is arithmetic for the options screen
	// to show, not a value any stylesheet can spend.
	foreach (bridge_button_schemes($tokens) as $key => $scheme) {
		unset($scheme['audit']);

		$button['on' . ucfirst($key)] = $scheme;
	}

	return $button;
}

/**
 * Compile a token set into a theme.json fragment.
 *
 * @param array<string, mixed> $tokens Sanitised tokens.
 * @return array<string, mixed>
 */
function bridge_compile_theme_json(array $tokens): array
{
	$palette = array();
	foreach ($tokens['brand']['palette'] as $slug => $entry) {
		$palette[] = array(
			'slug'  => $slug,
			'color' => $entry['color'],
			'name'  => $entry['name'],
		);
	}

	$layout = $tokens['layout'];
	$type   = $tokens['typography'];

	$block_gap    = sprintf('var(--wp--preset--spacing--%s)', $layout['blockGap']);
	$root_padding = sprintf('var(--wp--preset--spacing--%s)', $layout['rootPadding']);
	// Published as a custom property rather than compiled into a style rule:
	// a section's padding is claimed by several selectors — the skins, the
	// section patterns — and a property lets all of them name one value
	// instead of each being regenerated whenever the setting moves.
	$section_padding = sprintf('var(--wp--preset--spacing--%s)', $layout['sectionPadding']);

	// The card surface. Published the same way and for the same reason: six
	// blocks draw a card, each in its own stylesheet bundle, and a custom
	// property lets all of them name one value. abstracts/_card.scss is the
	// only thing that reads these, and every card block goes through it.
	$cards    = $tokens['cards'];
	$shadows  = bridge_card_shadows();
	// The sanitiser guarantees the slug is one of these, but a filter that
	// removed a preset after a site had saved it would not — and a missing
	// shadow should be no shadow rather than a PHP notice.
	$shadow   = $shadows[$cards['shadow']] ?? array('shadow' => 'none', 'hover' => 'none');

	// One property per ground: `--wp--custom--card--on-primary` and friends.
	// The card itself never names one of these — it reads `--bridge-card-bg`,
	// which the section skin points at whichever of them belongs to the band.
	/**
	 * The cut corner.
	 *
	 * A rounded corner and a cut one are two answers to the same question, so
	 * the cut wins outright and the radius compiles to zero — a card cannot be
	 * both, and a 6px round on three corners with a notch on the fourth is the
	 * look nobody asked for. The operator's radius is left in the record
	 * rather than overwritten, so turning the cut off gives them back the
	 * corner they had instead of a square one they have to set again.
	 *
	 * ---- And the shadow goes -----------------------------------------------
	 *
	 * A `box-shadow` is drawn around the card's *box*, so on a cut card it
	 * traces the square corner the cut just removed — the one line on the card
	 * that contradicts its shape. A cut card is therefore given no shadow,
	 * whatever the Shadow control says, on the same principle as the radius: a
	 * card cannot be two shapes, and the corner treatment is the one the
	 * operator asked for most recently. The saved preset is left alone, so
	 * turning the cut off gives back the shadow they had.
	 *
	 * ---- Why the switches are here and not in the stylesheet ---------------
	 *
	 * A cut card is painted differently from a rounded one, not just shaped
	 * differently: the surface moves to a layer that can be clipped, so that
	 * clipping it does not take the card's focus ring with it.
	 *
	 * Every one of those swaps is decided here and published as a value, so the
	 * stylesheet names one property per job and never asks which mode it is in.
	 * That is what keeps the default case honest: with the cut off, the clip is
	 * `none`, the containment is `normal`, the filter is `none` and the surface
	 * is back on the card itself — a card that paints exactly what it painted
	 * before this feature existed, rather than one that goes through the cut
	 * machinery with the numbers set to zero.
	 */
	$cut = ! empty($cards['cutCorner']);

	$card = array(
		'padding'     => sprintf('var(--wp--preset--spacing--%s)', $cards['padding']),
		// A plain length, not a preset: the corner radius scale in the static
		// theme.json is for the small furniture — buttons, thumbnails — and a
		// card's corner is read off a design in pixels.
		'radius'      => $cut ? '0px' : (int) $cards['radius'] . 'px',

		/**
		 * The shape, in container units.
		 *
		 * `cqw` is 1% of the card's own inline size, which is the one way CSS
		 * can spend a share of an element's *width* on both axes at once — and
		 * both is what a 45° line needs. A percentage in a polygon resolves per
		 * axis, so `8% 8%` on a 400×500 card is 32px across and 40px down, and
		 * the line comes out at 51°. The card makes itself the query container
		 * below, which is why the containment is published beside this.
		 */
		'clip'        => $cut
			? sprintf(
				'polygon(0 %1$dcqw, %1$dcqw 0, 100%% 0, 100%% 100%%, 0 100%%)',
				(int) $cards['cutSize']
			)
			: 'none',
		// Containment is not free — it fixes the card's inline size against its
		// contents — so it is only asked for when something is going to read a
		// `cqw`, and the answer is `normal` the rest of the time.
		'container'   => $cut ? 'inline-size' : 'normal',

		/**
		 * And the width the containment then requires.
		 *
		 * Inline-size containment sizes an element *as if it had no contents*,
		 * which is only safe while something else is deciding how wide the card
		 * is. Usually something is: a card is a grid item, and a grid item
		 * stretches to its track. But two of them — the post card and the
		 * download — cap themselves with `max-width` and centre the result with
		 * `margin-inline: auto`, and an auto margin opts an item out of that
		 * stretch: its width then comes from its contents, which containment
		 * has just made nothing. The card collapsed to zero and `overflow:
		 * hidden` did the rest — the grid still reserved the track, so the row
		 * kept its shape and every card in it disappeared.
		 *
		 * `100%` gives the containment a width that was never going to come
		 * from the contents. It is what the stretched cards already resolve to,
		 * so it changes nothing for them, and the capped two keep their
		 * `max-width` and their centring — `width` proposes, `max-width`
		 * disposes.
		 *
		 * Published rather than written into the stylesheet so a card is only
		 * held to a width while it is being contained. This is also the guard
		 * for the next card block someone writes: it cannot collapse, whatever
		 * it does with its own margins.
		 */
		'width'       => $cut ? '100%' : 'auto',

		/**
		 * And the stacking context the surface layer sits in.
		 *
		 * That layer is a `::before` at `z-index: -1` — behind the card's
		 * content, in front of the card's own (clear) background — and a
		 * negative index only stays *inside* an element that establishes a
		 * stacking context. Nothing else on a cut card reliably establishes one:
		 * the containment above did in some engines and versions, but Chrome 129
		 * stopped `container-type: inline-size` doing it. Without one the
		 * surface escapes the card and paints behind the band, taking the card's
		 * ground with it and leaving the photograph floating on the page.
		 *
		 * `isolation: isolate` establishes one for no other reason, in every
		 * engine, and says so. `auto` with the cut off, so a card that is not
		 * carrying a surface layer is not carrying a stacking context either.
		 *
		 * This is now the *only* thing holding that layer in place — a cut card
		 * has no shadow and so no filter — which is exactly why it is stated
		 * rather than left to a side effect of something else.
		 */
		'isolation'   => $cut ? 'isolate' : 'auto',

		/**
		 * Which layer paints the card.
		 *
		 * The cut needs a surface it can clip without taking the card's focus
		 * ring with it, so the ground moves to a layer inside the card and the
		 * card itself goes clear.
		 *
		 * Both of these are switches, never colours. The colour is a chain —
		 * the band's ground, or the light one — and a chain published as a
		 * custom property is resolved at `:root`, where the skins have not set
		 * `--bridge-card-bg` yet; every card came out wearing the light ground
		 * whatever band it stood on. So the colour stays in the stylesheet,
		 * where it is resolved on the card, and only the on/off travels.
		 *
		 * `initial` rather than a colour for the off case: it is the
		 * guaranteed-invalid value, so `var()` falls through to the ground the
		 * stylesheet names. And the layer is switched with `display` rather
		 * than a transparent fill, so a card that has chosen its own ground —
		 * the outlined testimonial is transparent by design — is not painted
		 * over by a layer it never asked for.
		 */
		'ownBg'       => $cut ? 'transparent' : 'initial',
		'layer'       => $cut ? 'block' : 'none',

		// Nothing, on a cut card. See the note above.
		'shadow'      => $cut ? 'none' : $shadow['shadow'],
		'shadowHover' => $cut ? 'none' : $shadow['hover'],
	);

	foreach (bridge_card_grounds() as $key => $entry) {
		// `light` => `onLight` => `--wp--custom--card--on-light`.
		$card['on' . ucfirst($key)] = (string) ($cards['colors'][$key] ?? $entry['default']);
	}

	/**
	 * The per-style settings, one nested group per style.
	 *
	 * WordPress turns nesting into `--` and camelCase into `-`, so
	 * `card.team.avatar` arrives as `--wp--custom--card--team--avatar`.
	 * The Cards block's stylesheet reads these and nothing else does; each
	 * style points its own `--post-card-*` variable at the group that belongs
	 * to it, so the base rules stay written once.
	 *
	 * Every value is resolved here rather than in Sass. A heading becomes a
	 * `var()` at the site's own font-size preset — so the card follows the type
	 * scale rather than carrying a length of its own — and a ratio and an
	 * avatar width become the literal the browser needs.
	 */
	$ratios  = bridge_card_ratios();
	$avatars = bridge_card_avatar_sizes();

	foreach (bridge_card_styles() as $slug => $entry) {
		$saved = (array) ($cards['styles'][$slug] ?? array());
		$group = array();

		foreach ((array) ($entry['fields'] ?? array()) as $field) {
			$value = (string) ($saved[$field] ?? ($entry[$field] ?? ''));

			// The sanitiser guarantees each of these is a slug the theme
			// knows, but a filter that dropped a preset after a site had saved
			// it would not — and a missing preset should fall back to the
			// style's own default rather than publish an empty property.
			switch ($field) {
				case 'heading':
					$group['heading'] = sprintf('var(--wp--preset--font-size--%s)', $value);
					break;

				case 'ratio':
					$group['ratio'] = (string) (
						$ratios[$value]['value'] ?? $ratios[$entry['ratio'] ?? '16-9']['value'] ?? '16 / 9'
					);
					break;

				case 'avatar':
					$group['avatar'] = (string) (
						$avatars[$value]['width'] ?? $avatars[$entry['avatar'] ?? 'm']['width'] ?? 'min(72%, 14rem)'
					);
					break;

				/**
				 * The wash, and the ink that has to survive it.
				 *
				 * Two properties from one setting, because only one of them is
				 * a choice. An operator picks the colour the gradient rises in;
				 * what the title is then painted is arithmetic, and arithmetic
				 * this file is better at than they are.
				 *
				 * Without it a wash in Background or Surface — both legitimate
				 * on a brand with a dark photograph behind them — would leave
				 * the theme's light title white on near-white. `bridge_readable_on()`
				 * is the same function the button labels go through: it takes
				 * the palette's own light and dark slugs first, so the ink is a
				 * brand colour wherever one clears 4.5:1, and falls back to
				 * plain white or black only when neither does.
				 */
				case 'wash':
					$group['wash'] = sprintf('var(--wp--preset--color--%s)', $value);

					$ground = bridge_palette_hex($value);
					$group['ink'] = bridge_readable_on(
						$ground,
						array(
							bridge_palette_hex('background'),
							bridge_palette_hex('text'),
						)
					);
					break;
			}
		}

		if ($group) {
			$card[$slug] = $group;
		}
	}

	return array(
		// Matches the static theme.json. If core's schema advances,
		// WP_Theme_JSON migrates this forward — declaring the latest version
		// blind would skip a migration we may well need.
		'version'  => 3,

		'settings' => array(
			'color'      => array(
				'palette' => $palette,
			),
			// WordPress kebab-cases these into `--wp--custom--section-padding`
			// and `--wp--custom--card--shadow-hover`.
			'custom'     => array(
				'sectionPadding' => $section_padding,
				'card'           => $card,
				// The chosen skin's geometry, and the colour scheme of each of
				// the four grounds. components/_button.scss is the only thing
				// that reads these; every button on the site goes through it.
				'button'         => bridge_compile_button_custom($tokens),
			),
			'typography' => array(
				'fontFamilies' => bridge_compile_font_families($type),
				'fontSizes'    => bridge_compile_font_sizes($type),
			),
			'spacing'    => array(
				// Explicit, fluid presets. The scale below stays as the shape
				// WordPress falls back to if these are ever absent; where both
				// describe a slug, the explicit value wins.
				'spacingSizes' => bridge_compile_spacing_sizes($layout),
				'spacingScale' => array(
					'operator'   => '*',
					'increment'  => (float) $layout['spacingIncrement'],
					// Step count is fixed: WordPress derives the preset slugs
					// (20…80) from it, and both theme.json and the SCSS layer
					// reference those by name.
					'steps'      => 7,
					'mediumStep' => (float) $layout['spacingBase'],
					'unit'       => 'rem',
				),
			),
			'layout'     => array(
				'contentSize' => $layout['contentSize'] . 'px',
				'wideSize'    => $layout['wideSize'] . 'px',
			),
		),

		'styles'   => array(
			'typography' => array(
				'lineHeight' => bridge_css_number((float) $type['bodyLineHeight'], 2),
			),
			'blocks'     => array(
				// The skin's geometry, restated as block styles so the editor
				// canvas draws the right shape from global styles alone. The
				// values are the same custom properties the stylesheet spends,
				// so there is one place to change a skin and no chance of the
				// two descriptions of a button disagreeing.
				'core/button' => array(
					'border'     => array(
						'radius' => 'var(--wp--custom--button--radius)',
						'width'  => 'var(--wp--custom--button--border-width)',
						'style'  => 'solid',
						'color'  => 'var(--wp--custom--button--on-default--border)',
					),
					'spacing'    => array(
						'padding' => array(
							'top'    => 'var(--wp--custom--button--padding-block)',
							'right'  => 'var(--wp--custom--button--padding-inline)',
							'bottom' => 'var(--wp--custom--button--padding-block)',
							'left'   => 'var(--wp--custom--button--padding-inline)',
						),
					),
					'typography' => array(
						'fontSize'      => 'var(--wp--preset--font-size--medium)',
						'fontWeight'    => 'var(--wp--custom--button--weight)',
						'lineHeight'    => '1.2',
						'letterSpacing' => 'var(--wp--custom--button--letter-spacing)',
						'textTransform' => 'var(--wp--custom--button--transform)',
					),
				),
			),
			'spacing'    => array(
				'blockGap' => $block_gap,
				'padding'  => array(
					'top'    => '0',
					'right'  => $root_padding,
					'bottom' => '0',
					'left'   => $root_padding,
				),
			),
			'elements'   => array(
				// The resting fill of a button on the page's own ground.
				// Named here rather than left to the stylesheet so a button
				// looks right in the editor before main.css has been applied
				// to the canvas, and so a block that draws a bare
				// `.wp-element-button` — a login form, a comment reply —
				// picks it up without knowing anything about the skins.
				'button'  => array(
					'color' => array(
						'background' => 'var(--wp--custom--button--on-default--bg)',
						'text'       => 'var(--wp--custom--button--on-default--fg)',
					),
				),
				'heading' => array(
					'typography' => array(
						// Points at the `heading` family rather than `sans`,
						// so a Google heading face applies to every h1–h6
						// without touching a single block.
						'fontFamily'    => 'var(--wp--preset--font-family--heading)',
						'fontWeight'    => $type['headingWeight'],
						'lineHeight'    => bridge_css_number((float) $type['headingLineHeight'], 2),
						// Emitted even when 'none' so switching back off
						// actually resets rather than leaving the previous
						// value standing in the cascade.
						'textTransform' => $type['headingCase'],
					),
				),
			),
		),
	);
}

/**
 * Merge the compiled tokens into the theme origin of the global styles.
 *
 * @param WP_Theme_JSON_Data $theme_json Theme-origin data.
 * @return WP_Theme_JSON_Data
 */
function bridge_filter_theme_json_data($theme_json)
{
	// Core documents that extenders may receive a compatible object that is
	// not WP_Theme_JSON_Data, so check the contract rather than the class.
	if (! is_object($theme_json) || ! method_exists($theme_json, 'update_with')) {
		return $theme_json;
	}

	$data = bridge_compile_theme_json(bridge_get_tokens());

	// Merged here rather than inside the compiler: the compiler turns the token
	// record into geometry and colour, and this is a fixed statement about the
	// theme's own blocks that no token moves.
	$data['settings']['blocks'] = bridge_block_style_lockdown();

	return $theme_json->update_with($data);
}

/**
 * Every block this theme ships, by name.
 *
 * Read off the block.json files rather than listed by hand, so a block added
 * later is covered the day it exists — the alternative is a list that is
 * correct until somebody forgets it, and the failure there is a styling panel
 * quietly reappearing on one block.
 *
 * Memoised: theme.json is resolved more than once a request.
 *
 * @return string[]
 */
function bridge_block_names(): array
{
	static $names = null;

	if (null !== $names) {
		return $names;
	}

	$names = array();

	foreach ((array) glob(get_theme_file_path('src/blocks') . '/*/block.json') as $file) {
		$json = wp_json_file_decode((string) $file, array('associative' => true));

		if (is_array($json) && ! empty($json['name']) && is_string($json['name'])) {
			$names[] = $json['name'];
		}
	}

	sort($names);

	return $names;
}

/**
 * Colour and typography, off, on every block this theme ships.
 *
 * ---- What this is for ------------------------------------------------------
 *
 * A block's own `supports` decide what its Styles tab offers when it is
 * selected on a page. They do not decide what Site Editor → Styles → Blocks
 * offers, which reads theme.json settings and will hand any block the whole
 * palette and the whole type scale unless told otherwise. So a block can
 * declare no colour support and still be given a colour panel one screen over.
 *
 * Both surfaces have to be closed to close the door, and this is the second
 * one.
 *
 * ---- Why every block, and why all of it ------------------------------------
 *
 * Because a Bridge block is not a place where type and colour are decided. Its
 * colour comes from the band's skin — Surface, Inverted, Accent — and its type
 * from the scale in Theme Options, and both of those are one decision applied
 * everywhere rather than a decision per block per page. A colour control on a
 * band is a way to produce a section that matches nothing else on the site,
 * which is the failure this theme exists to prevent.
 *
 * Every key WP_Theme_JSON declares is named, not the obvious four: the panels
 * appear if *any* one control survives, which is how `textAlign` and
 * `textColumns` kept the Typography panel open after the seven likely keys had
 * been turned off.
 *
 * @return array<string, array<string, mixed>>
 */
function bridge_block_style_lockdown(): array
{
	$off = array(
		'color'      => array(
			'background'       => false,
			'button'           => false,
			'caption'          => false,
			'custom'           => false,
			'customDuotone'    => false,
			'customGradient'   => false,
			'defaultDuotone'   => false,
			'defaultGradients' => false,
			'defaultPalette'   => false,
			'heading'          => false,
			'link'             => false,
			'text'             => false,
			'duotone'          => array(),
			'gradients'        => array(),
			'palette'          => array(),
		),
		'typography' => array(
			'customFontSize'   => false,
			'defaultFontSizes' => false,
			'dropCap'          => false,
			'fontStyle'        => false,
			'fontWeight'       => false,
			'letterSpacing'    => false,
			'lineHeight'       => false,
			'textAlign'        => false,
			'textColumns'      => false,
			'textDecoration'   => false,
			'textTransform'    => false,
			'writingMode'      => false,
			'fontSizes'        => array(),
			'fontFamilies'     => array(),
		),
	);

	$blocks = array();

	foreach (bridge_block_names() as $name) {
		$blocks[$name] = $off;
	}

	return $blocks;
}
add_filter('wp_theme_json_data_theme', 'bridge_filter_theme_json_data');
