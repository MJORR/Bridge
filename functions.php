<?php

/**
 * Bridge theme bootstrap.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

define('BRIDGE_VERSION', '1.0.0');

/**
 * Client-facing name for this build.
 *
 * The only editor-visible use of the theme's name (the admin menu
 * category label). Change this and `Theme Name:` in style.css to rebrand a
 * build; the `bridge` prefixes on functions, blocks, meta keys and CSS
 * classes are internal identifiers and must stay put — block namespaces and
 * meta keys are written into post content and postmeta, so renaming them
 * breaks existing content.
 */
define('BRIDGE_BRAND', 'Cape Marketing');

define('BRIDGE_DIST_URI', get_theme_file_uri('dist'));
define('BRIDGE_DIST_PATH', get_theme_file_path('dist'));

/**
 * The design-system control plane.
 *
 * Loaded at theme-load time rather than on a hook: WordPress can resolve
 * theme.json as early as `after_setup_theme`, and the compiler has to be
 * listening before the first resolution or the design system is a request
 * behind. Both files are side-effect-light — they register filters and
 * declare functions, nothing more.
 */
require_once get_theme_file_path('inc/color.php');
require_once get_theme_file_path('inc/tokens.php');
require_once get_theme_file_path('inc/fonts.php');
require_once get_theme_file_path('inc/theme-json.php');
require_once get_theme_file_path('inc/icons.php');
require_once get_theme_file_path('inc/structure.php');
require_once get_theme_file_path('inc/section-blocks.php');
require_once get_theme_file_path('inc/card-data.php');
require_once get_theme_file_path('inc/hero-blocks.php');
require_once get_theme_file_path('inc/header.php');

/**
 * Access control and the editor lockdown.
 *
 * capabilities.php decides who counts as an operator; everything else defers
 * to it, so it loads first.
 */
require_once get_theme_file_path('inc/capabilities.php');
require_once get_theme_file_path('inc/lockdown.php');
require_once get_theme_file_path('inc/svg.php');
require_once get_theme_file_path('inc/site-options.php');
require_once get_theme_file_path('inc/admin-page.php');
require_once get_theme_file_path('inc/rest.php');

require_once get_theme_file_path('inc/cli.php');

/**
 * Front-end head cleanup. Purely subtractive — it registers no output of its
 * own, so it loads last and nothing depends on it.
 */
require_once get_theme_file_path('inc/wp-clean.php');

/**
 * Theme setup: declare feature support and register editor styling.
 */
function bridge_setup(): void
{
	load_theme_textdomain('bridge', get_template_directory() . '/languages');

	add_theme_support('title-tag');
	add_theme_support('post-thumbnails');
	add_theme_support('responsive-embeds');
	add_theme_support('editor-styles');
	add_theme_support('wp-block-styles');
	add_theme_support('html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	));

	add_editor_style('dist/main.css');
}
add_action('after_setup_theme', 'bridge_setup');

/**
 * Enqueue compiled frontend assets from the Vite /dist folder.
 */
function bridge_enqueue_assets(): void
{
	if (bridge_register_style('bridge-style', 'main.css')) {
		wp_enqueue_style('bridge-style');
	}

	if (bridge_register_script('bridge-script', 'main.js', array(), true)) {
		wp_enqueue_script('bridge-script');
	}
}
add_action('wp_enqueue_scripts', 'bridge_enqueue_assets');

/**
 * Register a compiled script from /dist, if the build produced it.
 *
 * Every script this theme loads is a Vite artefact, versioned by its own
 * mtime and skipped when absent — a half-built checkout should degrade, not
 * fatal. That is three lines of ceremony per handle, and it was repeated for
 * every script in the theme until they all drifted apart in small ways.
 *
 * @param string   $handle Script handle.
 * @param string   $file   Filename within /dist.
 * @param string[] $deps   Registered handles this script needs.
 * @param bool     $defer  Defer and load in the footer. Front-end scripts
 *                         want this; editor scripts must not, because the
 *                         block registry has to exist before the editor
 *                         boots.
 * @return bool True when the file existed and the handle was registered.
 */
function bridge_register_script(string $handle, string $file, array $deps = array(), bool $defer = false): bool
{
	$path = BRIDGE_DIST_PATH . '/' . $file;

	if (! file_exists($path)) {
		return false;
	}

	wp_register_script(
		$handle,
		BRIDGE_DIST_URI . '/' . $file,
		$deps,
		(string) filemtime($path),
		$defer ? array('in_footer' => true, 'strategy' => 'defer') : true
	);

	// Every __() in the editor scripts was decorative until this line: without
	// it WordPress never hands the script its translations, so a translated
	// site still saw English labels on every block. Only scripts that asked for
	// wp-i18n get it — a front-end view script would otherwise be given the
	// whole i18n library as a dependency for strings it does not have.
	if (in_array('wp-i18n', $deps, true)) {
		wp_set_script_translations($handle, 'bridge', get_template_directory() . '/languages');
	}

	return true;
}

/**
 * Register a compiled script and enqueue it in one step.
 *
 * @param string   $handle Script handle.
 * @param string   $file   Filename within /dist.
 * @param string[] $deps   Registered handles this script needs.
 */
function bridge_enqueue_script(string $handle, string $file, array $deps = array()): void
{
	if (bridge_register_script($handle, $file, $deps)) {
		wp_enqueue_script($handle);
	}
}

/**
 * Register a compiled stylesheet from /dist, if the build produced it.
 *
 * @param string   $handle Style handle.
 * @param string   $file   Filename within /dist.
 * @param string[] $deps   Registered handles this style needs.
 * @return bool True when the file existed and the handle was registered.
 */
function bridge_register_style(string $handle, string $file, array $deps = array()): bool
{
	$path = BRIDGE_DIST_PATH . '/' . $file;

	if (! file_exists($path)) {
		return false;
	}

	wp_register_style($handle, BRIDGE_DIST_URI . '/' . $file, $deps, (string) filemtime($path));

	return true;
}

/**
 * Register a block from src/blocks, if this build ships it.
 *
 * @param string $slug Directory name under src/blocks.
 */
function bridge_register_block(string $slug): void
{
	$dir = get_theme_file_path('src/blocks/' . $slug);

	if (is_dir($dir) && file_exists($dir . '/block.json')) {
		register_block_type($dir);
	}
}

/**
 * The script handles every block editor entry depends on.
 *
 * @return string[]
 */
function bridge_editor_script_deps(): array
{
	// `wp-data` is in the list because block scripts reach for the data layer —
	// the gallery dispatches `insertBlocks` to turn a multi-select into child
	// blocks. It arrives anyway as a dependency of wp-block-editor, so naming it
	// costs no request; leaving it unnamed just meant the block worked by way of
	// somebody else's dependency.
	return array('wp-blocks', 'wp-block-editor', 'wp-element', 'wp-i18n', 'wp-components', 'wp-data');
}

/**
 * Register custom blocks and their compiled Vite assets.
 *
 * Script and style handles are registered first so that the handles
 * referenced in each block's block.json (editorScript, viewScript, viewStyle)
 * resolve to real, versioned files in /dist.
 */
function bridge_register_blocks(): void
{
	// Blocks that render a preview of themselves through the REST block
	// renderer need the store and the component that talks to it.
	$previewed = array_merge(bridge_editor_script_deps(), array('wp-server-side-render'));

	// The carousel runtime, shared by every block with a swipe layout. One
	// handle, named by both block.json files as their `viewScript`, so WP
	// enqueues it where one of those blocks renders and only once when a page
	// carries both. Nothing needs it to scroll — the row does that in CSS —
	// so it is deferred.
	bridge_register_script('bridge-carousel', 'carousel.js', array(), true);

	// --- Hero Slider -------------------------------------------------------
	bridge_register_script('bridge-hero-slider-editor', 'hero-slider-editor.js', bridge_editor_script_deps());
	bridge_register_script('bridge-hero-slider-view', 'slider.js', array(), true);
	bridge_register_style('bridge-hero-slider-style', 'slider.css');
	bridge_register_block('hero-slider');

	// --- Hero Banner -------------------------------------------------------
	// No view script: the banner is static markup, which is the point of it
	// existing separately from the slider. The stylesheet is registered as
	// `style` rather than `viewStyle` in block.json, so this one handle
	// paints the editor and the front end alike.
	bridge_register_script('bridge-hero-banner-editor', 'hero-banner-editor.js', bridge_editor_script_deps());
	bridge_register_style('bridge-hero-banner-style', 'hero-banner.css');
	bridge_register_block('hero-banner');

	// --- Section blocks ----------------------------------------------------
	// Converted from the old ACF flexible-content layouts. Each registers the
	// same three things under the same three names, so they go through one
	// helper rather than three lines apiece.
	// No stylesheet of its own: its two rules live in main.css beside the
	// section skins they belong with.
	bridge_register_section_block('section', false);
	bridge_register_section_block('map');
	bridge_register_section_block('call-to-action');
	bridge_register_section_block('testimonials');
	bridge_register_section_block('downloads');
	bridge_register_section_block('price-table');
	bridge_register_section_block('feature-blocks');
	bridge_register_section_block('gallery', true, true);
	bridge_register_section_block('logo-slider');
	bridge_register_section_block('alternating-content');
	bridge_register_section_block('split-content');

	// Child blocks render inside their parent and are painted by the parent's
	// stylesheet, so they register a script and a type only.
	foreach (
		array(
			'testimonial',
			'download-item',
			'price-card',
			'feature-block',
			'gallery-item',
			'alternating-row',
		) as $child
	) {
		bridge_register_script('bridge-' . $child . '-editor', $child . '-editor.js', bridge_editor_script_deps());
		bridge_register_block($child);
	}

	// --- Cards -------------------------------------------------------------
	bridge_register_script('bridge-cards-editor', 'cards-editor.js', $previewed);

	/**
	 * The site's excerpt length, for the block's own inspector.
	 *
	 * The block stores 0 for "use the site default", and a control that says
	 * only "site default" leaves an editor guessing what that is. The number
	 * comes from the token record, which is where the operator set it.
	 *
	 * Printed as data rather than fetched: the alternative was a REST round
	 * trip on every editor load for one integer that is already in memory.
	 */
	$card_tokens = bridge_get_tokens();
	wp_add_inline_script(
		'bridge-cards-editor',
		'window.bridgeCards = ' . wp_json_encode(
			array('excerptLength' => (int) ($card_tokens['cards']['excerpt'] ?? 20))
		) . ';',
		'before'
	);

	bridge_register_block('cards');

	// --- Page Title --------------------------------------------------------
	bridge_register_script('bridge-page-title-editor', 'page-title-editor.js', $previewed);
	bridge_register_block('page-title');
}
add_action('init', 'bridge_register_blocks');

/**
 * Register the per-page Title Banner settings as post meta.
 *
 * These drive the `bridge/page-title` block in the default Page template:
 * banner style (hidden/none/contained/fullwidth), title alignment, and the
 * theme-palette colour used for the banner background. Exposed to REST so
 * the "Title Banner" document panel can read and write them.
 *
 * `hidden` suppresses the heading entirely. It is a display choice, not a
 * naming one — the page keeps its title for the menu, the browser tab, search
 * results and the admin list; only the on-page heading goes away.
 */
function bridge_register_page_meta(): void
{
	$can_edit = static function (): bool {
		return current_user_can('edit_pages');
	};

	register_post_meta(
		'page',
		'bridge_banner_style',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => 'none',
			'show_in_rest'      => true,
			'sanitize_callback' => static function ($value): string {
				return in_array($value, array('hidden', 'none', 'contained', 'fullwidth'), true) ? $value : 'none';
			},
			'auth_callback'     => $can_edit,
		)
	);

	register_post_meta(
		'page',
		'bridge_title_align',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => 'left',
			'show_in_rest'      => true,
			'sanitize_callback' => static function ($value): string {
				return ('center' === $value) ? 'center' : 'left';
			},
			'auth_callback'     => $can_edit,
		)
	);

	register_post_meta(
		'page',
		'bridge_banner_color',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_key',
			'auth_callback'     => $can_edit,
		)
	);
}
add_action('init', 'bridge_register_page_meta');

/**
 * Flag pages that use the full-width title banner with a body class.
 *
 * Lets the stylesheet drop the block-gap margin the site layout adds above
 * `main`, so a full-width banner sits flush beneath the header instead of
 * being pushed down by the gap between the nav and the first content block.
 * Pages with no title at all get their own class for the same reason.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function bridge_banner_body_class(array $classes): array
{
	if (is_page()) {
		$style = (string) get_post_meta(get_queried_object_id(), 'bridge_banner_style', true);
		if ('fullwidth' === $style) {
			$classes[] = 'bridge-banner-fullwidth';
		}
		// Same problem from the other end. A page with no title has nothing
		// standing between the header and its first block, so both the gap
		// above `main` and the content region's own top padding are air the
		// page never asked for — the title they were spacing away from is
		// not there. The class carries the same name the editor uses for
		// this choice, because it says the same thing on both sides.
		if ('hidden' === $style) {
			$classes[] = 'bridge-title-hidden';
		}
	}

	return $classes;
}
add_filter('body_class', 'bridge_banner_body_class');

/**
 * Decide whether a hex colour is "light" for contrast purposes.
 *
 * Used by the Page Title banner to pick a readable title colour (dark text
 * on light banners, light text on dark banners). Uses the perceived
 * luminance (ITU-R BT.601 weighting); threshold 0.6 of 255.
 *
 * @param string $hex Colour as #rgb or #rrggbb.
 * @return bool True when the colour is light.
 */
function bridge_is_light_color(string $hex): bool
{
	$hex = ltrim(trim($hex), '#');

	if (3 === strlen($hex)) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	if (6 !== strlen($hex) || ! ctype_xdigit($hex)) {
		return false;
	}

	$r = hexdec(substr($hex, 0, 2));
	$g = hexdec(substr($hex, 2, 2));
	$b = hexdec(substr($hex, 4, 2));

	$luminance = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);

	return $luminance > (0.6 * 255);
}

/**
 * Preload the hero-slider CSS when the current request actually renders
 * the block. The browser starts fetching it in parallel with the HTML
 * parser, removing it from the render-blocking critical path.
 */
function bridge_preload_hero_slider_css(): void
{
	if (is_admin() || is_feed() || is_embed()) {
		return;
	}

	if (! has_block('bridge/hero-slider')) {
		return;
	}

	$css_path = BRIDGE_DIST_PATH . '/slider.css';
	if (! file_exists($css_path)) {
		return;
	}

	printf(
		'<link rel="preload" href="%s" as="style" />' . "\n",
		esc_url(BRIDGE_DIST_URI . '/slider.css?ver=' . filemtime($css_path))
	);
}
add_action('wp_head', 'bridge_preload_hero_slider_css', 1);

/**
 * Editor scripts that belong only to the Page edit screen.
 *
 *   template-watcher    Subscribes to template changes and, when "Landing
 *                       Page" is selected, hides the post-title field and
 *                       auto-inserts a Hero Slider with the page title seeded
 *                       into the first slide's H1.
 *   page-banner-panel   The "Title Banner" document panel: banner style,
 *                       alignment and background colour, stored as post meta
 *                       and rendered by `bridge/page-title`.
 *   banner-preview      Draws the chosen banner on the editor's native title
 *                       field, so the setting is visible where it applies.
 *
 * One hook and one screen check for all three: they load together, on the same
 * screen, for the same reason.
 */
function bridge_enqueue_page_editor_assets(): void
{
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;

	if (! $screen || 'page' !== $screen->post_type) {
		return;
	}

	bridge_enqueue_script(
		'bridge-template-watcher',
		'template-watcher.js',
		array('wp-data', 'wp-blocks', 'bridge-hero-slider-editor')
	);

	bridge_enqueue_script(
		'bridge-page-banner-panel',
		'page-banner-panel.js',
		array(
			'wp-plugins',
			'wp-editor',
			'wp-edit-post',
			'wp-element',
			'wp-components',
			'wp-block-editor',
			'wp-data',
			'wp-core-data',
			'wp-i18n',
		)
	);

	bridge_enqueue_script('bridge-banner-preview', 'banner-preview.js', array('wp-data', 'wp-block-editor'));
}
add_action('enqueue_block_editor_assets', 'bridge_enqueue_page_editor_assets');

/**
 * Editor scripts that belong to every screen with a block editor on it.
 *
 * Unlike the three above, these are not about the Page screen: they take the
 * spacing and border controls off core/paragraph and the styling controls off
 * core/button, so they have to run wherever either block can be written —
 * posts, patterns and the site editor included.
 */
function bridge_enqueue_editor_assets(): void
{
	bridge_enqueue_script(
		'bridge-paragraph-lock',
		'paragraph-lock.js',
		array(
			'wp-hooks',
			'wp-blocks',
		)
	);

	bridge_enqueue_script(
		'bridge-button-lock',
		'button-lock.js',
		array(
			'wp-hooks',
			'wp-blocks',
		)
	);
}
add_action('enqueue_block_editor_assets', 'bridge_enqueue_editor_assets');
