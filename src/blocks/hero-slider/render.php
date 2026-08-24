<?php

/**
 * Server-side render for `bridge/hero-slider`.
 *
 * Serialises the slider's settings as `data-*` attributes, read by slider.js to
 * configure Swiper, plus three CSS variables: `--bridge-slider-height` drives
 * the container height, `--bridge-slider-inset` takes the header out of it, and
 * `--bridge-slider-lead` leads the slide content past a header that overlays
 * rather than precedes it.
 *
 * The arithmetic behind all three, the width rule and the LCP hint are shared
 * with `bridge/hero-banner` and live in inc/hero-blocks.php.
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
$autoplay        = ! empty($attributes['autoplay']);
$autoplay_delay  = isset($attributes['autoplayDelay']) ? (int) $attributes['autoplayDelay'] : 6;
$loop            = ! empty($attributes['loop']);
$show_pagination = ! empty($attributes['showPagination']);
$show_navigation = ! empty($attributes['showNavigation']);

$metrics       = bridge_hero_metrics($attributes);
$is_full_width = bridge_hero_is_full_width($attributes);
$content       = bridge_hero_prioritise_cover_image($content);

/*
 * Swiper's own a11y module writes the labels for the controls it manages, and
 * it would otherwise write English ones over the translated labels below. The
 * strings travel to it as data attributes so this file stays the one place they
 * are written, and translating the theme translates the slider with it.
 */
$label_previous = __('Previous slide', 'bridge');
$label_next     = __('Next slide', 'bridge');
/* translators: %s: slide number. Swiper substitutes {{index}} itself. */
$label_bullet   = __('Go to slide {{index}}', 'bridge');

// Nothing escaped on the way in: get_block_wrapper_attributes() runs esc_attr()
// over every value it is handed.
$open_tag = '<div ' . get_block_wrapper_attributes(
	array(
		'class'               => 'bridge-hero-slider swiper' . ($is_full_width ? ' alignfull' : ''),
		'style'               => sprintf(
			'--bridge-slider-height: %s; --bridge-slider-inset: %s; --bridge-slider-lead: %s;',
			$metrics['height'],
			$metrics['inset'],
			$metrics['lead']
		),
		'data-effect'         => $effect,
		'data-autoplay'       => $autoplay ? '1' : '0',
		'data-autoplay-delay' => (string) ($autoplay_delay * 1000),
		'data-loop'           => $loop ? '1' : '0',
		'data-pagination'     => $show_pagination ? '1' : '0',
		'data-navigation'     => $show_navigation ? '1' : '0',
		'data-label-previous' => $label_previous,
		'data-label-next'     => $label_next,
		'data-label-bullet'   => $label_bullet,
	)
) . '>';

if (! $is_full_width) {
	$open_tag = bridge_hero_strip_bare_align($open_tag);
}
?>
<?php echo $open_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — get_block_wrapper_attributes() is pre-escaped. ?>
	<div class="swiper-wrapper">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<?php if ($show_pagination) : ?>
		<?php
		/*
		 * Not `aria-hidden`. Swiper renders these bullets clickable and its
		 * a11y module gives each one a role, a label and a tabindex — so
		 * hiding the container hid focusable controls from the very users the
		 * labels were for, which is the one thing aria-hidden must never do.
		 */
		?>
		<div class="swiper-pagination"></div>
	<?php endif; ?>
	<?php if ($show_navigation) : ?>
		<button class="swiper-button-prev" type="button" aria-label="<?php echo esc_attr($label_previous); ?>"></button>
		<button class="swiper-button-next" type="button" aria-label="<?php echo esc_attr($label_next); ?>"></button>
	<?php endif; ?>
</div>
