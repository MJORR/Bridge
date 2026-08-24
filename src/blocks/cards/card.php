<?php
/**
 * Single card markup for `bridge/cards`.
 *
 * The card is an <article> with the link on its title, not an <a> wrapped
 * around the whole thing. Wrapping it made the link's accessible name the
 * entire card — title, excerpt and "Read more" read out as one long phrase —
 * and gave a screen-reader user no way to skim a grid by its headings. The
 * click target is still the whole card: the title's link stretches over it
 * with a pseudo-element, which is the same technique `bridge/feature-block`
 * already uses and the reason its docblock argues against the wrapper.
 *
 * Included once per post inside render.php's loop. Each iteration sets up:
 *
 * @var string $permalink         Post permalink.
 * @var string $title             Post title (raw).
 * @var string $excerpt           Trimmed plain-text excerpt.
 * @var int    $thumbnail_id      Featured image attachment ID, or 0 if none.
 * @var string $card_image_sizes  Responsive `sizes` attribute for the image.
 * @var bool   $show_read_more    Whether to render the read-more label.
 * @var string $read_more_text    Read-more label text.
 *
 * @package Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article class="post-card">
	<figure class="post-card__image">
		<?php if ( $thumbnail_id ) : ?>
			<?php
			// wp_get_attachment_image() emits srcset/sizes/width/height so the
			// browser fetches a right-sized file and the slot has no CLS.
			//
			// `sizes` is ours, because only this block knows how wide a card
			// is. `loading` and `decoding` are not: core sets those per image
			// and knows whether this is the page's first, which naming them
			// here overrode — making the top card of an archive lazy, the one
			// image on the page that should not be.
			echo wp_get_attachment_image(
				$thumbnail_id,
				'large',
				false,
				array(
					'sizes' => $card_image_sizes,
				)
			);
			?>
		<?php else : ?>
			<div class="post-card__image-placeholder" aria-hidden="true"></div>
		<?php endif; ?>
	</figure>
	<div class="post-card__body">
		<h3 class="post-card__title">
			<a class="post-card__link" href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
		</h3>
		<p class="post-card__excerpt"><?php echo esc_html( $excerpt ); ?></p>
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
