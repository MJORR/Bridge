<?php
/**
 * Server-side render for `bridge/breadcrumb`.
 *
 * The trail, and the BreadcrumbList that describes it, printed together.
 *
 * Together rather than from a `wp_head` hook, and that is the whole design of
 * this block. Structured data is a claim about what is on the page, so the
 * only safe place to make it is beside the thing it is describing: an operator
 * who deletes this block from a template takes the markup and the claim with
 * it in one action, and there is no state in which a search engine is told
 * about a trail no visitor can see. `bridge/testimonial` prints its Review the
 * same way.
 *
 * Both halves come from `bridge_breadcrumb_items()`, so the words in the
 * <nav> and the words in the JSON-LD are the same words by construction.
 *
 * Nothing renders on the front page, or anywhere the trail would be a single
 * step: see the note in bridge_breadcrumb_items().
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

// The block's own wrapper attributes go on the <nav> itself rather than on a
// div around it — a landmark inside a wrapper whose only job is to hold a
// class is one element more than the page needs.
$trail = bridge_breadcrumb_html(
	get_block_wrapper_attributes( array( 'class' => 'bridge-breadcrumb' ) )
);

if ( '' === $trail ) {
	return;
}

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped — the trail is
// assembled from esc_url()'d and esc_html()'d parts; the JSON-LD is encoded for
// its context by bridge_schema_json().
echo $trail;
echo bridge_schema_json( bridge_breadcrumb_schema() );
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
