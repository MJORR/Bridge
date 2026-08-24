<?php
/**
 * Server-side render for `bridge/gallery-item`.
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

$image_id = (int) ( $attributes['imageId'] ?? 0 );
$label    = trim( (string) ( $attributes['label'] ?? '' ) );
$link_url = trim( (string) ( $attributes['linkUrl'] ?? '' ) );
$alt      = trim( (string) ( $attributes['alt'] ?? '' ) );

if ( 0 === $image_id ) {
	return;
}

$lightbox = ! empty( $block->context['bridge/lightbox'] ) && '' === $link_url;
$full     = wp_get_attachment_image_src( $image_id, 'full' );

/*
 * `alt` is whatever the field holds, empty included. It used to fall back to
 * the attachment's own alt text when empty — which sounds helpful and means a
 * decorative image can never be made silent, in the one block whose help text
 * promises exactly that. The field is seeded from the media library when the
 * image is chosen, so the description is still typed only once; it is just
 * answerable afterwards.
 *
 * No `loading` or `decoding` either: core sets them per image, and it is the
 * only thing that knows whether this is the first image on the page and should
 * therefore be fetched eagerly rather than lazily. A gallery is often the first
 * thing under a page title.
 */
$image = wp_get_attachment_image(
	$image_id,
	'large',
	false,
	array(
		'class' => 'bridge-gallery__image',
		'alt'   => $alt,
	)
);

$wrapper = get_block_wrapper_attributes( array( 'class' => 'bridge-gallery__item' ) );
?>
<li <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core. ?>>
	<?php if ( '' !== $link_url ) : ?>
		<a class="bridge-gallery__link" href="<?php echo esc_url( $link_url ); ?>">
			<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built by core. ?>
			<?php if ( '' !== $label ) : ?>
				<span class="bridge-gallery__label"><?php echo esc_html( $label ); ?></span>
			<?php endif; ?>
		</a>
	<?php elseif ( $lightbox && $full ) : ?>
		<?php
		// A button, not a link: it opens a dialog on this page rather than
		// going anywhere, and announcing it as a link would promise a
		// navigation that never happens.
		?>
		<button class="bridge-gallery__trigger" type="button"
			data-full="<?php echo esc_url( $full[0] ); ?>"
			data-caption="<?php echo esc_attr( $label ); ?>"
			aria-label="<?php echo esc_attr( '' !== $label ? sprintf( /* translators: %s: image label. */ __( 'View %s full size', 'bridge' ), $label ) : __( 'View image full size', 'bridge' ) ); ?>">
			<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built by core. ?>
			<?php if ( '' !== $label ) : ?>
				<span class="bridge-gallery__label"><?php echo esc_html( $label ); ?></span>
			<?php endif; ?>
		</button>
	<?php else : ?>
		<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built by core. ?>
		<?php if ( '' !== $label ) : ?>
			<span class="bridge-gallery__label"><?php echo esc_html( $label ); ?></span>
		<?php endif; ?>
	<?php endif; ?>
</li>
