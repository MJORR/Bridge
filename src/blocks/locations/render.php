<?php
/**
 * Server-side render for `bridge/locations`.
 *
 * A band of locations: each a place name in the heading face with its details
 * beside it. On a phone each location stacks — the name, then its details
 * close under it — and the locations sit further apart than a name is from its
 * details, so the eye groups them the same way at every width.
 *
 *   source       `site` reads the list from Site Options, so it is written
 *                once for the whole site. `custom` uses the block's own list.
 *   showAddress  The address and postcode, before the phone and email.
 *   background   A palette slug for the band, or none.
 *   borderColor  A palette slug for a hairline along the bottom, or none.
 *   nameSize     `x-large` (an h2's size) or `large` (an h3's).
 *
 * The markup is shared with the Hybrid footer — see inc/locations.php.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Unused.
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'bridge_locations_list' ) ) {
	return;
}

$bridge_locations = 'custom' === ( $attributes['source'] ?? 'site' )
	? bridge_sanitize_locations( $attributes['locations'] ?? array() )
	: bridge_site_locations();

$bridge_list = bridge_locations_list( $bridge_locations, ! empty( $attributes['showAddress'] ) );

if ( '' === $bridge_list ) {
	return;
}

list( $bridge_classes, $bridge_style ) = bridge_locations_band( $attributes );

echo bridge_section_wrapper( $attributes, $bridge_classes, $bridge_style ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
echo $bridge_list; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from escaped parts.
echo '</section>';
