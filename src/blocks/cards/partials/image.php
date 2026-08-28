<?php
/**
 * The image slot, for every card style.
 *
 * Three styles crop this differently — Summary and Tile show a 16:9 photograph,
 * Portrait shows a circular avatar — but none of that is decided here. The
 * shape is CSS; what this file owns is the part that has to be PHP: which
 * attachment, at which size, with which loading behaviour, and what to draw
 * when there is no featured image at all.
 *
 * Kept as a partial rather than a function because it is markup, and markup in
 * inc/ is markup nobody looks for. Included from each card partial with the
 * card record already in scope.
 *
 * @var array $card            The card record. See bridge_card_data_defaults().
 * @var bool  $card_show_badge Whether this style draws the badge over the
 *                             image. Tile does; the others do not, and a post
 *                             type that happens to have a `distance` field
 *                             should not sprout chips on a style that was never
 *                             designed around one. Defaults to false, so a
 *                             caller that says nothing gets no badge.
 *
 * @package Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<figure class="post-card__image">
	<?php if ( $card['thumbnail_id'] ) : ?>
		<?php
		// wp_get_attachment_image() emits srcset/sizes/width/height so the
		// browser fetches a right-sized file and the slot has no CLS.
		//
		// `sizes` is ours, because only the block knows how wide a card is.
		// `decoding` is not, and neither is `loading` for the first row of
		// cards: core sets those per image and knows whether this is the page's
		// first, which naming them here overrode — making the top card of an
		// archive lazy, the one image on the page that should not be.
		//
		// Past the first row it is the other way round. Core counts images in
		// source order and cannot see that a carousel's later cards are off to
		// the side, so it loads them eagerly; only render.php knows how many
		// cards are in view. An empty `loading` is dropped from the attribute
		// list rather than printed, which leaves core holding the decision.
		$bridge_image_attr = array( 'sizes' => $card['image_sizes'] );

		if ( '' !== $card['loading'] ) {
			$bridge_image_attr['loading'] = $card['loading'];
		}

		echo wp_get_attachment_image( $card['thumbnail_id'], 'large', false, $bridge_image_attr );
		?>
	<?php elseif ( $card['placeholder_logo_id'] ) : ?>
		<?php
		// The site's mark, on the brand's primary colour. Decorative, and
		// aria-hidden with it: the card is already named by its title, and a
		// screen reader announcing the site's logo on every post without a
		// photograph would be reading out furniture.
		//
		// `medium` rather than the full file: it is drawn inside a tile a few
		// hundred pixels wide at most, and a wordmark uploaded at 2000px would
		// otherwise be downloaded in full for every card.
		?>
		<div class="post-card__image-placeholder post-card__image-placeholder--logo" aria-hidden="true">
			<?php
			// The same rule for the stand-in. It is the same file on every card
			// that wants it, so the browser fetches it once whatever this says
			// — but a card off the side of a carousel has no business being on
			// the critical path either way.
			$bridge_logo_attr = array(
				'class' => 'post-card__placeholder-logo',
				'alt'   => '',
			);

			if ( '' !== $card['loading'] ) {
				$bridge_logo_attr['loading'] = $card['loading'];
			}

			echo wp_get_attachment_image( $card['placeholder_logo_id'], 'medium', false, $bridge_logo_attr );
			?>
		</div>
	<?php else : ?>
		<div class="post-card__image-placeholder" aria-hidden="true"></div>
	<?php endif; ?>
	<?php
	// Tile's chip, drawn over the corner of the photograph. It lives inside the
	// figure because that is what it is positioned against, and it is printed
	// only when the post actually has the field — an empty chip is a coloured
	// rectangle with nothing in it.
	//
	// aria-hidden, like every other decorative cue on a card: the distance is
	// repeated on the page the card links to, and a screen reader reading
	// "1.3 miles" between a photograph and a heading is reading out an orphan.
	if ( ! empty( $card_show_badge ) && '' !== $card['badge'] ) :
		?>
		<span class="post-card__badge" aria-hidden="true"><?php echo esc_html( $card['badge'] ); ?></span>
	<?php endif; ?>
</figure>
