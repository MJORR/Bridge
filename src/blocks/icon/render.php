<?php
/**
 * Server-side render for `bridge/icon`.
 *
 * Rendering on the server rather than saving markup means the stroke weight
 * follows the design system: changing the icon weight in Theme Options
 * restyles every icon already placed on every page, with no block
 * revalidation and no content migration.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block HTML (unused).
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$name  = isset( $attributes['name'] ) ? (string) $attributes['name'] : '';
$size  = isset( $attributes['size'] ) ? (string) $attributes['size'] : 'medium';
$label = isset( $attributes['label'] ) ? (string) $attributes['label'] : '';

$svg = bridge_render_icon(
	$name,
	array(
		'size'  => $size,
		'label' => $label,
	)
);

// An unknown icon renders nothing rather than a broken placeholder — the name
// only becomes invalid if an icon is removed from the library in a later
// build, and a silent gap beats a visible error on a client's live page.
if ( '' === $svg ) {
	return;
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'bridge-icon-block' ) );

printf(
	'<span %1$s>%2$s</span>',
	$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — get_block_wrapper_attributes() is pre-escaped.
	$svg // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — assembled and escaped in bridge_render_icon().
);
