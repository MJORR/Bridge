<?php
/**
 * Server-side render for `bridge/feature-block`.
 *
 * The old block wrapped the whole panel in a link when one was set, which put
 * the graphic, the title and the copy all inside one enormous anchor — a
 * screen reader reads the lot as the link's name. Here the link is on its own
 * text and stretched over the panel with a pseudo-element, so the click target
 * is still the whole card while the accessible name is just the link.
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

$title      = trim( (string) ( $attributes['title'] ?? '' ) );
$text       = trim( (string) ( $attributes['text'] ?? '' ) );
$graphic    = (int) ( $attributes['graphicId'] ?? 0 );
$background = (int) ( $attributes['backgroundId'] ?? 0 );
$link_url   = trim( (string) ( $attributes['linkUrl'] ?? '' ) );
$link_text  = trim( (string) ( $attributes['linkText'] ?? '' ) );

if ( '' === $title && '' === $text && 0 === $graphic ) {
	return;
}

$align_top = ! empty( $block->context['bridge/alignImageTop'] );

$classes = 'bridge-feature';

if ( $background > 0 ) {
	$classes .= ' has-background-image';
}

if ( $link_url ) {
	$classes .= ' is-linked';
}

$wrapper = get_block_wrapper_attributes( array( 'class' => $classes ) );
?>
<li <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core. ?>>
	<?php if ( $background > 0 ) : ?>
		<?php
		echo wp_get_attachment_image(
			$background,
			'large',
			false,
			array(
				// `alt=""` and nothing else: an empty alt already takes an
				// image out of the accessibility tree, so the `aria-hidden`
				// that used to sit beside it said the same thing twice. And
				// `loading`/`decoding` are core's call — a panel grid can be
				// the first thing on a page.
				'class' => 'bridge-feature__background' . ( $align_top ? ' is-aligned-top' : '' ),
				'alt'   => '',
			)
		);
		?>
		<div class="bridge-feature__scrim" aria-hidden="true"></div>
	<?php endif; ?>

	<div class="bridge-feature__body">
		<?php if ( $graphic > 0 ) : ?>
			<?php
			echo wp_get_attachment_image(
				$graphic,
				'medium',
				false,
				array(
					// Decorative: the title beside it names the panel.
					'class' => 'bridge-feature__graphic',
					'alt'   => '',
				)
			);
			?>
		<?php endif; ?>

		<?php if ( '' !== $title ) : ?>
			<h3 class="bridge-feature__title"><?php echo esc_html( $title ); ?></h3>
		<?php endif; ?>

		<?php if ( '' !== $text ) : ?>
			<p class="bridge-feature__text"><?php echo esc_html( $text ); ?></p>
		<?php endif; ?>

		<?php if ( '' !== $link_url ) : ?>
			<a class="bridge-feature__link" href="<?php echo esc_url( $link_url ); ?>">
				<?php echo esc_html( '' !== $link_text ? $link_text : __( 'Read more', 'bridge' ) ); ?>
			</a>
		<?php endif; ?>
	</div>
</li>
