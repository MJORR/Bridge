<?php

/**
 * The WordPress functions the token layer touches, and nothing else.
 *
 * ---- Why there is no WordPress here --------------------------------------
 *
 * The obvious way to test a theme is the WordPress test suite: a database, a
 * fixture install, `WP_UnitTestCase`. That is the right tool for code whose
 * behaviour *is* WordPress behaviour — a block that queries posts, a template
 * that resolves parts.
 *
 * The token layer is not that code. `bridge_sanitize_tokens()` takes an array
 * and returns an array. `bridge_contrast_ratio()` takes two colours and
 * returns a number. `bridge_button_schemes()` turns a palette into a set of
 * colours with the WCAG arithmetic behind them. None of it queries anything,
 * and all of it is the part where a regression ships wrong colours to every
 * client site without anybody noticing. Tests for it should run in a second,
 * on any machine, with no database — so they get run.
 *
 * What that costs is stated plainly below, because a test double that quietly
 * disagrees with the real function is worse than no test at all.
 *
 * ---- What these doubles are and are not ----------------------------------
 *
 * `sanitize_hex_color()` and `sanitize_key()` are copied from core, because
 * their exact behaviour is load-bearing: the sanitiser leans on hex validation
 * to guarantee that every colour downstream is `#rrggbb`, and the contrast
 * maths would silently produce black for anything it let through.
 *
 * The rest are honest simplifications, and the tests are written so that
 * nothing depends on their detail:
 *
 *   - `sanitize_text_field()` here trims and strips tags. Core does more.
 *     No test asserts on the difference.
 *   - `esc_url_raw()` here is a pass-through, and **no test asserts that a
 *     `javascript:` URL is rejected**. That guarantee is core's, and a test
 *     that "proved" it against this file would be testing this file. The
 *     theme's own decision — that the CTA URL goes through `esc_url_raw()` at
 *     all — is visible in inc/tokens.php and is not something a unit test can
 *     add confidence to.
 *   - `apply_filters()` returns its value untouched, so every test sees the
 *     unfiltered defaults. Filtered behaviour is a separate concern and would
 *     need its own doubles.
 *
 * @package Bridge
 */

declare(strict_types=1);

// ---- Hooks -----------------------------------------------------------------
//
// The theme's files register hooks as they load. Nothing here dispatches them:
// the functions under test are called directly, and a hook system would only
// add a way for one test to leak into the next.

if (! function_exists('add_action')) {
	function add_action(...$args): bool
	{
		return true;
	}
}

if (! function_exists('add_filter')) {
	function add_filter(...$args): bool
	{
		return true;
	}
}

if (! function_exists('do_action')) {
	function do_action(...$args): void
	{
	}
}

if (! function_exists('apply_filters')) {
	/**
	 * Returns the value unfiltered — see the note at the top of this file.
	 */
	function apply_filters(string $tag, $value, ...$args)
	{
		return $value;
	}
}

// ---- Translation -----------------------------------------------------------

if (! function_exists('__')) {
	function __(string $text, string $domain = 'default'): string
	{
		return $text;
	}
}

if (! function_exists('esc_html__')) {
	function esc_html__(string $text, string $domain = 'default'): string
	{
		return $text;
	}
}

// ---- Sanitisers ------------------------------------------------------------

if (! function_exists('sanitize_hex_color')) {
	/**
	 * Core's implementation, copied deliberately.
	 *
	 * Every colour in the token record passes through this, and the promise it
	 * makes — a leading hash and three or six hex digits, or null — is what the
	 * contrast maths and the compiled CSS both assume.
	 */
	function sanitize_hex_color(string $color): ?string
	{
		if ('' === $color) {
			return '';
		}

		return preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', $color) ? $color : null;
	}
}

if (! function_exists('sanitize_key')) {
	/** Core's implementation. */
	function sanitize_key(string $key): string
	{
		return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
	}
}

if (! function_exists('sanitize_text_field')) {
	/** A simplification. No test depends on the difference — see the top. */
	function sanitize_text_field(string $str): string
	{
		return trim(strip_tags($str));
	}
}

if (! function_exists('sanitize_textarea_field')) {
	/** As above, and for the same reason: trimmed, with tags removed. */
	function sanitize_textarea_field(string $str): string
	{
		return trim(strip_tags($str));
	}
}

if (! function_exists('_n')) {
	/**
	 * Singular or plural, untranslated.
	 *
	 * Enough for the spam report, whose plural forms are only ever read back as
	 * the reason attached to a held enquiry.
	 */
	function _n(string $single, string $plural, int $number, string $domain = 'default'): string
	{
		return 1 === $number ? $single : $plural;
	}
}

if (! function_exists('is_email')) {
	/**
	 * Core's shape check, reduced to the part the enquiry validator relies on:
	 * something, an @, something with a dot in it. Deliberately *stricter* than
	 * a pass-through would be, because a test that accepted `not-an-address` as
	 * valid would prove the opposite of what it claims.
	 */
	function is_email(string $email)
	{
		return (bool) preg_match('/^[^@\s]+@[^@\s.]+(\.[^@\s.]+)+$/', $email) ? $email : false;
	}
}

if (! function_exists('wp_hash')) {
	/**
	 * A real HMAC against a fixed salt.
	 *
	 * Copied in shape rather than simplified: the signed clock in
	 * inc/enquiries.php is only worth testing if the signature is genuinely one,
	 * and a pass-through here would let a tampered stamp verify.
	 */
	function wp_hash(string $data, string $scheme = 'auth'): string
	{
		return hash_hmac('md5', $data, 'bridge-test-salt-' . $scheme);
	}
}

if (! function_exists('esc_url_raw')) {
	/** A pass-through. No test depends on it — see the top. */
	function esc_url_raw(string $url): string
	{
		return trim($url);
	}
}

// ---- Options ---------------------------------------------------------------
//
// An in-memory store, so the round trip through bridge_update_tokens() and
// bridge_get_tokens() can be exercised without a database. Reset between tests
// by BridgeTestCase.

if (! function_exists('get_option')) {
	function get_option(string $option, $default = false)
	{
		return $GLOBALS['bridge_test_options'][$option] ?? $default;
	}
}

if (! function_exists('update_option')) {
	function update_option(string $option, $value, $autoload = null): bool
	{
		$existing = $GLOBALS['bridge_test_options'][$option] ?? null;

		if ($existing === $value) {
			return false;
		}

		$GLOBALS['bridge_test_options'][$option] = $value;

		return true;
	}
}

// ---- Files and encoding ----------------------------------------------------

if (! function_exists('get_theme_file_path')) {
	function get_theme_file_path(string $file = ''): string
	{
		return dirname(__DIR__) . ('' !== $file ? '/' . ltrim($file, '/') : '');
	}
}

if (! function_exists('wp_json_file_decode')) {
	function wp_json_file_decode(string $filename, array $options = array())
	{
		$decoded = json_decode(
			(string) file_get_contents($filename),
			! empty($options['associative'])
		);

		return JSON_ERROR_NONE === json_last_error() ? $decoded : null;
	}
}

if (! function_exists('wp_json_encode')) {
	function wp_json_encode($data, int $options = 0, int $depth = 512)
	{
		return json_encode($data, $options, $depth);
	}
}

if (! function_exists('esc_url')) {
	/** Pass-through: no test asserts on escaping, only on the value carried. */
	function esc_url(string $url): string
	{
		return $url;
	}
}

if (! function_exists('wp_get_attachment_url')) {
	/**
	 * A predictable URL for any non-zero id.
	 *
	 * The mask helper only asks whether there is a shape and what to point at;
	 * which attachment it is is WordPress's business, not this suite's.
	 */
	function wp_get_attachment_url(int $id)
	{
		return $id > 0 ? "https://example.test/mask-{$id}.svg" : false;
	}
}

// ---- Site identity and the current request ---------------------------------
//
// The schema layer asks WordPress two kinds of question: what the site is
// called and where it lives, and what page is being viewed. Neither has any
// arithmetic in it, so these doubles are the smallest thing that answers
// truthfully — each reads a global the test sets, and defaults to the state of
// a site nobody has configured. That is the state most of the assertions are
// about: a theme that publishes an empty phone number or a one-step trail is
// the regression worth catching.

if (! function_exists('home_url')) {
	function home_url(string $path = ''): string
	{
		return 'https://example.test' . ('' === $path ? '' : $path);
	}
}

if (! function_exists('get_bloginfo')) {
	function get_bloginfo(string $show = '', string $filter = 'raw'): string
	{
		$info = array_merge(
			array(
				'name'        => 'Example Site',
				'description' => 'Just another site',
				'language'    => 'en-GB',
			),
			(array) ($GLOBALS['bridge_test_bloginfo'] ?? array())
		);

		return (string) ($info[$show] ?? '');
	}
}

if (! function_exists('get_theme_mod')) {
	function get_theme_mod(string $name, $default = false)
	{
		return $GLOBALS['bridge_test_theme_mods'][$name] ?? $default;
	}
}

if (! function_exists('wp_get_attachment_image_src')) {
	/** A predictable 512×256 image for any non-zero id, false for none. */
	function wp_get_attachment_image_src(int $id, $size = 'thumbnail')
	{
		return $id > 0
			? array("https://example.test/logo-{$id}.png", 512, 256, false)
			: false;
	}
}

if (! function_exists('esc_html')) {
	/** Pass-through: the assertions are about which words are carried. */
	function esc_html(string $text): string
	{
		return $text;
	}
}

if (! function_exists('esc_attr')) {
	function esc_attr(string $text): string
	{
		return $text;
	}
}

if (! function_exists('esc_attr__')) {
	function esc_attr__(string $text, string $domain = 'default'): string
	{
		return $text;
	}
}

if (! function_exists('wp_strip_all_tags')) {
	function wp_strip_all_tags(string $text, bool $remove_breaks = false): string
	{
		return trim(strip_tags($text));
	}
}

// The current request, as four booleans and a post id. `bridge_test_view` is
// the whole of it: a test sets `singular` or `archive`, and everything else
// answers no.

if (! function_exists('is_front_page')) {
	function is_front_page(): bool
	{
		return 'front' === ($GLOBALS['bridge_test_view'] ?? 'front');
	}
}

if (! function_exists('is_singular')) {
	function is_singular($types = ''): bool
	{
		return 'singular' === ($GLOBALS['bridge_test_view'] ?? 'front');
	}
}

if (! function_exists('is_archive')) {
	function is_archive(): bool
	{
		return 'archive' === ($GLOBALS['bridge_test_view'] ?? 'front');
	}
}

if (! function_exists('is_search')) {
	function is_search(): bool
	{
		return 'search' === ($GLOBALS['bridge_test_view'] ?? 'front');
	}
}

if (! function_exists('get_the_ID')) {
	function get_the_ID()
	{
		return $GLOBALS['bridge_test_post_id'] ?? 0;
	}
}

if (! function_exists('get_post_ancestors')) {
	/** Nearest parent first, which is the order core returns them in. */
	function get_post_ancestors($post): array
	{
		return (array) ($GLOBALS['bridge_test_ancestors'] ?? array());
	}
}

if (! function_exists('get_the_title')) {
	function get_the_title($post = 0): string
	{
		return (string) ($GLOBALS['bridge_test_titles'][(int) $post] ?? '');
	}
}

if (! function_exists('get_permalink')) {
	function get_permalink($post = 0, bool $leavename = false)
	{
		return 'https://example.test/?p=' . (int) $post;
	}
}

if (! function_exists('get_the_archive_title')) {
	function get_the_archive_title(): string
	{
		return (string) ($GLOBALS['bridge_test_archive_title'] ?? 'Archives');
	}
}

if (! function_exists('get_search_query')) {
	function get_search_query(bool $escaped = true): string
	{
		return (string) ($GLOBALS['bridge_test_search'] ?? '');
	}
}

// ---- Blocks ----------------------------------------------------------------
//
// Enough of a WP_Block to hand `bridge_prime_item_images()` a band with items
// in it. The real class carries a parsed block, its context and a renderer;
// what the priming walks is the two properties below, so those are what this
// has. Nothing here renders anything.

if (! function_exists('absint')) {
	function absint($maybeint): int
	{
		return abs((int) $maybeint);
	}
}

if (! function_exists('_prime_post_caches')) {
	/**
	 * A spy, not a double: there is no object cache here to warm, so what a
	 * test can assert is that the theme asked for the right ids once.
	 */
	function _prime_post_caches(array $ids, bool $update_term_cache = true, bool $update_meta_cache = true): void
	{
		$GLOBALS['bridge_test_primed'][] = array(
			'ids'   => $ids,
			'terms' => $update_term_cache,
			'meta'  => $update_meta_cache,
		);
	}
}

if (! class_exists('WP_Block')) {
	class WP_Block
	{
		/** @var string */
		public $name;

		/** @var array<string, mixed> */
		public $attributes;

		/** @var array<int, WP_Block> */
		public $inner_blocks;

		/**
		 * @param string               $name
		 * @param array<string, mixed> $attributes
		 * @param array<int, WP_Block> $inner_blocks
		 */
		public function __construct(string $name = '', array $attributes = array(), array $inner_blocks = array())
		{
			$this->name         = $name;
			$this->attributes   = $attributes;
			$this->inner_blocks = $inner_blocks;
		}
	}
}
