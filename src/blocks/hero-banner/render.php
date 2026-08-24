<?php

/**
 * Server-side render for `bridge/hero-banner`.
 *
 * A single Cover, sized to the window and wrapped so the page's header is
 * accounted for. Publishes three CSS variables the stylesheet consumes:
 * `--bridge-hero-banner-height` is the floor the banner is measured against,
 * `--bridge-hero-banner-inset` takes the header out of it, and
 * `--bridge-hero-banner-lead` leads the content past a header that overlays
 * the banner rather than sitting above it.
 *
 * The arithmetic behind all three, the width rule and the LCP hint are shared
 * with `bridge/hero-slider` and live in inc/hero-blocks.php — they were written
 * out in full in both files, which is two places for the static hero and the
 * sliding one to start disagreeing about where the middle of the window is.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner-block HTML (the Cover).
 * @var WP_Block $block      Parsed block instance.
 */

if (! defined('ABSPATH')) {
	exit;
}

if ('' === trim((string) $content)) {
	return;
}

$metrics       = bridge_hero_metrics($attributes);
$is_full_width = bridge_hero_is_full_width($attributes);
$content       = bridge_hero_prioritise_cover_image($content);

// Nothing escaped on the way in: get_block_wrapper_attributes() runs esc_attr()
// over every value it is handed.
$open_tag = '<div ' . get_block_wrapper_attributes(
	array(
		'class' => 'bridge-hero-banner' . ($is_full_width ? ' alignfull' : ''),
		'style' => sprintf(
			'--bridge-hero-banner-height: %s; --bridge-hero-banner-inset: %s; --bridge-hero-banner-lead: %s;',
			$metrics['height'],
			$metrics['inset'],
			$metrics['lead']
		),
	)
) . '>';

if (! $is_full_width) {
	$open_tag = bridge_hero_strip_bare_align($open_tag);
}
?>
<?php echo $open_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — get_block_wrapper_attributes() is pre-escaped. ?>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
