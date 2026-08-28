<?php
/**
 * Tile — a photograph, a large heading, and a rule that runs.
 *
 * The busiest of the three, and the one for a grid of places rather than a grid
 * of articles: the chip over the corner of the image carries a fact about the
 * thing itself — a distance, a duration — and the heading is sized to be read
 * across a room rather than skimmed at reading distance.
 *
 * The rule along the bottom edge is the whole hover state. It sits short and
 * inset at rest and runs the full width of the card under the pointer, which
 * is a direction rather than a colour change — the one hover cue on this card
 * that a reader who cannot distinguish the two brand colours still sees.
 *
 * @var array $card The card record. See bridge_card_data_defaults().
 *
 * @package Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The chip is Tile's, and only Tile's. See image.php.
$card_show_badge = true;
?>
<article class="post-card post-card--tile">
	<?php require __DIR__ . '/image.php'; ?>
	<div class="post-card__body">
		<h3 class="post-card__title">
			<a class="post-card__link" href="<?php echo esc_url( $card['permalink'] ); ?>"><?php echo esc_html( $card['title'] ); ?></a>
		</h3>
		<?php if ( '' !== $card['excerpt'] ) : ?>
			<p class="post-card__excerpt"><?php echo esc_html( $card['excerpt'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	// Presentational, and empty by design — it is a painted rectangle, not
	// content, and there is nothing in it for a screen reader to reach.
	?>
	<span class="post-card__rule" aria-hidden="true"></span>
</article>
