<?php
/**
 * Server-side render for `bridge/alternating-footer`.
 *
 * A ruled line of short facts along the bottom of a row's copy — "4.9 star
 * reviews", "0% finance" — with an optional star rating in front. The Row
 * prints it last in its copy whatever order the blocks were saved in, and the
 * stylesheet pins it to the bottom of the row; see alternating-row/render.php.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks (a list, usually).
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rating = max( 0, min( 5, (int) ( $attributes['rating'] ?? 0 ) ) );

if ( '' === trim( wp_strip_all_tags( $content ) ) && 0 === $rating ) {
	return;
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'bridge-alternating__footer' ) );
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core. ?>>
	<div class="bridge-alternating__footer-inner">
		<?php if ( $rating > 0 ) : ?>
			<?php
			// One text alternative for the group, as on the testimonial.
			?>
			<span class="bridge-alternating__rating" role="img"
				aria-label="<?php echo esc_attr( sprintf( /* translators: %d: rating out of five. */ __( 'Rated %d out of 5', 'bridge' ), $rating ) ); ?>">
				<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
					<span class="bridge-alternating__star<?php echo $i <= $rating ? ' is-filled' : ''; ?>" aria-hidden="true">&#9733;</span>
				<?php endfor; ?>
			</span>
		<?php endif; ?>
		<div class="bridge-alternating__footer-body">
			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
</div>
