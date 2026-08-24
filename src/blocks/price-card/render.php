<?php
/**
 * Server-side render for `bridge/price-card`.
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

$title       = trim( (string) ( $attributes['title'] ?? '' ) );
$price       = trim( (string) ( $attributes['price'] ?? '' ) );
$description = trim( (string) ( $attributes['description'] ?? '' ) );
$image_id    = (int) ( $attributes['imageId'] ?? 0 );
$featured    = ! empty( $attributes['featured'] );

if ( '' === $title && '' === $price ) {
	return;
}

$wrapper = get_block_wrapper_attributes(
	array( 'class' => 'bridge-price-card' . ( $featured ? ' is-featured' : '' ) )
);
?>
<li <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core. ?>>
	<?php if ( $image_id > 0 ) : ?>
		<?php
		echo wp_get_attachment_image(
			$image_id,
			'medium',
			false,
			array(
				// Decorative: the title below names the card, and a screen
				// reader announcing the illustration's filename after it adds
				// noise rather than meaning. `loading` and `decoding` are
				// core's to decide — it is the only thing that knows whether
				// this is the page's first image.
				'class' => 'bridge-price-card__image',
				'alt'   => '',
			)
		);
		?>
	<?php endif; ?>

	<?php if ( '' !== $title ) : ?>
		<p class="bridge-price-card__title"><?php echo esc_html( $title ); ?></p>
	<?php endif; ?>

	<?php if ( '' !== $price ) : ?>
		<?php
		// The price is the loudest thing on the card, so it is set large —
		// but it is not a heading, and marking it up as one would put a
		// meaningless entry in the page's outline for every plan on offer.
		?>
		<p class="bridge-price-card__price"><?php echo esc_html( $price ); ?></p>
	<?php endif; ?>

	<?php if ( '' !== $description ) : ?>
		<p class="bridge-price-card__description"><?php echo esc_html( $description ); ?></p>
	<?php endif; ?>
</li>
