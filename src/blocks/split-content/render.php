<?php

/**
 * Server-side render for `bridge/split-content`.
 *
 * The old layout's `make_contact_block` switch turned the left column into a
 * hard-coded contact panel. It is gone: the two columns are inner blocks now,
 * so a contact panel is whatever blocks a contact panel needs — a heading, an
 * address, a form — rather than a shape the theme had to guess in advance.
 * One setting fewer and no template to maintain.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner-block HTML (the two columns).
 * @var WP_Block $block      Parsed block instance.
 */

if (! defined('ABSPATH')) {
	exit;
}

if ('' === trim((string) $content)) {
	return;
}

$width = 'narrow' === ($attributes['width'] ?? 'wide') ? 'narrow' : 'wide';
$ratio = (string) ($attributes['ratio'] ?? 'even');
$ratio = in_array($ratio, array('even', 'wide-left', 'wide-right'), true) ? $ratio : 'even';

/*
 * Only the modifiers that mean something. `--wide` and `--even` are the
 * defaults and the stylesheet has no rule for either, so emitting them put two
 * classes on every one of these sections that nothing could ever match.
 */
$classes = 'bridge-split';

if ('narrow' === $width) {
	$classes .= ' bridge-split--narrow';
}

if ('even' !== $ratio) {
	$classes .= ' bridge-split--' . $ratio;
}

/*
 * The band's accessible name, taken from the first heading in the left column —
 * which is what the column template seeds and what a reader would call this
 * part of the page. Every other section block names its <section> this way; this
 * one did not, so it was producing the unnamed landmarks that
 * bridge_section_wrapper() exists to avoid. A split with no heading in it still
 * gets no name, which is the correct outcome rather than a made-up one.
 */
$label_id = bridge_heading_label_id($content);

echo bridge_section_wrapper($attributes, $classes, '', $label_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
?>
	<div class="bridge-split__inner">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</section>
