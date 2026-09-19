<?php

/**
 * Server-side render for `bridge/hero-banner`.
 *
 * A band sized to the window, carrying a background and one column of content.
 * The background is the block's own setting rather than a Cover an editor has
 * to configure — see bridge_hero_banner_ground(), which resolves the five
 * choices into a ground, a legible foreground and an overlay.
 *
 * Three CSS variables place the band in the page: `--bridge-hero-banner-height`
 * is the floor it is measured against, `--bridge-hero-banner-inset` takes the
 * header out of that floor, and `--bridge-hero-banner-lead` leads the content
 * past a header that overlays the banner rather than sitting above it. The
 * arithmetic behind all three, the width rule and the LCP hint are shared with
 * `bridge/hero-slider` and live in inc/hero-blocks.php.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner-block HTML (the banner's content).
 * @var WP_Block $block      Parsed block instance.
 */

if (! defined('ABSPATH')) {
	exit;
}

$metrics = bridge_hero_metrics($attributes);
$ground  = bridge_hero_banner_ground($attributes);

$bridge_image_id  = isset($attributes['imageId']) ? (int) $attributes['imageId'] : 0;
$bridge_image_url = isset($attributes['imageUrl']) ? (string) $attributes['imageUrl'] : '';
$bridge_image_alt = isset($attributes['imageAlt']) ? (string) $attributes['imageAlt'] : '';

// Only three of the five backgrounds carry a picture, and none of them draws an
// empty frame: a curve with no image chosen yet is a colour panel, which is a
// banner an editor can publish rather than a hole in the page.
$bridge_has_media = in_array($ground['kind'], array('image', 'curve', 'custom-gradient'), true)
	&& ($bridge_image_id > 0 || '' !== $bridge_image_url);

/**
 * A fixed background is a painted box, not an <img>.
 *
 * `background-attachment: fixed` is a property of a background image, and there
 * is no equivalent for an element — so this is the one case where the picture
 * gives up its `srcset` and its LCP hint to hold still while the page moves.
 * The same trade core/cover makes for its own parallax option, for the same
 * reason, and the toggle is offered on the plain image background only: behind
 * a curve the picture is framed by a shape drawn against the band, and pinning
 * it to the window would slide it out from under that shape.
 */
$bridge_fixed = 'image' === $ground['kind'] && ! empty($attributes['fixed']) && $bridge_has_media;

/*
 * A curve with no picture behind it yet is a colour panel, and it is drawn as
 * one: the curve reserves the right of the band for the image and ranges the
 * words left against it, which with nothing in that half is a banner with its
 * content pushed into a corner. Falling back to the solid arrangement means an
 * editor who picks Custom curve before choosing a picture sees a finished
 * banner rather than a broken one.
 *
 * The custom gradient makes no such fallback, and wants none: its bands are
 * laid over whatever is behind them, and with no picture chosen that is the
 * band's own colour — which is a finished composition rather than a broken one.
 * A gradient fading a brand colour out to nothing across the left-hand half is
 * the same banner with or without a photograph under it.
 */
$bridge_kind = 'curve' === $ground['kind'] && ! $bridge_has_media
	? 'solid'
	: $ground['kind'];

/*
 * Always the full width of the window, and not a setting.
 *
 * The block had a width toggle while it was a Cover an editor arranged; as a
 * hero it has one answer. A banner held inside the text column is not a banner
 * — it is a picture with a headline on it, which is what a Cover already is and
 * what this block stopped being. The class is written here rather than left to
 * core's alignment support, which the block no longer declares: there is
 * nothing for a toolbar control to choose between.
 */
$bridge_classes = array('bridge-hero-banner', 'is-bg-' . $bridge_kind, 'alignfull');

if ($ground['inverted']) {
	// What the button component reads to decide which ghost pair it may use,
	// and the one thing about this band that the stylesheet cannot work out for
	// itself — CSS cannot ask whether a colour is light.
	$bridge_classes[] = 'is-inverted';
}

if ($bridge_fixed) {
	$bridge_classes[] = 'has-fixed-media';
}

/*
 * The words in the left-hand half of the band.
 *
 * A class rather than a length, because what is being asked for is one of the
 * two arrangements this banner has and not a number to tune: the band is the
 * page's own two-column grid, and this puts the content in the first column
 * with the gutter between. The stylesheet does the arithmetic — see
 * `.is-title-left` in blocks/_hero-banner — because every term in it is a
 * property the band already publishes.
 *
 * It is offered whatever the background is. The curve already ranges its words
 * left against the picture, so there this is the same answer said twice and
 * costs nothing; everywhere else it is the difference between a centred hero
 * and a two-column one.
 */
if (! empty($attributes['titleLeft'])) {
	$bridge_classes[] = 'is-title-left';
}

$bridge_style = sprintf(
	'--bridge-hero-banner-height:%s;--bridge-hero-banner-inset:%s;--bridge-hero-banner-lead:%s;--bridge-hero-banner-ground:%s;--bridge-hero-banner-fg:%s;--bridge-hero-banner-overlay:%s;',
	$metrics['height'],
	$metrics['inset'],
	$metrics['lead'],
	$ground['ground'],
	$ground['fg'],
	bridge_css_number($ground['overlay'], 2)
);

/*
 * Where the picture sits.
 *
 * Three properties for the one setting, because the two ways this theme paints
 * a photograph cannot be moved the same way. An `<img>` is translated — it
 * actually moves — and grown by however much that move would otherwise have
 * uncovered, which is the third. A fixed background cannot be either: a
 * transform on the wrapper would break `background-attachment: fixed`, so there
 * the setting stays what it always was, a choice of which part of the file
 * survives the crop, which never uncovers anything and so needs no scale. See
 * bridge_hero_focal_shift(), bridge_hero_focal_zoom() and
 * bridge_hero_focal_point().
 */
if ($bridge_has_media) {
	$bridge_style .= sprintf(
		'--bridge-hero-banner-focal:%s;--bridge-hero-banner-shift:%s;--bridge-hero-banner-zoom:%s;',
		bridge_hero_focal_point($attributes),
		bridge_hero_focal_shift($attributes),
		bridge_hero_focal_zoom($attributes)
	);
}

/**
 * The curve, and the room it takes from the words.
 *
 * What the stylesheet cannot work out for itself: how far in from the right the
 * words have to stop — the curve's deepest reach, which three of the settings
 * between them decide, see bridge_hero_curve_inset() — and the clear air they
 * keep off it. Published rather than repeated, so a settings change moves the
 * words and the drawing together.
 *
 * And the mobile half of it, which is a second set of numbers and a width to
 * swap them at. The drawing is baked into the stacked paths below; the two
 * things a path cannot carry — where the words sit and how strong the crescent
 * is — go into a stylesheet of this banner's own, because the width they change
 * at is a setting and a media query cannot read one.
 */
$bridge_curve        = array();
$bridge_curve_mobile = array();
$bridge_curve_css    = '';
$bridge_curve_class  = '';

if ('curve' === $bridge_kind) {
	$bridge_curve        = bridge_hero_curve($attributes);
	$bridge_curve_mobile = bridge_hero_curve_mobile($attributes);

	// The crescent's opacity is not here with the other two. It has a second
	// value below the breakpoint, and an inline declaration beats every
	// stylesheet rule that is not `!important` — written here it would be the
	// one the phone got as well. Both figures are in the instance's own
	// stylesheet instead; see bridge_hero_curve_css().
	$bridge_style .= sprintf(
		'--bridge-hero-banner-curve-inset:%s%%;--bridge-hero-banner-curve-pad:%s%%;',
		bridge_css_number(bridge_hero_curve_inset($bridge_curve), 3),
		bridge_css_number($bridge_curve['padding'], 3)
	);

	// This banner's own class, and the stylesheet that is scoped to it. The
	// breakpoint is a per-block number and a media query cannot read a custom
	// property, so the query has to be written once per banner — which is what
	// this class exists to address.
	$bridge_curve_class = wp_unique_id('bridge-hero-banner-curve-');
	$bridge_classes[]   = $bridge_curve_class;

	$bridge_curve_css = bridge_hero_curve_css(
		$bridge_curve_class,
		$bridge_curve_mobile,
		$bridge_curve['opacity'],
		bridge_hero_curve_breakpoint($attributes)
	);
}

/*
 * Custom gradient: the whole thing, as one custom property.
 *
 * A built `linear-gradient()` rather than a set of numbers the stylesheet
 * assembles, because the number of stops is itself a setting — CSS can hold a
 * value it does not understand, but it cannot loop. The whole function is built
 * once here and painted once there; see bridge_hero_gradient_css(), which
 * explains why the bands come out as the stop list they do.
 *
 * In a stylesheet of this banner's own rather than on the wrapper, which is
 * where every other per-instance value here goes. Not a preference: WordPress
 * runs inline styles through `safecss_filter_attr()`, which allows a gradient
 * in a custom property only as far as one level of nested functions — and a
 * palette colour at less than full strength is a `color-mix()` with a `var()`
 * inside it, which is two. Written on the wrapper the gradient is thrown away
 * silently on the page while working perfectly in the editor, whose canvas is
 * not filtered. See bridge_hero_gradient_style().
 *
 * No second answer at another width: the bands are percentages of the gradient
 * line and the angle is an angle, so the picture holds its proportions on a
 * phone the way the curve's paths do. The breakpoint the curve needs a
 * per-instance sheet for does not arise here — this one exists only to get past
 * the style attribute.
 */
$bridge_gradient_css   = '';
$bridge_gradient_class = '';

if ('custom-gradient' === $bridge_kind) {
	$bridge_gradient = bridge_hero_gradient_css(bridge_hero_gradient($attributes));

	if ('' !== $bridge_gradient) {
		$bridge_gradient_class = wp_unique_id('bridge-hero-banner-gradient-');
		$bridge_classes[]      = $bridge_gradient_class;
		$bridge_gradient_css   = bridge_hero_gradient_style(
			$bridge_gradient_class,
			$bridge_gradient
		);
	}
}

// Nothing escaped on the way in: get_block_wrapper_attributes() runs esc_attr()
// over every value it is handed.
$open_tag = '<div ' . get_block_wrapper_attributes(
	array(
		'class' => implode(' ', $bridge_classes),
		'style' => $bridge_style,
	)
) . '>';

/**
 * The picture.
 *
 * `wp_get_attachment_image()` rather than the stored URL, so the browser gets a
 * `srcset` and picks a file for the window it is in — a hero is the largest
 * image on the page and the one most worth not sending at 4000px to a phone.
 * The stored URL is the fallback for an image whose attachment has since been
 * deleted, which would otherwise take the banner's background with it.
 *
 * `fetchpriority="high"` and `loading="eager"` for the same reason the slider's
 * first slide carries them: this is the LCP element on any page that opens with
 * a hero, and WordPress's own default would lazy-load it.
 */
$bridge_media = '';

if ($bridge_has_media && ! $bridge_fixed) {
	$bridge_attr = array(
		'class'         => 'bridge-hero-banner__image',
		'alt'           => $bridge_image_alt,
		'fetchpriority' => 'high',
		'loading'       => 'eager',
		'decoding'      => 'async',
	);

	$bridge_media = $bridge_image_id > 0
		? wp_get_attachment_image($bridge_image_id, 'full', false, $bridge_attr)
		: sprintf(
			'<img class="bridge-hero-banner__image" src="%s" alt="%s" fetchpriority="high" loading="eager" decoding="async">',
			esc_url($bridge_image_url),
			esc_attr($bridge_image_alt)
		);
}

?>
<?php echo $open_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — get_block_wrapper_attributes() is pre-escaped. ?>
	<?php if ('' !== $bridge_curve_css) : ?>
		<?php
		/*
		 * This banner's own switch, in the page.
		 *
		 * A `<style>` in the body rather than an enqueued sheet, because what
		 * it carries is a media query with a per-block number written into it
		 * — see bridge_hero_curve_css(). Nothing in it is interpolated from
		 * anything an author typed: the selector is generated here and every
		 * value is a clamped float, so there is no string that could close the
		 * element early.
		 *
		 * Inside the banner rather than in front of it, which is where it was
		 * and where it cost every curve banner a gap above it. The block is
		 * usually the first thing in `.wp-block-post-content`, and core's
		 * layout rules give that container's first child `margin-block-start:
		 * 0` and every other child a spacing preset — so an element in front of
		 * the banner takes the zero and hands the banner the margin. A rule
		 * about the *first* child is one an invisible element can break by
		 * being it.
		 *
		 * It costs nothing here: `<style>` is `display: none`, so it is not a
		 * grid item and the banner's `> * { grid-area: 1 / 1 }` never sees it.
		 */
		?>
		<style><?php echo $bridge_curve_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — generated selector and clamped numbers only; see bridge_hero_curve_css(). ?></style>
	<?php endif; ?>
	<?php if ('' !== $bridge_gradient_css) : ?>
		<?php
		/*
		 * And this banner's gradient, for the same kind of reason and a
		 * different one: a `<style>` because the value cannot go in a style
		 * attribute at all — `safecss_filter_attr()` drops it — and inside the
		 * banner because an invisible element in front of the block takes the
		 * `margin-block-start: 0` core gives a container's first child and
		 * hands the banner a spacing preset instead. The curve's note above has
		 * the long version of that second half.
		 *
		 * Nothing in it is interpolated from anything an author typed: the
		 * selector is generated here and every value is a clamped float or a
		 * sanitised slug. See bridge_hero_gradient_style().
		 */
		?>
		<style><?php echo $bridge_gradient_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — generated selector, clamped numbers and sanitised slugs only; see bridge_hero_gradient_style(). ?></style>
	<?php endif; ?>
	<?php if ($bridge_has_media) : ?>
		<div
			class="bridge-hero-banner__media"
			<?php if ($bridge_fixed) : ?>
				style="background-image:url(<?php echo esc_url($bridge_image_id > 0 ? (string) wp_get_attachment_image_url($bridge_image_id, 'full') : $bridge_image_url); ?>)"
			<?php endif; ?>
		>
			<?php echo $bridge_media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built by wp_get_attachment_image() or escaped above. ?>
		</div>
	<?php endif; ?>

	<?php
	/**
	 * The curve, painted over the picture.
	 *
	 * Two paths and a fill rule would be one way to say this; a colour filled to
	 * the left of a curve is simpler. The second curve is painted first and the
	 * colour over it, so what survives is the band between the two edges — see
	 * bridge_hero_curve_path(), which draws both from the same three points.
	 *
	 * The picture underneath stays a plain full-bleed `<img>` with a `srcset`.
	 * Nothing is clipped to it and nothing is masked by it, so there is nothing
	 * for the two to get out of step about.
	 *
	 * `preserveAspectRatio="none"` stretches the 100 × 100 viewBox over the
	 * band, which is what makes every number in the path a percentage of the
	 * banner: the drawing resizes with the window and is never measured or
	 * redrawn. It also means the curve follows the banner's proportions rather
	 * than holding its own — a taller banner gets a longer, lazier version of
	 * the same sweep, which is what a shape pinned to the banner's own corners
	 * has to do.
	 *
	 * Both arrangements are drawn and the stylesheet shows one — the curve
	 * across a wide band, and the same curve turned on its side where the
	 * picture and the words are stacked. Two paths rather than one path and a
	 * transform, because a rotation inside a box that is being stretched to the
	 * band is a shape nobody can predict; and in the same <svg> rather than two,
	 * because they are one drawing in two positions. The pair that is not in use
	 * is `display: none`, which an SVG path takes like any other element.
	 */
	?>
	<?php if ('curve' === $bridge_kind) : ?>
		<div class="bridge-hero-banner__curve" aria-hidden="true">
			<svg class="bridge-hero-banner__curve-box" viewBox="0 0 100 100" preserveAspectRatio="none" focusable="false">
				<path class="bridge-hero-banner__curve-veil is-stacked" d="<?php echo esc_attr(bridge_hero_curve_path_stacked($bridge_curve_mobile, $bridge_curve_mobile['gap'])); ?>" />
				<path class="bridge-hero-banner__curve-panel is-stacked" d="<?php echo esc_attr(bridge_hero_curve_path_stacked($bridge_curve_mobile)); ?>" />
				<path class="bridge-hero-banner__curve-veil is-wide" d="<?php echo esc_attr(bridge_hero_curve_path($bridge_curve, $bridge_curve['gap'])); ?>" />
				<path class="bridge-hero-banner__curve-panel is-wide" d="<?php echo esc_attr(bridge_hero_curve_path($bridge_curve)); ?>" />
			</svg>
		</div>
	<?php endif; ?>

	<?php
	/**
	 * Custom gradient: one painted box over the picture and under the words.
	 *
	 * Its own element rather than a `background-image` on the wrapper, for two
	 * reasons. The wrapper already carries the ground colour as its
	 * `background-color`, and a gradient with transparent stops has to be laid
	 * *over* the photograph rather than behind it — and the photograph is an
	 * `<img>` in the band, not a background of it. And it is a grid item like
	 * everything else here, so `z-index` and DOM order place it between the
	 * media and the content without anything being positioned absolutely
	 * against a band whose height its own content decides.
	 *
	 * It carries no meaning and takes no pointer, so it is hidden from
	 * assistive technology.
	 */
	?>
	<?php if ('' !== $bridge_gradient_css) : ?>
		<div class="bridge-hero-banner__gradient" aria-hidden="true"></div>
	<?php endif; ?>

	<div class="bridge-hero-banner__content">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</div>
