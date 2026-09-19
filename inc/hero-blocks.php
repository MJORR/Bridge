<?php

/**
 * Bridge — shared plumbing for the two hero blocks.
 *
 * `bridge/hero-banner` and `bridge/hero-slider` are the same box asked to do
 * two things: one Cover or several. Everything about the box was identical and
 * written out twice — the height presets, the header allowance, the full-width
 * class, the LCP hint on the first Cover image. Sixty-odd duplicated lines is
 * sixty-odd chances for the static hero and the sliding one to start disagreeing
 * about where the middle of the window is.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The three lengths a hero publishes as CSS variables.
 *
 * `height` is the floor the hero is measured against. `inset` takes the header
 * out of that floor. `lead` leads the content past a header that overlays the
 * hero rather than sitting above it.
 *
 * A full-screen hero is only full-screen if it accounts for what is above it,
 * and the header decides which of the two arrangements applies: a solid header
 * occupies its own band of the window, so the hero's floor is that much lower
 * and content centred in a shorter box lands centred in what is actually
 * visible. A transparent header floats over the hero instead, taking no height
 * from it, so the floor stays as tall as asked and the content is led down by
 * the height of the thing covering it.
 *
 * Either measure already counts the optional top bar: header.js measures the
 * rendered header, strip included, and bridge_header_height_estimate() adds the
 * strip's term when the header is configured to show one.
 *
 * The measurement header.js publishes wins the moment it exists; until then the
 * estimate keeps the first paint from being a frame taller than the rest.
 *
 * @param array $attributes Block attributes.
 * @return array{height:string,inset:string,lead:string}
 */
function bridge_hero_metrics(array $attributes): array
{
	$preset = isset($attributes['heightPreset']) ? (string) $attributes['heightPreset'] : 'full';

	// Whether the chosen height is a share of the window or a fixed number of
	// pixels. It decides whether the header is worth subtracting: "full screen"
	// means the window, and the header is part of the window; 600px means
	// 600px, and an operator who typed it did not mean 600 minus the header.
	$viewport_relative = true;

	switch ($preset) {
		case 'tall':
			$height = '80dvh';
			break;
		case 'medium':
			$height = '60dvh';
			break;
		case 'custom':
			$unit              = isset($attributes['customHeightUnit']) ? (string) $attributes['customHeightUnit'] : 'vh';
			$unit              = in_array($unit, array('vh', 'dvh', 'px'), true) ? $unit : 'vh';
			$size              = isset($attributes['customHeight']) ? (int) $attributes['customHeight'] : 80;
			$height            = max(20, min(4000, $size)) . $unit;
			$viewport_relative = 'px' !== $unit;
			break;
		default:
			$height = '100dvh';
	}

	$header_used = sprintf(
		'var(--bridge-header-height, %s)',
		function_exists('bridge_header_height_estimate') ? bridge_header_height_estimate() : '0px'
	);

	$overlays = function_exists('bridge_header_overlays') && bridge_header_overlays();

	return array(
		'height' => $height,
		'inset'  => ($viewport_relative && ! $overlays) ? $header_used : '0px',
		'lead'   => $overlays ? $header_used : '0px',
	);
}

/**
 * The five things a banner can be standing on, resolved to one answer.
 *
 * The block offers a background rather than asking an editor to build one out
 * of a Cover: a photograph, a flat colour, that colour graded across, a colour
 * panel whose edge is a drawn curve with a picture behind it, or a run of
 * colour bands at an angle laid over a photograph. All five are the same two
 * questions — what is behind the words, and what colour can the words be — so
 * they are answered in one place and the renderer spends the answer.
 *
 * ---- The foreground is worked out, not chosen ------------------------------
 *
 * There is no text-colour setting, because there is no useful second answer.
 * A palette ground has exactly one legible label colour and the palette already
 * knows which: `bridge_is_light_color()` is the same test the header makes
 * against the same six colours. A photograph is darkened by the overlay below
 * it, so its answer is always the light one — a hero that asked an editor to
 * pick would mostly be offering them the chance to get it wrong.
 *
 * ---- Custom gradient asks the gradient, not the band ----------------------
 *
 * It is the one background where what is behind the words is not settled by a
 * single setting: a gradient laid over a photograph is either a veil the
 * picture reads through — in which case the type is on the picture and takes
 * the picture's answer — or a stretch of brand colour the type is on instead.
 * bridge_hero_gradient_cover() decides which, and only when it says there is a
 * cover does the test run on that colour rather than on the band's own. A light
 * cover therefore gets dark type here, which a photograph never does.
 *
 * ---- The gradient takes no second colour ----------------------------------
 *
 * It is the chosen colour graded, an eighth darker at one edge and an eighth
 * lighter at the other — the same gradient the section skins wear, drawn by the
 * same arithmetic in components/_sections.scss. A hero with its own two-colour
 * picker would be a second gradient vocabulary on a site that already has one,
 * and the one it has follows a rebrand because it is derived rather than typed.
 *
 * @param array $attributes Block attributes.
 * @return array{kind:string,slug:string,ground:string,fg:string,inverted:bool,overlay:float}
 */
function bridge_hero_banner_ground(array $attributes): array
{
	$kind = isset($attributes['background']) ? (string) $attributes['background'] : 'image';

	if (! in_array($kind, array('image', 'solid', 'gradient', 'curve', 'custom-gradient'), true)) {
		$kind = 'image';
	}

	$palette = bridge_get_tokens()['brand']['palette'];
	$slug    = bridge_pick_palette_slug($attributes['color'] ?? null, 'primary');
	$hex     = (string) ($palette[$slug]['color'] ?? '#0f172a');

	// The gradient's own cover, where it has one. Null says the bands are a
	// veil rather than something to stand on, and the background underneath
	// answers instead — see bridge_hero_gradient_cover().
	$cover = 'custom-gradient' === $kind
		? bridge_hero_gradient_cover(bridge_hero_gradient($attributes))
		: null;

	// A gradient with no cover falls back to whatever it is laid over, which is
	// the photograph when there is one and the band's own colour when there is
	// not. Stated here rather than left to the `image` branch below, because
	// the kind is not `image` and the picture is optional.
	$veiled_photo = 'custom-gradient' === $kind
		&& null === $cover
		&& (! empty($attributes['imageId']) || ! empty($attributes['imageUrl']));

	// White type over a photograph whatever the palette is set to — the picture
	// is dimmed to carry it, which is what the overlay is for. Everywhere else
	// the ground is a palette colour and the label is the one it can hold — the
	// gradient's cover where there is one, since that is what the words are on,
	// and the banner's ground everywhere else.
	$legible = null !== $cover
		? (string) ($palette[$cover]['color'] ?? $hex)
		: $hex;

	$inverted = ('image' === $kind || $veiled_photo)
		? true
		: ! bridge_is_light_color($legible);

	// A share, not a percentage: the stylesheet spends it as an alpha.
	$overlay = isset($attributes['overlay']) ? (float) $attributes['overlay'] : 50.0;
	$overlay = max(0.0, min(80.0, $overlay)) / 100;

	return array(
		'kind'     => $kind,
		'slug'     => $slug,
		'ground'   => sprintf('var(--wp--preset--color--%s)', $slug),
		'fg'       => $inverted
			? 'var(--wp--preset--color--background)'
			: 'var(--wp--preset--color--text)',
		'inverted' => $inverted,
		'overlay'  => $overlay,
	);
}

/**
 * The Custom curve's settings, clamped to what the shape can survive.
 *
 * ---- What the banner is made of -------------------------------------------
 *
 * Three things, and each setting belongs to exactly one of them:
 *
 *   One curve, pinned by its two ends and how far it bows between them —
 *   `align`, `vertical`, `bulge`.
 *   The colour, filling everything to the left of it.
 *   A second copy of the curve beside the first, in the same colour at reduced
 *   strength, laid over the picture — `gap` and `opacity`.
 *
 * The curve is a curve and not an arc of anything. It was a circle once, and
 * the circle was the problem: a circle has a radius, a radius has to reach, and
 * every setting ended up answering to that instead of to the drawing. Two ends
 * and a bulge say the same shapes with nothing left over.
 *
 * Every one of them is a share of the banner's width rather than a length: the
 * shapes are drawn in a 100 × 100 box that is square and as wide as the banner,
 * so a radius of 30 is thirty per cent of the width at every window size and
 * there is nothing to keep in step as the band resizes. The exceptions are the
 * angle, which is a rotation and has no size to be a share of, and the opacity.
 *
 * @param array $attributes Block attributes.
 * @return array{align:float,vertical:float,bulge:float,gap:float,opacity:float,padding:float}
 */
function bridge_hero_curve(array $attributes): array
{
	$read = static function (string $key, float $default, float $min, float $max) use ($attributes): float {
		$value = isset($attributes[$key]) ? (float) $attributes[$key] : $default;

		return max($min, min($max, $value));
	};

	return array(
		/*
		 * Where the curve meets the bottom of the banner, measured from the
		 * bottom-right corner: 0 is the corner itself and 100 the bottom-left.
		 *
		 * Measured from the right because that is the end the shape is built
		 * from — the curve comes down the right-hand side of the banner and the
		 * question an operator is answering is how far across the bottom it has
		 * got to by the time it lands.
		 */
		'align'    => $read('curveAlign', 44, 0, 100),
		/*
		 * And where its other end sits on the right-hand edge, measured down
		 * from the top-right corner as a share of the banner's height.
		 *
		 * 0 by default, which is the corner itself: the curve starts where the
		 * top edge and the right edge meet and sweeps down and left from there,
		 * so the shape is anchored to the banner rather than floating in it.
		 * Push it down and the curve starts partway along the right-hand edge,
		 * leaving a square corner of colour above it. Pull it negative and the
		 * end goes above the banner, so what shows is the part of the curve
		 * after it has already turned over.
		 */
		'vertical' => $read('curveVertical', 0, -100, 100),
		/*
		 * How far the curve bows away from the straight line between its two
		 * ends, as a share of the banner's width.
		 *
		 * Not a radius. The shape is not a circle and does not need to be: a
		 * radius is a fact about a circle and this is a question about a curve —
		 * how far out does it bow — which is the thing an operator can see and
		 * the thing a slider should therefore hold. 0 is a straight diagonal
		 * between the two ends.
		 */
		'bulge'    => $read('curveRadius', 29, 0, 100),
		// How far the second curve sits to the right of the first, as a share
		// of the width. The same curve, moved — so the band between them is an
		// even width the whole way down.
		'gap'      => $read('curveGap', 9, -40, 40),
		'opacity'  => $read('curveOpacity', 35, 0, 100),
		// How much clear air the words keep between themselves and the curve at
		// its deepest. The other side is the page's own gutter and is not a
		// choice: a headline that does not start where the logo starts is a
		// headline out of true.
		'padding'  => $read('curvePadding', 4, 0, 20),
	);
}

/**
 * The same shape's settings for a narrow screen.
 *
 * Four numbers, not seven, and the four are not an abridgement — they are all
 * the stacked arrangement has anything to do with.
 *
 * Turned on its side the panel is the floor of the band rather than one side
 * of it, so `align` and `angle` have nothing left to answer: both say where
 * across the *width* the curve sits, and across the width is the one choice
 * this composition does not have. `padding` goes the same way — the words are
 * centred in the panel below the curve with the page's own gutter either side
 * of them, which is not a distance off the shape. See
 * bridge_hero_curve_path_stacked(), which reads none of the three.
 *
 * What is left is the drawing itself — how far it bulges, how wide the second
 * edge is, how much of the panel's colour that edge lays over the picture —
 * plus the one thing the wide layout has no equivalent of: how far down the
 * band the join falls, which is `align` rotated a quarter turn and is the
 * setting that decides how much phone screen the words get.
 *
 * The defaults are what the block drew before any of this was adjustable, so a
 * banner nobody has opened since keeps the mobile composition it had. `stack`
 * in particular is BRIDGE_HERO_CURVE_STACK, which is where that number came
 * from and is now the default rather than the answer.
 *
 * @param array $attributes Block attributes.
 * @return array{radius:float,gap:float,opacity:float,stack:float}
 */
function bridge_hero_curve_mobile(array $attributes): array
{
	$read = static function (string $key, float $default, float $min, float $max) use ($attributes): float {
		$value = isset($attributes[$key]) ? (float) $attributes[$key] : $default;

		return max($min, min($max, $value));
	};

	return array(
		// Still the depth of the bulge, unlike the wide layout's — the stacked
		// arrangement has no second control to be round or tall against, so
		// there is nothing here for a radius to mean that a depth does not.
		'radius'  => $read('curveRadiusMobile', 8, 0, 40),
		// How much shallower the second curve is than the first, as a
		// percentage. The wide layout's `gap` with the axes swapped, and the
		// same crescent: widest at the bulge, closed at the two side edges.
		'gap'     => $read('curveGapMobile', 3, 0, 20),
		'opacity' => $read('curveOpacityMobile', 60, 0, 100),
		// Where the join crosses, as a share of the *height*. The floor and the
		// ceiling are the two ways this stops being a composition: past 85 the
		// words have no room left, and below 30 the picture is a strip.
		'stack'   => $read('curveStackMobile', BRIDGE_HERO_CURVE_STACK, 30, 85),
		// A tilt, in degrees, spent as an equal and opposite shift of the two
		// ends — the same arithmetic as the wide layout's `angle` with the axes
		// swapped, so here it is the left and right crossings that move rather
		// than the top and bottom, and the join leans instead of the panel
		// rotating. The same ±45 for the same reason: past that the ends have
		// crossed more of the band than the bulge is deep and what is left is a
		// diagonal.
		'angle'   => $read('curveAngleMobile', 0, -45, 45),
		// How far across the band the curve is at its deepest, as a signed
		// share of the width either side of the middle. The wide layout's
		// `vertical` turned a quarter turn: there it slides the fullest part up
		// and down, here left and right. Same ±60, and past it the fullest part
		// of the shape is outside the band and what is left inside is an edge
		// leaning one way, which the angle says better.
		'offset'  => $read('curveOffsetMobile', 0, -60, 60),
		/*
		 * Clear air either side of the words, as a share of the band's width,
		 * on top of the page's own gutter.
		 *
		 * The wide layout's Text padding has one side to answer for — the words
		 * run from the page's left margin to wherever the curve has got to, and
		 * only the second of those is a judgement. Stacked there is no shape
		 * beside the type at all: the curve is above it and the words are
		 * centred in the panel under it, so this is both sides at once and what
		 * it buys is a measure rather than a distance off anything.
		 *
		 * Which is worth having on a phone more than anywhere else. The gutter
		 * is the same length the logo is set against and it is right for a
		 * logo; a headline, a line of body copy and a button all running to
		 * within that of the screen edge is a column with no margins, and the
		 * button in particular reads as touching the side of the phone.
		 *
		 * Defaulted to the wide layout's own 4 rather than to nothing, because
		 * a banner that has never been opened is one nobody has set this for
		 * and four per cent is the figure this theme already calls a sensible
		 * amount of air.
		 */
		'padding' => $read('curvePaddingMobile', 4, 0, 20),
		/*
		 * And the same question down the band — in pixels, which is the part
		 * worth explaining.
		 *
		 * Every other number in this file is a share of the band, so the shape
		 * keeps its proportions at any size and nothing has to be re-tuned. A
		 * vertical padding cannot join them: a percentage padding resolves
		 * against the containing block's *width* whichever edge it is on, so
		 * `padding-block: 6%` on a phone is six per cent of 375px and on a
		 * tablet six per cent of 900, and the air above the headline would grow
		 * as the screen got wider while the headline stayed where it was. That
		 * is the same trap the `fr` rows in blocks/_hero-banner.scss exist to
		 * avoid, and the answer is the same: use a measure that means what it
		 * says vertically.
		 *
		 * So a length. The default is roughly what the spacing preset this
		 * replaced resolved to at phone width, rounded to something an operator
		 * would type, so a banner nobody has opened keeps the band it had.
		 */
		'paddingBlock' => $read('curvePaddingBlockMobile', 16, 0, 96),
	);
}

/**
 * Where this banner turns from the wide arrangement to the stacked one.
 *
 * A per-block number rather than the theme's own breakpoint, which is what it
 * used to be and what the default still is. The two arrangements are not the
 * same composition at two sizes — one has the picture beside the words and the
 * other has it above them — and which of the two a given banner can carry
 * depends on things only its editor can see: how long the headline is, how
 * much of the photograph matters, whether there is a button under it. A
 * headline of six words survives the wide layout much further down than one of
 * twenty.
 *
 * Bounded at both ends because a breakpoint outside them is not a choice, it is
 * one of the two arrangements switched off: below 360 no phone ever reaches the
 * stacked version, and above 1600 no ordinary laptop ever reaches the wide one.
 *
 * Returned as an integer of pixels, because it is spent in a media query and a
 * fractional pixel in one is a query that reads as a mistake even where it
 * works.
 *
 * @param array $attributes Block attributes.
 * @return int Pixels.
 */
function bridge_hero_curve_breakpoint(array $attributes): int
{
	$value = isset($attributes['curveBreakpoint'])
		? (int) $attributes['curveBreakpoint']
		: BRIDGE_HERO_CURVE_BREAKPOINT;

	return max(360, min(1600, $value));
}

/**
 * One banner's curve, as a stylesheet of its own.
 *
 * The breakpoint is a per-block setting and a media query cannot read a custom
 * property, so there is no way to hand this to the shared stylesheet as a
 * value — the query has to be written with the number already in it, which
 * means one query per banner. Hence a `<style>` beside the block rather than
 * rules in blocks/_hero-banner.scss, and hence the class: everything here is
 * scoped to the instance that emitted it, so two banners on one page can turn
 * at two different widths.
 *
 * Only the things that actually differ per instance are here. Everything about
 * how a curve is drawn, coloured and laid out stays in the stylesheet where it
 * can be read; this is the switch and the four numbers on the far side of it.
 *
 * ---- Why the opacity is here and not on the style attribute ---------------
 *
 * It used to be published inline with the inset and the padding, and it cannot
 * stay there now that it has a second value: an inline declaration beats every
 * stylesheet rule that is not `!important`, so the mobile figure below would
 * never have won and the crescent would have kept its desktop strength on a
 * phone. Both values are written here instead, which leaves the cascade in
 * charge of which one is live. The same trade the header's call to action
 * makes with `--bridge-cta-*`.
 *
 * ---- The selectors ---------------------------------------------------------
 *
 * Each one carries the instance class *and* the classes the rule it is
 * overriding carries, so it outranks that rule on specificity rather than on
 * the accident of a `<style>` in the body landing after a stylesheet in the
 * head. `.bridge-hero-banner.is-bg-curve .bridge-hero-banner__content` is
 * three classes; a scoped rule with two would have lost to it and the words
 * would have stayed ranged left under the picture.
 *
 * @param string $class     The instance's own class, without the dot.
 * @param array  $mobile    Numbers from bridge_hero_curve_mobile().
 * @param float  $opacity   The wide layout's crescent opacity, 0-100.
 * @param int    $breakpoint Pixels, from bridge_hero_curve_breakpoint().
 * @return string CSS, with no <style> tags around it.
 */
function bridge_hero_curve_css(string $class, array $mobile, float $opacity, int $breakpoint): string
{
	$sel = '.' . sanitize_html_class($class);
	$n   = static fn(float $value): string => bridge_css_number($value, 3);

	return sprintf(
		'%1$s{--bridge-hero-banner-curve-opacity:%2$s}'
			. '@media (max-width:%3$dpx){'
			. '%1$s .bridge-hero-banner__curve .is-wide{display:none}'
			. '%1$s .bridge-hero-banner__curve .is-stacked{display:inline}'
			. '%1$s.bridge-hero-banner.is-bg-curve{'
			. 'grid-template-rows:%4$sfr %5$sfr;'
			. '--bridge-hero-banner-curve-opacity:%6$s'
			. '}'
			. '%1$s.bridge-hero-banner.is-bg-curve .bridge-hero-banner__content{'
			. 'grid-row:2;'
			. 'align-items:center;'
			. 'text-align:center;'
			// The page's gutter plus the horizontal padding setting, both sides,
			// and the vertical one above and below. See the notes on `padding`
			// and `paddingBlock` in bridge_hero_curve_mobile(), including why
			// only one of the two can be a share of the band.
			. 'padding-inline:calc(var(--bridge-hero-banner-gutter) + %7$s%%);'
			. 'padding-block:%8$spx'
			. '}'
			/*
			 * And the one child `text-align` cannot reach.
			 *
			 * The content's children are `width: 100%` — see the note on that
			 * rule in blocks/_hero-banner.scss, which explains why they take
			 * the column rather than shrinking to their own text — so a
			 * centred column is centred type, and that is the whole of it for
			 * a heading or a paragraph. A Buttons block is not type: it is a
			 * flex row of its own, and a full-width flex row ignores the
			 * `text-align` above it and leaves its button on the left of a
			 * banner where everything else is centred.
			 *
			 * Only when the editor has not said otherwise, and `:where()` is
			 * what makes that true rather than merely intended. Justification
			 * is a layout attribute on `core/buttons` and core spends it as a
			 * generated `.wp-container-core-buttons-is-layout-*` rule worth one
			 * class — so this selector, which names a block, a background and a
			 * per-banner scope, would outrank the choice it exists to yield to.
			 * Wrapped in `:where()` it weighs nothing and every core rule beats
			 * it, which is what a default is.
			 *
			 * The `:not()` is for content written before WordPress moved that
			 * choice out of a class and into the layout attribute.
			 */
			. ':where(%1$s.bridge-hero-banner.is-bg-curve .bridge-hero-banner__content '
			. '.wp-block-buttons:not([class*="is-content-justification"])){'
			. 'justify-content:center'
			. '}'
			. '}',
		$sel,
		$n($opacity / 100),
		// The query is the complement of the setting, the way `$panel` is the
		// complement of `$bar` in abstracts/_nav.scss: the setting is the width
		// at which the banner is still wide, so the stacked arrangement starts
		// one pixel below it and there is no width where neither applies.
		$breakpoint - 1,
		// The crossing and its remainder. `fr` rather than a percentage because
		// the join is at a share of the banner's *height* and a percentage
		// padding would resolve against its width — see the note on
		// BRIDGE_HERO_CURVE_STACK.
		$n($mobile['stack']),
		$n(100 - $mobile['stack']),
		$n($mobile['opacity'] / 100),
		$n($mobile['padding']),
		$n($mobile['paddingBlock'])
	);
}

/**
 * Custom gradient — a run of colour bands laid across the band, over whatever
 * is behind it.
 *
 * ---- What it is -----------------------------------------------------------
 *
 * One gradient at one angle, described as a list of bands rather than as a list
 * of stops. Each band is the thing an operator can actually see and point at: a
 * stretch of the banner with a start, an end, and a colour at each of those two
 * places. "Solid at the left, gone by halfway" is one band; "back again at
 * seventy per cent and four-fifths of the way there by the right-hand edge" is
 * a second one.
 *
 * A raw stop list says the same shapes and says them worse. A stop has one
 * position and one colour and no idea what it belongs to, so the operator is
 * left holding the invariant — that stop 3 and stop 4 are a pair, that moving
 * one without the other turns a fade into a step. A band holds its own two
 * ends, which is why the tool over the preview can give it two handles and
 * nothing else has to be kept in step by hand.
 *
 * ---- What happens between the bands ---------------------------------------
 *
 * Nothing, which is the point. The gap between one band's end and the next
 * one's start holds the colour the last band finished on, all the way across —
 * so a band that fades to nothing leaves nothing behind it until the next band
 * starts, and a band that ends solid stays solid until something else happens.
 * The same rule runs off both ends of the list: before the first band and after
 * the last, the gradient holds.
 *
 * That is CSS's own behaviour for a stop list, kept rather than fought, and it
 * means there is no hard edge anywhere the operator did not ask for by starting
 * a band on a different colour from the one the last one ended on.
 *
 * ---- Why the angle is not per band ----------------------------------------
 *
 * Because two gradients at two angles is two gradients, and this is one. The
 * bands are stretches of a single line laid across the banner; the angle is
 * which way that line runs. Per-band angles would make the positions mean
 * different things in different bands, and the tool over the preview — one rail
 * with a handle per end — would have nothing left to be a rail of.
 *
 * ---- Transparent is an opacity, not a colour ------------------------------
 *
 * Every end of every band is a palette slug and a strength. Transparent is that
 * strength at zero, rather than a `transparent` entry in the colour list, so
 * there is one control to reach for whichever direction a band is going and a
 * fade to nothing keeps the hue it is fading from — which is what stops the
 * grey cast browsers used to give a fade to the `transparent` keyword.
 *
 * @param array $attributes Block attributes.
 * @return array{angle:float,bands:array<int,array{start:float,end:float,from:string,fromAlpha:float,to:string,toAlpha:float}>}
 */
function bridge_hero_gradient(array $attributes): array
{
	$clamp = static function ($value, float $default, float $min, float $max): float {
		$number = is_numeric($value) ? (float) $value : $default;

		return max($min, min($max, $number));
	};

	// A rotation, so it wraps rather than clamping: 370 is 10 and -90 is 270.
	// fmod twice because PHP's fmod keeps the sign of the dividend.
	$angle = is_numeric($attributes['gradientAngle'] ?? null)
		? (float) $attributes['gradientAngle']
		: 90.0;
	$angle = fmod(fmod($angle, 360.0) + 360.0, 360.0);

	$stored = $attributes['gradientStops'] ?? null;
	$bands  = array();

	if (is_array($stored)) {
		foreach ($stored as $band) {
			if (! is_array($band)) {
				continue;
			}

			$start = $clamp($band['start'] ?? null, 0.0, 0.0, 100.0);
			// Never behind its own start. A band dragged past itself is a band
			// with no length rather than one that runs backwards, which is a
			// gradient stop list no browser will parse.
			$end = max($start, $clamp($band['end'] ?? null, 100.0, 0.0, 100.0));

			$bands[] = array(
				'start'     => $start,
				'end'       => $end,
				'from'      => bridge_pick_palette_slug($band['from'] ?? null, 'primary'),
				'fromAlpha' => $clamp($band['fromAlpha'] ?? null, 100.0, 0.0, 100.0),
				'to'        => bridge_pick_palette_slug($band['to'] ?? null, 'primary'),
				'toAlpha'   => $clamp($band['toAlpha'] ?? null, 0.0, 0.0, 100.0),
			);
		}
	}

	// In the order they are laid across the banner rather than the order they
	// were added, so the stop list below comes out ascending whatever the
	// editor did. `usort` is stable in PHP 8, so two bands starting at the same
	// place keep the order the operator put them in.
	usort($bands, static fn(array $a, array $b): int => $a['start'] <=> $b['start']);

	/*
	 * And no band overlapping the one before it.
	 *
	 * Two bands sharing a stretch of the line is a stop list that goes
	 * backwards, and a browser handed one clamps each stop up to the highest
	 * one before it — so the overlap is not drawn, it is silently turned into a
	 * hard edge somewhere the operator did not put one. Better to be the thing
	 * that decides where that edge goes: the earlier band keeps the ground it
	 * already covers, and the later one starts where that one finishes.
	 *
	 * The editor will not let a handle cross its neighbour, so this is the net
	 * under hand-edited block markup rather than something an operator can
	 * reach. It runs after the sort because "the one before it" is a fact about
	 * the painted order, not the stored one.
	 */
	$edge = 0.0;

	foreach ($bands as $index => $band) {
		$bands[$index]['start'] = max($edge, $band['start']);
		$bands[$index]['end']   = max($bands[$index]['start'], $band['end']);
		$edge                   = $bands[$index]['end'];
	}

	// Eight is not a composition, it is a stress test. The cap is here rather
	// than in the editor as well because what is stored is what gets printed,
	// and a hand-edited block should not be able to put a kilobyte of gradient
	// in a style attribute.
	return array(
		'angle' => $angle,
		'bands' => array_slice($bands, 0, BRIDGE_HERO_GRADIENT_MAX),
	);
}

/**
 * How many bands one banner may carry.
 *
 * The editor stops offering Add band at this number and bridge_hero_gradient()
 * drops anything past it, so the two agree without either asking the other.
 */
define('BRIDGE_HERO_GRADIENT_MAX', 8);

/**
 * One end of one band, as a CSS colour.
 *
 * `color-mix()` against `transparent` rather than an `rgb()` with an alpha,
 * because the colour arrives as a palette custom property and its channels are
 * not ours to take apart — the same reason the rest of this theme mixes rather
 * than rebuilds. At full strength the mix is skipped: a plain `var()` is
 * shorter, and it is what the majority of stops are.
 *
 * @param string $slug  A palette slug, already picked.
 * @param float  $alpha 0–100.
 * @return string A CSS colour.
 */
function bridge_hero_gradient_color(string $slug, float $alpha): string
{
	$color = sprintf('var(--wp--preset--color--%s)', sanitize_html_class($slug));

	if ($alpha >= 100.0) {
		return $color;
	}

	return sprintf(
		'color-mix(in srgb, %s %s%%, transparent)',
		$color,
		bridge_css_number($alpha, 2)
	);
}

/**
 * The bands, as one `linear-gradient()`.
 *
 * Two stops per band — its start colour at its start, its end colour at its end
 * — and, wherever there is clear air between one band and the next, two more
 * that hold the last colour flat across it. Holding it takes a pair rather than
 * a single stop because one stop between two others is a stop the browser
 * interpolates *through*; a pair with the same colour at both ends is a flat
 * run, which is what a gap is.
 *
 * Nothing is emitted for the run before the first band or after the last one.
 * CSS already holds the first and last stop's colour out to the edges of the
 * gradient line, which is the same rule the gaps follow, so saying it again
 * would only be two more stops that cannot change the picture.
 *
 * Every value in the result is a clamped float or a slug through
 * sanitize_html_class(), so there is no string here that could close the style
 * attribute this ends up in.
 *
 * @param array $gradient The result of bridge_hero_gradient().
 * @return string A `linear-gradient()`, or '' when there are no bands.
 */
function bridge_hero_gradient_css(array $gradient): string
{
	if (empty($gradient['bands'])) {
		return '';
	}

	$n     = static fn(float $value): string => bridge_css_number($value, 3);
	$stops = array();
	$held  = null;
	$at    = null;

	foreach ($gradient['bands'] as $band) {
		$from = bridge_hero_gradient_color($band['from'], $band['fromAlpha']);
		$to   = bridge_hero_gradient_color($band['to'], $band['toAlpha']);

		// Clear air behind this band: the last colour, flat across the gap.
		if (null !== $held && $band['start'] > $at) {
			$stops[] = $held . ' ' . $n($at) . '%';
			$stops[] = $held . ' ' . $n($band['start']) . '%';
		}

		$stops[] = $from . ' ' . $n($band['start']) . '%';
		$stops[] = $to . ' ' . $n($band['end']) . '%';

		$held = $to;
		$at   = $band['end'];
	}

	return sprintf(
		'linear-gradient(%sdeg, %s)',
		$n($gradient['angle']),
		implode(', ', $stops)
	);
}

/**
 * The gradient, as a stylesheet rule scoped to one banner.
 *
 * ---- Why this is not on the wrapper like everything else ------------------
 *
 * Because it would not survive the trip. WordPress runs every inline style
 * through `safecss_filter_attr()`, which strips the CSS functions it knows,
 * then throws away any declaration that still has a parenthesis left in it.
 *
 * A gradient in a custom property is on that list — but only as far as one
 * level of nested functions, which is exactly what the matching pattern in
 * kses.php allows. These stops are two: `color-mix()` with a `var()` inside it,
 * which is what a palette colour at less than full strength has to be, because
 * the colour arrives as a variable and its channels are not ours to take apart.
 * So the gradient does not match, the parentheses stay, and the whole
 * declaration is dropped — silently, with no error and no property, giving a
 * background that works in the editor, whose canvas is not filtered, and does
 * nothing on the page. The same trap bridge_hex_alpha() in inc/color.php exists
 * to sidestep, one turn further in.
 *
 * A rule in a `<style>` is not an inline style and is not filtered, so the
 * gradient goes there instead, scoped to a class generated for this one banner.
 * That is the same arrangement the curve already needs for its opacity, and for
 * a different reason — see bridge_hero_curve_css().
 *
 * Nothing in the result is interpolated from anything an author typed: the
 * selector is generated by the caller and every value in the gradient is a
 * clamped float or a slug through sanitize_html_class(), so there is no string
 * here that could close the element early.
 *
 * @param string $class    The banner's own generated class.
 * @param string $gradient A `linear-gradient()` from bridge_hero_gradient_css().
 * @return string A stylesheet, or '' when there is no gradient to paint.
 */
function bridge_hero_gradient_style(string $class, string $gradient): string
{
	if ('' === $gradient) {
		return '';
	}

	return sprintf(
		'.%s{--bridge-hero-banner-gradient:%s}',
		sanitize_html_class($class),
		$gradient
	);
}

/**
 * The colour the words are actually sitting on, when there is one.
 *
 * A gradient laid over a photograph is either a veil — something the picture
 * reads through, which leaves the type on the picture — or a cover, a stretch
 * of brand colour the type is on instead. The two want opposite labels, and
 * the thing that decides which it is is how much of the strongest band is
 * poured on.
 *
 * Half. Below it the picture is still the thing under the words and the answer
 * is the photograph's; at or above it the colour is, and the answer is whatever
 * that colour can carry. There is no arrangement where the threshold is the
 * interesting part — a gradient hovering at exactly half strength over a
 * photograph has a legibility problem no label colour can fix.
 *
 * @param array $gradient The result of bridge_hero_gradient().
 * @return string|null A palette slug, or null when the gradient is a veil.
 */
function bridge_hero_gradient_cover(array $gradient): ?string
{
	$slug  = null;
	$alpha = 50.0;

	foreach ($gradient['bands'] as $band) {
		foreach (array(array($band['from'], $band['fromAlpha']), array($band['to'], $band['toAlpha'])) as $end) {
			if ($end[1] >= $alpha) {
				$alpha = $end[1];
				$slug  = $end[0];
			}
		}
	}

	return $slug;
}

/**
 * The picture's focal point, as an `object-position`.
 *
 * Only the fixed-media banner still uses this. `background-attachment: fixed`
 * paints the picture as a background rather than printing an `<img>` — see the
 * rule in blocks/_hero-banner — and a background cannot be moved by a
 * transform, so there the focal point stays what it always was: which part of
 * the file survives the crop, spent as a `background-position`.
 *
 * Everything else takes bridge_hero_focal_shift() instead, which moves the
 * picture rather than choosing a crop of it.
 *
 * Stored as two shares of the image, which is what core's focal point picker
 * deals in, and spent as two percentages. Clamped because a stored value is
 * only ever as trustworthy as the request that wrote it.
 *
 * @param array $attributes Block attributes.
 * @return string A CSS position, two percentages.
 */
function bridge_hero_focal_point(array $attributes): string
{
	$point = isset($attributes['focalPoint']) && is_array($attributes['focalPoint'])
		? $attributes['focalPoint']
		: array();

	$axis = static function (string $key) use ($point): string {
		$value = isset($point[$key]) ? (float) $point[$key] : 0.5;

		return bridge_css_number(max(0.0, min(1.0, $value)) * 100, 2) . '%';
	};

	return $axis('x') . ' ' . $axis('y');
}

/**
 * How much the picture has to grow to stay behind the move.
 *
 * ---- The problem -----------------------------------------------------------
 *
 * bridge_hero_focal_shift() moves the picture rather than choosing a crop of
 * it, which is the only way a focal point does anything on a photograph the
 * same shape as the band — `object-position` has no slack to work with there
 * and the control does nothing at all. The cost was a hole: the picture is the
 * size of the band, so anything that moves it uncovers the ground behind, and
 * a banner pushed to the end of the travel was half photograph and half flat
 * colour.
 *
 * ---- The arithmetic --------------------------------------------------------
 *
 * The picture is scaled about its own centre and then moved, so at scale `z` it
 * reaches `z / 2` of a band either side of centre and the band's own edges are
 * half a band out. Moving it by `t` leaves the near edge at `t - z / 2`, which
 * has to be at or past `-1 / 2`:
 *
 *     z >= 1 + 2 * |t|
 *
 * — on both axes at once, so the larger of the two decides. At the end of the
 * travel that is `z = 2`: half a band of movement is paid for by a picture
 * twice the size, which is exactly the hole it would otherwise have left.
 *
 * ---- Why it is worked out and not a setting --------------------------------
 *
 * Because there is no useful second answer. Every value below the one this
 * returns shows the ground through, and every value above it is a picture
 * cropped harder than the move needs — which is a zoom control, a different
 * question, and one nothing here is asking. Derived, it follows the focal point
 * without anyone having to keep the two in step.
 *
 * Nothing about the band is in it. The move is a share of the band and so is
 * the reach, so the answer holds at any window size and any photograph, and the
 * front end needs no measurement to arrive at it.
 *
 * @param array $attributes Block attributes.
 * @return string A scale factor, 1 or greater.
 */
function bridge_hero_focal_zoom(array $attributes): string
{
	$point = isset($attributes['focalPoint']) && is_array($attributes['focalPoint'])
		? $attributes['focalPoint']
		: array();

	$travel = static function (string $key) use ($point): float {
		$value = isset($point[$key]) ? (float) $point[$key] : 0.5;

		return abs(0.5 - max(0.0, min(1.0, $value)));
	};

	return bridge_css_number(1 + 2 * max($travel('x'), $travel('y')), 4);
}

/**
 * How far the picture is allowed to travel off the middle of the band, as a
 * share of the band in each direction.
 *
 * Half a band each way, which is as far as a subject ever needs to go: at the
 * end of it the picture's own middle is on the banner's edge.
 *
 * It used to be half a band of *gap* as well — the picture moved and nothing
 * took its place, so a banner dragged to the end of the travel was half
 * photograph and half ground. That is no longer what happens; see
 * bridge_hero_focal_zoom(), which grows the picture by exactly as much as the
 * move would otherwise have uncovered.
 */
define('BRIDGE_HERO_FOCAL_TRAVEL', 50);

/**
 * The picture's focal point, as a translation.
 *
 * ---- Why a transform and not `object-position` -----------------------------
 *
 * Because `object-position` cannot move a picture; it can only choose which
 * part of one survives a crop, and it runs out the moment there is no crop left
 * to choose from. A photograph the same shape as the band has no slack in
 * either direction, so the control did nothing at all; a wide one has slack
 * down and none across, so the horizontal half of the picker did nothing. An
 * operator dragging a crosshair that moves nothing has no way of telling a
 * control that is at its limit from one that is broken.
 *
 * A translate always moves the picture, by exactly what the picker says. Past
 * the edge of the file's own slack the ground shows through behind it, which is
 * visible, undoable, and the operator's to decide about — and behind a curve it
 * is usually invisible anyway, because the side the picture has left is the
 * side the colour covers.
 *
 * ---- Why it cannot change the banner's size --------------------------------
 *
 * Two reasons, either of which would do. A transform is paint, not layout: an
 * element that has been translated occupies the same box it did before and
 * nothing around it moves. And the picture is in a wrapper that is
 * `position: absolute; inset: 0; overflow: hidden` — out of the flow, the size
 * of the band whatever it does, and clipping whatever leaves.
 *
 * ---- Which way round --------------------------------------------------------
 *
 * The picker says which part of the picture to look at, so pulling the
 * crosshair left has to move the picture right to bring its left-hand side into
 * view. Hence `0.5 - $value` rather than the other way about: the same sense
 * `object-position` had, so a banner that has been set up already still looks
 * the way it was pointed.
 *
 * @param array $attributes Block attributes.
 * @return string Two CSS percentages, ready for `translate()`.
 */
function bridge_hero_focal_shift(array $attributes): string
{
	$point = isset($attributes['focalPoint']) && is_array($attributes['focalPoint'])
		? $attributes['focalPoint']
		: array();

	$axis = static function (string $key) use ($point): string {
		$value = isset($point[$key]) ? (float) $point[$key] : 0.5;
		$value = max(0.0, min(1.0, $value));

		return bridge_css_number((0.5 - $value) * 2 * BRIDGE_HERO_FOCAL_TRAVEL, 3) . '%';
	};

	// Comma-separated, because this is substituted whole into `translate()` and
	// that function takes two arguments rather than a pair. A custom property
	// carries the comma as happily as it carries the numbers.
	return $axis('x') . ',' . $axis('y');
}

/**
 * How far the curve's circle reaches past the band, as a multiple of its half
 * height.
 *
 * The shape is a circle the band crops, not a curve that fits inside it — which
 * is the whole difference between this and the pair of border radii it replaced.
 * A curve drawn to *fit* arrives at the top edge flattening out, because its
 * tangent there is horizontal; a cropped one is still travelling when the band
 * cuts it, and meets the edge at an angle. The second is what a circle laid over
 * a photograph looks like, and the first is what a bowl looks like.
 *
 * 1.6 is the reference composition measured: an arc whose radius is a little
 * over three-quarters of the band's height. Fixed rather than offered, because
 * it is the one number here with no useful second answer — lower and the shape
 * closes into a bowl, higher and it straightens into a diagonal, and the two
 * settings either side of it already say where the curve is and how deep it
 * cuts.
 *
 * Expressed against the half height so it is a proportion of the band: the
 * shape then reads the same whatever height the banner is set to, and changing
 * that height moves nothing about the drawing.
 */
define('BRIDGE_HERO_CURVE_CROP', 1.6);

/**
 * How far sideways the straight run beyond the arc may travel per unit of
 * height.
 *
 * Only the stacked layout still needs this. Its arc is cut mid-curve to a
 * band's width and continues on the tangent, and a nearly round curve is
 * travelling nearly vertically where that cut falls — a tangent that shallow
 * would leave the band within a unit or two and read as a shelf rather than a
 * continuation. Clamped to 45°, steep enough to still be going the way the
 * curve was going and shallow enough to look like a chamfer instead of a step.
 *
 * The wide layout has no use for it: its arc runs to the ellipse's own ends,
 * where the natural continuation is the straight side of the shape and not a
 * tangent at all.
 */
define('BRIDGE_HERO_CURVE_RUN', 1.0);

/**
 * The curve, as three points: where it starts, where it ends, and the one that
 * bends it.
 *
 * ---- What the curve is -----------------------------------------------------
 *
 * A parabola. Not an arc of a circle, not a border radius, not an ellipse —
 * one quadratic Bézier, which is a parabola exactly, and the only shape here
 * that three numbers describe without anything left over.
 *
 * A circle was the obvious thing and it was wrong for this. A circle has a
 * radius, and a radius has to reach: pin a circle to two points and its size is
 * no longer yours to set, set its size and one of the points has to move. Every
 * setting ended up answering to the geometry instead of to the drawing. A
 * parabola through two ends with a bulge has no such arithmetic in it — any
 * bulge is a curve, 0 included, which is the straight diagonal between the two
 * ends.
 *
 * ---- The three numbers ------------------------------------------------------
 *
 * `vertical` puts the upper end on the right-hand edge, measured down from the
 * top-right corner and normally above it, so the visible curve is the part
 * after it has already turned over. `align` puts the lower end along the
 * bottom edge, measured in from the bottom-right corner. `bulge` is the sag —
 * how far the curve bows away from the straight line between those two.
 *
 * Both ends are outside the picture as often as not, which is the point of
 * letting `vertical` go negative: a hero's curve is a piece of a much bigger
 * sweep, and pinning both of its ends inside the band is what makes a shape
 * look like it was fitted rather than drawn.
 *
 * ---- Why the control point is twice the bulge -------------------------------
 *
 * A quadratic Bézier passes through the point halfway between its control
 * point and the middle of its chord, not through the control point itself. So
 * a control point set two bulges out puts the curve one bulge out, and `bulge`
 * means the distance an operator can actually measure on the screen.
 *
 * @param array $curve Numbers from bridge_hero_curve().
 * @param float $shift Units to move the whole curve right by — the gap, for the
 *                     second one.
 * @return array{sx:float,sy:float,cx:float,cy:float,ex:float,ey:float} Start,
 *               control and end, in hundredths of the banner.
 */
function bridge_hero_curve_points(array $curve, float $shift = 0.0): array
{
	// The upper end, on the right-hand edge; and the lower one, along the
	// bottom. `align` is measured from the right-hand corner, so it subtracts.
	$sx = 100.0 + $shift;
	$sy = $curve['vertical'];
	$ex = 100.0 - $curve['align'] + $shift;
	$ey = 100.0;

	$dx  = $ex - $sx;
	$dy  = $ey - $sy;
	$len = sqrt(($dx ** 2) + ($dy ** 2));

	// Two ends in the same place is not a chord and has no normal. Nothing to
	// bow away from either, so the control point sits on top of them and the
	// curve is a point — which is what the caller draws, harmlessly.
	if ($len <= 0.0) {
		return array('sx' => $sx, 'sy' => $sy, 'cx' => $sx, 'cy' => $sy, 'ex' => $ex, 'ey' => $ey);
	}

	// The chord's left-hand normal, which is the way the curve bows: into the
	// colour, away from the picture.
	$nx = -$dy / $len;
	$ny = $dx / $len;

	if ($nx > 0) {
		$nx = -$nx;
		$ny = -$ny;
	}

	return array(
		'sx' => $sx,
		'sy' => $sy,
		'cx' => (($sx + $ex) / 2) + (2 * $curve['bulge'] * $nx),
		'cy' => (($sy + $ey) / 2) + (2 * $curve['bulge'] * $ny),
		'ex' => $ex,
		'ey' => $ey,
	);
}

/**
 * Everything to the left of the curve, as a closed path.
 *
 * Down the bottom of the banner to the curve's lower end, up the curve, and
 * then out and around the top in a loop that is entirely off the canvas. The
 * loop is overdrawn by a wide margin on purpose: the curve's upper end is
 * usually above the top of the banner, and a path that tried to close neatly at
 * the corner would need to know where the curve crosses the top edge — an extra
 * calculation, an extra thing to get wrong, and nothing to show for it, because
 * an `<svg>` clips to its own viewBox and everything outside is simply not
 * drawn.
 *
 * @param array $curve Numbers from bridge_hero_curve().
 * @param float $shift Units to move the whole curve right by.
 * @return string An SVG path `d` attribute.
 */
function bridge_hero_curve_path(array $curve, float $shift = 0.0): string
{
	$p = bridge_hero_curve_points($curve, $shift);
	$n = static fn(float $value): string => bridge_css_number($value, 3);

	// Far enough out that no setting can pull an edge of the loop into view.
	$out = 400.0;

	return sprintf(
		'M%1$s,100 L%2$s,100 Q%3$s,%4$s %5$s,%6$s L%7$s,%6$s L%7$s,%8$s L%1$s,%8$s Z',
		$n(-$out),
		$n($p['ex']),
		$n($p['cx']),
		$n($p['cy']),
		$n($p['sx']),
		$n($p['sy']),
		$n($p['sx'] + $out),
		$n(-$out)
	);
}

/**
 * How far in from the right edge the words have to stop.
 *
 * The furthest left the curve gets *inside the banner*, which is not the same
 * as the furthest left it gets: the bulge is measured against a chord whose
 * upper end is usually above the top of the band, so the deepest part of the
 * parabola is often off the canvas and reserving room for it would cost the
 * headline a column it could have had.
 *
 * So the curve is walked rather than solved. A hundred steps along it, the ones
 * that fall outside the band thrown away, and the leftmost of what is left —
 * which is exact enough at a hundredth of a banner and needs no case for a
 * curve whose deepest point is off one end or the other.
 *
 * Deliberately not clamped. Settings that leave the words a narrow column will
 * leave them a narrow column: an operator can see that and pull them back,
 * whereas a cap would quietly let the shape cross the type and look like a
 * rendering fault instead of a choice.
 *
 * @param array $curve Numbers from bridge_hero_curve().
 * @return float A percentage of the banner's width, measured from its right
 *               edge.
 */
function bridge_hero_curve_inset(array $curve): float
{
	$p       = bridge_hero_curve_points($curve);
	$deepest = 100.0;

	for ($step = 0; $step <= 100; $step++) {
		$t = $step / 100;
		$u = 1 - $t;

		$y = ($u * $u * $p['sy']) + (2 * $u * $t * $p['cy']) + ($t * $t * $p['ey']);

		if ($y < 0 || $y > 100) {
			continue;
		}

		$x = ($u * $u * $p['sx']) + (2 * $u * $t * $p['cx']) + ($t * $t * $p['ex']);

		$deepest = min($deepest, $x);
	}

	return 100 - $deepest;
}

/**
 * Where the panel's edge crosses the sides of a stacked banner, as a percentage
 * of its height.
 *
 * On a narrow screen the composition turns: the picture takes the top of the
 * band and the words the bottom, with the curve between them. Which means the
 * two settings that place the curve across a wide band — where it crosses, and
 * how far it leans — have nothing to place: there is no left and right to
 * choose between when the panel is the floor of the banner. So they are ignored
 * there and this is the crossing instead, one number, chosen to leave the words
 * a comfortable third of a phone screen.
 *
 * `.bridge-hero-banner.is-bg-curve`'s stacked grid rows in blocks/_hero-banner
 * are this number and its remainder. The two cannot be derived from one another
 * — a stylesheet cannot read a PHP constant and a path cannot be written in
 * `fr` — so they are two, and each says where the other is.
 */
define('BRIDGE_HERO_CURVE_STACK', 62);

/**
 * Where the curve turns, when a banner has not been told otherwise.
 *
 * `nav.$panel + 1px` — the width at which the theme's own menu stops being a
 * bar — because that is the number this switch was hard-wired to before it
 * became a setting, and a banner nobody has opened since should not move.
 *
 * Stated as the bar's width rather than the panel's, and spent as
 * `max-width: (this - 1px)`, for the reason abstracts/_nav.scss gives for
 * keeping `$panel` derived: two numbers that have to stay adjacent should be
 * one number.
 */
define('BRIDGE_HERO_CURVE_BREAKPOINT', 1024);

/**
 * The same curve, turned on its side for a stacked banner.
 *
 * The panel is the floor of the band rather than one side of it, and its top
 * edge is the same arc: an ellipse wider than the banner, cropped by the two
 * side edges, so it is still travelling where it meets them instead of
 * flattening out. Everything the wide version says about that applies here with
 * the axes swapped — including the sag, which is again the setting rather than
 * the radius behind it, so `radius` means the same depth of bulge whichever way
 * the banner is laid out.
 *
 * The wide layout's `align` is not read, and cannot be: it answers "where
 * across the width does the panel start", and across the width is exactly what
 * this arrangement does not have a choice about. Everything else has an
 * equivalent here with the axes swapped — `stack` is the crossing down the
 * height, `offset` slides the fullest part across the band the way `vertical`
 * slides it down one, and `angle` leans the join by moving the two side
 * crossings instead of the top and bottom.
 *
 * @param array $curve  Numbers from bridge_hero_curve_mobile().
 * @param float $narrow How much shallower to draw the bulge, as a percentage
 *                      of it — the gap, for the second curve.
 * @return string An SVG path `d` attribute.
 */
function bridge_hero_curve_path_stacked(array $curve, float $narrow = 0.0): string
{
	// The crossing is the mobile settings' `stack`, and the constant is only
	// the default it was read with — a banner drawn from a `$curve` that has no
	// such key is one of the wide layout's arrays, which is the editor preview
	// and the tests.
	$stack  = (float) ($curve['stack'] ?? BRIDGE_HERO_CURVE_STACK);
	$offset = (float) ($curve['offset'] ?? 0);

	// The second curve is the first with a shallower bulge and the same two
	// side crossings, which is what makes the crescent widest at the fullest
	// part of the curve and closed at either end. The wide layout's `narrow`
	// with the axes swapped.
	$depth = $curve['radius'] * (1 - ($narrow / 100));

	// Half the tilt to each end, so the angle is spent as an equal and opposite
	// shift and the join leans about its middle. The wide version does the same
	// with the axes the other way up.
	$tilt = ((float) ($curve['angle'] ?? 0)) * 0.5;

	$n = static fn(float $value): string => bridge_css_number($value, 3);

	// No bulge is a straight edge — but a leaning one, which is the whole of
	// what the angle has left to say once there is no arc for it to tilt. An
	// arc with a zero radius is not a shape SVG can draw.
	if ($depth <= 0) {
		return sprintf(
			'M0,100 H100 V%s L0,%s Z',
			$n($stack - $tilt),
			$n($stack + $tilt)
		);
	}

	/*
	 * The ellipse, and what stays fixed about it.
	 *
	 * The crop is the answer here rather than the floor: `offset` no longer
	 * widens it. Same reasoning as the wide layout with the axes swapped —
	 * growing the ellipse to keep it reaching the far side meant that sliding
	 * the shape across the band flattened it, and a control that reshapes what
	 * it moves is exactly what this stopped doing. See
	 * the wide layout's own circle.
	 */
	$rx  = 50 * BRIDGE_HERO_CURVE_CROP;
	$sag = 1 - sqrt(1 - ((50 / $rx) ** 2));
	$ry  = $depth / $sag;

	// The ellipse placed by its centre. `offset` slides it across the band and
	// the top of it — `cy - ry` — is the curve at its deepest, which is one
	// bulge above the crossing the stack setting names.
	$cx = 50 + $offset;
	$cy = $stack - $depth + $ry;

	// A band's width of arc and no more, so that moving it moves all of it.
	$left  = $cx - 50;
	$right = $cx + 50;

	$arc = static function (float $x) use ($cx, $cy, $rx, $ry): float {
		$reach = max(-1.0, min(1.0, ($x - $cx) / $rx));

		return $cy - ($ry * sqrt(1 - ($reach ** 2)));
	};

	// The slope the arc is travelling at where it is cut. Both ends fall away
	// from the picture, because both are short of the ellipse's top.
	$reach = 50 / $rx;
	$slope = min(
		BRIDGE_HERO_CURVE_RUN,
		($ry * $reach) / ($rx * sqrt(max(1.0e-9, 1 - ($reach ** 2))))
	);

	$edge = static function (float $x) use ($arc, $left, $right, $slope, $tilt, $cx): float {
		if ($x < $left) {
			$y = $arc($left) + ($slope * ($left - $x));
		} elseif ($x > $right) {
			$y = $arc($right) + ($slope * ($x - $right));
		} else {
			$y = $arc($x);
		}

		return $y - ($tilt * (($x - $cx) / 50));
	};

	$arc_left  = max(0.0, $left);
	$arc_right = min(100.0, $right);

	// Right to left along the top edge, sweeping 0 — which bows the arc up into
	// the picture, the way sweep 0 bows the wide version left into the panel.
	// The straight runs either side of it are the tangent the arc was cut on,
	// and only one of them is ever there: the arc is a whole band wide, so it
	// can hang over one side or the other but never both.
	$path = array('M0,100', 'H100', 'V' . $n($edge(100)));

	if ($right < 100) {
		$path[] = sprintf('L%s,%s', $n($arc_right), $n($edge($arc_right)));
	}

	$path[] = sprintf(
		'A%s,%s 0 0 0 %s,%s',
		$n($rx),
		$n($ry),
		$n($arc_left),
		$n($edge($arc_left))
	);

	if ($left > 0) {
		$path[] = sprintf('L0,%s', $n($edge(0)));
	}

	$path[] = 'Z';

	return implode(' ', $path);
}


/**
 * Does this hero run the full width of the window?
 *
 * Full window by default: this is a hero, and a hero that stops at the text
 * column is a picture. Cleared to '' by the width toggle, which leaves the
 * block an ordinary constrained child and lets the page's own content width
 * size it — no width rule of our own to keep in step with theme.json.
 *
 * @param array $attributes Block attributes.
 */
function bridge_hero_is_full_width(array $attributes): bool
{
	return 'full' === (isset($attributes['align']) ? (string) $attributes['align'] : 'full');
}

/**
 * Tell the browser the first Cover image is the one that matters.
 *
 * It is the LCP element on any page that opens with a hero, and WordPress
 * otherwise defaults it to `loading="lazy"` with `fetchpriority="auto"` —
 * delaying the most visible image on the page.
 *
 * Only those two attributes. Setting `decoding` here never changed a page:
 * wp_filter_content_tags() runs on `the_content` at 12, after do_blocks() at 9,
 * and puts its own back on every render. It leaves `fetchpriority` and
 * `loading` alone when an image already states them, which is why these stand.
 *
 * @param string $content Rendered inner-block HTML.
 * @return string The same HTML, with the first Cover image prioritised.
 */
function bridge_hero_prioritise_cover_image(string $content): string
{
	$processor = new WP_HTML_Tag_Processor($content);

	$found = $processor->next_tag(
		array(
			'tag_name'   => 'img',
			'class_name' => 'wp-block-cover__image-background',
		)
	);

	if (! $found) {
		return $content;
	}

	$processor->set_attribute('fetchpriority', 'high');
	$processor->set_attribute('loading', 'eager');

	return $processor->get_updated_html();
}

/**
 * Drop the bare `align` class core writes for a contained hero.
 *
 * Core writes `align` + whatever the attribute holds, and the contained choice
 * holds an empty string — which arrives as a bare `align` class that names no
 * alignment and matches no rule. Leaving the attribute out instead is not an
 * option: block.json has to keep defaulting it to `full`, or every hero saved
 * before the width control existed would quietly become a narrow one on the
 * next render.
 *
 * @param string $open_tag The wrapper's opening tag.
 * @return string The same tag without the empty alignment class.
 */
function bridge_hero_strip_bare_align(string $open_tag): string
{
	$processor = new WP_HTML_Tag_Processor($open_tag);

	if (! $processor->next_tag()) {
		return $open_tag;
	}

	$processor->remove_class('align');

	return $processor->get_updated_html();
}
