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

list( $intro, $items ) = bridge_section_split( $block, 'bridge/feature-block' );

if ( '' === trim( $intro . $items ) ) {
	return;
}

$classes = sprintf(
	'bridge-features bridge-features--%s bridge-features--text-%s%s',
	$width,
	$alignment,
	$gutters ? '' : ' bridge-features--flush'
);

list( $intro_html, $label_id ) = bridge_section_intro( $intro, 'bridge-features__intro' );

echo bridge_section_wrapper( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
	$attributes,
	$classes,
	sprintf( '--columns: %d;', $columns ),
	$label_id
);
?>
	<div class="bridge-features__inner">
		<?php echo $intro_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php if ( '' !== trim( $items ) ) : ?>
			<ul class="bridge-features__list" role="list">
				<?php echo $items; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</ul>
		<?php endif; ?>
	</div>
</section>
