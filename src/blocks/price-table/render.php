<?php
/**
 * Server-side render for `bridge/price-table`.
 *
 * The old layout carried a `card_count` field — how many of the repeater's
 * cards to display — and one button for the whole table. Neither survives:
 * the number of cards is now the number of card blocks, which is the thing an
 * editor is already looking at, and a button belongs in the intro where it can
 * be a real core/buttons block with the site's own button styles. Two settings
 * fewer, and no way for the count to disagree with the content.
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

$columns = max( 1, min( 4, (int) ( $attributes['columns'] ?? 3 ) ) );
$width   = 'narrow' === ( $attributes['width'] ?? 'wide' ) ? 'narrow' : 'wide';

list( $intro, $cards ) = bridge_section_split( $block, 'bridge/price-card' );

if ( '' === trim( $intro . $cards ) ) {
	return;
}

list( $intro_html, $label_id ) = bridge_section_intro( $intro, 'bridge-price-table__intro' );

echo bridge_section_wrapper( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
	$attributes,
	'bridge-price-table bridge-price-table--' . $width,
	sprintf( '--columns: %d;', $columns ),
	$label_id
);
?>
	<div class="bridge-price-table__inner">
		<?php echo $intro_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php if ( '' !== trim( $cards ) ) : ?>
			<ul class="bridge-price-table__list" role="list">
				<?php echo $cards; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</ul>
		<?php endif; ?>
	</div>
</section>
