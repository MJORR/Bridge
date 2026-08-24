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
