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

list( $intro_html, $label_id ) = bridge_section_intro( $intro, 'bridge-goals__intro' );

echo bridge_section_wrapper( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
	$attributes,
	$classes,
	sprintf( '--columns: %d;', $columns ),
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
