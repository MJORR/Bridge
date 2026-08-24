<?php
/**
 * Server-side render for `bridge/downloads`.
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

list( $intro, $items ) = bridge_section_split( $block, 'bridge/download-item' );

if ( '' === trim( $intro . $items ) ) {
	return;
}

list( $intro_html, $label_id ) = bridge_section_intro( $intro, 'bridge-downloads__intro' );

echo bridge_section_wrapper( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
	$attributes,
	'bridge-downloads bridge-downloads--' . $width,
	sprintf( '--columns: %d;', $columns ),
	$label_id
);
?>
	<div class="bridge-downloads__inner">
		<?php echo $intro_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php if ( '' !== trim( $items ) ) : ?>
			<?php
			// A list, because that is what it is. The old block rendered a
			// stack of divs, which told a screen-reader user nothing about
			// how many downloads there were or where the set ended.
			?>
			<ul class="bridge-downloads__list" role="list">
				<?php echo $items; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</ul>
		<?php endif; ?>
	</div>
</section>
