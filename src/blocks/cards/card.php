<?php
/**
 * One card, in whichever style the block was set to.
 *
 * Included once per post from render.php's loop, which has already built the
 * record — see `bridge_card_data()` in inc/card-data.php — and resolved the
 * style. All this file does is choose a partial, which is the whole reason it
 * still exists: render.php should not grow a three-way branch inside its loop,
 * and the partials should not each have to defend against a style name that
 * arrived from an attribute.
 *
 * ---- What every style shares ----------------------------------------------
 *
 * The card is an <article> with the link on its title, never an <a> wrapped
 * around the whole thing. Wrapping made the link's accessible name the entire
 * card — title, subtitle and button read out as one long phrase — and gave a
 * screen-reader user no way to skim a grid by its headings. The click target is
 * still the whole card: the title's link stretches over it with a
 * pseudo-element, the same technique `bridge/feature-block` uses.
 *
 * That is why Tile's badge and Team's button are `aria-hidden` spans rather
 * than links. They are the visual cue that the card is clickable, not a second
 * destination; a real anchor under a stretched link is unreachable by pointer
 * and a duplicate tab stop for a keyboard.
 *
 * @var array  $card       The card record. See bridge_card_data_defaults().
 * @var string $card_style Style slug from the block's `cardStyle` attribute.
 * @var bool   $is_list    Whether the band is arranged as a list rather than a
 *                         grid. A list draws one partial whatever the card
 *                         style says — see below.
 *
 * @package Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A post type that draws its own card.
 *
 * For a plugin whose content is not a picture and some words — a before and
 * after slider, say — and so fits none of the styles below. Return the card's
 * HTML, escaped, and it is printed in place of the style's; return null and
 * nothing changes. No callback is hooked by the theme, so every existing band
 * is untouched. A type that does this should also be named in
 * `bridge_cards_own_card_post_types`, so the block's sidebar stops offering
 * card options that would change nothing.
 *
 * @param string|null $html      Null, or the card's HTML.
 * @param array       $card      The card record.
 * @param WP_Post     $post      The post the card is for.
 * @param array       $card_args The style and layout the band asked for.
 */
$bridge_own_card = apply_filters(
	'bridge_card_html',
	null,
	$card,
	get_post(),
	array(
		'style'   => $card_style ?? '',
		'is_list' => ! empty( $is_list ),
	)
);

if ( is_string( $bridge_own_card ) ) {
	echo $bridge_own_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — the filter returns escaped HTML.

	return;
}

/**
 * A list draws its own row, and the card style does not enter into it.
 *
 * Deliberately not four styles crossed with two layouts. That is eight markup
 * variants to keep in step for the sake of two that make sense: Tile and Cover
 * are poster shapes built around a photograph filling the card, and neither
 * survives being a third of a wide row — the badge lands on a thumbnail, the
 * wash covers text it was never measured against. So the layout wins outright,
 * and the Card style control is hidden in the inspector while it is set to
 * list rather than left there to mean nothing.
 *
 * The row is a Summary card turned sideways: same record, same fields, same
 * tokens. See _card-list.scss.
 */
if ( ! empty( $is_list ) ) {
	require __DIR__ . '/partials/card-list.php';

	return;
}

// The attribute is sanitized in render.php and again in block.json's enum, but
// this is the line that turns a string into a filesystem path, so it is the one
// that has to be an allowlist rather than a check.
$bridge_card_styles = array( 'summary', 'tile', 'cover', 'team' );
$bridge_card_style  = in_array( $card_style ?? '', $bridge_card_styles, true )
	? $card_style
	: 'summary';

require __DIR__ . '/partials/card-' . $bridge_card_style . '.php';
