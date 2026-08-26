<?php
/**
 * Server-side render for `bridge/testimonials`.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner-block HTML (unused — see below).
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$columns  = max( 1, min( 4, (int) ( $attributes['columns'] ?? 3 ) ) );
$overflow = 'carousel' === ( $attributes['overflowStyle'] ?? 'wrap' ) ? 'carousel' : 'wrap';
// This file is the front end's alone — the editor builds its own preview from
// inner blocks — so the setting is the whole test.
$is_carousel = 'carousel' === $overflow;
$card     = 'outlined' === ( $attributes['cardStyle'] ?? 'solid' ) ? 'outlined' : 'solid';
$width    = 'wide' === ( $attributes['width'] ?? 'narrow' ) ? 'wide' : 'narrow';

// The intro is whatever is not a card — see bridge_section_split(). The cards
// need a container the intro stays out of, because in the swipe layout that
// container scrolls sideways and a heading inside it would scroll away from
// the cards it introduces.
list( $intro, $cards ) = bridge_section_split( $block, 'bridge/testimonial' );

if ( '' === trim( $intro . $cards ) ) {
	return;
}

$classes = sprintf(
	'bridge-testimonials bridge-testimonials--%s bridge-testimonials--cards-%s bridge-testimonials--%s',
	$overflow,
	$card,
	$width
);

list( $intro_html, $label_id ) = bridge_section_intro( $intro, 'bridge-testimonials__intro' );

echo bridge_section_wrapper( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
	$attributes,
	$classes,
	sprintf( '--columns: %d;', $columns ),
	$label_id
);
?>
	<div class="bridge-testimonials__inner"<?php echo $is_carousel ? ' data-bridge-carousel' : ''; ?>>
		<?php echo $intro_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php if ( '' !== trim( $cards ) ) : ?>
			<?php
			// tabindex on a scrolling container, so the swipe layout can be
			// reached and scrolled with a keyboard. Without it the row is
			// operable by mouse and touch only — the cards themselves hold no
			// focusable content to tab through.
			?>
			<div class="bridge-testimonials__track"
				<?php
				if ( $is_carousel ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — escaped inside.
					echo ' ' . bridge_carousel_track_attrs( __( 'Testimonials, scrollable', 'bridge' ) );
				}
				?>>
				<?php echo $cards; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<?php
			if ( $is_carousel ) {
				// The same strip the cards band uses, driven by the same
				// script. See bridge_carousel_controls().
				echo bridge_carousel_controls( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from escaped parts.
					array(
						'prev' => __( 'Previous testimonials', 'bridge' ),
						'next' => __( 'Next testimonials', 'bridge' ),
						'dots' => __( 'Testimonial pages', 'bridge' ),
						/* translators: %d: page number. */
						'dot'  => __( 'Page %d', 'bridge' ),
					)
				);
			}
			?>
		<?php endif; ?>
	</div>
</section>
