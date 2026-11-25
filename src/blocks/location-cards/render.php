<?php
/**
 * Server render for `bridge/location-cards`.
 *
 * The intro — eyebrow, heading — is the block's inner blocks. The cards are
 * not content at all: they are read from Site Options on every request, so an
 * address or phone number changed there is right here too, with nothing to
 * retype.
 *
 * With `preview` set (the editor's ServerSideRender) only the grid is
 * returned: the editor draws the band and the intro itself.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks (the intro).
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'bridge_location_card_html' ) ) {
	return;
}

$bridge_book_label = trim( (string) ( $attributes['bookLabel'] ?? '' ) );
$bridge_dir_label  = trim( (string) ( $attributes['directionsLabel'] ?? '' ) );
$bridge_card_args  = array(
	'book_label'       => '' !== $bridge_book_label ? $bridge_book_label : __( 'Book appointment', 'bridge' ),
	'book_url'         => trim( (string) ( $attributes['bookUrl'] ?? '' ) ),
	'directions_label' => '' !== $bridge_dir_label ? $bridge_dir_label : __( 'Get directions', 'bridge' ),
	'heading_level'    => (string) ( $attributes['headingLevel'] ?? 'h3' ),
);

$bridge_cards = '';

foreach ( bridge_site_locations() as $bridge_location ) {
	$bridge_cards .= bridge_location_card_html( $bridge_location, $bridge_card_args );
}

$bridge_grid = '' !== $bridge_cards ? '<div class="bridge-location-cards__grid">' . $bridge_cards . '</div>' : '';

if ( ! empty( $attributes['preview'] ) ) {
	echo $bridge_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from escaped parts.
	return;
}

list( $bridge_intro, $bridge_label_id ) = bridge_section_intro( (string) $content, 'bridge-location-cards__intro' );

if ( '' === $bridge_grid && '' === $bridge_intro ) {
	return;
}

$bridge_classes = 'bridge-location-cards bridge-location-cards--' . ( 'narrow' === ( $attributes['width'] ?? 'wide' ) ? 'narrow' : 'wide' );
$bridge_columns = max( 1, min( 3, (int) ( $attributes['columns'] ?? 2 ) ) );

echo bridge_section_wrapper( $attributes, $bridge_classes, '--columns: ' . $bridge_columns . ';', $bridge_label_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
?>
	<div class="bridge-location-cards__inner">
		<?php echo $bridge_intro; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — rendered inner blocks. ?>
		<?php echo $bridge_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from escaped parts. ?>
	</div>
</section>
