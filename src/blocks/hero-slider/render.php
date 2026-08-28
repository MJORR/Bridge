<?php

/**
 * Server-side render for `bridge/hero-slider`.
 *
 * Prints the slides as ordinary markup and the chrome around them, and
 * serialises the slider's settings as `data-*` attributes for slider.js — plus
 * three CSS variables: `--bridge-slider-height` drives the container height,
 * `--bridge-slider-inset` takes the header out of it, and `--bridge-slider-lead`
 * leads the slide content past a header that overlays rather than precedes it.
 *
 * The transition effect travels as a class rather than a data attribute,
 * because it is the stylesheet that performs it — the script only ever says
 * which slide is showing.
 *
 * The chevrons ship `hidden` and the dot strip ships empty, in the manner of
 * the carousel controls: the first slide is readable before any script runs,
 * and nothing is on screen that does not yet work.
 *
 * The arithmetic behind the three lengths, the width rule and the LCP hint are
 * shared with `bridge/hero-banner` and live in inc/hero-blocks.php.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner-block HTML (the cover slides).
 * @var WP_Block $block      Parsed block instance.
 */

if (! defined('ABSPATH')) {
	exit;
}

if ('' === trim((string) $content)) {
	return;
}

$effect          = isset($attributes['effect']) ? (string) $attributes['effect'] : 'fade';
$effect          = in_array($effect, array('fade', 'slide'), true) ? $effect : 'fade';
$autoplay        = ! empty($attributes['autoplay']);
$autoplay_delay  = isset($attributes['autoplayDelay']) ? (int) $attributes['autoplayDelay'] : 6;
$loop            = ! empty($attributes['loop']);
$show_pagination = ! empty($attributes['showPagination']);
$show_navigation = ! empty($attributes['showNavigation']);

$metrics       = bridge_hero_metrics($attributes);
$is_full_width = bridge_hero_is_full_width($attributes);
$content       = bridge_hero_prioritise_cover_image($content);

/*
 * Every string the script needs, translated here. slider.js builds one dot per
 * slide and names each slide as it wires them up, and it carries no
 * translations of its own — the two patterns below travel to it as data
 * attributes, so this file stays the one place the slider's words are written
 * and translating the theme translates the slider with it.
 */
$label_previous = __('Previous slide', 'bridge');
$label_next     = __('Next slide', 'bridge');
/* translators: %d: slide number. */
$label_bullet   = __('Go to slide %d', 'bridge');
/* translators: 1: slide number, 2: total number of slides. */
$label_slide    = __('Slide %1$d of %2$d', 'bridge');

// Nothing escaped on the way in: get_block_wrapper_attributes() runs esc_attr()
// over every value it is handed.
$open_tag = '<div ' . get_block_wrapper_attributes(
	array(
		'class'                => sprintf(
			'bridge-hero-slider is-effect-%s%s',
			$effect,
			$is_full_width ? ' alignfull' : ''
		),
		'style'                => sprintf(
			'--bridge-slider-height: %s; --bridge-slider-inset: %s; --bridge-slider-lead: %s;',
			$metrics['height'],
			$metrics['inset'],
			$metrics['lead']
		),
		// A named landmark rather than an anonymous div: a screen reader
		// announces "hero slider, carousel" and can jump past the whole thing,
		// which matters most for the block that opens the page.
		'role'                 => 'region',
		'aria-roledescription' => _x('carousel', 'accessible role description for the hero slider', 'bridge'),
		'aria-label'           => __('Hero slider', 'bridge'),
		// The hook the script finds its work through, and the settings it
		// reads once it has.
		'data-bridge-slider'   => '',
		'data-autoplay'        => $autoplay ? '1' : '0',
		'data-autoplay-delay'  => (string) ($autoplay_delay * 1000),
		'data-loop'            => $loop ? '1' : '0',
		'data-label-bullet'    => $label_bullet,
		'data-label-slide'     => $label_slide,
	)
) . '>';

if (! $is_full_width) {
	$open_tag = bridge_hero_strip_bare_align($open_tag);
}

/**
 * One of the two chevrons.
 *
 * Drawn from the PHP icon library, so it wears the stroke weight set in Theme
 * Options like every other icon in the theme, rather than a glyph from a
 * font a slider library brought with it.
 *
 * @param string $direction prev or next.
 * @param string $icon      Icon name.
 * @param string $label     Accessible name.
 * @return string
 */
$arrow = static function (string $direction, string $icon, string $label): string {
	return sprintf(
		'<button type="button" class="bridge-hero-slider__arrow bridge-hero-slider__arrow--%1$s" data-bridge-slider-%1$s hidden>%2$s</button>',
		esc_attr($direction),
		bridge_render_icon($icon, array('label' => $label))
	);
};
?>
<?php echo $open_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — get_block_wrapper_attributes() is pre-escaped. ?>
	<div class="bridge-hero-slider__track" data-bridge-slider-track>
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<?php if ($show_pagination) : ?>
		<?php
		/*
		 * Not `aria-hidden`. The script fills this with a labelled button per
		 * slide, and hiding the container would hide focusable controls from
		 * the very users the labels are for — the one thing aria-hidden must
		 * never do.
		 */
		?>
		<div class="bridge-hero-slider__dots" role="group" aria-label="<?php esc_attr_e('Slides', 'bridge'); ?>" data-bridge-slider-dots></div>
	<?php endif; ?>
	<?php if ($show_navigation) : ?>
		<?php
		echo $arrow('prev', 'chevron-left', $label_previous),  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — assembled from escaped parts.
			$arrow('next', 'chevron-right', $label_next);      // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	<?php endif; ?>
</div>
