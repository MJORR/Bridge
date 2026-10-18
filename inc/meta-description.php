<?php

/**
 * Bridge — the per-page meta description.
 *
 * One field on a post or a page, and one `<meta name="description">` in the
 * head of that entry when it is filled in.
 *
 * ---- Why the theme carries this at all -------------------------------------
 *
 * Because nothing else on this site does. inc/schema.php publishes who the
 * organisation is, and says in its own header that a page's own description is
 * the page's job — which was true and left the job undone: there was no field
 * for it and no tag in the head, so every snippet in every search result was
 * whatever Google chose to lift out of the page. That is a reasonable default
 * and a poor one for a home page or a service page, where the first two lines
 * of the copy are rarely the sentence you would have written for a stranger.
 *
 * An SEO plugin would also do this, and rather more. If one is ever installed
 * it will print its own description tag, so this one stands aside — see the
 * `wp_head` guard below.
 *
 * ---- What it does not do ---------------------------------------------------
 *
 * It invents nothing. An entry with the field left empty prints no tag, rather
 * than falling back to the excerpt or to the first paragraph — a truncated
 * sentence with no full stop is a worse snippet than the one a search engine
 * would have assembled, and a fallback that is usually wrong is a field nobody
 * can tell is empty.
 *
 * It is one tag, not a set. Open Graph and Twitter cards are a separate job
 * with separate fields — an image, a title, a type — and a description tag
 * quietly doubling as three is how a theme ends up owning a feature nobody
 * asked it for.
 *
 * @package Bridge
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The meta key the field is stored under.
 *
 * Prefixed but not underscore-prefixed: `show_in_rest` is what the editor
 * panel reads and writes it through, and a protected key — one starting with
 * an underscore — is not exposed to REST without an `auth_callback` fight that
 * buys nothing here. The value is a plain sentence an editor typed.
 */
const BRIDGE_META_DESCRIPTION_KEY = 'bridge_meta_description';

/**
 * The length the field is held to.
 *
 * Google truncates a snippet at around 155–160 characters on desktop and less
 * on a phone, so anything past this is a sentence written to be cut off. The
 * panel counts down to it and this is the backstop, applied on the way in so
 * the stored value is the published one — a field that accepts 400 characters
 * and prints 160 is a field that lies about what it did with them.
 */
const BRIDGE_META_DESCRIPTION_MAX = 160;

/**
 * The post types that get the field.
 *
 * Posts and pages, which are the two an editor writes and the two that turn up
 * in a search result as themselves. The theme's own types are either not
 * public or are shown through a band on a page rather than at a URL of their
 * own, so a description on one would be a tag nobody ever sees.
 *
 * Filterable, because "which types" is exactly the kind of thing a particular
 * build has a different answer to, and the alternative is editing this file.
 *
 * @return string[]
 */
function bridge_meta_description_post_types(): array
{
	return (array) apply_filters(
		'bridge_meta_description_post_types',
		array('post', 'page')
	);
}

/**
 * Register the field, once per post type.
 *
 * `single` and `show_in_rest` are what make it a thing the block editor can
 * bind a control to with `useEntityProp` — the same route the Title Banner
 * panel takes for its three. The sanitiser is the one place the length is
 * enforced, so REST, the editor and anything else writing the key all land on
 * the same stored value.
 *
 * `auth_callback` asks the capability the *post type* uses rather than a fixed
 * one: `edit_posts` on a page is the wrong question, and core's default for an
 * unprotected key is `edit_posts` flat.
 */
function bridge_register_meta_description(): void
{
	foreach (bridge_meta_description_post_types() as $post_type) {
		$object = get_post_type_object($post_type);

		if (! $object) {
			continue;
		}

		$capability = $object->cap->edit_posts ?? 'edit_posts';

		register_post_meta(
			$post_type,
			BRIDGE_META_DESCRIPTION_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'bridge_sanitize_meta_description',
				'auth_callback'     => static function () use ($capability): bool {
					return current_user_can($capability);
				},
			)
		);
	}
}
add_action('init', 'bridge_register_meta_description');

/**
 * A description, cleaned up and cut to length.
 *
 * Tags out, entities decoded, runs of whitespace — newlines included, since the
 * control is a textarea — collapsed to single spaces. A description is one
 * sentence of plain prose by the time it reaches an attribute, and everything
 * here is about getting it there rather than about safety: escaping happens at
 * the point of output, where the context is known.
 *
 * The cut is on words rather than characters. Truncating mid-word publishes
 * half of one, which reads as a fault in the page rather than as a snippet that
 * ran long.
 *
 * @param mixed $value Raw field value.
 * @return string The description as it will be stored and printed.
 */
function bridge_sanitize_meta_description($value): string
{
	$text = wp_strip_all_tags((string) $value);
	$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
	$text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

	if ('' === $text || mb_strlen($text) <= BRIDGE_META_DESCRIPTION_MAX) {
		return $text;
	}

	$cut  = mb_substr($text, 0, BRIDGE_META_DESCRIPTION_MAX);
	$last = mb_strrpos($cut, ' ');

	// Unless there is no space to cut at — one very long word, which is a URL
	// or a mistake, and either way the character cut is the only one available.
	return rtrim($last ? mb_substr($cut, 0, $last) : $cut, " ,;:-–—");
}

/**
 * The description for the entry being viewed, or an empty string.
 *
 * Singular views only, and only the types the field was registered for. An
 * archive, the blog index and a search results page each show many entries and
 * have no one description to give; the front page is included when it is a
 * static page, because then it is an entry like any other.
 *
 * @return string
 */
function bridge_get_meta_description(): string
{
	if (! is_singular(bridge_meta_description_post_types())) {
		return '';
	}

	$post_id = get_queried_object_id();

	if (! $post_id) {
		return '';
	}

	$value = (string) get_post_meta($post_id, BRIDGE_META_DESCRIPTION_KEY, true);

	/**
	 * The description, before it is printed.
	 *
	 * Where a build supplies its own — generated from a field of its own, or
	 * lifted from the excerpt, which this theme deliberately does not do. An
	 * empty string prints no tag at all.
	 *
	 * @param string $value   The stored description.
	 * @param int    $post_id The entry being viewed.
	 */
	return (string) apply_filters('bridge_meta_description', $value, $post_id);
}

/**
 * Print it.
 *
 * At priority 1, so it lands near the top of the head with the charset and the
 * title rather than after a page's worth of enqueued assets — the tag is read
 * by crawlers that stop early, and it costs nothing to be early.
 *
 * ---- Standing aside for a plugin -------------------------------------------
 *
 * Yoast, Rank Math, SEOPress and All in One each print a description of their
 * own from a field of their own, and two description tags in one head is a
 * page telling a crawler two different things. The theme's is the one that
 * gives way: the plugin is the more specific tool and the one an editor will
 * have been filling in. A constant is the cheapest reliable test for each of
 * them, and it is checked at output rather than at load because a plugin may
 * be activated after the theme is.
 */
function bridge_print_meta_description(): void
{
	$seo_plugins = array(
		'WPSEO_VERSION',           // Yoast SEO.
		'RANK_MATH_VERSION',       // Rank Math.
		'SEOPRESS_VERSION',        // SEOPress.
		'AIOSEO_VERSION',          // All in One SEO.
	);

	foreach ($seo_plugins as $constant) {
		if (defined($constant)) {
			return;
		}
	}

	$description = bridge_get_meta_description();

	if ('' === $description) {
		return;
	}

	printf(
		'<meta name="description" content="%s">' . "\n",
		esc_attr($description)
	);
}
add_action('wp_head', 'bridge_print_meta_description', 1);
