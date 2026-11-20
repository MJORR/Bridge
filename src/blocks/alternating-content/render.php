<?php
/**
 * Server-side render for `bridge/alternating-content`.
 *
 * Which side the media sits on is not stored per row — it alternates, which is
 * the block's whole reason for existing, and the only choice is which side the
 * first row starts on. Storing it per row would let an editor break the
 * alternation by accident and leave two images stacked on the same side.
 *
 * That choice is written here as one class on the wrapper, and the stylesheet
 * counts the rows itself with `:nth-child(… of .bridge-alternating__row)` —
 * the `of` is what makes it safe, because the rows are not the only children
 * and a plain nth-child would count the intro too and flip every row. Counting
 * in CSS rather than stamping a class onto each row in PHP is what lets the
 * editor and the front end share the rule instead of running two copies of it
 * that can disagree — which they did: with the first row set to the right, the
 * editor's copy and this file's used to fire together and push every row's
 * media to the right, alternation and all.
 *
 * ---- No heading, no summary ----------------------------------------------
 *
 * This block holds rows and nothing else. It used to open with an optional
 * headline and a line of summary, the way every other section block does, and
 * they were never used: the rows carry their own headings, so the section's
 * title said the same thing twice or sat empty. The editor no longer allows
 * them, and there is no intro to split out here — the inner blocks render as
 * they come.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner-block HTML — the rows.
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Three layouts, and the attribute names the container rather than the look:
 *
 *   narrow  the text column
 *   wide    the wide container — the default
 *   full    the window, with the media flush to its edge and the copy still
 *           lined up with the wide container. See the stylesheet.
 *
 * Anything else is read as `wide`, so a record from a build that only knew two
 * of them still renders the layout it was written for.
 */
$width = (string) ( $attributes['width'] ?? 'wide' );
$width = in_array( $width, array( 'narrow', 'wide', 'full' ), true ) ? $width : 'wide';
$flip  = ! empty( $attributes['firstImageRight'] );

if ( '' === trim( $content ) ) {
	return;
}

$classes = 'bridge-alternating'
	. ( 'wide' !== $width ? ' bridge-alternating--' . $width : '' )
	// This layout insets its own content — the intro through its container,
	// the copy through its padding — so the band opts out of the page gutter a
	// skin would otherwise give it. See components/_sections.scss.
	. ( 'full' === $width ? ' bridge-band--flush' : '' )
	. ( $flip ? ' is-first-right' : '' );

// No label id: with no heading in the block there is nothing to name the
// <section> after, and an `aria-labelledby` pointing at nothing is worse than
// an unnamed region — a region with a broken name is announced as one.
/*
 * How tall a row is: by the picture's shape, or by a set height.
 *
 * Image shape is the default, and 16:9 adds no class, so a band saved before
 * either option existed renders exactly as it did. The other shapes cut every
 * picture to that ratio, in any layout.
 *
 * Set height is for a full-window band only. It takes the hero's presets, read
 * through the hero's own function so "Tall" is the same 80% of the window on
 * both — the height only: the hero subtracts the header because it sits under
 * it, and a band further down the page does not. It is a floor, as on the
 * hero: a row whose words need more room grows past it.
 */
$sizing = 'full' === $width && 'height' === ( $attributes['sizing'] ?? 'ratio' ) ? 'height' : 'ratio';
$ratio  = (string) ( $attributes['ratio'] ?? '16-9' );
$ratio  = in_array( $ratio, array( '4-3', '1-1', '4-5' ), true ) ? $ratio : '';
$style  = '';

if ( 'height' === $sizing && function_exists( 'bridge_hero_metrics' ) ) {
	$metrics  = bridge_hero_metrics( $attributes + array( 'heightPreset' => 'tall' ) );
	$style    = '--bridge-alternating-height:' . $metrics['height'] . ';';
	$classes .= ' has-set-height';
} elseif ( '' !== $ratio ) {
	$classes .= ' is-ratio-' . $ratio;
}

echo bridge_section_wrapper( $attributes, $classes, $style ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
?>
	<div class="bridge-alternating__inner">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</section>
