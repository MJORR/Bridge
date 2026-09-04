<?php

/**
 * Bridge — test bootstrap.
 *
 * Loads the doubles first, then the theme files under test, in dependency
 * order. Nothing here starts WordPress; see tests/stubs.php for why, and for
 * what that costs.
 *
 * The files are loaded rather than autoloaded because the theme has no
 * autoloader: it is a WordPress theme, its functions are global, and the load
 * order below is the same one functions.php uses.
 *
 * @package Bridge
 */

declare(strict_types=1);

// The guard every theme file opens with. Defining it is what lets them be
// included outside a WordPress request at all.
if (! defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

// Constants functions.php would otherwise define. Only the ones the token
// layer reads.
if (! defined('BRIDGE_VERSION')) {
	define('BRIDGE_VERSION', '1.0.0');
}

if (! defined('BRIDGE_BRAND')) {
	define('BRIDGE_BRAND', 'Bridge');
}

require_once __DIR__ . '/stubs.php';

$bridge_theme_dir = dirname(__DIR__);

require_once $bridge_theme_dir . '/inc/color.php';
require_once $bridge_theme_dir . '/inc/tokens.php';
// bridge_sanitize_tokens() asks this file which post type slugs are reserved
// and how many types are allowed, so the real lists are used rather than a
// double — a test that accepted `page` as a slug would prove nothing.
require_once $bridge_theme_dir . '/inc/post-types.php';
// bridge_sanitize_tokens() validates the font set against the real catalogue
// and the block lists against the real required-blocks list, so both files are
// loaded rather than stubbed — a test that accepted a font set the site would
// reject is a test that proves nothing.
require_once $bridge_theme_dir . '/inc/fonts.php';
require_once $bridge_theme_dir . '/inc/lockdown.php';
require_once $bridge_theme_dir . '/inc/theme-json.php';
// The band mask turns a signed percentage into a colour, which is arithmetic
// worth pinning the same way the button schemes are.
require_once $bridge_theme_dir . '/inc/section-blocks.php';
// The activation seeds. Loaded for bridge_seed_post_types(), which is pure
// token arithmetic — the menu seeding beside it talks to the database and is
// not called from a test.
require_once $bridge_theme_dir . '/inc/activation.php';

// The contact form's server half. Loaded for the three pure pieces of it —
// the validator, the spam score and the signed clock — which are the parts a
// regression would ship as either a form nobody can submit or a form every
// script can. The rest of the file talks to the database and is not called
// from a test.
require_once $bridge_theme_dir . '/inc/enquiries.php';

require_once __DIR__ . '/BridgeTestCase.php';
