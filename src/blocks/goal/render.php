<?php
/**
 * Server-side render for `bridge/goal`.
 *
 * One figure. The number is printed at its final value and carries the parsed
 * target beside it, so goals-view.js can count to a number it never has to
 * work out from the visible text — and so a page with no JavaScript shows the
 * figure rather than a zero waiting for a script that is not coming.
 *
 * The number the script animates is `aria-hidden`, with the whole figure
 * repeated once for assistive technology in a `screen-reader-text` span. Not
 * belt and braces: without it, a screen reader reaching the row mid-count
 * reads out whatever number the animation happened to be passing through.
 * The hidden copy never changes, so it is always the true figure.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block HTML (unused).
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$number = trim( (string) ( $attributes['number'] ?? '' ) );
$unit   = trim( (string) ( $attributes['unit'] ?? '' ) );
$label  = trim( (string) ( $attributes['label'] ?? '' ) );

if ( '' === $number && '' === $unit && '' === $label ) {
	return;
}

// Which side the unit sits on, read from the unit itself rather than asked for
// as a fourth field. `\p{Sc}` is Unicode's currency-symbol category — £, $, €
// — which is the set of units written in front of the number. Everything else
// (miles, kg, %, hours) follows it. edit.js makes the same test.
$leads = '' !== $unit && 1 === preg_match( '/^\p{Sc}/u', $unit );

/*
 * The number as a number, for the count-up.
 *
 * Commas are thousands separators and come out; what is left has to be numeric
 * or there is nothing to count to. That is not a failure — "Hundreds" and
 * "1 in 4" are perfectly good figures — so an unparseable number simply ships
 * without the data attributes and the script leaves it alone.
 */
$plain    = str_replace( ',', '', $number );
$numeric  = is_numeric( $plain ) ? $plain : '';
$decimals = 0;

if ( '' !== $numeric ) {
	$point = strpos( $plain, '.' );
	// Capped at four: the digits after the point are formatted on every frame
	// of the animation, and a figure quoted to five decimal places is a
	// spreadsheet cell that has escaped onto a page.
	$decimals = false === $point ? 0 : min( 4, strlen( $plain ) - $point - 1 );
}

// What assistive technology is given: the figure as it reads aloud, with a
// space between a number and a trailing word that the visual setting closes up.
if ( '' === $unit ) {
	$spoken = $number;
} else {
	$spoken = $leads ? $unit . $number : $number . ' ' . $unit;
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'bridge-goal' ) );
?>
<li <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core. ?>>
	<?php if ( '' !== $number || '' !== $unit ) : ?>
		<p class="bridge-goal__value">
			<?php if ( $leads ) : ?>
				<span class="bridge-goal__unit is-prefix" aria-hidden="true"><?php echo esc_html( $unit ); ?></span>
			<?php endif; ?>

			<span
				class="bridge-goal__number"
				aria-hidden="true"
				<?php if ( '' !== $numeric ) : ?>
					data-bridge-goal-number
					data-value="<?php echo esc_attr( $numeric ); ?>"
					data-decimals="<?php echo (int) $decimals; ?>"
					data-group="<?php echo str_contains( $number, ',' ) ? '1' : '0'; ?>"
				<?php endif; ?>
			><?php echo esc_html( $number ); ?></span>

			<?php if ( '' !== $unit && ! $leads ) : ?>
				<span class="bridge-goal__unit" aria-hidden="true"><?php echo esc_html( $unit ); ?></span>
			<?php endif; ?>

			<span class="screen-reader-text"><?php echo esc_html( $spoken ); ?></span>
		</p>
	<?php endif; ?>

	<?php if ( '' !== $label ) : ?>
		<p class="bridge-goal__label"><?php echo esc_html( $label ); ?></p>
	<?php endif; ?>
</li>
