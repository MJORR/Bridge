<?php
/**
 * Split row — one post in a list.
 *
 * The photograph on one side, the words on the other, one row to a line down
 * the band. The same record every other style draws from and the same fields
 * Summary draws: a heading that carries the link, the post's own words, and a
 * read-more cue. What differs is the arrangement, and that is entirely CSS —
 * which side the picture is on, how wide it is, whether it is rounded, whether
 * the row is tinted. None of it is decided here.
 *
 * ---- Why this is not four partials ----------------------------------------
 *
 * A list row is a Summary card turned sideways, so it would be reasonable to
 * expect Tile and Cover to have list forms too. They do not, and the reason is
 * in card.php: those two are built around a photograph that fills the card, and
 * a third of a wide row is not that. One row markup, one stylesheet.
 *
 * ---- The image, and what it is not ----------------------------------------
 *
 * `image.php` is shared with the grid styles, so the frame, the placeholder and
 * the srcset arithmetic are the same code — a list is not a second answer to
 * "what happens when a post has no featured image". The badge is off for the
 * same reason Summary has it off: the chip belongs to Tile.
 *
 * @var array  $card           The card record. See bridge_card_data_defaults().
 * @var bool   $show_read_more Whether to draw the read-more cue.
 * @var string $read_more_text Its wording.
 *
 * @package Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// No chip on a list row. See image.php.
$card_show_badge = false;
?>
<article class="post-card post-card--list">
	<?php require __DIR__ . '/image.php'; ?>
	<div class="post-card__body">
		<h3 class="post-card__title">
			<a class="post-card__link" href="<?php echo esc_url( $card['permalink'] ); ?>"><?php echo esc_html( $card['title'] ); ?></a>
		</h3>
		<?php
		/*
		 * The role line, where the post type has one.
		 *
		 * Summary has no room for it and Team makes it the point; a split row
		 * has a whole column of space beside the picture, which is where a
		 * project's client or year belongs. Printed only when the field is
		 * filled — an empty line still spends the body's gap.
		 */
		if ( '' !== $card['subtitle'] ) :
			?>
			<p class="post-card__subtitle"><?php echo esc_html( $card['subtitle'] ); ?></p>
		<?php endif; ?>
		<p class="post-card__excerpt"><?php echo esc_html( $card['excerpt'] ); ?></p>
		<?php if ( $show_read_more ) : ?>
			<?php
			// Decorative. The title above is the link; this is the visual cue
			// that says so, and a screen reader that read it aloud would be
			// announcing a phrase with nothing behind it.
			?>
			<span class="post-card__read-more" aria-hidden="true"><?php echo esc_html( $read_more_text ); ?></span>
		<?php endif; ?>
	</div>
</article>
