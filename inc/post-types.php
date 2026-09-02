<?php

/**
 * Bridge — custom post types, declared in Theme Options.
 *
 * A CPT here is three words: what one is called, what many are called, and the
 * slug its URLs are built from. Everything else — public, archived, block
 * editor, REST, thumbnails, excerpts — is fixed by the theme, because the
 * combinations that matter to a brochure site are all the same combination,
 * and each one exposed as a switch is a way for a client to build a content
 * type that no template renders.
 *
 * The record lives in the design tokens rather than in an option of its own so
 * that the whole site configuration stays one portable, self-describing
 * document — the same thing `wp bridge export` already carries between
 * environments.
 *
 * ---- On, or off ------------------------------------------------------------
 *
 * The theme seeds two content types on activation — Team and FAQs — and a
 * given site wants one, both or neither. So each declared type has an on
 * switch, and turning it off is not the same as removing the row: the name,
 * the slug and every other setting stay in the record, ready to come back
 * exactly as they were. What stops is the registration, which is what puts the
 * type in the admin sidebar and on the front end.
 *
 * Off, there is no post type at all: no menu item, no editor, no REST route,
 * no archive. The posts are still in `wp_posts` and the blocks that point at
 * the type still hold its slug, so switching it back on restores both — see
 * "What this file will not do" below, which is the same promise.
 *
 * ---- Types with pages, and types without ----------------------------------
 *
 * One switch, because there is only one real question: does this content have
 * an address, or is it material a block assembles? A "Project" is a page —
 * someone links to it. An "FAQ" is not: it exists to be pulled into a block,
 * and giving it a single page produces a URL showing one answer with no
 * question around it, plus an archive listing them all in a layout nobody
 * designed, plus search results pointing at both.
 *
 * Off, the type keeps its admin screen, its editor and its REST endpoint —
 * everything an author and a block need — and loses `public`,
 * `publicly_queryable`, `has_archive`, its rewrite rules, its query variable
 * and its place in search. There is no URL left to land on.
 *
 * It also decides what the editing screen asks for. A pageless type is never
 * rendered as a page, so its excerpt and its featured image have nothing that
 * would draw them — and a field nothing reads is a field an author fills in
 * for no one. So the two are dropped there, and an FAQ is written as the two
 * things an FAQ is: a question and an answer.
 *
 * ---- Categories -----------------------------------------------------------
 *
 * A second switch, and off by default. Sets of content divide (billing
 * questions, shipping questions) and sets of people usually do not, so this is
 * a decision per type rather than something every type is given.
 *
 * On, the type gets one hierarchical taxonomy — `{slug}_cat` — with the labels
 * built from its own name, so an FAQ type produces "FAQ Categories". One
 * taxonomy and not two: tags and categories differ by hierarchy alone, and a
 * brochure site that has to choose between them has been given a decision
 * rather than a feature. It follows the pages switch for everything public,
 * because a category archive for a type with no pages is the same URL nobody
 * designed.
 *
 * ---- What renders them ----------------------------------------------------
 *
 * Nothing extra. The block template hierarchy falls back from
 * `single-{slug}.html` to `single.html`, and from `archive-{slug}.html` to
 * `archive.html`, both of which the theme already ships — so a type declared
 * here has a working article page and a working archive the moment it is
 * saved. A client who later wants one type to look different gets a template
 * of its own; until then there is nothing to keep in step.
 *
 * ---- What this file will not do -------------------------------------------
 *
 * It never deletes content. Removing a post type from the record stops it
 * being registered, which makes its posts invisible in the admin and on the
 * front end — but every row is still in `wp_posts`, and putting the slug back
 * brings them all back with it. That is the reversible reading of a removal,
 * and it is the only safe one for a control an operator can reach.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Where the "the registered shape changed" fingerprint lives.
 *
 * Registering a post type teaches WordPress a set of rewrite rules, but the
 * rules themselves are cached in an option and are only rebuilt when something
 * asks for it. Change a slug without that rebuild and the admin is correct
 * while every front-end URL 404s — the classic "I renamed it and the site
 * broke" report.
 */
define('BRIDGE_POST_TYPES_HASH_OPTION', 'bridge_post_types_hash');

/**
 * The slug of the theme's own FAQ content type.
 *
 * The one declared type this theme is not neutral about. It seeds it on
 * activation, ships a block that renders it, gives it a two-field editing
 * screen and names its fields Question and Answer — four files that have to
 * agree on one string, so the string is defined once here.
 *
 * It is still an ordinary declared type in every other respect: an operator can
 * rename it, switch off its pages, switch off its categories, switch the whole
 * thing off, or remove it. What they cannot do is point the FAQs block at
 * something else, because there is nothing else it is for.
 */
define('BRIDGE_FAQ_POST_TYPE', 'faq');

/**
 * The FAQ type's slug when the site has one, or an empty string.
 *
 * Empty when it has been removed from the record or switched off in Theme
 * Options — both of which are answers rather than errors, and both of which
 * the FAQs block reports rather than works around.
 */
function bridge_faq_post_type(): string
{
	foreach (bridge_active_post_types() as $entry) {
		if (BRIDGE_FAQ_POST_TYPE === (string) ($entry['slug'] ?? '')) {
			return BRIDGE_FAQ_POST_TYPE;
		}
	}

	return '';
}

/**
 * The FAQ type's declared row, or an empty array.
 *
 * @return array<string, mixed>
 */
function bridge_faq_post_type_entry(): array
{
	foreach (bridge_active_post_types() as $entry) {
		if (BRIDGE_FAQ_POST_TYPE === (string) ($entry['slug'] ?? '')) {
			return $entry;
		}
	}

	return array();
}

/**
 * The most post types an operator may declare.
 *
 * Not a technical limit — a cap on how far the admin menu can be grown from a
 * settings screen. A site that genuinely needs a twelfth content type needs a
 * conversation, not another row in a form.
 */
function bridge_post_type_limit(): int
{
	return 10;
}

/**
 * Slugs an operator may not take.
 *
 * Three groups, and all three fail differently:
 *
 *   - WordPress's own post types. Re-registering `page` replaces the real one.
 *   - Reserved query variables. WordPress documents these as unusable for a
 *     post type: a public post type registers `?{slug}=…`, and a slug of
 *     `name` or `type` collides with a variable the query already owns, so the
 *     archive resolves to something else entirely.
 *   - The theme's own prefix, kept clear for anything Bridge registers later.
 *
 * @return string[]
 */
function bridge_reserved_post_type_slugs(): array
{
	return array(
		// Core post types.
		'post',
		'page',
		'attachment',
		'revision',
		'nav_menu_item',
		'custom_css',
		'customize_changeset',
		'oembed_cache',
		'user_request',
		'wp_block',
		'wp_template',
		'wp_template_part',
		'wp_global_styles',
		'wp_navigation',
		'wp_font_family',
		'wp_font_face',
		// Reserved query variables — see the post type registration handbook.
		'action',
		'author',
		'order',
		'theme',
		'type',
		'name',
		'date',
		'calendar',
		'tag',
		'category',
		'terms',
		'cpage',
		'paged',
		'feed',
		'error',
		'preview',
		'embed',
		'fields',
		'output',
		'page_id',
		'post_type',
		// The theme's own namespace, and the one type in it — see
		// inc/enquiries.php. Re-registering that one would not replace it (the
		// registrar refuses a slug that already exists), but it would give an
		// operator a content type that silently never appears.
		'bridge',
		'bridge_enquiry',
	);
}

/**
 * The post types declared in the token record — every row, on or off.
 *
 * Two callers want this rather than `bridge_active_post_types()`: the options
 * screen, which has to draw the switched-off rows in order to switch them back
 * on, and the seeder, which has to see a row before deciding not to add it a
 * second time. Everything else is asking what content types the site has, and
 * that is the other function.
 *
 * @return array<int, array{singular:string,plural:string,slug:string}>
 */
function bridge_declared_post_types(): array
{
	$tokens = bridge_get_tokens();

	return isset($tokens['postTypes']) && is_array($tokens['postTypes'])
		? $tokens['postTypes']
		: array();
}

/**
 * Whether a declared row is switched on.
 *
 * Absent means yes, matching the sanitiser: a record written before the switch
 * existed described a type that was registered, and a missing key must not be
 * what takes a live site's content type away.
 *
 * @param array<string, mixed> $entry One declared type.
 */
function bridge_post_type_enabled(array $entry): bool
{
	return ! isset($entry['enabled']) || ! empty($entry['enabled']);
}

/**
 * The declared post types that are switched on.
 *
 * The list to ask for whenever the question is "what content types does this
 * site have" — the admin sidebar, the FAQs block's source list, anything an
 * editor is offered. `bridge_declared_post_types()` is the other question,
 * "what is in the record", and only the options screen and the seeder want
 * that one.
 *
 * @return array<int, array{singular:string,plural:string,slug:string}>
 */
function bridge_active_post_types(): array
{
	return array_values(
		array_filter(
			bridge_declared_post_types(),
			static function ($entry): bool {
				return is_array($entry) && bridge_post_type_enabled($entry);
			}
		)
	);
}

/**
 * Whether a declared row asked for categories.
 *
 * Absent means no — the opposite reading to the two switches above, and for
 * the reason the sanitiser gives: those describe something an older record
 * already had, where a taxonomy is something it never did.
 *
 * @param array<string, mixed> $entry One declared type.
 */
function bridge_post_type_has_categories(array $entry): bool
{
	return ! empty($entry['hasCategories']);
}

/**
 * The taxonomy name a declared type's categories are registered under.
 *
 * `_cat` rather than `_category`: a taxonomy name is stored in a VARCHAR(32)
 * and a post type slug may be 20 characters, so the longer suffix would put
 * the two longest slugs over the limit and truncate them silently — the same
 * failure the slug cap exists to prevent one level up.
 *
 * @param string $slug A declared post type slug.
 */
function bridge_post_type_taxonomy(string $slug): string
{
	return $slug . '_cat';
}

/**
 * The arguments a declared type's category taxonomy is registered with.
 *
 * Hierarchical, because that is what "category" means to the person filling it
 * in: a box of checkboxes with an Add link, not a free-text field that makes a
 * new term out of every typo.
 *
 * Everything public follows the pages switch, for the reason the file header
 * gives — a term archive for a type with no pages is a URL listing content the
 * theme never designed a page for.
 *
 * @param array{singular:string,plural:string,slug:string} $entry One declared type.
 * @return array<string, mixed>
 */
function bridge_post_type_taxonomy_args(array $entry): array
{
	$singular  = $entry['singular'];
	$has_pages = ! isset($entry['hasPages']) || ! empty($entry['hasPages']);

	/* translators: %s: singular post type name. */
	$plural_label   = sprintf(__('%s Categories', 'bridge'), $singular);
	/* translators: %s: singular post type name. */
	$singular_label = sprintf(__('%s Category', 'bridge'), $singular);

	return array(
		'labels'             => array(
			'name'                       => $plural_label,
			'singular_name'              => $singular_label,
			'menu_name'                  => __('Categories', 'bridge'),
			'all_items'                  => $plural_label,
			/* translators: %s: singular taxonomy name. */
			'edit_item'                  => sprintf(__('Edit %s', 'bridge'), $singular_label),
			/* translators: %s: singular taxonomy name. */
			'view_item'                  => sprintf(__('View %s', 'bridge'), $singular_label),
			/* translators: %s: singular taxonomy name. */
			'update_item'                => sprintf(__('Update %s', 'bridge'), $singular_label),
			/* translators: %s: singular taxonomy name. */
			'add_new_item'               => sprintf(__('Add %s', 'bridge'), $singular_label),
			/* translators: %s: singular taxonomy name. */
			'new_item_name'              => sprintf(__('New %s name', 'bridge'), $singular_label),
			/* translators: %s: plural taxonomy name. */
			'parent_item'                => sprintf(__('Parent %s', 'bridge'), $singular_label),
			/* translators: %s: singular taxonomy name. */
			'parent_item_colon'          => sprintf(__('Parent %s:', 'bridge'), $singular_label),
			/* translators: %s: plural taxonomy name. */
			'search_items'               => sprintf(__('Search %s', 'bridge'), $plural_label),
			/* translators: %s: plural taxonomy name. */
			'not_found'                  => sprintf(__('No %s found.', 'bridge'), strtolower($plural_label)),
			/* translators: %s: plural taxonomy name. */
			'no_terms'                   => sprintf(__('No %s', 'bridge'), strtolower($plural_label)),
			/* translators: %s: plural taxonomy name. */
			'back_to_items'              => sprintf(__('← Go to %s', 'bridge'), $plural_label),
			/* translators: %s: plural taxonomy name. */
			'separate_items_with_commas' => sprintf(__('Separate %s with commas', 'bridge'), strtolower($plural_label)),
		),
		'public'             => $has_pages,
		'publicly_queryable' => $has_pages,
		'show_in_nav_menus'  => $has_pages,
		// Not derived from `public`, for the same reason the post type sets
		// these explicitly: a pageless type still needs the box on its editing
		// screen and the column on its list table.
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_admin_column'  => true,
		// What lets the block editor draw the Categories panel at all, and
		// what the FAQs block reads to list the categories it can filter by.
		'show_in_rest'       => true,
		'hierarchical'       => true,
		'query_var'          => $has_pages ? bridge_post_type_taxonomy($entry['slug']) : false,
		'rewrite'            => $has_pages
			? array(
				'slug'         => $entry['slug'] . '-category',
				'with_front'   => false,
				'hierarchical' => true,
			)
			: false,
	);
}

/**
 * The arguments every declared post type is registered with.
 *
 * One shape for all of them, deliberately. See the file header.
 *
 * `show_in_rest` is not optional here even though it looks like a preference:
 * without it the post type opens in the classic editor, and a site whose pages
 * are built from Bridge blocks would have one content type that cannot use any
 * of them.
 *
 * @param array{singular:string,plural:string,slug:string} $entry One declared type.
 * @param int                                              $index Position in the declared list.
 * @return array<string, mixed>
 */
function bridge_post_type_args(array $entry, int $index = 0): array
{
	$singular = $entry['singular'];
	$plural   = $entry['plural'];

	// Absent means yes, matching the sanitiser: a record written before the
	// switch existed described a public type.
	$has_pages = ! isset($entry['hasPages']) || ! empty($entry['hasPages']);

	return array(
		'labels'              => array(
			'name'                  => $plural,
			'singular_name'         => $singular,
			'menu_name'             => $plural,
			'all_items'             => $plural,
			/* translators: %s: singular post type name. */
			'add_new_item'          => sprintf(__('Add %s', 'bridge'), $singular),
			/* translators: %s: singular post type name. */
			'edit_item'             => sprintf(__('Edit %s', 'bridge'), $singular),
			/* translators: %s: singular post type name. */
			'new_item'              => sprintf(__('New %s', 'bridge'), $singular),
			/* translators: %s: singular post type name. */
			'view_item'             => sprintf(__('View %s', 'bridge'), $singular),
			/* translators: %s: plural post type name. */
			'view_items'            => sprintf(__('View %s', 'bridge'), $plural),
			/* translators: %s: plural post type name. */
			'search_items'          => sprintf(__('Search %s', 'bridge'), $plural),
			/* translators: %s: plural post type name. */
			'not_found'             => sprintf(__('No %s found.', 'bridge'), $plural),
			/* translators: %s: plural post type name. */
			'not_found_in_trash'    => sprintf(__('No %s found in Trash.', 'bridge'), $plural),
			/* translators: %s: plural post type name. */
			'archives'              => sprintf(__('%s archive', 'bridge'), $singular),
			'featured_image'        => __('Featured image', 'bridge'),
			'set_featured_image'    => __('Set featured image', 'bridge'),
			'remove_featured_image' => __('Remove featured image', 'bridge'),
			'use_featured_image'    => __('Use as featured image', 'bridge'),
			// The title of the box `page-attributes` adds. Core's default is
			// "Page Attributes", which on a flat type is a box named after
			// hierarchy it does not have, holding the one field it does: the
			// order. Naming it for its contents matters most on a pageless
			// type, where inc/simple-editor.php has taken every other
			// page-shaped word off the screen.
			'attributes'            => __('Order', 'bridge'),
		),
		// The five that decide whether this content has an address. See the
		// file header for what the switch is actually asking.
		'public'              => $has_pages,
		'publicly_queryable'  => $has_pages,
		'has_archive'         => $has_pages,
		'exclude_from_search' => ! $has_pages,
		'show_in_nav_menus'   => $has_pages,
		// Not derived from `public`, and set explicitly for that reason:
		// register_post_type() defaults both of these to whatever `public` is,
		// so a pageless type would quietly lose its admin screen — which is
		// the one thing it still needs.
		'show_ui'             => true,
		'show_in_menu'        => true,
		// Likewise not optional. Without it the post type opens in the classic
		// editor, and a site whose pages are built from Bridge blocks would
		// have one content type that cannot use any of them. It is also what
		// the FAQs block reads to list its questions in the editor.
		'show_in_rest'        => true,
		'hierarchical'        => false,
		'menu_icon'           => $has_pages ? 'dashicons-portfolio' : 'dashicons-editor-help',
		// Below Pages (20) and above Comments (25), so declared types read as
		// content rather than as settings. Spaced by their position in the
		// record, so several of them keep the order the operator put them in
		// instead of colliding on one slot and being resolved alphabetically.
		'menu_position'       => 21 + $index,
		// `page-attributes` on a flat type offers exactly one thing: the Order
		// field. That was dead weight until the FAQs block started ordering by
		// it, and it is now the only way an author can say which question
		// comes first — so it earns its place.
		//
		// `excerpt` and `thumbnail` only on a type with pages. Both exist to
		// be rendered — the excerpt in a card and in search results, the image
		// on the item's own page and in the card above it — and a pageless
		// type has none of those places. The cards block, which is the one
		// thing that would draw them from a block instead, offers only
		// viewable post types, so it never sees a pageless type either.
		//
		// That is what makes writing an FAQ two fields: a question and an
		// answer, with nothing else on the screen to fill in for nobody.
		//
		// Still no `custom-fields` on either: it turns on the meta panel and
		// the meta REST surface, neither of which a locked-down brochure site
		// has a use for.
		'supports'            => $has_pages
			? array('title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes')
			: array('title', 'editor', 'revisions', 'page-attributes'),
		// A type with no pages has no URLs, so it has no rewrite rules and no
		// query variable. Leaving either in place would keep `?faq=slug`
		// resolving to a page the theme never designed.
		'rewrite'             => $has_pages
			? array(
				'slug'       => $entry['slug'],
				// The permalink is /{slug}/{post}, not /blog/{slug}/{post}: a
				// declared type is a section of the site in its own right, not
				// something filed under wherever posts happen to live.
				'with_front' => false,
			)
			: false,
		'query_var'           => $has_pages ? $entry['slug'] : false,
	);
}

/**
 * What this request actually registered.
 *
 * Not the same question as "what does the record declare", and the difference
 * is what the rewrite fingerprint below has to be built from — see the comment
 * there. Each entry is `name:1` or `name:0` — a post type slug or a taxonomy
 * name, and whether it brought rewrite rules with it. A type switched off
 * contributes nothing, which is exactly what makes switching one off flush the
 * rules that mentioned it.
 *
 * @param string[]|null $set Values to store, or null to read.
 * @return string[]
 */
function bridge_registered_post_types(?array $set = null): array
{
	static $registered = array();

	if (null !== $set) {
		$registered = $set;
	}

	return $registered;
}

/**
 * Register every declared post type, and the taxonomies they asked for.
 *
 * On `init` at the default priority, which is where WordPress expects post
 * types: earlier and the token record's own option filters are not ready,
 * later and the REST controllers have already been built.
 *
 * ---- Why the taxonomies are a second pass ---------------------------------
 *
 * Because a taxonomy name is derived rather than typed, and the name it
 * derives to is one an operator could also have typed. A site with an "FAQ"
 * type that has categories and a separate "FAQ Cat" type declares `faq`,
 * `faq_cat` and — from the first — a `faq_cat` taxonomy. Post types and
 * taxonomies share the query-variable namespace, so one of the two has to give
 * way, and it has to be the derived one: an operator typed the other.
 *
 * Interleaved, that depends on the order of the record. A `faq` row above a
 * `faq_cat` row would register the taxonomy while the post type it collides
 * with is still two iterations away, and the check would pass. Registering
 * every post type first makes the answer the same whichever way round the
 * operator put them.
 */
function bridge_register_declared_post_types(): void
{
	$registered = array();
	$pending    = array();

	foreach (bridge_declared_post_types() as $index => $entry) {
		if (! is_array($entry) || empty($entry['slug'])) {
			continue;
		}

		// A row that is switched off is a row that is still in the record and
		// is not a post type. Nothing else here runs for it: no registration,
		// no taxonomy, and no entry in the fingerprint — which is what makes
		// the next request rebuild the rewrite rules without its archive in
		// them.
		if (! bridge_post_type_enabled($entry)) {
			continue;
		}

		// Never replace something already registered. Sanitisation rejects the
		// slugs the theme knows about, but a plugin activated after the record
		// was saved can still land on the same name, and taking it over would
		// break that plugin rather than this one.
		if (post_type_exists($entry['slug'])) {
			continue;
		}

		$args = bridge_post_type_args($entry, (int) $index);

		register_post_type($entry['slug'], $args);

		$registered[] = $entry['slug'] . ':' . ($args['has_archive'] ? '1' : '0');

		if (bridge_post_type_has_categories($entry)) {
			$pending[] = $entry;
		}
	}

	foreach ($pending as $entry) {
		$taxonomy = bridge_post_type_taxonomy($entry['slug']);

		// The same rule the post types keep, against both namespaces — see the
		// docblock for the collision this is about. Silent either way, which
		// is why it is checked rather than assumed.
		if (taxonomy_exists($taxonomy) || post_type_exists($taxonomy)) {
			continue;
		}

		$tax_args = bridge_post_type_taxonomy_args($entry);

		register_taxonomy($taxonomy, array($entry['slug']), $tax_args);

		$registered[] = $taxonomy . ':' . ($tax_args['rewrite'] ? '1' : '0');
	}

	bridge_registered_post_types($registered);
}
add_action('init', 'bridge_register_declared_post_types');

/**
 * Rebuild the rewrite rules when the registered set changes.
 *
 * A fingerprint comparison rather than a flush on save, because the two things
 * happen in the wrong order: the REST request that saves the record has
 * already run `init`, so the post type it just declared is not registered in
 * that request, and flushing there would write rules that do not include it.
 * Doing it on the next `init` — after registration — is the only point where
 * WordPress can see what it is being asked to build rules for.
 *
 * The fingerprint is taken from what was *registered*, never from what the
 * record declares, and that distinction is the whole correctness of this
 * function. Theme activation is where the two come apart: core fires
 * `after_switch_theme` from `check_theme_switched()` on `init` at priority 99,
 * so the seeder in inc/activation.php writes its post types into the record
 * after this request's registration has already run. Hashing the record there
 * would store a fingerprint for types that are not registered — and every
 * request after it would compare equal and never flush, leaving a seeded type
 * with an admin screen and a 404 for an archive.
 *
 * Same priority as `check_theme_switched()`, and deliberately behind it:
 * default-filters.php adds that callback while core loads, this one is added
 * when the theme's functions.php loads, and WordPress runs same-priority
 * callbacks in the order they were added. So the seeder has finished before
 * this asks what is registered — and what is registered is still the old set,
 * which is exactly the answer that makes the next request flush.
 *
 * `false` skips the hard rewrite of .htaccess: these are WordPress-level rules
 * and nothing about them needs a server config change.
 */
function bridge_maybe_flush_post_type_rules(): void
{
	$hash   = md5((string) wp_json_encode(bridge_registered_post_types()));
	$stored = get_option(BRIDGE_POST_TYPES_HASH_OPTION, '');

	if ($hash === $stored) {
		return;
	}

	flush_rewrite_rules(false);
	update_option(BRIDGE_POST_TYPES_HASH_OPTION, $hash, true);
}
add_action('init', 'bridge_maybe_flush_post_type_rules', 99);

/**
 * How many posts each declared type is holding.
 *
 * Sent to the options screen so that switching a type off — or removing it —
 * can say what it is about to hide, rather than asking an operator to
 * remember. Counts every status a human would call "content": a type with only
 * auto-drafts in it is empty as far as this question goes.
 *
 * Not `wp_count_posts()`, which is the obvious answer and the wrong one twice
 * over. It returns an empty object for a post type that is not registered —
 * and a type that is switched off is precisely the one an operator needs a
 * number for, since the number is what the switch is hiding. It is also one
 * query per type, where the whole screen wants one query.
 *
 * So: one grouped read of the posts table, which is what `wp_count_posts()`
 * does per type anyway. Uncached, because this runs once, on one admin screen,
 * behind the capability check on the options REST route.
 *
 * @return array<string, int> Slug => post count.
 */
function bridge_post_type_counts(): array
{
	global $wpdb;

	$slugs = array();

	foreach (bridge_declared_post_types() as $entry) {
		$slug = is_array($entry) ? (string) ($entry['slug'] ?? '') : '';

		if ('' !== $slug) {
			$slugs[] = $slug;
		}
	}

	if (! $slugs) {
		return array();
	}

	// Every slug is `[a-z][a-z0-9_]*` by the time it is in the record, so this
	// is placeholders for values that could not be anything else — prepared
	// regardless, because the day someone loosens the sanitiser is not the day
	// to discover this was trusting it.
	$slug_placeholders   = implode(', ', array_fill(0, count($slugs), '%s'));
	$statuses            = array('publish', 'future', 'draft', 'pending', 'private');
	$status_placeholders = implode(', ', array_fill(0, count($statuses), '%s'));

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared — the two interpolations are generated placeholder lists, not input.
			"SELECT post_type, COUNT(*) AS total
			 FROM {$wpdb->posts}
			 WHERE post_type IN ({$slug_placeholders})
			 AND post_status IN ({$status_placeholders})
			 GROUP BY post_type",
			// phpcs:enable
			array_merge($slugs, $statuses)
		),
		ARRAY_A
	);

	// Every declared type gets an entry, including the ones with nothing in
	// them: the screen reads this as a map, and a missing key and a zero would
	// have to be told apart by whoever reads it next.
	$counts = array_fill_keys($slugs, 0);

	foreach ((array) $rows as $row) {
		$counts[(string) $row['post_type']] = (int) $row['total'];
	}

	return $counts;
}
