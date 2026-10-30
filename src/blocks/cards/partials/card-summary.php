<?php
/**
 * Summary — the default card.
 *
 * A photograph, a heading, a paragraph of the post's own words and a read-more
 * cue. The card the block has always drawn, and the right one wherever the
 * grid is a list of things to read: it is the only style that puts enough of
 * the post on the page for a reader to decide whether to open it.
 *
 * @var array  $card             The card record. See bridge_card_data_defaults().
 * @var bool   $show_read_more   Whether to draw the read-more cue.
 * @var string $link_class       Button classes for the cue, or '' for a text link.
 * @var string $read_more_text   Its wording.
 *
 * @package Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Summary has no chip. See image.php.
$card_show_badge = false;
?>
<article class="post-card post-card--summary">
	<?php require __DIR__ . '/image.php'; ?>
	<div class="post-card__body">
		<?php if ( '' !== $card['eyebrow'] ) : ?>
			<p class="post-card__eyebrow"><?php echo esc_html( $card['eyebrow'] ); ?></p>
		<?php endif; ?>
		<h3 class="post-card__title">
			<a class="post-card__link" href="<?php echo esc_url( $card['permalink'] ); ?>"><?php echo esc_html( $card['title'] ); ?></a>
		</h3>
		<p class="post-card__excerpt"><?php echo esc_html( $card['excerpt'] ); ?></p>
		<?php if ( $show_read_more || '' !== $card['item_link_text'] ) : ?>
			<?php
			// Decorative. The title above is the link; this is the visual cue
			// that says so, and a screen reader that read it aloud would be
			// announcing a phrase with nothing behind it.
			//
			// A pageless item with a link of its own says its own words —
			// see inc/simple-editor.php. How it is drawn is the block's
			// one Link style setting, the same for every card.
			$card_cue_text  = '' !== $card['item_link_text'] ? $card['item_link_text'] : $read_more_text;
			$card_cue_class = '' !== $link_class ? 'post-card__read-more post-card__read-more--button ' . $link_class : 'post-card__read-more';
			?>
			<span class="<?php echo esc_attr( $card_cue_class ); ?>" aria-hidden="true"><?php echo esc_html( $card_cue_text ); ?></span>
		<?php endif; ?>
	</div>
</article>
