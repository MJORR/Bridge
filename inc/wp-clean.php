<?php

/**
 * Bridge — head cleanup.
 *
 * WordPress prints a set of discovery links, generator meta and emoji
 * machinery into every front-end response. Each item exists for a real
 * historical reason — Windows Live Writer, XML-RPC clients, feed readers,
 * browsers that could not render an emoji — and none of those reasons apply
 * to the sites this theme builds. What is left is bytes on the critical path,
 * a version number handed to anyone scanning for one, and a canvas-based
 * feature test that runs before the page can settle.
 *
 * Everything here is a removal, and every removal is reversible from one
 * filter: `bridge_clean_head` takes the flag array below, so a build that
 * genuinely needs feeds or oEmbed discovery turns that key back on rather
 * than editing this file.
 *
 * Deliberately *not* removed:
 *
 *   - Version query strings on assets (`?ver=`). Stripping them is a popular
 *     "optimisation" that does nothing for speed and breaks cache busting,
 *     so a CSS change ships to nobody until their cache expires.
 *   - jQuery. This theme does not use it, but plugins do, and dequeuing a
 *     dependency other code declares produces a blank page, not a fast one.
 *   - Speculation rules and the block-template skip link. The first is a
 *     performance feature; the second is an accessibility requirement.
 *   - `global-styles` and the per-block inline CSS. That is the design
 *     system rendering itself.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Which pieces of core's head output this build strips.
 *
 * Keys are stable; a build overrides them through the `bridge_clean_head`
 * filter rather than by editing the defaults, so a theme update cannot
 * silently re-enable something a site turned off (or vice versa).
 *
 *   emoji       The emoji detection script, its inline styles and the
 *               feed/e-mail fallbacks. ~3KB of JS plus a canvas feature test
 *               on every page load, to render characters every browser this
 *               theme supports already draws natively.
 *   generator   `<meta name="generator" content="WordPress x.y">`, and the
 *               same version string wherever else core emits it.
 *   feeds       RSS/Atom `<link rel="alternate">` tags — the site feed and
 *               the per-post, per-category, per-comment extras. This one is
 *               not a constant: it follows the "Show RSS feed links" switch
 *               on the Theme Options screen (Templates → Site output), which
 *               ships off. A build with a real blog turns it on there rather
 *               than in code.
 *   rsd         The RSD/XML-RPC discovery link and the `X-Pingback` header.
 *               Advertises an endpoint nothing in this stack uses.
 *   shortlink   `<link rel="shortlink">` and its `Link:` header twin.
 *   rest_link   The REST API discovery link and header. This does not disable
 *               the REST API — the editor and this theme's own options screen
 *               depend on it. It removes only the advertisement.
 *   oembed      oEmbed discovery links, which let *other* sites embed this
 *               one. Embedding other sites into this one is unaffected.
 *   adjacent    `rel="next"` / `rel="prev"` post links.
 *
 * @return array<string, bool>
 */
function bridge_clean_head_flags(): array
{
	$flags = array(
		'emoji'     => true,
		'generator' => true,
		// Inverted deliberately: the operator's switch reads "show the feed
		// links", and this array asks "strip them?". Storing the operator-
		// facing sense in the token and flipping it here keeps the options
		// screen from having a switch whose ON state means less output.
		'feeds'     => ! bridge_site_feeds_enabled(),
		'rsd'       => true,
		'shortlink' => true,
		'rest_link' => true,
		'oembed'    => true,
		'adjacent'  => true,
	);

	/**
	 * Filters which parts of the WordPress head Bridge strips.
	 *
	 * @param array<string, bool> $flags Cleanup key => whether to strip it.
	 */
	return (array) apply_filters('bridge_clean_head', $flags);
}

/**
 * Does this site advertise its RSS feeds?
 *
 * Reads the `site.feeds` token, so the answer is one operator switch rather
 * than a code edit per client. Guarded on the token layer existing at all:
 * this file is deliberately self-contained enough to drop into a build
 * without one, and a missing design system should mean "clean", not "fatal".
 *
 * Note that this governs *discovery* only — the `<link rel="alternate">`
 * tags. `/feed/` keeps serving either way; suppressing the endpoint itself
 * is a redirect, not a head cleanup, and it belongs somewhere it can be seen.
 */
function bridge_site_feeds_enabled(): bool
{
	if (! function_exists('bridge_get_tokens')) {
		return false;
	}

	$tokens = bridge_get_tokens();

	return ! empty($tokens['site']['feeds']);
}

/**
 * Is this cleanup enabled for the current build?
 *
 * An unknown key is false. A filter that drops a key is asking for core's
 * behaviour back, which is the safe reading of an ambiguous answer.
 */
function bridge_clean_head_enabled(string $key): bool
{
	$flags = bridge_clean_head_flags();

	return ! empty($flags[$key]);
}

/**
 * Unhook the head output this build does not need.
 *
 * Runs on `init` rather than at file load: the flags are filterable, and a
 * plugin or child theme registering that filter needs to have loaded first.
 * `init` is still comfortably before `wp_head` fires.
 *
 * `remove_action()` on a callback core no longer registers is a harmless
 * no-op, which is what makes the back-compat entries below safe to keep.
 */
function bridge_clean_wp_head(): void
{
	if (bridge_clean_head_enabled('emoji')) {
		bridge_clean_emoji();
	}

	if (bridge_clean_head_enabled('generator')) {
		remove_action('wp_head', 'wp_generator');
		// wp_generator() is only one of the callers. The filter catches the
		// version string in feeds and the RSD document too.
		add_filter('the_generator', '__return_empty_string');
	}

	if (bridge_clean_head_enabled('feeds')) {
		remove_action('wp_head', 'feed_links', 2);
		remove_action('wp_head', 'feed_links_extra', 3);
	}

	if (bridge_clean_head_enabled('rsd')) {
		remove_action('wp_head', 'rsd_link');
		// Removed from core in 6.7; still registered on older installs.
		remove_action('wp_head', 'wlwmanifest_link');
	}

	if (bridge_clean_head_enabled('shortlink')) {
		remove_action('wp_head', 'wp_shortlink_wp_head', 10);
		remove_action('template_redirect', 'wp_shortlink_header', 11);
	}

	if (bridge_clean_head_enabled('rest_link')) {
		remove_action('wp_head', 'rest_output_link_wp_head', 10);
		remove_action('template_redirect', 'rest_output_link_header', 11);
	}

	if (bridge_clean_head_enabled('oembed')) {
		// Core registers the discovery links twice — once at priority 4 and
		// once at the default 10, the second as a back-compat shim it unhooks
		// itself on first run. Both have to go, or the shim prints them.
		remove_action('wp_head', 'wp_oembed_add_discovery_links', 4);
		remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
		remove_action('wp_head', 'wp_oembed_add_host_js');
	}

	if (bridge_clean_head_enabled('adjacent')) {
		remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10);
	}
}
add_action('init', 'bridge_clean_wp_head');

/**
 * Tear out the emoji subsystem.
 *
 * Core spreads this across seven hooks and three contexts (page, embed,
 * admin) plus three content filters that rewrite emoji characters into
 * `<img>` tags for feeds and e-mail. Removing the script but leaving the
 * styles — the usual half-done version of this snippet — still ships the
 * inline CSS and still leaves `img.emoji` rules for images that will never
 * be printed.
 *
 * The `wp_enqueue_emoji_styles` / `print_emoji_styles` pair is one mechanism
 * across two core generations: the newer callback unhooks the older one when
 * it runs, so removing only the newer resurrects the older. Both come out.
 */
function bridge_clean_emoji(): void
{
	remove_action('wp_head', 'print_emoji_detection_script', 7);
	remove_action('embed_head', 'print_emoji_detection_script');
	remove_action('admin_print_scripts', 'print_emoji_detection_script');

	remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');
	remove_action('enqueue_embed_scripts', 'wp_enqueue_emoji_styles');
	remove_action('wp_print_styles', 'print_emoji_styles');
	remove_action('admin_print_styles', 'print_emoji_styles');

	remove_filter('the_content_feed', 'wp_staticize_emoji');
	remove_filter('comment_text_rss', 'wp_staticize_emoji');
	remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

	// Keeps the classic editor from loading the emoji TinyMCE plugin, which
	// would pull the same detection script back in through the editor iframe.
	add_filter('tiny_mce_plugins', 'bridge_clean_emoji_tinymce_plugin');
}

/**
 * Drop the emoji plugin from the TinyMCE plugin list.
 *
 * @param mixed $plugins Registered TinyMCE plugins.
 * @return array<int, string>
 */
function bridge_clean_emoji_tinymce_plugin($plugins): array
{
	if (! is_array($plugins)) {
		return array();
	}

	return array_values(array_diff($plugins, array('wpemoji')));
}

/**
 * Strip the `X-Pingback` header.
 *
 * The RSD link is the discoverable half of XML-RPC pingbacks; this is the
 * other half, and removing one without the other just moves the
 * advertisement from the body to the response headers.
 *
 * This does not disable XML-RPC — that is a host-level decision with real
 * consequences for mobile apps and Jetpack, and it does not belong in a
 * theme.
 *
 * @param array<string, string> $headers Response headers.
 * @return array<string, string>
 */
function bridge_clean_headers(array $headers): array
{
	if (bridge_clean_head_enabled('rsd')) {
		unset($headers['X-Pingback']);
	}

	return $headers;
}
add_filter('wp_headers', 'bridge_clean_headers');
