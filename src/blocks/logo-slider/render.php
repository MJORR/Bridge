<?php
/**
 * Server-side render for `bridge/logo-slider`.
 *
 * The old block ran a JavaScript carousel. This is a CSS animation over a
 * duplicated track: no layout thrash, and it stops dead for anyone who has
 * asked their system to reduce motion — which a JS carousel on a timer does
 * not. The one thing script is still asked for is the measurement CSS cannot
 * make: whether a row's logos already fit, in which case they stand still in
 * the middle rather than sliding past nothing. That is logo-slider-view.js,
 * it decides nothing else, and the row slides without it.
 *
 * The row helpers live in inc/section-blocks.php, because this file is
 * included once per block and would redeclare them.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block HTML (unused — the intro is fields).
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

/*
 * The three sliders, as custom properties on the band. Zero is not a size — it
 * is the block saying it never asked for one, which is what every block saved
 * before these controls existed says. Nothing is printed for it and the
 * stylesheet's own value stands.
 *
 *
 * The two lengths are printed as fluid clamps rather than as the number the
 * slider holds: what an editor sets is the size on a desktop, and the same
 * length carried onto a phone is out of proportion with everything beside it.
 * The speed is a plain multiplier — it divides the duration, so it means the
 * same thing at every width.
 *
 * The height is printed whether or not its slider has been moved, because the
 * fallback behind it is a number of this block's own and nothing else knows
 * to make it fluid. The spacing is not: what stands in for it is a step from
 * the theme's spacing scale, and those presets are already fluid — leaving it
 * alone keeps an untouched block spending spacing through the design system
 * rather than through this block's arithmetic.
 */
$style = '';

foreach (
	array(
		// The attribute, the slider's bounds, what an untouched slider means,
		// and whether the value is a length or a bare number.
		'--bridge-logo-size'  => array( $attributes['logoSize'] ?? 0, 1.5, 10.0, 3.0, true ),
		'--bridge-logo-space' => array( $attributes['logoSpace'] ?? 0, 0.25, 8.0, 0.0, true ),
		'--bridge-logo-speed' => array( $attributes['speed'] ?? 0, 0.25, 4.0, 0.0, false ),
	) as $property => $spec
) {
	list( $value, $min, $max, $unset, $is_length ) = $spec;
	$value                                         = (float) $value;

	if ( $value <= 0 ) {
		$value = $unset;
	}

	if ( $value <= 0 ) {
		continue;
	}

	// The bounds are the sliders' own. An attribute reaching here outside them
	// came from hand-edited post content rather than from the control.
	$value  = min( max( $value, $min ), $max );
	$style .= $property . ':' . ( $is_length ? bridge_logo_fluid( $value ) : bridge_css_number( $value ) ) . ';';
}

foreach ( array( 'images', 'imagesSecond' ) as $key ) {
	if ( 'imagesSecond' === $key && empty( $attributes['secondRow'] ) ) {
		continue;
	}

	$row = isset( $attributes[ $key ] ) && is_array( $attributes[ $key ] ) ? $attributes[ $key ] : array();

	if ( ! empty( $row ) ) {
		$rows[] = $row;
	}
}

/*
 * The label is optional and off until an editor types one — a band of logos
 * says what it is by being a band of logos, and a heading printed above every
 * one of them by default is a word nobody chose.
 *
 * Its size and alignment fall back to the block's own defaults, which is the
 * same pair edit.js falls back to: an attribute outside these sets came from
 * hand-edited post content, and drawing it as the default is better than
 * drawing a label with no size class at all.
 */
$label = trim( (string) ( $attributes['label'] ?? '' ) );
$align = in_array( $attributes['labelAlign'] ?? '', array( 'left', 'center', 'right' ), true )
	? $attributes['labelAlign']
	: 'left';
$size  = in_array( $attributes['labelSize'] ?? '', array( 'small', 'medium', 'large', 'x-large' ), true )
	? $attributes['labelSize']
	: 'medium';

if ( empty( $rows ) && '' === $label ) {
	return;
}

/*
 * The label names the band for assistive technology. It is set small, but it
 * is the only thing the band says in words, and a landmark with a name is one
 * a screen-reader user can find and skip; unnamed, the <section> is not
 * announced as a landmark at all.
 */
$label_id = '' === $label ? '' : wp_unique_id( 'bridge-section-' );

echo bridge_section_wrapper( $attributes, 'bridge-logos bridge-logos--' . $width, $style, $label_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
?>
	<div class="bridge-logos__inner">
		<?php if ( '' !== $label ) : ?>
			<?php // The wrapper is what holds the label to the content column when the logos run to the window. ?>
			<div class="bridge-section__intro bridge-logos__intro">
				<p class="bridge-logos__label is-align-<?php echo esc_attr( $align ); ?> is-size-<?php echo esc_attr( $size ); ?>" id="<?php echo esc_attr( $label_id ); ?>"><?php echo esc_html( $label ); ?></p>
			</div>
		<?php endif; ?>

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
