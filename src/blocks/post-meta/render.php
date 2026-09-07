<?php
/**
 * Server-side render for `bridge/post-meta`.
 *
 * The byline, drawn from the token record rather than from three core blocks
 * sitting in the template.
 *
 * ---- Why a block and not three blocks and some CSS -------------------------
 *
 * The obvious alternative was to leave `core/post-date`, `core/post-terms` and
 * `core/post-author-name` in `single.html` and hide the ones an operator had
 * switched off with a class on <body>, which is how the three article layouts
 * work. It does not survive the separators. A hidden element is still a
 * sibling, so `.item + .item::before` draws a slash in front of whatever
 * follows a hidden item — a byline that opens with "/ Uncategorized" whenever
 * the date is off. CSS has no way to ask for the previous *visible* sibling,
 * so the parts an operator turned off have to be absent from the markup, not
 * merely invisible. One block that renders what is on is the way to get that.
 *
 * It also puts the decision in one place. `bridge_post_meta_parts()` is read
 * here, by the block's canvas preview and by the options screen, so what the
 * operator switched, what the editor draws and what a visitor reads are the
 * same three booleans rather than three implementations of them.
 *
 * Nothing renders when all three are off. Deliberately: the byline carries a
 * rule under it, and an empty byline would leave a line across the top of the
 * article separating it from nothing.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes (none).
 * @var string   $content    Inner block HTML (unused).
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bridge_meta = bridge_post_meta_html(
	get_block_wrapper_attributes( array( 'class' => 'bridge-article__meta' ) )
);

if ( '' === $bridge_meta ) {
	return;
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — assembled
// from esc_html()'d and esc_url()'d parts by bridge_post_meta_html().
echo $bridge_meta;
