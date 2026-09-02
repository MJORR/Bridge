<?php
/**
 * Server-side render for `bridge/map`.
 *
 * The old block loaded the Google Maps JavaScript API, which meant a billable
 * API key in Theme Options before an editor could place a pin, ~200KB of
 * third-party script, and a map that rendered as an empty grey box the day the
 * key's quota or referrer restrictions changed.
 *
 * A container carrying the key and the point, and map-view.js turning it into
 * a Maps JavaScript API map once it is scrolled to. Not an embedded frame: a
 * frame cannot take a custom marker or a map style, and both are the reason for
 * having a key at all.
 *
 * The script is deferred and the map is not built until it is in view, so a map
 * at the foot of a contact page costs a visitor nothing until they reach it.
 * The rest of the cost is unavoidable — the API is ~200KB and a billable map
 * load per view, which is the price of a map you can style.
 *
 * With no key there is no map to build, so the block renders the address as a
 * link to Google Maps rather than an empty box.
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
/*
 * Three widths, and the third is a different kind of thing: `full` takes the
 * map to the edges of the window, so the band also has to stop spending a
 * gutter on it — `bridge-band--flush` is what says so. See _sections.scss.
 */
$width = (string) ( $attributes['width'] ?? 'wide' );
$width = in_array( $width, array( 'narrow', 'wide', 'full' ), true ) ? $width : 'wide';

// The map's accessible name. Without one it is announced as an image and
// nothing else, so a screen-reader user cannot tell one map from another.
$title = '' !== $label
	/* translators: %s: place name given by the editor. */
	? sprintf( __( 'Map showing %s', 'bridge' ), $label )
	/* translators: %s: address or coordinates. */
	: sprintf( __( 'Map showing %s', 'bridge' ), $address );

/*
 * What the pin is placed on, in the order of how exact it is.
 *
 * Coordinates name one point and are what a map is centred on. An address is a
 * search, so it is passed on only when there is nothing better and map-view.js
 * geocodes it once — the fallback for a block written before the finder set
 * coordinates.
 */
$key    = function_exists( 'bridge_map_key' ) ? bridge_map_key() : '';
$map_id = function_exists( 'bridge_map_id' ) ? bridge_map_id() : '';
$lat = isset( $attributes['lat'] ) ? (float) $attributes['lat'] : null;
$lng = isset( $attributes['lng'] ) ? (float) $attributes['lng'] : null;

echo bridge_section_wrapper( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
	$attributes,
	'bridge-map bridge-map--' . $width . ( 'full' === $width ? ' bridge-band--flush' : '' ),
	sprintf( '--bridge-map-height: %dpx;', $height )
);
?>
	<div class="bridge-map__inner">
		<?php if ( '' !== $key ) : ?>
			<?php
			/*
			 * map-view.js reads everything off these attributes — no inline
			 * script and no global, so two maps on one page are two containers
			 * and one script.
			 *
			 * The key is in the markup because the Maps JavaScript API runs in
			 * the browser and there is nowhere else to put it. The control that
			 * matters is the HTTP referrer restriction on the key, which Site
			 * Options says beside the field.
			 */
			?>
			<div
				class="bridge-map__canvas"
				data-bridge-map
				data-key="<?php echo esc_attr( $key ); ?>"
				<?php if ( null !== $lat && null !== $lng ) : ?>
					data-lat="<?php echo esc_attr( (string) $lat ); ?>"
					data-lng="<?php echo esc_attr( (string) $lng ); ?>"
				<?php else : ?>
					data-address="<?php echo esc_attr( $address ); ?>"
				<?php endif; ?>
				data-zoom="<?php echo esc_attr( (string) $zoom ); ?>"
				<?php if ( '' !== $map_id ) : ?>
					data-map-id="<?php echo esc_attr( $map_id ); ?>"
				<?php endif; ?>
				data-title="<?php echo esc_attr( '' !== $label ? $label : $address ); ?>"
				role="img"
				aria-label="<?php echo esc_attr( $title ); ?>"><noscript><?php
					// Without JavaScript there is no map to build, so the
					// address is readable and clickable rather than a hole.
					printf(
						'<a href="%s" rel="noopener" target="_blank">%s</a>',
						esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $address ) ),
						esc_html( $title )
					);
				?></noscript></div>
		<?php else : ?>
			<p class="bridge-map__empty">
				<a href="<?php echo esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $address ) ); ?>"
					rel="noopener" target="_blank"><?php echo esc_html( $title ); ?></a>
			</p>
		<?php endif; ?>
		<?php if ( '' !== $label ) : ?>
			<p class="bridge-map__label"><?php echo esc_html( $label ); ?></p>
		<?php endif; ?>
	</div>
</section>
