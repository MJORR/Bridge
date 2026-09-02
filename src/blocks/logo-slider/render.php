<?php
/**
 * Server-side render for `bridge/logo-slider`.
 *
 * The old block ran a JavaScript carousel. This is a CSS animation over a
 * duplicated track: no script at all, no layout thrash, and it stops dead for
 * anyone who has asked their system to reduce motion — which a JS carousel on
 * a timer does not. The row helpers live in inc/section-blocks.php, because
 * this file is included once per block and would redeclare them.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block HTML (the intro).
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$display = 'cover' === ( $attributes['imageDisplay'] ?? 'contain' ) ? 'cover' : 'contain';
/*
 * Two widths, and anything else is `wide`. The block used to offer `narrow`
 * as well; a block still carrying it comes back as `wide` rather than as an
 * unstyled row, which is what an unrecognised value would draw.
 */
$width   = 'full' === ( $attributes['width'] ?? 'full' ) ? 'full' : 'wide';
$rows    = array();

foreach ( array( 'images', 'imagesSecond' ) as $key ) {
	if ( 'imagesSecond' === $key && empty( $attributes['secondRow'] ) ) {
		continue;
	}

	$row = isset( $attributes[ $key ] ) && is_array( $attributes[ $key ] ) ? $attributes[ $key ] : array();

	if ( ! empty( $row ) ) {
		$rows[] = $row;
	}
}

if ( empty( $rows ) && '' === trim( (string) $content ) ) {
	return;
}

list( $intro_html, $label_id ) = bridge_section_intro( (string) $content, 'bridge-logos__intro' );

echo bridge_section_wrapper( $attributes, 'bridge-logos bridge-logos--' . $width, '', $label_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
?>
	<div class="bridge-logos__inner">
		<?php echo $intro_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php foreach ( $rows as $index => $row ) : ?>
			<?php
			// The second row travels the other way. Two rows sliding in
			// parallel read as one wide band that happens to be split; in
			// opposite directions they read as two, which is the point of
			// having asked for a second one.
			bridge_logo_slider_row( $row, $display, 1 === $index );
			?>
		<?php endforeach; ?>
	</div>
</section>
