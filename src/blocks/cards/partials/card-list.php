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
 * ---- What is clickable, and what is not -------------------------------------
 *
 * Three targets: the picture, the title and the cue. Not the row.
 *
 * A grid card stretches its title's link over the whole card, which is right
 * for a card — it is a small object a reader is aiming at as one thing. A list
 * row is not small. It is the full width of the window and as tall as a
 * photograph, and on a phone that is most of the screen: a reader scrolling
 * with a thumb resting anywhere on it opens a post they were only passing.
 * The stretched pseudo-element is switched off for these rows in
 * _card-list.scss, and the three real targets below are what replaces it.
 *
 * Only one of the three is reachable by keyboard, and that is deliberate. The
 * title is the link — it carries the accessible name, and it is the one a
 * screen reader announces and a tab stop lands on. The picture and the cue are
 * the same destination said again for a pointer, so they take
 * `tabindex="-1"` and `aria-hidden="true"`: pressable, and not a second and
 * third stop on the way down a list of ten. That is the same reasoning card.php
 * gives for Tile's badge and Team's button being spans rather than links, from
 * the other direction — those are cues that must not become targets; these are
 * targets that must not become names.
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
	<?php
	/*
	 * A pointer target, not a second name for the post. See the note above.
	 * `aria-hidden` on the anchor takes the whole subtree out of the
	 * accessibility tree, which is what we want — the image inside it is
	 * already `alt=""`, so nothing is lost that a reader was being told.
	 */
	?>
	<a class="post-card__media-link" href="<?php echo esc_url( $card['permalink'] ); ?>" tabindex="-1" aria-hidden="true">
		<?php require __DIR__ . '/image.php'; ?>
	</a>
	<div class="post-card__body">
		<h3 class="post-card__title">
			<a class="post-card__link" href="<?php echo esc_url( $card['permalink'] ); ?>"><?php echo esc_html( $card['title'] ); ?></a>
		</h3>
		<p class="post-card__excerpt"><?php echo esc_html( $card['excerpt'] ); ?></p>
		<?php if ( $show_read_more ) : ?>
			<?php
			// An anchor here where the grid styles draw a span: with the row
			// no longer clickable end to end, the cue has to be a target
			// rather than a note about one. Hidden from assistive technology
			// and out of the tab order for the reason above — the title is
			// the link, and this is that link said again for a thumb.
			?>
			<a class="post-card__read-more" href="<?php echo esc_url( $card['permalink'] ); ?>" tabindex="-1" aria-hidden="true"><?php echo esc_html( $read_more_text ); ?></a>
		<?php endif; ?>
	</div>
</article>
