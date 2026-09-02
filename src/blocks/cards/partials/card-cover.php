<?php
/**
 * Cover — the photograph is the card.
 *
 * A poster rather than a summary. The image fills the whole card, a gradient in
 * the brand's Inverted colour rises from the foot of it, and the title and a
 * link marker sit on that gradient at the bottom edge. No excerpt, no read-more
 * line: the only words are the headline, which is what makes it work at small
 * sizes and what makes it demand a short one.
 *
 * ---- Why the marker is a span --------------------------------------------
 *
 * The circle and arrow are the one visual cue that the card is clickable, and
 * they are `aria-hidden` decoration for the same reason Tile's chip and Team's
 * button are: the title above already carries the link, stretched over the
 * whole card by a pseudo-element. A real anchor here would be unreachable by
 * pointer — the stretched link covers it — and a second tab stop to the same
 * destination for a keyboard.
 *
 * So the whole card *is* the clickable surface, with exactly one link in it and
 * the headline as that link's accessible name.
 *
 * @var array $card The card record. See bridge_card_data_defaults().
 *
 * @package Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The chip belongs to Tile. See image.php.
$card_show_badge = false;
?>
<article class="post-card post-card--cover">
	<?php require __DIR__ . '/image.php'; ?>
	<div class="post-card__body">
		<h3 class="post-card__title">
			<a class="post-card__link" href="<?php echo esc_url( $card['permalink'] ); ?>"><?php echo esc_html( $card['title'] ); ?></a>
		</h3>
		<span class="post-card__go" aria-hidden="true">
			<?php
			// From the theme's own icon library, so it inherits the stroke
			// weight an operator chose in Theme Options rather than shipping a
			// second arrow drawn to its own thickness. `bridge_render_icon()`
			// returns '' for a name the library does not hold, which leaves an
			// empty circle rather than a broken card.
			if ( function_exists( 'bridge_render_icon' ) ) {
				echo bridge_render_icon( 'arrow-right', array( 'size' => 'small' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — assembled from escaped parts.
			}
			?>
		</span>
	</div>
</article>
