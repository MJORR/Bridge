<?php
/**
 * Server-side render for `bridge/gallery`.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner-block HTML (unused).
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$columns  = max( 1, min( 6, (int) ( $attributes['columns'] ?? 3 ) ) );
$width    = 'narrow' === ( $attributes['width'] ?? 'wide' ) ? 'narrow' : 'wide';
$lightbox = ! empty( $attributes['lightbox'] );

list( $intro, $items ) = bridge_section_split( $block, 'bridge/gallery-item' );

if ( '' === trim( $intro . $items ) ) {
	return;
}

list( $intro_html, $label_id ) = bridge_section_intro( $intro, 'bridge-gallery__intro' );

echo bridge_section_wrapper( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
	$attributes,
	'bridge-gallery bridge-gallery--' . $width,
	sprintf( '--columns: %d;', $columns ),
	$label_id
);
?>
	<div class="bridge-gallery__inner">
		<?php echo $intro_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php if ( '' !== trim( $items ) ) : ?>
			<ul class="bridge-gallery__grid" role="list"
				<?php echo $lightbox ? 'data-bridge-lightbox="1"' : ''; ?>>
				<?php echo $items; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</ul>
		<?php endif; ?>
	</div>

	<?php if ( $lightbox ) : ?>
		<?php
		// A native <dialog>, printed once per gallery and filled by the view
		// script when an image is opened. The old block shipped a lightbox
		// library for this; <dialog> brings the focus trap, the Escape key and
		// the inert background with it, and needs no CSS to be usable.
		?>
		<dialog class="bridge-gallery__lightbox" aria-label="<?php esc_attr_e( 'Image viewer', 'bridge' ); ?>">
			<button class="bridge-gallery__close" type="button"
				aria-label="<?php esc_attr_e( 'Close', 'bridge' ); ?>">&times;</button>
			<button class="bridge-gallery__prev" type="button"
				aria-label="<?php esc_attr_e( 'Previous image', 'bridge' ); ?>">&#8249;</button>
			<figure class="bridge-gallery__figure">
				<img class="bridge-gallery__full" src="" alt="">
				<figcaption class="bridge-gallery__caption"></figcaption>
			</figure>
			<button class="bridge-gallery__next" type="button"
				aria-label="<?php esc_attr_e( 'Next image', 'bridge' ); ?>">&#8250;</button>
		</dialog>
	<?php endif; ?>
</section>
