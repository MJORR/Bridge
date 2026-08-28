<?php
/**
 * Server-side render for `bridge/section`.
 *
 * The band every other section block is, with nothing particular inside it.
 * Cards render a query, downloads render files, testimonials render quotes —
 * this one renders whatever an editor put in it, which is what makes it the
 * right home for a paragraph that wants to be a stripe across the page.
 *
 * Why a block rather than a group with a section style: a group offers the
 * editor padding, margin, border, radius and its own layout controls, and a
 * band whose inset can be typed in by hand is a band that stops matching the
 * one above it. Here the only decisions are the two that should be decisions —
 * how wide the content runs, and which skin the band wears.
 *
 * The skin is the whole of the colour story: core's own colour support is off
 * on this block, so there is no Typography, Background or Elements panel to
 * type a one-off text, background or link colour into. Background and text
 * come as a pair from the section skins registered in structure.php, which is
 * what keeps one band matching the next.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner-block HTML.
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// An empty band is a stripe of colour with nothing in it, which is never what
// was meant — most often it is a block inserted and then abandoned.
if ( '' === trim( (string) $content ) ) {
	return;
}

$width = 'wide' === ( $attributes['width'] ?? 'narrow' ) ? 'wide' : 'narrow';

// The heading inside names the landmark, exactly as it does for every other
// band. A section with no heading gets no name and is not announced as a
// region, which is better than announcing an unnamed one.
$label_id = bridge_heading_label_id( $content );

echo bridge_section_wrapper( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
	$attributes,
	'bridge-section--' . $width,
	'',
	$label_id
);
?>
	<div class="bridge-section__inner">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — inner blocks, rendered by core. ?>
	</div>
</section>
