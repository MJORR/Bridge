<?php
/**
 * Server-side render for `bridge/goals`.
 *
 * A band of figures: a number, its unit and a label, three or four across.
 * Everything visible here is printed at its final value — the count-up is an
 * enhancement goals-view.js adds once it has seen the row enter the viewport,
 * so a visitor with no JavaScript, or one who has asked for reduced motion,
 * reads the numbers rather than a row of zeros.
 *
 * That is also why the `data-bridge-goals` hook sits on the list rather than
 * on the <section>: it marks the thing the script observes and animates, and
 * it is absent altogether when the count-up is switched off, so the script
 * finds no work rather than being asked not to do it.
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

$columns   = 4 === (int) ( $attributes['columns'] ?? 3 ) ? 4 : 3;
$width     = 'narrow' === ( $attributes['width'] ?? 'wide' ) ? 'narrow' : 'wide';
$alignment = 'left' === ( $attributes['textAlignment'] ?? 'center' ) ? 'left' : 'center';
$count_up  = ! empty( $attributes['countUp'] );

list( $intro, $items ) = bridge_section_split( $block, 'bridge/goal' );

if ( '' === trim( $intro . $items ) ) {
	return;
}

$classes = sprintf(
	'bridge-goals bridge-goals--%s bridge-goals--text-%s',
	$width,
	$alignment
);

/*
 * The trust-strip options. Every one is off by default and adds a class only
 * when it is on, so a band saved before they existed prints exactly the
 * markup it always did.
 *
 *   figureStyle  heading   The figure in the site's heading face and weight
 *                          rather than bold body type.
 *   labelStyle   eyebrow   The label small, uppercase, spaced and muted.
 *   dividers               A hairline between neighbouring figures.
 *   bottomRule             A hairline along the band's bottom edge.
 *   spacing      compact   Less air above and below the row.
 */
$style = sprintf( '--columns: %d;', $columns );

if ( 'heading' === ( $attributes['figureStyle'] ?? 'bold' ) ) {
	$classes .= ' bridge-goals--figure-heading';

	// The heading weight is a Theme Options token with no custom property of
	// its own, and adding one would change every site's compiled styles. So
	// the band carries it.
	$tokens  = function_exists( 'bridge_get_tokens' ) ? bridge_get_tokens() : array();
	$weight  = (string) ( $tokens['typography']['headingWeight'] ?? '' );
	$style  .= preg_match( '/^[1-9]00$/', $weight ) ? '--bridge-goal-figure-weight:' . $weight . ';' : '';
}

if ( 'eyebrow' === ( $attributes['labelStyle'] ?? 'default' ) ) {
	$classes .= ' bridge-goals--label-eyebrow';
}

if ( ! empty( $attributes['dividers'] ) ) {
	$classes .= ' bridge-goals--dividers';
}

if ( ! empty( $attributes['bottomRule'] ) ) {
	$classes .= ' bridge-goals--bottom-rule';
}

if ( 'compact' === ( $attributes['spacing'] ?? 'default' ) ) {
	$classes .= ' bridge-goals--compact';
}

list( $intro_html, $label_id ) = bridge_section_intro( $intro, 'bridge-goals__intro' );

echo bridge_section_wrapper( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
	$attributes,
	$classes,
	$style,
	$label_id
);
?>
	<div class="bridge-goals__inner">
		<?php echo $intro_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php if ( '' !== trim( $items ) ) : ?>
			<ul class="bridge-goals__list" role="list"<?php echo $count_up ? ' data-bridge-goals' : ''; ?>>
				<?php echo $items; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</ul>
		<?php endif; ?>
	</div>
</section>
