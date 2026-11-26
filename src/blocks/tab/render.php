<?php
/**
 * Server-side render for `bridge/tab` — one tab's panel body.
 *
 * The words (heading, text, Tab Features, buttons: the tab's own inner
 * blocks) and the optional image beside them. The parent wraps this in the
 * tab panel and takes the label for the tab row — see tabs/render.php. With
 * no image the words take the full width.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks.
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$image_id = (int) ( $attributes['imageId'] ?? 0 );
$image    = '';

if ( $image_id && wp_attachment_is_image( $image_id ) ) {
	$alt   = trim( (string) ( $attributes['imageAlt'] ?? '' ) );
	$image = wp_get_attachment_image(
		$image_id,
		'large',
		false,
		array_filter(
			array(
				'class'   => 'bridge-tab__img',
				'sizes'   => '(min-width: 64rem) 40vw, 100vw',
				'loading' => 'lazy',
				'alt'     => '' !== $alt ? $alt : null,
			),
			static fn( $value ) => null !== $value
		)
	);
}

$content = bridge_strip_empty_blocks( (string) $content );

if ( '' === trim( $content ) && '' === $image ) {
	return;
}
?>
<div class="bridge-tab<?php echo '' !== $image ? ' bridge-tab--has-image' : ''; ?>">
	<div class="bridge-tab__content">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — rendered inner blocks. ?>
	</div>
	<?php if ( '' !== $image ) : ?>
		<figure class="bridge-tab__media">
			<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — core image markup. ?>
		</figure>
	<?php endif; ?>
</div>
