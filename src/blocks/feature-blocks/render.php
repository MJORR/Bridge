<?php
/**
 * Server-side render for `bridge/feature-blocks`.
 *
 * The old layout's `show_sidebar` / `sidebar_content` pair is gone. A sidebar
 * beside a grid is a two-column page layout, which core/columns already does
 * properly — and did better, because the ACF sidebar was a single WYSIWYG that
 * global styles never reached. A section that wants one now goes inside a
 * columns block. Two settings fewer, and the sidebar gains every block.
 *
 * What did come across from that layout is the *numbered* run of panels it was
 * built around, because that is a different shape of panel rather than a page
 * layout: a column of steps, each carrying its position. It is a `<ol>` and a
 * CSS counter, so nothing here numbers anything — an editor dragging the third
 * step above the first renumbers both, and no panel carries a number that has
 * to be kept in step with where it sits.
 *
 * ---- What this band deliberately does not decide ---------------------------
 *
 * The crop of a panel's photograph, the corner, the padding, the shadow and
 * the lift under the pointer are all set once in Theme Options and spent by
 * every card on the site. This band had grown controls of its own for the
 * first and the last of those — a graphic size and a hover effect — which is
 * two ways to answer a question the design system had already answered, and
 * the first thing that happens to a site with two of those is that they
 * disagree. Both are gone; the panels read the card tokens like the cards do.
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

$columns   = max( 1, min( 4, (int) ( $attributes['columns'] ?? 3 ) ) );
$width     = 'narrow' === ( $attributes['width'] ?? 'wide' ) ? 'narrow' : 'wide';
$gutters   = ! empty( $attributes['gutters'] );
$alignment = 'center' === ( $attributes['textAlignment'] ?? 'left' ) ? 'center' : 'left';
$layout    = 'numbered' === ( $attributes['layout'] ?? 'grid' ) ? 'numbered' : 'grid';

/**
 * The settings that are checked against a list rather than a pair.
 *
 * Every one of them arrives from saved post content, which the editor that
 * wrote it does not validate on the way back in — block.json's `enum` is a
 * hint to the editor, not a guarantee to this file. An unknown value falls
 * back to the default rather than being printed into a class name.
 */
$panel_styles = array( 'card', 'plain', 'outline' );
$panel_style  = sanitize_key( $attributes['panelStyle'] ?? 'card' );
$panel_style  = in_array( $panel_style, $panel_styles, true ) ? $panel_style : 'card';

/**
 * The swipe layout, and the one place it is refused.
 *
 * A numbered run is read top to bottom and its numbers are its order, so a row
 * that scrolls sideways would hide most of the steps and put the rest in a
 * line that reads across rather than down. The setting is left alone rather
 * than reset — an editor trying the two layouts against each other gets their
 * swipe row back when they switch to the grid.
 */
$overflow    = 'carousel' === ( $attributes['overflowStyle'] ?? 'wrap' ) ? 'carousel' : 'wrap';
$is_carousel = 'carousel' === $overflow && 'grid' === $layout;

list( $intro, $items ) = bridge_section_split( $block, 'bridge/feature-block' );

if ( '' === trim( $intro . $items ) ) {
	return;
}

$classes = sprintf(
	'bridge-features bridge-features--%s bridge-features--%s bridge-features--text-%s'
		. ' bridge-features--panels-%s%s%s',
	$width,
	$layout,
	$alignment,
	$panel_style,
	$is_carousel ? ' bridge-features--carousel' : '',
	// Flush panels are a grid treatment: a numbered column already sets its
	// own rhythm between steps, and one with no gap is a single block of text
	// with numbers in it.
	$gutters || 'numbered' === $layout ? '' : ' bridge-features--flush'
);

list( $intro_html, $label_id ) = bridge_section_intro( $intro, 'bridge-features__intro' );

// The decorative shape behind the panels, if this band asks for one. Shared
// with the cards band — see bridge_band_mask().
list( $mask_class, $mask_style ) = bridge_band_mask( $attributes );

echo bridge_section_wrapper( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
	$attributes,
	$classes . $mask_class,
	sprintf( '--columns: %d;', $columns ) . $mask_style,
	$label_id
);
?>
	<div class="bridge-features__inner"<?php echo $is_carousel ? ' data-bridge-carousel' : ''; ?>>
		<?php echo $intro_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php if ( '' !== trim( $items ) ) : ?>
			<?php
			// An ordered list when the panels are numbered, because then the
			// order is the content: the steps are first, second and third
			// rather than a set of things that happen to be in a row.
			//
			// `role="list"` on both, for the reason every list in this theme
			// carries it: `list-style: none` takes a list out of the
			// accessibility tree in Safari, and an ordered list is no
			// exception. The role restores it without flattening the order —
			// a screen reader still counts each panel's position in the set.
			$list_tag = 'numbered' === $layout ? 'ol' : 'ul';
			?>
			<<?php echo esc_html( $list_tag ); ?> class="bridge-features__list" role="list"
				<?php
				if ( $is_carousel ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — escaped inside.
					echo ' ' . bridge_carousel_track_attrs( __( 'Features, scrollable', 'bridge' ) );
				}
				?>>
				<?php echo $items; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</<?php echo esc_html( $list_tag ); ?>>

			<?php
			if ( $is_carousel ) {
				// The same strip the cards and testimonials bands print,
				// driven by the same script. See bridge_carousel_controls().
				echo bridge_carousel_controls( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from escaped parts.
					array(
						'prev' => __( 'Previous features', 'bridge' ),
						'next' => __( 'Next features', 'bridge' ),
						'dots' => __( 'Feature pages', 'bridge' ),
						/* translators: %d: page number. */
						'dot'  => __( 'Page %d', 'bridge' ),
					)
				);
			}
			?>
		<?php endif; ?>
	</div>
</section>
