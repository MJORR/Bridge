<?php

/**
 * Bridge — the two-field editing screen for content that has no pages.
 *
 * An FAQ is a question and an answer. Writing one in the block editor means a
 * canvas, an inserter, a block toolbar, a settings sidebar, a slug, a publish
 * date, a visibility control and a document panel — every one of which is a
 * decision about a page, and none of which an FAQ has. The block editor is the
 * right tool for a page; it is the wrong tool for a record with two fields.
 *
 * So a declared type with no pages gets a form instead: a heading, a box for
 * the question, a heading, a small rich-text box for the answer, and Publish.
 *
 * ---- Why this is still post.php underneath --------------------------------
 *
 * Because everything else on that screen is worth keeping and expensive to
 * rebuild. Trash and restore, autosave, revisions, the capability checks, the
 * nonces, the categories box, the order field, the list table with its search
 * and its bulk actions — all of it already works, and a bespoke screen would
 * be reimplementing them one bug at a time.
 *
 * What this file does is take the page-shaped things off the screen. What is
 * left is a form, and what is underneath is still WordPress.
 *
 * ---- Which types ----------------------------------------------------------
 *
 * The pageless ones, and that is not a new switch — it is the one already in
 * Theme Options, read for what it has always meant. A type with no pages is
 * material a block assembles: it has a name and a body and nothing else, which
 * is exactly the screen below. A type with pages keeps the block editor,
 * because a page is what the block editor is for.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The declared types that get the two-field screen.
 *
 * @return string[] Post type slugs.
 */
function bridge_simple_editor_post_types(): array
{
	$slugs = array();

	foreach (bridge_active_post_types() as $entry) {
		// Absent means yes, the same reading the sanitiser takes.
		$has_pages = ! isset($entry['hasPages']) || ! empty($entry['hasPages']);

		if (! $has_pages) {
			$slugs[] = (string) $entry['slug'];
		}
	}

	return $slugs;
}

/**
 * Whether this post type is edited as a form rather than as a page.
 *
 * @param string $post_type Post type slug.
 */
function bridge_is_simple_editor_post_type(string $post_type): bool
{
	return '' !== $post_type && in_array($post_type, bridge_simple_editor_post_types(), true);
}

/**
 * The post type being edited on this admin screen, or an empty string.
 *
 * `get_current_screen()` is the reliable answer on both post.php and
 * post-new.php, and it is the only one that is right on both: the global
 * `$post` does not exist yet on the second, and `$_GET['post_type']` is absent
 * on the first.
 */
function bridge_current_editor_post_type(): string
{
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;

	if (! $screen || 'post' !== $screen->base) {
		return '';
	}

	return (string) $screen->post_type;
}

/**
 * What the two fields are called.
 *
 * Generic by default — a pageless type is a named thing with a body, and those
 * are the two words for it — with one type named outright.
 *
 * FAQ is named because the theme is not neutral about it: it seeds the type on
 * activation, ships a block that renders it, and knows the shape of the answer
 * that block draws. \"Question\" and \"Answer\" are what those two boxes hold, and
 * an author looking at a screen that says \"Title\" and \"Content\" has to
 * translate on every visit.
 *
 * The filter is how a site adds its own without editing this file. Anything it
 * returns is escaped where it is printed, and a missing key falls back to the
 * default rather than to nothing.
 *
 * @param string $post_type Post type slug.
 * @return array{title:string, body:string}
 */
function bridge_simple_editor_labels(string $post_type): array
{
	$object   = get_post_type_object($post_type);
	$singular = $object ? (string) $object->labels->singular_name : '';

	$labels = BRIDGE_FAQ_POST_TYPE === $post_type
		? array(
			'title' => __('Question', 'bridge'),
			'body'  => __('Answer', 'bridge'),
		)
		: array(
			/* translators: %s: singular post type name. */
			'title' => $singular ? sprintf(__('%s name', 'bridge'), $singular) : __('Name', 'bridge'),
			'body'  => __('Details', 'bridge'),
		);

	/**
	 * Filter the labels on a pageless type's two-field screen.
	 *
	 * @param array{title:string, body:string} $labels    The two labels.
	 * @param string                           $post_type Post type slug.
	 */
	$filtered = apply_filters('bridge_simple_editor_labels', $labels, $post_type);

	return array(
		'title' => isset($filtered['title']) && is_string($filtered['title']) && '' !== $filtered['title']
			? $filtered['title']
			: $labels['title'],
		'body'  => isset($filtered['body']) && is_string($filtered['body']) && '' !== $filtered['body']
			? $filtered['body']
			: $labels['body'],
	);
}

/**
 * Send pageless types to the classic screen.
 *
 * Not a preference about editors. The block editor's whole surface — the
 * canvas, the inserter, the document sidebar — describes a page, and the
 * pieces of it cannot be removed one at a time the way a metabox can. The
 * classic screen can be reduced to two fields; the block editor cannot be
 * reduced at all.
 *
 * @param bool   $use       Whether to use the block editor.
 * @param string $post_type Post type being edited.
 */
function bridge_disable_block_editor_for_simple_types(bool $use, string $post_type): bool
{
	return bridge_is_simple_editor_post_type($post_type) ? false : $use;
}
add_filter('use_block_editor_for_post_type', 'bridge_disable_block_editor_for_simple_types', 10, 2);

/**
 * The boxes that come off the screen.
 *
 * Every one of them is a decision about a page. A slug and a permalink for
 * content with no URL, an author on a site where the byline is never printed,
 * a comment thread on a type that does not support comments, a trackback, a
 * post format — and the revisions panel, which is a list of past versions
 * rather than a field, and which the browser's own history serves better than
 * a second table on a form with two rows in it.
 *
 * Revisions are still *recorded*: the panel is what goes, not the support. A
 * site that needs to recover an answer someone overwrote still can.
 *
 * `add_meta_boxes` at a late priority, so anything a plugin added is gone too
 * — the point of the screen is that it holds two fields, and a plugin that
 * adds a third has not been told about this type.
 *
 * @param string $post_type Post type being edited.
 */
function bridge_strip_simple_editor_boxes(string $post_type): void
{
	if (! bridge_is_simple_editor_post_type($post_type)) {
		return;
	}

	foreach (array('slugdiv', 'authordiv', 'postcustom', 'commentsdiv', 'commentstatusdiv', 'trackbacksdiv', 'formatdiv', 'revisionsdiv') as $box) {
		foreach (array('normal', 'side', 'advanced') as $context) {
			remove_meta_box($box, $post_type, $context);
		}
	}
}
add_action('add_meta_boxes', 'bridge_strip_simple_editor_boxes', 99);

/**
 * The label on the title field.
 *
 * `enter_title_here` is filtering a `<label for="title">` rather than a
 * placeholder attribute, whatever its name suggests: core prints the text into
 * `#title-prompt-text` inside `#titlewrap`, marks it `screen-reader-text`, and
 * — since WordPress 5.x — no script ever unhides it. So the classic title
 * field has an accessible name and nothing visible at all.
 *
 * That is the right hook for a *visible* label too, and the only one: it is the
 * only text core places inside the title wrapper, and the stylesheet unclips it
 * rather than this file printing a second label somewhere a hook can reach. See
 * `#titlediv #title-prompt-text` in src/scss/admin/_simple-editor.scss.
 *
 * Which is why this returns the bare word. It is a label now, sitting above the
 * box, not a sentence inside one.
 *
 * @param string  $text Current placeholder.
 * @param WP_Post $post Post being edited.
 */
function bridge_simple_editor_title_label_text(string $text, $post): string
{
	$post_type = $post instanceof WP_Post ? (string) $post->post_type : '';

	if (! bridge_is_simple_editor_post_type($post_type)) {
		return $text;
	}

	return bridge_simple_editor_labels($post_type)['title'];
}
add_filter('enter_title_here', 'bridge_simple_editor_title_label_text', 10, 2);

/**
 * A visible heading above the body field.
 *
 * `edit_form_after_title` is the hook between the title and the editor, and —
 * unlike `edit_form_top`, which fires before `#poststuff` opens and so lands
 * outside the whole two-column layout — it is inside `#post-body-content`,
 * directly above `#postdivrich`. See wp-admin/edit-form-advanced.php. It is not a `<label>`, unlike the one above the title:
 * TinyMCE replaces the textarea with an iframe, and a label pointing at a
 * textarea that has been hidden sends a keyboard user to a field that is not
 * there. TinyMCE names its own editing region; this is the visible heading.
 *
 * @param WP_Post $post Post being edited.
 */
function bridge_simple_editor_body_label($post): void
{
	$post_type = $post instanceof WP_Post ? (string) $post->post_type : '';

	if (! bridge_is_simple_editor_post_type($post_type)) {
		return;
	}

	$labels = bridge_simple_editor_labels($post_type);

	printf(
		'<p class="bridge-simple-editor__label">%s</p>',
		esc_html($labels['body'])
	);
}
add_action('edit_form_after_title', 'bridge_simple_editor_body_label');

/**
 * Turn off the expanding editor.
 *
 * `editor-expand` is the behaviour that makes the post editor grow with the
 * text, pin its toolbar to the top of the window as you scroll past it, and
 * offer distraction-free writing. All three are answers to one problem — an
 * article long enough that its toolbar scrolls away — and a question and an
 * answer is not that.
 *
 * It also has to be off rather than merely unused, because leaving it on while
 * changing the editor underneath it is what breaks the screen. In expand mode
 * core marks `#postdivrich` with `wp-editor-expand`, loads editor-expand.js,
 * and the iframe's height then comes from TinyMCE's `wpautoresize` plugin
 * rather than from `editor_height`. Teeny mode does not load that plugin — so
 * the layout says "grow to fit", nothing is doing the growing, and the answer
 * box renders with no height at all. An editor that is in the DOM and zero
 * pixels tall is an editor that is not displaying.
 *
 * Off, all three agree: no expand class, no expand script, and a box the size
 * `editor_height` says.
 *
 * @param bool   $expand    Whether to enable the expanding editor.
 * @param string $post_type Post type being edited.
 */
function bridge_disable_editor_expand(bool $expand, string $post_type): bool
{
	return bridge_is_simple_editor_post_type($post_type) ? false : $expand;
}
add_filter('wp_editor_expand', 'bridge_disable_editor_expand', 10, 2);

/**
 * The body field: a small rich-text box, and nothing else.
 *
 * Rich text rather than a bare textarea because an answer routinely needs a
 * link to the page that actually does the thing, or three bullets — and a site
 * whose FAQs cannot link anywhere is a site whose FAQs end in "see the
 * such-and-such page". Six buttons is the whole of what an answer needs.
 *
 * Teeny mode is what takes the rest away: no media button, no block quotes, no
 * alignment, no full-screen. `quicktags` off removes the Text tab — an HTML
 * view is a way to paste markup the front end then has to be trusted with, and
 * the answer renders through the_content() where the block editor's own
 * sanitising is not in the path.
 *
 * @param array<string, mixed> $settings  Editor settings.
 * @param string               $editor_id Which editor on the page.
 * @return array<string, mixed>
 */
function bridge_simple_editor_settings(array $settings, string $editor_id): array
{
	// Only the post body. `wp_editor()` is used by plugins and by core in
	// several other places on the admin, and this must not reach them.
	if ('content' !== $editor_id || ! bridge_is_simple_editor_post_type(bridge_current_editor_post_type())) {
		return $settings;
	}

	$settings['teeny']         = true;
	$settings['media_buttons'] = false;
	$settings['quicktags']     = false;
	// TinyMCE's height. `textarea_rows` is the same measurement for the plain
	// textarea a user gets with the visual editor switched off in their
	// profile; core reads one or the other, never both, so both are set.
	$settings['editor_height'] = 240;
	$settings['textarea_rows'] = 8;

	// Merged into what edit-form-advanced.php passed rather than assigned over
	// it. That array carries `resize`, `add_unload_trigger` and
	// `wp_autoresize_on`, which are core's answers about how the editor box
	// behaves on this screen — replacing it wholesale silently changed all
	// three, and `wp_autoresize_on` is the one that decides whether the iframe
	// gets a height. Only the keys below are this file's opinion.
	//
	// The merged array is applied over the computed init, so these win — see
	// the `$set['tinymce']` merge in class-wp-editor.php.
	$core_tinymce = isset($settings['tinymce']) && is_array($settings['tinymce'])
		? $settings['tinymce']
		: array();

	$settings['tinymce'] = array_merge($core_tinymce, array(
		// Named rather than left to teeny's default set, which also carries
		// underline, blockquote, strikethrough, three alignments and
		// full-screen — nine more decisions than an answer has to make.
		'toolbar1'   => 'bold,italic,link,unlink,bullist,numlist,undo,redo',
		'toolbar2'   => '',
		'toolbar3'   => '',
		'toolbar4'   => '',
		// Appended to the class list core builds for the editor iframe's
		// body, which is the only way to reach the text inside it: the iframe
		// has its own document, so the admin stylesheet cannot style it.
		'body_class' => 'bridge-simple-editor__body',
	));

	return $settings;
}
add_filter('wp_editor_settings', 'bridge_simple_editor_settings', 10, 2);

/**
 * The stylesheet TinyMCE loads inside the answer box.
 *
 * The iframe has a document of its own, so the admin stylesheet cannot reach
 * the text being typed. `content_css` is TinyMCE's list of stylesheet URLs for
 * that document, and appending to it is the whole of what this does.
 *
 * ---- Why not `content_style` ----------------------------------------------
 *
 * Because it is a string, and WordPress serialises the TinyMCE configuration
 * to JavaScript with `_WP_Editors::_parse_init()`, which writes every string
 * as `key:"value"` and escapes nothing at all — see class-wp-editor.php:851.
 * One double quote in the CSS closes the JS string early and breaks the object
 * literal, and a font stack naming "Segoe UI" carries two of them.
 *
 * The result is not a mis-styled editor. It is `tinyMCEPreInit` failing to
 * parse, so TinyMCE never initialises and the answer box is not there at all —
 * which is exactly how this was found.
 *
 * A URL has no quotes in it and cannot do that. It also puts the CSS in the
 * build, where stylelint reads it and the cache-buster is handled with
 * everything else.
 *
 * @param array<string, mixed> $init      TinyMCE configuration.
 * @param string               $editor_id Which editor on the page.
 * @return array<string, mixed>
 */
function bridge_simple_editor_content_css(array $init, string $editor_id): array
{
	if ('content' !== $editor_id || ! bridge_is_simple_editor_post_type(bridge_current_editor_post_type())) {
		return $init;
	}

	$path = BRIDGE_DIST_PATH . '/answer-editor.css';

	// The theme can be running from a checkout with no build in it. Missing,
	// the answer box keeps core's own styling rather than pointing TinyMCE at
	// a 404.
	if (! file_exists($path)) {
		return $init;
	}

	// Cache-busted the way bridge_register_style() does it, because TinyMCE
	// caches this stylesheet as readily as the browser does.
	$url = add_query_arg('ver', (string) filemtime($path), BRIDGE_DIST_URI . '/answer-editor.css');

	// A comma-separated list of URLs, and core has already put its own in it —
	// the editor reset among them. Appended, so nothing core loads is dropped
	// and this still wins on order.
	$existing = isset($init['content_css']) && is_string($init['content_css']) && '' !== $init['content_css']
		? $init['content_css'] . ','
		: '';

	$init['content_css'] = $existing . $url;

	return $init;
}
// Teeny mode is on, so `teeny_mce_before_init` is the filter that runs — see
// class-wp-editor.php. The other is registered against the day somebody has a
// reason to turn teeny off, so the styling does not quietly go with it.
add_filter('teeny_mce_before_init', 'bridge_simple_editor_content_css', 10, 2);
add_filter('tiny_mce_before_init', 'bridge_simple_editor_content_css', 10, 2);

/**
 * Take the page-shaped controls out of the Publish box.
 *
 * Hidden in CSS rather than unhooked, and the difference matters. Status,
 * visibility and the publish date are inputs the form submits: removing them
 * from the markup would make every save read their defaults instead of the
 * post's own values, so a scheduled post would publish itself the first time
 * anybody fixed a typo. Hidden, they carry exactly what they carried, and the
 * screen offers one button.
 *
 * What is left in the box: Publish, or Update, and Move to Trash.
 *
 * All of it in one stylesheet with the rest of the screen's styling, loaded
 * only on the screens this file has taken over — see src/scss/admin/
 * _simple-editor.scss.
 */
function bridge_simple_editor_styles(): void
{
	if (! bridge_is_simple_editor_post_type(bridge_current_editor_post_type())) {
		return;
	}

	if (bridge_register_style('bridge-simple-editor', 'simple-editor.css')) {
		wp_enqueue_style('bridge-simple-editor');
	}
}
add_action('admin_enqueue_scripts', 'bridge_simple_editor_styles');

/**
 * Nothing on this screen has a help tab worth reading.
 *
 * Core adds three to every post screen, and all three describe the page editor
 * — its excerpt box, its custom fields, its trackbacks. On a form with two
 * fields they document controls that are not there.
 */
function bridge_simple_editor_help(): void
{
	if (! bridge_is_simple_editor_post_type(bridge_current_editor_post_type())) {
		return;
	}

	$screen = get_current_screen();

	if ($screen) {
		$screen->remove_help_tabs();
	}
}
add_action('admin_head', 'bridge_simple_editor_help');
