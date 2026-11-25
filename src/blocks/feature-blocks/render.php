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

$columns   = max( 1, min( 5, (int) ( $attributes['columns'] ?? 3 ) ) );
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
$panel_styles = array( 'card', 'plain', 'outline', 'open' );
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

// Step numbers over each title: a grid-only setting, because the numbered
// layout already draws its own badges. The panels read it through block
// context and print the label; this only starts the counter they draw from.
$show_numbers = 'grid' === $layout && ! empty( $attributes['showNumbers'] );

list( $intro, $items ) = bridge_section_split( $block, 'bridge/feature-block' );

if ( '' === trim( $intro . $items ) ) {
	return;
}

$classes = sprintf(
	'bridge-features bridge-features--%s bridge-features--%s bridge-features--text-%s'
		. ' bridge-features--panels-%s%s%s%s%s',
	$width,
	$layout,
	$alignment,
	$panel_style,
	$is_carousel ? ' bridge-features--carousel' : '',
	// Flush panels are a grid treatment: a numbered column already sets its
	// own rhythm between steps, and one with no gap is a single block of text
	// with numbers in it.
	$gutters || 'numbered' === $layout ? '' : ' bridge-features--flush',
	$show_numbers ? ' bridge-features--show-numbers' : '',
	// Five across needs a smaller floor per panel or the wide container
	// cannot fit them and the grid quietly drops to four.
	'grid' === $layout && $columns >= 5 ? ' bridge-features--dense' : ''
);

/*
 * Options added after the band shipped. Each adds a class only when chosen,
 * so a band saved before them prints the markup it always did.
 *
 *   panelStyle   open    No card at all: the picture stands on the band with
 *                        its own rounded corners and the words line up with
 *                        its edge. (In the list above.)
 *   imageCorners round   Open panels' pictures at twice the large radius
 *                        rather than the card radius.
 *   buttonSize   small   A smaller button: less padding, a smaller label.
 *
 * The button's Outline style goes to each panel through block context, since
 * the panel prints the button.
 */
if ( 'open' === $panel_style && 'round' === ( $attributes['imageCorners'] ?? 'card' ) ) {
	$classes .= ' bridge-features--image-round';
}

if ( 'small' === ( $attributes['buttonSize'] ?? 'default' ) ) {
	$classes .= ' bridge-features--button-small';
}

// A panel set to double width. The band then holds one column until it is
// wide enough for its full set of columns — see _feature-blocks.scss. Grid
// only: a swipe row has no columns to span.
if ( 'grid' === $layout && ! $is_carousel ) {
	foreach ( (array) ( $block->parsed_block['innerBlocks'] ?? array() ) as $bridge_child ) {
		if ( 'bridge/feature-block' === ( $bridge_child['blockName'] ?? '' ) && ! empty( $bridge_child['attrs']['wide'] ) ) {
			$classes .= ' bridge-features--has-wide';
			break;
		}
	}
}

// The words at the foot of the panel and the arrow in its top-right corner.
if ( ! empty( $attributes['titleBottom'] ) ) {
	$classes .= ' bridge-features--title-bottom';
}

// The arrow as a solid white disc with a Primary arrow, rather than an
// outline in the text colour.
if ( ! empty( $attributes['showArrow'] ) && ! empty( $attributes['arrowSolid'] ) ) {
	$classes .= ' bridge-features--arrow-solid';
}

// Rounded corners on the band, at the XL radius, with the colour that shows
// behind each curve: the section above for the top two, the section below for
// the bottom two. Palette slugs; empty is the page colour.
$corner_style = '';

if ( ! empty( $attributes['roundCorners'] ) ) {
	$classes  .= ' bridge-features--corners';
	$palette   = bridge_palette_slugs();
	$behind    = static function ( $slug ) use ( $palette ): string {
		$slug = (string) $slug;

		return isset( $palette[ $slug ] ) ? $slug : 'background';
	};
	$corner_style = sprintf(
		'--bridge-corner-top: var(--wp--preset--color--%1$s);--bridge-corner-bottom: var(--wp--preset--color--%2$s);',
		$behind( $attributes['cornerTop'] ?? '' ),
		$behind( $attributes['cornerBottom'] ?? '' )
	);
}

list( $intro_html, $label_id ) = bridge_section_intro( $intro, 'bridge-features__intro' );

// The decorative shape behind the panels, if this band asks for one. Shared
// with the cards band — see bridge_band_mask().
list( $mask_class, $mask_style ) = bridge_band_mask( $attributes );

echo bridge_section_wrapper( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
	$attributes,
	$classes . $mask_class,
	sprintf( '--columns: %d;', $columns ) . $corner_style . $mask_style,
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
