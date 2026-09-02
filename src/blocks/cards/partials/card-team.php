<?php
/**
 * Team — a face, a name, a role and a way in.
 *
 * For a grid of people. The avatar is circular and overhangs the panel it sits
 * on, so the arch behind it is the panel's own background rather than a shape
 * drawn on top — which is why this style paints the body and the image slot
 * instead of the card, and why it is the one style whose card is transparent.
 * The whole of that is CSS; see blocks/cards/_card-team.scss.
 *
 * No excerpt. A biography truncated to twenty words is a sentence that stops
 * mid-clause under someone's name, and the field that belongs here is the role
 * — one line, authored, and the thing a visitor is actually scanning for.
 *
 * The button is a span, not a link. The card is already one link, stretched
 * from the name over the whole card; an anchor inside that is unreachable by
 * pointer, because the stretched link covers it, and a second tab stop to the
 * same destination for a keyboard. So this is the affordance without the
 * duplicate — `wp-element-button` so it wears the site's button skin, and the
 * band it lands on points the button variables at the right ground, which is
 * why nothing here names a colour.
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
<article class="post-card post-card--team">
	<?php require __DIR__ . '/image.php'; ?>
	<div class="post-card__body">
		<h3 class="post-card__title">
			<a class="post-card__link" href="<?php echo esc_url( $card['permalink'] ); ?>"><?php echo esc_html( $card['title'] ); ?></a>
		</h3>
		<?php
		// Printed only when the post has one. A post type with no role field
		// gets a name and a button, not a name and an empty line holding the
		// button away from it.
		if ( '' !== $card['subtitle'] ) :
			?>
			<p class="post-card__subtitle"><?php echo esc_html( $card['subtitle'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $card['cta_text'] ) : ?>
			<span class="post-card__cta wp-element-button" aria-hidden="true"><?php echo esc_html( $card['cta_text'] ); ?></span>
		<?php endif; ?>
	</div>
</article>
