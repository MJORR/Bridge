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
 * Only the blocks *before* the first row are the intro. Anything an editor
 * drops between two rows renders where they put it, because that is where the
 * editor shows it; hoisting it to the top would silently rearrange the page.
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

$width = 'narrow' === ( $attributes['width'] ?? 'wide' ) ? 'narrow' : 'wide';
$flip  = ! empty( $attributes['firstImageRight'] );

$intro    = '';
$body     = '';
$seen_row = false;

foreach ( $block->inner_blocks as $inner ) {
	if ( 'bridge/alternating-row' === $inner->name ) {
		$seen_row = true;
	}

	if ( $seen_row ) {
		$body .= $inner->render();
		continue;
	}

	$intro .= $inner->render();
}

if ( '' === trim( $intro . $body ) ) {
	return;
}

$classes = 'bridge-alternating'
	. ( 'narrow' === $width ? ' bridge-alternating--narrow' : '' )
	. ( $flip ? ' is-first-right' : '' );

list( $intro_html, $label_id ) = bridge_section_intro( $intro, 'bridge-alternating__intro' );

echo bridge_section_wrapper( $attributes, $classes, '', $label_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
?>
	<div class="bridge-alternating__inner">
		<?php echo $intro_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</section>
