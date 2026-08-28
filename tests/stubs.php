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
