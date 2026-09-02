<?php

/**
 * Bridge — what the theme sets up the first time it is switched on.
 *
 * Two things, and both are seeds rather than settings: content types a client
 * site will want on day one, and the navigation menus the header and footer
 * expect to be pointed at. Everything here is written once and then belongs to
 * the operator — a second activation adds nothing it can already see, and
 * nothing here ever edits or removes what it finds.
 *
 * ---- Why `after_switch_theme` fires later than it looks -------------------
 *
 * Not during the click that activates the theme. `switch_theme()` runs in a
 * request that still has the *old* theme loaded, so a callback added by this
 * file could not possibly be listening. What core does instead is leave a note
 * in the `theme_switched` option, and `check_theme_switched()` — on `init` at
 * priority 99 — fires `after_switch_theme` on the next request, where the new
 * theme is loaded and this file exists.
 *
 * That timing has a consequence worth stating plainly, because it looks like a
 * bug from the outside: `init` 99 is *after* `init` 10, where the theme's post
 * types register. So the types seeded here are not registered in the request
 * that seeds them — which is the themes.php page the operator is redirected
 * to. They appear on the next page load, so the admin sidebar gains Team and
 * FAQs a click later than the theme was switched on.
 *
 * That is cosmetic. What is not is the rewrite rules, which would otherwise be
 * built and fingerprinted while the seeded types are still missing — see
 * `bridge_maybe_flush_post_type_rules()` for what stops that.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The content types a fresh Bridge site starts with.
 *
 * Two, because these are the two every brochure site has asked for and neither
 * is expressible as a page: a team is a set of people rendered as cards on
 * whichever page wants them, and a set of FAQs is a set of answers pulled into
 * the FAQs block wherever a question comes up.
 *
 * They differ on the one switch that matters, and the difference is not a
 * preference:
 *
 *   Team has pages. The team card is a link — the whole card is stretched over
 *   the person's permalink — so a pageless team type would render a grid of
 *   cards that all 404. If a client genuinely wants no biographies, the switch
 *   in Theme Options turns them off, and the cards block is where the link
 *   would then need dealing with.
 *
 *   FAQs do not. A single page showing one answer with no question around it
 *   is not a page anybody designed, and an archive of them is the FAQs block
 *   done worse.
 *
 * They differ on categories for the same kind of reason. A set of questions
 * divides — billing, delivery, returns — and the FAQs block can be pointed at
 * one division per page, which is the whole use for the taxonomy. A team is a
 * list of people, and a site that wants it split by department is a site that
 * says so, in Theme Options.
 *
 * Both are seeded switched on, because a site that wants neither turns them
 * off in Theme Options and a site that wants them should not have to go and
 * find them. See inc/post-types.php for what "off" does and does not touch.
 *
 * @return array<int, array<string, mixed>>
 */
function bridge_seeded_post_types(): array
{
	return array(
		array(
			'singular'      => __('Team Member', 'bridge'),
			'plural'        => __('Team', 'bridge'),
			'slug'          => 'team',
			'enabled'       => true,
			'hasPages'      => true,
			'hasCategories' => false,
		),
		array(
			'singular'      => __('FAQ', 'bridge'),
			'plural'        => __('FAQs', 'bridge'),
			'slug'          => BRIDGE_FAQ_POST_TYPE,
			'enabled'       => true,
			'hasPages'      => false,
			'hasCategories' => true,
		),
	);
}

/**
 * The navigation menus a fresh Bridge site starts with.
 *
 * Created empty. A menu seeded with guesses at a site's pages is a menu an
 * operator has to audit before trusting, which is more work than building the
 * three-item menu they wanted — and on a site with sixty pages it is a menu
 * with sixty items in it.
 *
 * @return string[]
 */
function bridge_seeded_menus(): array
{
	return array(
		__('Primary Nav', 'bridge'),
		__('Footer Quick Links', 'bridge'),
		__('Footer Legal', 'bridge'),
	);
}

/**
 * The id of a navigation menu with this exact title, or 0.
 *
 * Matched on title because a title is what the operator sees and what this
 * file created. Drafts count: a menu someone started and left unpublished is
 * still that menu, and seeding a second one beside it would be the duplicate
 * this check exists to prevent.
 *
 * @param string $title Menu title.
 */
function bridge_navigation_menu_by_title(string $title): int
{
	$posts = get_posts(
		array(
			'post_type'        => 'wp_navigation',
			'post_status'      => array('publish', 'draft', 'pending', 'private'),
			'title'            => $title,
			'numberposts'      => 1,
			'fields'           => 'ids',
			'suppress_filters' => false,
		)
	);

	return $posts ? (int) $posts[0] : 0;
}

/**
 * The seed fields that may be written into a row that already exists.
 *
 * A named list rather than "every key the stored row is missing", because the
 * two ways a key can be absent are not the same thing and only one of them is
 * safe to fill in:
 *
 *   A key the theme has since added. Nobody has decided anything about it,
 *   because there was nothing to decide when the row was written. Filling it
 *   in with the seed's answer gives an existing site what a fresh activation
 *   would give it today, which is the only reading under which a new switch
 *   ships to every site rather than to new ones.
 *
 *   A key whose absence the sanitiser already reads as an answer. `hasPages`
 *   is the one: a record written before that switch existed described a public
 *   type, and the sanitiser says so. Filling that in from the seed would take
 *   the archives off a live site's FAQ type on the next activation — which is
 *   the precise thing `test_a_row_with_no_pages_field_keeps_its_pages` exists
 *   to stop.
 *
 * So a field is listed here when it is new, and comes off the list once the
 * sanitiser has an opinion about its absence worth keeping.
 *
 * @return string[]
 */
function bridge_seed_backfill_fields(): array
{
	return array('hasCategories');
}

/**
 * Add the seeded content types to the record, keeping everything already in it.
 *
 * Appended rather than assigned, and matched on slug: an operator who renamed
 * "Team" to "Our People" keeps their name, because the slug is what identifies
 * a type and the labels are theirs. An operator who deleted the FAQ type
 * outright gets it back on the next activation — which is the honest reading
 * of activating a theme that ships an FAQs block.
 *
 * The one thing it writes into a row that already exists is a field from the
 * list above, and only when the *stored* row does not hold it. Stored, not
 * resolved: `bridge_declared_post_types()` has been through the sanitiser,
 * which fills every field in, so asking it what a row is missing always
 * answers "nothing". The option is the only place the difference survives.
 */
function bridge_seed_post_types(): void
{
	$declared = bridge_declared_post_types();
	$slugs    = array_column($declared, 'slug');
	$stored   = bridge_stored_post_types();
	$backfill = bridge_seed_backfill_fields();
	$changed  = false;

	foreach (bridge_seeded_post_types() as $seed) {
		$index = array_search($seed['slug'], $slugs, true);

		if (false === $index) {
			$declared[] = $seed;
			$changed    = true;
			continue;
		}

		$row = $stored[$seed['slug']] ?? array();

		foreach ($backfill as $field) {
			if (! array_key_exists($field, $seed) || array_key_exists($field, $row)) {
				continue;
			}

			// The sanitiser's reading of the absence may already match the
			// seed — an unchanged write would still bump the token
			// fingerprint and invalidate every token-derived asset on the
			// site, for no change anybody would see.
			if (array_key_exists($field, $declared[$index]) && $declared[$index][$field] === $seed[$field]) {
				continue;
			}

			$declared[$index][$field] = $seed[$field];
			$changed                  = true;
		}
	}

	if (! $changed) {
		return;
	}

	// A site already at the cap loses the seeds rather than the types its
	// operator declared: the sanitiser keeps the first N, and these are last.
	// That is the right way round — a seed is a suggestion, and the types
	// somebody chose are not.
	bridge_patch_tokens(array('postTypes' => $declared));
}

/**
 * The post type rows exactly as the option holds them, keyed by slug.
 *
 * Unsanitised on purpose — see `bridge_seed_post_types()`. Rows with no usable
 * slug are dropped rather than kept under an empty key: they are what the
 * sanitiser is about to discard anyway, and the only question asked of this
 * list is whether a given slug's row holds a given field.
 *
 * @return array<string, array<string, mixed>>
 */
function bridge_stored_post_types(): array
{
	$stored = get_option(BRIDGE_TOKENS_OPTION, array());
	$rows   = is_array($stored) && isset($stored['postTypes']) && is_array($stored['postTypes'])
		? $stored['postTypes']
		: array();

	$by_slug = array();

	foreach ($rows as $row) {
		if (! is_array($row) || empty($row['slug']) || ! is_string($row['slug'])) {
			continue;
		}

		$by_slug[$row['slug']] = $row;
	}

	return $by_slug;
}

/**
 * Create the seeded navigation menus that are not already there.
 */
function bridge_seed_navigation_menus(): void
{
	foreach (bridge_seeded_menus() as $title) {
		if (bridge_navigation_menu_by_title($title) > 0) {
			continue;
		}

		wp_insert_post(
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => $title,
				// An empty menu, not an empty string of block markup: the
				// Navigation block reads no content as "nothing here yet" and
				// offers to add items, which is what an operator wants to see.
				'post_content' => '',
			)
		);
	}
}

/**
 * Point the header and footer slots at the menus just created.
 *
 * Only slots that are empty. A site whose operator already chose a menu for one
 * of these keeps it — re-activating a theme is not a request to redecorate.
 *
 * The footer's two columns matter more than the header's slot here: the header
 * falls back to whichever menu core would pick, so an unset slot still renders
 * something. A footer column with no menu does not render at all, so without
 * this the seeded menus would exist and the footer would show one column.
 */
function bridge_seed_menu_slots(): void
{
	$header = array();
	$footer = array();

	if (bridge_header_menu_id('primary') <= 0) {
		$id = bridge_navigation_menu_by_title(__('Primary Nav', 'bridge'));

		if ($id > 0) {
			$header['primary'] = $id;
		}
	}

	if (bridge_footer_menu_id('quick') <= 0) {
		$id = bridge_navigation_menu_by_title(__('Footer Quick Links', 'bridge'));

		if ($id > 0) {
			$footer['quick'] = $id;
		}
	}

	if (bridge_footer_menu_id('legal') <= 0) {
		$id = bridge_navigation_menu_by_title(__('Footer Legal', 'bridge'));

		if ($id > 0) {
			$footer['legal'] = $id;
		}
	}

	$patch = array();

	if ($header) {
		$patch['header'] = array('menus' => $header);
	}

	if ($footer) {
		$patch['footer'] = array('menus' => $footer);
	}

	// One write rather than up to three: each one re-sanitises the whole
	// record and bumps the token fingerprint, which invalidates every
	// token-derived asset on the site.
	if ($patch) {
		bridge_patch_tokens($patch);
	}
}

/**
 * Everything the theme seeds on activation, in the order it has to happen.
 *
 * One callback rather than three hooks: the header and footer cannot be
 * pointed at menus until the menus exist, and expressing that as hook
 * priorities would be hiding a sequence behind three numbers.
 */
function bridge_seed_on_activation(): void
{
	bridge_seed_post_types();
	bridge_seed_navigation_menus();
	bridge_seed_menu_slots();
}
add_action('after_switch_theme', 'bridge_seed_on_activation');
