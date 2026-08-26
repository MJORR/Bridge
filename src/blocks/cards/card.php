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
 * @var int    $placeholder_logo_id Light logo shown when there is no featured
 *                                image, or 0 to draw the neutral pattern.
 * @var string $card_loading      'lazy' for a card that cannot be on screen
 *                                yet, or '' to leave the decision to core.
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
			// is. `decoding` is not, and neither is `loading` for the first
			// row of cards: core sets those per image and knows whether this
			// is the page's first, which naming them here overrode — making
			// the top card of an archive lazy, the one image on the page that
			// should not be.
			//
			// Past the first row it is the other way round. Core counts images
			// in source order and cannot see that a carousel's later cards are
			// off to the side, so it loads them eagerly; only this file knows
			// how many cards are in view. An empty `loading` is dropped from
			// the attribute list below rather than printed, which leaves core
			// holding the decision exactly as before.
			$image_attr = array( 'sizes' => $card_image_sizes );

			if ( '' !== $card_loading ) {
				$image_attr['loading'] = $card_loading;
			}

			echo wp_get_attachment_image( $thumbnail_id, 'large', false, $image_attr );
			?>
		<?php elseif ( $placeholder_logo_id ) : ?>
			<?php
			// The site's mark, on the brand's primary colour. Decorative, and
			// aria-hidden with it: the card is already named by its title, and
			// a screen reader announcing the site's logo on every post without
			// a photograph would be reading out furniture.
			//
			// `medium` rather than the full file: it is drawn inside a tile a
			// few hundred pixels wide at most, and a wordmark uploaded at
			// 2000px would otherwise be downloaded in full for every card.
			?>
			<div class="post-card__image-placeholder post-card__image-placeholder--logo" aria-hidden="true">
				<?php
				// The same rule for the stand-in. It is the same file on every
				// card that wants it, so the browser fetches it once whatever
				// this says — but a card off the side of a carousel has no
				// business being on the critical path either way.
				$logo_attr = array(
					'class' => 'post-card__placeholder-logo',
					'alt'   => '',
				);

				if ( '' !== $card_loading ) {
					$logo_attr['loading'] = $card_loading;
				}

				echo wp_get_attachment_image( $placeholder_logo_id, 'medium', false, $logo_attr );
				?>
			</div>
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
