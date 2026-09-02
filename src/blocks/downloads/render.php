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

/*
 * Which of the site's card styles these download cards wear.
 *
 * Summary and Tile only, and the line between what is offered and what is not
 * is the same one everywhere: a style that is a *skin* transfers, a style that
 * is a *layout* does not. Summary and Tile differ from each other by heading
 * size and image crop — both global tokens, both meaningful on a document.
 * Cover puts the title inside the photograph and Team draws a circular avatar;
 * a download card has a file size, a format and a link to place, and neither
 * layout has anywhere to put them.
 *
 * The class only names the style. What it means is in the stylesheet, reading
 * the same `--wp--custom--card--{style}--*` properties the post cards read, so
 * a crop changed in Theme Options moves both.
 */
$card_styles = array( 'summary', 'tile' );
$card_style  = sanitize_key( (string) ( $attributes['cardStyle'] ?? 'summary' ) );
$card_style  = in_array( $card_style, $card_styles, true ) ? $card_style : 'summary';

list( $intro, $items ) = bridge_section_split( $block, 'bridge/download-item' );

if ( '' === trim( $intro . $items ) ) {
	return;
}

list( $intro_html, $label_id ) = bridge_section_intro( $intro, 'bridge-downloads__intro' );

// The decorative shape behind the cards, if this band asks for one. Shared
// with the cards band — see bridge_band_mask().
list( $mask_class, $mask_style ) = bridge_band_mask( $attributes );

echo bridge_section_wrapper( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
	$attributes,
	'bridge-downloads bridge-downloads--' . $width . ' bridge-downloads--' . $card_style . $mask_class,
	sprintf( '--columns: %d;', $columns ) . $mask_style,
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
