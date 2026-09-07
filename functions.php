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
define('BRIDGE_BRAND', 'White Label');

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
// Loaded beside the tokens because it is part of their vocabulary:
// bridge_sanitize_tokens() asks this file which slugs are reserved and how
// many types are allowed.
require_once get_theme_file_path('inc/post-types.php');
require_once get_theme_file_path('inc/fonts.php');
require_once get_theme_file_path('inc/theme-json.php');
require_once get_theme_file_path('inc/icons.php');
require_once get_theme_file_path('inc/structure.php');
require_once get_theme_file_path('inc/section-blocks.php');
require_once get_theme_file_path('inc/card-data.php');
require_once get_theme_file_path('inc/hero-blocks.php');
require_once get_theme_file_path('inc/header.php');
require_once get_theme_file_path('inc/footer.php');
// Seeds the content types and navigation menus a fresh site starts with.
// After header.php and footer.php, because it points their menu slots at the
// menus it just created and so needs bridge_header_menu_id() and
// bridge_footer_menu_id().
require_once get_theme_file_path('inc/activation.php');

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
require_once get_theme_file_path('inc/simple-editor.php');
require_once get_theme_file_path('inc/rest.php');
// The contact form's server half: the Enquiries post type, the submission
// handler and the two emails. After site-options.php, because it asks that
// file where notifications should go.
require_once get_theme_file_path('inc/enquiries.php');
// The site's identity as JSON-LD. After site-options.php, which holds every
// field it publishes, and after tokens.php, which holds the logo it names.
require_once get_theme_file_path('inc/schema.php');

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
 * Ship the core block styles the page actually uses, and no others.
 *
 * `wp_should_load_separate_core_block_assets()` defaults to **false** — still,
 * in WordPress 7.1 — so a page carrying a heading and a paragraph loads the
 * whole of `block-library/style.min.css`: 137KB, 18.7KB over the wire, against
 * 10.9KB for the entirety of this theme's own CSS. Nearly all of it styles
 * blocks a Bridge page has never held. The assumption that a block theme opts
 * into this automatically is a common one and it is wrong; the default is what
 * it was in 5.8.
 *
 * With the filter on, core enqueues each block's stylesheet as that block
 * renders, and inlines the small ones. Nothing here is deferred to a plugin:
 * a page's payload is the theme's business.
 *
 * The one thing it can change is the cascade. A rule that used to win because
 * every core block stylesheet was present on every page may now land on a page
 * where it is not — which is worth a look across a few templates after
 * deploying, and is why this is a filter in the theme rather than something
 * folded into an unrelated commit.
 *
 * Core answers false for admin, feeds and REST before this filter runs, so
 * `__return_true` only ever speaks for a front-end page view.
 */
add_filter('should_load_separate_core_block_assets', '__return_true');

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

	// Registering the path is what makes the handle a candidate for core's
	// `wp_maybe_inline_styles()`: it only considers styles that declare one,
	// and without this line every per-block bundle is a separate
	// render-blocking request no matter how small it is. Core inlines the ones
	// that fit inside its 40KB budget, smallest first, and leaves the rest as
	// links — which is the right trade for the block styles, all of which sit
	// below the fold. main.css declares a path too and simply never wins the
	// budget at 72KB, which is also correct: it is the one stylesheet worth a
	// cacheable request of its own across the whole site.
	wp_style_add_data($handle, 'path', $path);

	return true;
}

/**
 * Register a block from src/blocks, if this build ships it.
 *
 * @param string $slug Directory name under src/blocks.
 */
/**
 * Hand WordPress every block's metadata in one file instead of twenty-eight.
 *
 * `register_block_type_from_metadata()` asks WP_Block_Metadata_Registry first
 * and only falls back to reading and decoding a block.json when no collection
 * covers the path. Without this, each of the theme's blocks costs a file read
 * and a JSON decode on every request — including admin screens, REST calls and
 * cron, none of which render a single one of them.
 *
 * The manifest is written by the Vite build from the same block.json files, so
 * it cannot describe a block that does not exist; what it can do is describe an
 * older version of one, which is why it is generated on every build alongside
 * the rest of dist/ and why a block.json edit needs a rebuild to take effect.
 * Missing manifest, missing collection: WordPress reads the JSON as it always
 * did, so a half-built checkout is slower rather than broken.
 *
 * On `init` at priority 0 because it has to be in place before the first
 * `register_block_type()` call, and those run on `init` at 10 — here, in
 * inc/header.php and in inc/footer.php.
 */
function bridge_register_block_metadata(): void
{
	if (! function_exists('wp_register_block_metadata_collection')) {
		return;
	}

	$manifest = get_theme_file_path('dist/blocks-manifest.php');

	if (! file_exists($manifest)) {
		return;
	}

	wp_register_block_metadata_collection(get_theme_file_path('src/blocks'), $manifest);
}
add_action('init', 'bridge_register_block_metadata', 0);

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

	/**
	 * The decorative mask shape's editor controls, shared by every band that
	 * offers them.
	 *
	 * One handle, named in the dependencies of each of those blocks' editor
	 * scripts, so WordPress loads it once however many are on the page — the
	 * same arrangement the carousel runtime above has, one layer down.
	 *
	 * A shared *script* rather than a shared module: every bundle here is a
	 * self-contained IIFE loaded as a classic script, so a module two entries
	 * import is hoisted into a chunk nothing enqueues.
	 *
	 * The data travels with it — the shape, the palette and the address of the
	 * screen that sets them are the same answer whichever band is asking.
	 */
	bridge_register_script(
		'bridge-band-mask',
		'band-mask.js',
		array('wp-element', 'wp-components', 'wp-i18n')
	);

	wp_add_inline_script(
		'bridge-band-mask',
		'window.bridgeBandMask = ' . wp_json_encode(bridge_band_mask_data()) . ';',
		'before'
	);

	// --- Hero Slider -------------------------------------------------------
	// `wp-hooks` and `wp-compose` beyond the shared set: the bundle also filters
	// core/cover's edit view, to put "Add slide" on a slide's own toolbar.
	bridge_register_script(
		'bridge-hero-slider-editor',
		'hero-slider-editor.js',
		array_merge(bridge_editor_script_deps(), array('wp-hooks', 'wp-compose'))
	);

	/**
	 * The slider runtime.
	 *
	 * Registered rather than declared as the block's `viewScript`: WordPress
	 * enqueues a view script wherever the block renders, and a hero with one
	 * slide renders the block without ever having a use for it. How many slides
	 * were authored is a question only the block can answer, so it enqueues this
	 * itself. See src/blocks/hero-slider/render.php.
	 */
	bridge_register_script('bridge-hero-slider-view', 'slider.js', array(), true);

	/**
	 * The call to action's backdrop reader.
	 *
	 * Registered rather than enqueued: it is only wanted where the header
	 * overlays a photograph, and which templates those are is a question the
	 * header block answers at render time. See src/blocks/header/render.php,
	 * which enqueues this on that branch.
	 */
	bridge_register_script('bridge-header-cta-backdrop', 'header-cta-backdrop.js', array(), true);
	/**
	 * The slider's stylesheet.
	 *
	 * Declared as the block's `style` rather than its `viewStyle`, the way the
	 * banner's is: a view style is enqueued from WP_Block::render(), which the
	 * editor never calls, so the canvas was painting the slides with none of
	 * this — the height, the column the words sit in, the stacked preview — and
	 * an operator laying out a hero was looking at a page the site does not
	 * serve. One handle, both contexts, one answer.
	 */
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
	// A view script, and the only block here that ships one to build its own
	// content: the map is the Maps JavaScript API rather than an iframe, so
	// something has to construct it. See src/js/map-view.js.
	bridge_register_section_block('map', true, true);

	/**
	 * The Maps key, for the block's editor.
	 *
	 * With one, the editor loads a real Google map: an editor searches for a
	 * place, drags the pin onto the door rather than the postcode, and the
	 * coordinates that come back are what the front end's embed is built from.
	 * Without one it keeps the address field and the keyless preview, so the
	 * block is usable on a site that has never opened the Cloud console.
	 *
	 * The key reaches the page either way — it is in the front end's iframe URL
	 * too, which is what the Embed API expects. Restricting it to this domain
	 * in the Cloud console is the control that matters, and Site Options says
	 * so beside the field.
	 */
	wp_add_inline_script(
		'bridge-map-editor',
		'window.bridgeMap = ' . wp_json_encode(
			array(
				'key'        => bridge_map_key(),
				// A style from the Cloud console, and what an advanced marker
				// needs before it will draw. Empty is a working map.
				'mapId'      => bridge_map_id(),
				'optionsUrl' => admin_url('admin.php?page=' . BRIDGE_SITE_OPTIONS_SLUG),
			)
		) . ';',
		'before'
	);
	bridge_register_section_block('call-to-action', true, false, array('bridge-band-mask'));
	bridge_register_section_block('testimonials');
	// The downloads band offers the decorative mask shape, so its editor script
	// names the shared handle that draws those controls.
	bridge_register_section_block('downloads', true, false, array('bridge-band-mask'));
	bridge_register_section_block('price-table');
	bridge_register_section_block('feature-blocks');
	bridge_register_section_block('gallery', true, true);
	// A view script, and the row reads without it: the marquee is CSS, and a
	// page that never receives the file gets the sliding row it always got.
	// What it adds is the measurement CSS cannot make — whether the logos
	// already fit, in which case they stand still. See src/js/logo-slider-view.js.
	bridge_register_section_block('logo-slider', true, true);
	bridge_register_section_block('alternating-content');
	// No view script: the accordion is a <details> element, so the opening,
	// the closing and the "one at a time" all ship with the browser.
	//
	// `wp-core-data` because the edit view lists the questions the block will
	// pull in, which are posts of a declared content type rather than blocks
	// nested inside it.
	bridge_register_section_block('faqs', true, false, array('wp-core-data', 'bridge-band-mask'));

	/**
	 * Where the block's questions come from.
	 *
	 * One content type, not a choice of them. The block is called FAQs, it
	 * renders an accordion of questions and answers, and the theme declares an
	 * FAQ type for it to read — so "which content type" was a control with one
	 * right answer, which is a control that only exists to be got wrong. A
	 * page pointed at Team by accident renders a grid of biographies inside a
	 * disclosure widget and looks, in the editor, like it is working.
	 *
	 * Empty when the FAQ type has been switched off or removed in Theme
	 * Options. Both are answers rather than errors, so the block says which one
	 * happened rather than falling back to another type.
	 *
	 * Printed as data rather than fetched, for the same reason the cards
	 * block's excerpt length is — it is three strings already in memory here,
	 * against a REST round trip on every editor load. The taxonomy name is one
	 * of them: it decides whether the block offers a category filter at all,
	 * and the alternative is the editor fetching the whole taxonomy index to
	 * find out.
	 *
	 * Two addresses travel with it, so a block that cannot show anything sends
	 * an operator to the screen that fixes it rather than describing where to
	 * look: Theme Options when the content type itself is off, and the
	 * category screen when the type is on but nobody has written a category
	 * yet.
	 */
	$faq_entry    = bridge_faq_post_type_entry();
	$faq_taxonomy = $faq_entry && bridge_post_type_has_categories($faq_entry)
		? bridge_post_type_taxonomy($faq_entry['slug'])
		: '';

	wp_add_inline_script(
		'bridge-faqs-editor',
		'window.bridgeFaqs = ' . wp_json_encode(
			array(
				'source'         => bridge_faq_post_type(),
				'plural'         => $faq_entry ? $faq_entry['plural'] : '',
				'taxonomy'       => $faq_taxonomy,
				'optionsUrl'     => admin_url('admin.php?page=' . BRIDGE_OPTIONS_SLUG),
				'categoriesUrl'  => '' !== $faq_taxonomy
					? admin_url(
						sprintf(
							'edit-tags.php?taxonomy=%s&post_type=%s',
							$faq_taxonomy,
							$faq_entry['slug']
						)
					)
					: '',
			)
		) . ';',
		'before'
	);
	bridge_register_section_block('split-content');

	// A view script, for the count-up: the figures render at their final
	// values and goals-view.js counts them from zero once the row is on
	// screen. Nothing about the block needs it to be readable.
	bridge_register_section_block('goals', true, true);

	// --- Contact form ------------------------------------------------------
	// A view script, and the block works without it: the form is an ordinary
	// POST that the server answers with a redirect. contact-form-view.js only
	// keeps the visitor on the page. See inc/enquiries.php.
	bridge_register_section_block('contact-form', true, true);

	/**
	 * The fields, for the editor's preview of the form.
	 *
	 * The preview has to draw the same four controls the front end does, and a
	 * copy of the list written in JavaScript would be the copy nobody updates
	 * — the front end, the validator and both emails all read
	 * bridge_enquiry_fields(), so the preview reads it too.
	 *
	 * Printed as data rather than fetched, for the reason the FAQs and Cards
	 * blocks give: it is a handful of strings already in memory here, against
	 * a REST round trip on every editor load.
	 *
	 * The notification address travels with it so the inspector can say where
	 * enquiries will go — and, when nothing is set, send an editor to the
	 * screen that sets it rather than describing where to look.
	 */
	$notify         = bridge_site_option('notify_email');
	$enquiry_fields = bridge_enquiry_fields();

	wp_add_inline_script(
		'bridge-contact-form-editor',
		'window.bridgeContactForm = ' . wp_json_encode(
			array(
				'fields'      => array_values(
					array_map(
						static function (array $field, string $key): array {
							return array(
								'key'         => $key,
								'label'       => $field['label'],
								'type'        => $field['type'],
								'required'    => ! empty($field['required']),
								'placeholder' => $field['placeholder'],
								'rows'        => (int) ($field['rows'] ?? 0),
							);
						},
						$enquiry_fields,
						array_keys($enquiry_fields)
					)
				),
				'notifyEmail' => is_email($notify) ? $notify : '',
				'optionsUrl'  => admin_url('admin.php?page=' . BRIDGE_SITE_OPTIONS_SLUG),
			)
		) . ';',
		'before'
	);

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
			'goal',
		) as $child
	) {
		bridge_register_script('bridge-' . $child . '-editor', $child . '-editor.js', bridge_editor_script_deps());
		bridge_register_block($child);
	}

	/**
	 * The mask shape, for the row's inspector and its preview.
	 *
	 * One URL, set once in Theme Options and shared by every row that switches
	 * a mask on — which is why it is not a per-row media picker. Empty when no
	 * shape has been chosen, and the inspector says so rather than offering a
	 * toggle that would do nothing.
	 *
	 * After the loop above, not before it: `wp_add_inline_script()` attaches to
	 * a registered handle and fails silently against one that does not exist
	 * yet, which is a global that is simply never printed.
	 */
	wp_add_inline_script(
		'bridge-alternating-row-editor',
		'window.bridgeAlternatingRow = ' . wp_json_encode(
			array(
				'maskUrl'    => bridge_mask_shape_url(),
				'optionsUrl' => admin_url('admin.php?page=' . BRIDGE_OPTIONS_SLUG),
			)
		) . ';',
		'before'
	);

	// --- Cards -------------------------------------------------------------
	bridge_register_script(
		'bridge-cards-editor',
		'cards-editor.js',
		array_merge($previewed, array('bridge-band-mask'))
	);

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

	// --- Breadcrumbs -------------------------------------------------------
	// Not in `$previewed`: the trail is drawn in the canvas by the block's own
	// edit view rather than fetched from the block renderer, because a REST
	// request has no main query to build a trail from. See its index.js.
	// Styled by main.css, which every page loads, so there is no style handle.
	bridge_register_script('bridge-breadcrumb-editor', 'breadcrumb-editor.js', bridge_editor_script_deps());
	bridge_register_block('breadcrumb');
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
 * Inline the hero-slider CSS when the current request renders the block.
 *
 * The slider is the first section of a landing page, so its stylesheet is
 * above the fold by construction and belongs in front of the LCP paint.
 * Left to itself it is a `<link>` roughly 24KB into the head — after the
 * global styles and core's block CSS — which is 24KB the browser has to
 * receive before it can even discover the request, and a round trip after
 * that before it can paint. Inlining it costs 3.5KB of uncacheable HTML and
 * removes both.
 *
 * This bypasses `wp_maybe_inline_styles()` rather than duplicating it. Core
 * inlines file-backed styles too, but it sorts the queue by size and fills a
 * 40KB budget, with no notion of which file is above the fold — whether the
 * slider makes the cut depends on how many other blocks the page happens to
 * use. Claiming the handle here at priority 0, one step ahead of core's
 * priority 1, makes the answer the same on every landing page and hands the
 * whole budget to the blocks further down the page.
 *
 * Safe to inline because the Vite bundles carry no relative `url()` — the
 * only one in the build is a data URI in main.css — so moving the bytes into
 * the document cannot break an asset path.
 */
function bridge_inline_hero_slider_css(): void
{
	if (is_admin() || is_feed() || is_embed()) {
		return;
	}

	// The enqueue itself is the condition, not `has_block()`: block templates
	// and this theme's search.php both render the body before `wp_head()`, so
	// by now the block has asked for its stylesheet if it is on the page at
	// all — including from a template part or a pattern, which a `has_block()`
	// test against post content would miss.
	$handle = 'bridge-hero-slider-style';

	if (! wp_style_is($handle, 'enqueued')) {
		return;
	}

	$css_path = BRIDGE_DIST_PATH . '/slider.css';
	if (! file_exists($css_path)) {
		return;
	}

	$css = file_get_contents($css_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents — a build artefact on local disk, read the way core's own wp_maybe_inline_styles() reads it.

	// A build that produced an empty file should leave the `<link>` alone
	// rather than swallow it into a `<style>` element with nothing in it.
	if (false === $css || '' === trim($css)) {
		return;
	}

	wp_dequeue_style($handle);

	// Printed raw: this is a compiled stylesheet, and escaping it for HTML
	// would corrupt every `>` combinator and `&` in it. The content is a
	// build artefact, never user input.
	printf(
		'<style id="%s-inline-css">%s</style>' . "\n",
		esc_attr($handle),
		$css
	);
}
add_action('wp_head', 'bridge_inline_hero_slider_css', 0);

/**
 * Editor scripts that belong only to the Page edit screen.
 *
 *   template-watcher    Subscribes to template changes and hides the
 *                       post-title field on a Landing Page that has a hero,
 *                       which is the block carrying the headline there. It
 *                       used to insert that hero as well; see the note in the
 *                       file for why nothing does that any more.
 *   page-banner-panel   The "Title Banner" document panel: banner style,
 *                       alignment and background colour, stored as post meta
 *                       and rendered by `bridge/page-title`. It also takes the
 *                       Featured image panel off the Page editor, which is a
 *                       control for a value no page template draws.
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

	/*
	 * `wp-data` and nothing else. It read `wp-blocks` for `createBlock()` and
	 * the slider's own editor script so the block type existed before it built
	 * one; it builds nothing now, and only ever reads block *names* out of the
	 * store.
	 */
	bridge_enqueue_script(
		'bridge-template-watcher',
		'template-watcher.js',
		array('wp-data')
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
 * spacing and border controls off core/paragraph, the spacing off core/cover
 * and the styling controls off core/button, so they have to run wherever any of
 * them can be written — posts, patterns and the site editor included.
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

	// And the spacing controls off core/cover, which is what a hero slide is.
	bridge_enqueue_script(
		'bridge-cover-lock',
		'cover-lock.js',
		array(
			'wp-hooks',
			'wp-blocks',
		)
	);

	/*
	 * And the two controls the lock leaves it: which of the two button designs
	 * this is — Theme Options draws and audits both, and until this panel there
	 * was no way to ask for the second — and which of the four compiled schemes
	 * it is painted from. Needs the sidebar, so it asks for rather more than
	 * its neighbours above: the components, the block editor's
	 * InspectorControls and the HOC helper, plus wp-i18n, which
	 * bridge_register_script() reads as the signal to hand the handle its
	 * translations. See src/editor/button-panel.js.
	 */
	bridge_enqueue_script(
		'bridge-button-panel',
		'button-panel.js',
		array(
			'wp-hooks',
			'wp-blocks',
			'wp-element',
			'wp-block-editor',
			'wp-components',
			'wp-compose',
			'wp-i18n',
		)
	);

	/*
	 * The gradient a skinned band can wear. Offered on any block already
	 * wearing one of the three section skins rather than on a list of block
	 * names, so it reaches the sixteen section blocks and core/group alike and
	 * needs nothing of any of them. See src/editor/band-gradient.js.
	 */
	bridge_enqueue_script(
		'bridge-band-gradient',
		'band-gradient.js',
		array(
			'wp-hooks',
			'wp-element',
			'wp-block-editor',
			'wp-components',
			'wp-compose',
			'wp-i18n',
		)
	);

	// Keeps full-width bands out of columns, where their full-bleed arithmetic
	// resolves against the wrong box and hangs the band off the side of the
	// window. See src/editor/top-level-only.js.
	bridge_enqueue_script(
		'bridge-top-level-only',
		'top-level-only.js',
		array(
			'wp-hooks',
		)
	);

	/*
	 * The Inspector sidebar's own styles.
	 *
	 * An ordinary admin stylesheet, not add_editor_style(): that one prefixes
	 * every selector with `.editor-styles-wrapper` and injects the result into
	 * the canvas iframe, which the sidebar is not in. See src/scss/inspector.scss.
	 */
	if (bridge_register_style('bridge-inspector', 'inspector.css')) {
		wp_enqueue_style('bridge-inspector');
	}
}
add_action('enqueue_block_editor_assets', 'bridge_enqueue_editor_assets');
