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
 * That is why Tile's badge and Portrait's button are `aria-hidden` spans rather
 * than links. They are the visual cue that the card is clickable, not a second
 * destination; a real anchor under a stretched link is unreachable by pointer
 * and a duplicate tab stop for a keyboard.
 *
 * @var array  $card       The card record. See bridge_card_data_defaults().
 * @var string $card_style Style slug from the block's `cardStyle` attribute.
 *
 * @package Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The attribute is sanitized in render.php and again in block.json's enum, but
// this is the line that turns a string into a filesystem path, so it is the one
// that has to be an allowlist rather than a check.
$bridge_card_styles = array( 'summary', 'tile', 'portrait' );
$bridge_card_style  = in_array( $card_style ?? '', $bridge_card_styles, true )
	? $card_style
	: 'summary';

require __DIR__ . '/partials/card-' . $bridge_card_style . '.php';
