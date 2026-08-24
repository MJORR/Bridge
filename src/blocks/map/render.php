<?php
/**
 * Server-side render for `bridge/map`.
 *
 * The old block loaded the Google Maps JavaScript API, which meant a billable
 * API key in Theme Options before an editor could place a pin, ~200KB of
 * third-party script, and a map that rendered as an empty grey box the day the
 * key's quota or referrer restrictions changed.
 *
 * This renders the same map as an iframe against Google's keyless embed
 * endpoint. No key to obtain or leak, no script to load, and `loading="lazy"`
 * means a map below the fold costs nothing until it is scrolled to. The
 * trade-off is that a keyless embed cannot take a custom marker or map style —
 * neither of which the old block offered either.
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

$address = trim( (string) ( $attributes['address'] ?? '' ) );

// Nothing to point at. Rendering an empty frame would put a grey box on the
// page that no visitor can act on and no editor can see the cause of.
if ( '' === $address ) {
	return;
}

$zoom   = max( 1, min( 21, (int) ( $attributes['zoom'] ?? 14 ) ) );
$height = max( 160, min( 1200, (int) ( $attributes['height'] ?? 420 ) ) );
$label  = trim( (string) ( $attributes['label'] ?? '' ) );
$width  = 'narrow' === ( $attributes['width'] ?? 'wide' ) ? 'narrow' : 'wide';

// The accessible name for the frame. An iframe with no title is announced as
// "frame" and nothing else, so a screen-reader user cannot tell one map from
// another — or from any other embed on the page.
$title = '' !== $label
	/* translators: %s: place name given by the editor. */
	? sprintf( __( 'Map showing %s', 'bridge' ), $label )
	/* translators: %s: address or coordinates. */
	: sprintf( __( 'Map showing %s', 'bridge' ), $address );

$src = add_query_arg(
	array(
		'q'      => rawurlencode( $address ),
		'z'      => $zoom,
		'output' => 'embed',
	),
	'https://maps.google.com/maps'
);

echo bridge_section_wrapper( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
	$attributes,
	'bridge-map bridge-map--' . $width,
	sprintf( '--bridge-map-height: %dpx;', $height )
);
?>
	<div class="bridge-map__inner">
		<iframe
			class="bridge-map__frame"
			src="<?php echo esc_url( $src ); ?>"
			title="<?php echo esc_attr( $title ); ?>"
			loading="lazy"
			referrerpolicy="no-referrer-when-downgrade"
			allowfullscreen></iframe>
		<?php if ( '' !== $label ) : ?>
			<p class="bridge-map__label"><?php echo esc_html( $label ); ?></p>
		<?php endif; ?>
	</div>
</section>
