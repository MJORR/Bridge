<?php

/**
 * Bridge editor lockdown — closing the doors the options page does not own.
 *
 * Gating the options page is only half the job. The Site Editor's Styles
 * panel, the core pattern library and the Custom HTML block are three more
 * routes to the same design system, and each one is enough on its own to
 * produce the Frankenstein site this architecture exists to prevent.
 *
 * Everything here is conditional on bridge_lockdown_enabled(), so a build or
 * migration can switch it off wholesale with one constant.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Discard user-origin global styles entirely.
 *
 * This is the single most important filter in the theme, and the one whose
 * absence produces the classic "I saved it but nothing changed" report.
 * WordPress merges global styles by origin — default, then blocks, then
 * theme, then user — and the *user* origin wins. A single save in the Site
 * Editor's Styles panel therefore outranks everything the options page
 * compiles, permanently and invisibly.
 *
 * Stripping is global rather than per-user on purpose. Filtering it only for
 * clients would show operators a different site from the one visitors get,
 * which is a worse failure than the one being fixed.
 *
 * Values already stranded in the user record are not migrated — run
 * `wp bridge conflicts` to see what a site is about to lose and move anything
 * worth keeping into design/tokens.json first.
 *
 * @param WP_Theme_JSON_Data $theme_json User-origin data.
 * @return WP_Theme_JSON_Data
 */
function bridge_strip_user_global_styles($theme_json)
{
	if (! bridge_lockdown_enabled() || ! class_exists('WP_Theme_JSON_Data')) {
		return $theme_json;
	}

	return new WP_Theme_JSON_Data(array('version' => 3), 'custom');
}
add_filter('wp_theme_json_data_user', 'bridge_strip_user_global_styles');

/**
 * Refuse writes to the global-styles REST route while lockdown is on.
 *
 * The strip above is global, so a save in the Styles panel would otherwise
 * appear to succeed and then quietly do nothing — the same silent no-op this
 * architecture exists to eliminate, merely relocated to whoever holds the
 * keys. Failing loudly at the boundary is the honest behaviour.
 *
 * Applies to operators too. They keep the Site Editor for templates and
 * parts, which save through a different route; only the design system is
 * closed, and it is closed because it lives somewhere better.
 *
 * @param mixed           $result  Pre-empted response, or null to continue.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Request being dispatched.
 * @return mixed
 */
function bridge_block_global_styles_writes($result, $server, $request)
{
	if (null !== $result || ! bridge_lockdown_enabled()) {
		return $result;
	}

	$is_write = in_array($request->get_method(), array('POST', 'PUT', 'PATCH', 'DELETE'), true);

	if (! $is_write || ! preg_match('#^/wp/v2/global-styles/\d+#', $request->get_route())) {
		return $result;
	}

	return new WP_Error(
		'bridge_global_styles_locked',
		__('Site design is managed in Theme Options, so Global Styles cannot be saved here.', 'bridge'),
		array('status' => 403)
	);
}
add_filter('rest_pre_dispatch', 'bridge_block_global_styles_writes', 10, 3);

/**
 * Drop the core pattern library and the remote pattern directory.
 *
 * Both ship patterns carrying their own colours, type and spacing. One
 * insertion puts off-brand values into post content, where the design system
 * cannot reach them — the constraint has to be at the inserter, not the
 * stylesheet.
 *
 * Site-level rather than per-user: a curated inserter is a property of the
 * build, and operators want the same clean library clients get.
 */
function bridge_remove_core_patterns(): void
{
	if (! bridge_lockdown_enabled()) {
		return;
	}

	remove_theme_support('core-block-patterns');
}
add_action('after_setup_theme', 'bridge_remove_core_patterns', 20);

/**
 * Never fetch patterns from the WordPress.org directory.
 */
function bridge_disable_remote_patterns($load): bool
{
	return bridge_lockdown_enabled() ? false : (bool) $load;
}
add_filter('should_load_remote_block_patterns', 'bridge_disable_remote_patterns');

/**
 * The blocks a client may insert.
 *
 * An allowlist rather than a denylist: core adds blocks every release, and a
 * denylist silently lets each new one through. The list below is derived from
 * what this build actually uses plus the ordinary content set, and is
 * filterable so a client-specific build can extend it without a theme fork.
 *
 * Deliberately absent:
 *
 *   core/html       Arbitrary markup and inline styles — the single widest
 *                   hole in any locked design system.
 *   core/freeform   The classic editor, which predates every constraint here.
 *   core/legacy-widget, core/widget-group
 *                   Classic widget plumbing a block theme has no use for.
 *   core/spacer     Invites pixel-pushing where the spacing scale and
 *                   blockGap are the system's answer. The one block whose
 *                   whole purpose is to work around the design tokens.
 *   core/pullquote  A second quote block. Editors pick between them at
 *                   random and the two treatments drift apart.
 *   core/code, core/preformatted
 *                   Developer blocks on sites that publish prose.
 *   core/verse      Poetry formatting, whose whole feature is preserving the
 *                   line breaks you typed. Prose sites never ask for it.
 *   core/nextpage   The legacy paginated-post splitter, which cuts one post
 *                   across several URLs. Scrolling and archive templates
 *                   replaced it.
 *   core/calendar   The dated post-calendar widget, which brings its own
 *                   markup and matches no custom theme.
 *   core/tag-cloud  An archaic taxonomy display: clutter in the inserter, and
 *                   clutter on the page wherever it lands.
 *   core/more       The legacy teaser tag. Block themes read excerpts, so it
 *                   changes nothing an editor can see.
 *   core/video, core/audio
 *                   Self-hosted media, which is a performance problem before
 *                   it is a content one. core/embed covers the hosts people
 *                   actually use.
 *   core/file       Off by default because most sites never publish a
 *                   download. Sites that do switch it back on — this is a
 *                   default, not a rule.
 *
 * core/shortcode *is* allowed: shortcodes render through registered handlers
 * and carry no styling of their own, and excluding them breaks the form and
 * booking plugins clients genuinely need.
 *
 * core/social-links and core/search are allowed for the opposite reason —
 * near every site wants footer social icons and a search field, and leaving
 * them out means each one gets rebuilt by hand out of blocks that were not
 * meant for it.
 *
 * @return string[]
 */
function bridge_allowed_blocks(): array
{
	$core = array(
		// Text.
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/list-item',
		'core/quote',
		'core/table',
		'core/details',
		'core/footnotes',
		'core/shortcode',

		// Media.
		'core/image',
		'core/gallery',
		'core/cover',
		'core/media-text',
		'core/embed',

		// Structure.
		'core/group',
		'core/columns',
		'core/column',
		'core/buttons',
		'core/button',
		'core/separator',

		// Site furniture, used by templates and parts.
		'core/template-part',
		'core/site-title',
		'core/site-logo',
		'core/site-tagline',
		'core/navigation',
		'core/navigation-link',
		'core/navigation-submenu',
		'core/page-list',
		'core/page-list-item',
		'core/social-links',
		'core/search',

		// Post and query, used by index.html and page templates.
		'core/post-content',
		'core/post-title',
		'core/post-excerpt',
		'core/post-featured-image',
		'core/post-date',
		'core/post-terms',
		'core/post-author',
		'core/query',
		'core/query-no-results',
		'core/post-template',
		'core/query-pagination',
		'core/query-pagination-previous',
		'core/query-pagination-numbers',
		'core/query-pagination-next',
		// Used by the archive, search and single templates. Insertable as well
		// as renderable, so an operator who deletes one while editing a
		// template can put it back.
		'core/query-title',
		'core/term-description',
		'core/post-navigation-link',

		// Insertion plumbing. Patterns and synced patterns render through
		// these two; omitting them breaks the curated library itself.
		'core/pattern',
		'core/block',
	);

	// Every bridge/* block this build registers, without having to maintain a
	// second list that drifts out of date as blocks are added.
	$theme = array_filter(
		array_keys(WP_Block_Type_Registry::get_instance()->get_all_registered()),
		static function (string $name): bool {
			return str_starts_with($name, 'bridge/');
		}
	);

	$allowed = array_values(array_unique(array_merge($core, $theme)));

	/**
	 * Filters the blocks a non-operator may insert.
	 *
	 * @param string[] $allowed Block names.
	 */
	return (array) apply_filters('bridge_allowed_blocks', $allowed);
}

/**
 * Blocks that cannot be switched off from the options page.
 *
 * Not a matter of taste: without these the editor stops working rather than
 * becoming more constrained. Patterns and synced patterns render through
 * core/pattern and core/block, and a page template with no core/post-content
 * has nowhere to put the page.
 *
 * @return string[]
 */
function bridge_required_blocks(): array
{
	return array('core/pattern', 'core/block', 'core/post-content');
}

/**
 * Blocks that only ever exist inside another block.
 *
 * A block declaring `parent` or `ancestor` is never inserted on its own —
 * core offers core/list-item inside a list, never from the inserter. They
 * follow whatever their parent is doing, so the options page hides them and
 * this theme always allows them: a list with list-item switched off is a
 * broken list, not a constrained one.
 *
 * @return string[]
 */
function bridge_child_blocks(): array
{
	$names = array();

	foreach (WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type) {
		if (! empty($type->parent) || ! empty($type->ancestor)) {
			$names[] = (string) $name;
		}
	}

	return $names;
}

/**
 * Blocks a fresh install starts with switched off.
 *
 * The curated set in bridge_allowed_blocks() already leaves these out, but
 * that list is only in force while lockdown is on — and lockdown is off until
 * a site names an operator, which is exactly the window a freshly activated
 * theme sits in. Writing them to `blocks.disabled` on activation closes that
 * gap: `disabled` is honoured in both lockdown states, and it is the value the
 * Blocks tab reads, so the switches show the same answer the inserter gives.
 *
 * Each is legacy or single-purpose furniture that clutters the inserter
 * without earning its place in a block-built site:
 *
 *   core/freeform      The classic TinyMCE editor, which predates every
 *                      constraint here and emits unstyled legacy markup.
 *   core/verse         Poetry formatting with preserved line breaks.
 *   core/preformatted  Fixed-width raw text, superseded by core/code.
 *   core/nextpage      Legacy pagination that splits one post across URLs.
 *   core/calendar      The native post calendar widget.
 *   core/tag-cloud     An archaic taxonomy display.
 *
 * A default, not a rule: an operator who wants one back flips its switch, and
 * nothing here reaches into an existing site — only theme activation writes.
 *
 * @return string[]
 */
function bridge_decluttered_blocks(): array
{
	/**
	 * Filters the blocks switched off when the theme is activated.
	 *
	 * @param string[] $blocks Block names.
	 */
	return (array) apply_filters(
		'bridge_decluttered_blocks',
		array(
			'core/freeform',
			'core/verse',
			'core/preformatted',
			'core/nextpage',
			'core/calendar',
			'core/tag-cloud',
		)
	);
}

/**
 * Switch the decluttered blocks off when the theme is activated.
 *
 * Merged into whatever `disabled` already holds rather than assigned over it:
 * a site re-activating the theme keeps the blocks its operator switched off by
 * hand. Nothing is written when the list is already a subset of the stored
 * one, so a re-activation that changes nothing does not bump the token
 * fingerprint and invalidate every token-derived asset.
 */
function bridge_disable_decluttered_blocks(): void
{
	$disabled = bridge_get_tokens()['blocks']['disabled'];
	$merged   = array_values(array_unique(array_merge($disabled, bridge_decluttered_blocks())));

	sort($merged);

	if ($merged === $disabled) {
		return;
	}

	bridge_patch_tokens(array('blocks' => array('disabled' => $merged)));
}
add_action('after_switch_theme', 'bridge_disable_decluttered_blocks');

/**
 * Every insertable block, grouped into its editor category.
 *
 * What the options page draws. Each entry carries whether the theme ships it
 * on (`default`) and whether it can be switched off at all (`required`), so
 * the screen can show the operator what they are changing *from* rather than
 * just a row of switches.
 *
 * @return array<int, array<string, mixed>>
 */
function bridge_block_library(): array
{
	$defaults = bridge_allowed_blocks();
	$required = bridge_required_blocks();
	$disabled = bridge_get_tokens()['blocks']['disabled'];
	$groups   = array();

	foreach (WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type) {
		$name = (string) $name;

		// Blocks the theme places itself — the header, the page title — are
		// not the editor's to insert, so a switch for them would be a switch
		// that does nothing.
		if (isset($type->supports['inserter']) && false === $type->supports['inserter']) {
			continue;
		}

		// Child blocks are hidden — until one is switched off. A switch that is
		// never drawn is a switch that cannot be flipped back, and every off
		// state on this screen has to be reversible from this screen.
		if ((! empty($type->parent) || ! empty($type->ancestor)) && ! in_array($name, $disabled, true)) {
			continue;
		}

		$category = (string) ($type->category ?? '');

		$groups[$category ?: 'uncategorized'][] = array(
			'name'     => $name,
			'title'    => (string) ($type->title ?? '') ?: $name,
			'default'  => in_array($name, $defaults, true),
			'required' => in_array($name, $required, true),
			'theme'    => str_starts_with($name, 'bridge/'),
		);
	}

	// Core's own category order, which is the order the inserter uses — an
	// operator looking for a block should find it where the editor keeps it.
	$labels = array();

	foreach (get_default_block_categories() as $category) {
		$labels[(string) $category['slug']] = (string) $category['title'];
	}

	$library = array();

	foreach ($labels as $slug => $title) {
		if (empty($groups[$slug])) {
			continue;
		}

		usort(
			$groups[$slug],
			static function (array $a, array $b): int {
				return strcasecmp($a['title'], $b['title']);
			}
		);

		$library[] = array(
			'slug'   => $slug,
			'title'  => $title,
			'blocks' => $groups[$slug],
		);

		unset($groups[$slug]);
	}

	// Whatever is left belongs to a category core does not declare — a
	// plugin's own. Listed rather than dropped: an unlisted block is one the
	// operator cannot switch off.
	foreach ($groups as $slug => $blocks) {
		$library[] = array(
			'slug'   => $slug,
			'title'  => ucwords(str_replace('-', ' ', $slug)),
			'blocks' => $blocks,
		);
	}

	return $library;
}

/**
 * The blocks a client may insert, after the operator's switches.
 *
 * The theme's curated set is the starting point, not the answer: the options
 * page can switch anything in it off, and can switch on a block the theme
 * does not ship by default.
 *
 * @return string[]
 */
function bridge_effective_blocks(): array
{
	$blocks = bridge_get_tokens()['blocks'];

	$allowed = array_unique(
		array_merge(bridge_allowed_blocks(), $blocks['enabled'], bridge_child_blocks())
	);

	// `disabled` is subtracted last, after the child blocks are folded in, so
	// an explicit switch-off wins over the blanket allowance they get. Doing it
	// the other way round silently handed a disabled child block straight back
	// — which is how core/nextpage stayed insertable: it declares
	// `parent: core/post-content`, so it counts as a child block, and the page
	// content area is exactly where someone reaches for a page break.
	return array_values(array_diff($allowed, $blocks['disabled']));
}

/**
 * Restrict the inserter to the blocks the options page says the site uses.
 *
 * The switches answer one question — "does this site build with this block" —
 * and that is a decision about the site, not about who is editing it. So the
 * effective set applies to everyone, the operator who set it included. An
 * operator who wants a block back flips the same switch that took it away;
 * anything else makes the options page describe a site nobody is looking at.
 *
 * This is why the operator is not exempted. The curated allowlist used to
 * apply to clients only, leaving operators with the whole registry minus the
 * disabled list — so every block outside the curated set (core/html,
 * core/calendar, every plugin block) drew as *off* on the options page while
 * staying one click away in the operator's own inserter. Switching such a
 * block off writes nothing to `disabled`, because off is already its stored
 * state; for the operator that made the switch a no-op.
 *
 * @param bool|string[]           $allowed Current allowlist, or true for all.
 * @param WP_Block_Editor_Context $context Editor context.
 * @return bool|string[]
 */
function bridge_filter_allowed_blocks($allowed, $context)
{
	$disabled = bridge_get_tokens()['blocks']['disabled'];

	// Lockdown off means the curated set is not in force — but a block the
	// operator switched off is still off, because that answers a different
	// question from "is this site locked down".
	if (! bridge_lockdown_enabled()) {
		if (empty($disabled)) {
			return $allowed;
		}

		// `true` means "everything core knows about", which has to be spelled
		// out before anything can be taken away from it.
		$all = is_array($allowed)
			? $allowed
			: array_keys(WP_Block_Type_Registry::get_instance()->get_all_registered());

		return array_values(array_diff($all, $disabled));
	}

	// The navigation editor is a different vocabulary entirely — link, submenu,
	// page list. Handing it the page-content allowlist would leave whoever
	// opens it staring at an editor with nothing they can insert.
	$post_type = $context->post ? get_post_type($context->post) : '';

	if ('wp_navigation' === $post_type) {
		return array_values(
			array_diff(
				array(
					'core/navigation-link',
					'core/navigation-submenu',
					'core/page-list',
					'core/page-list-item',
					'core/home-link',
					'core/site-logo',
					'core/search',
					'core/social-links',
					'core/social-link',
					'core/spacer',
				),
				$disabled
			)
		);
	}

	return bridge_effective_blocks();
}
add_filter('allowed_block_types_all', 'bridge_filter_allowed_blocks', 10, 2);

/**
 * Trim the editor's remaining escape hatches for non-operators.
 *
 *   codeEditingEnabled           The code editor edits raw block markup, which
 *                                walks straight past the inserter allowlist.
 *   canLockBlocks                Without this, a client can unlock the
 *                                contentOnly patterns the layout families
 *                                depend on and restructure them freely.
 *   enableOpenverseMediaCategory Off-brand stock imagery, one click away.
 *
 * @param array<string, mixed>    $settings Editor settings.
 * @param WP_Block_Editor_Context $context  Editor context.
 * @return array<string, mixed>
 */
function bridge_filter_editor_settings($settings, $context): array
{
	$settings = (array) $settings;

	if (! bridge_lockdown_enabled() || bridge_current_user_is_operator()) {
		return $settings;
	}

	$settings['codeEditingEnabled']           = false;
	$settings['canLockBlocks']                = false;
	$settings['enableOpenverseMediaCategory'] = false;

	return $settings;
}
add_filter('block_editor_settings_all', 'bridge_filter_editor_settings', 10, 2);

/**
 * Report what the user-origin global styles record currently overrides.
 *
 * Used by the admin page and `wp bridge conflicts` to make the invisible
 * override visible before it is discarded.
 *
 * @return array<string, mixed> Empty when nothing is stranded.
 */
function bridge_user_global_styles_overrides(): array
{
	if (! class_exists('WP_Theme_JSON_Resolver')) {
		return array();
	}

	$post_id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();

	if (! $post_id) {
		return array();
	}

	$post = get_post($post_id);

	if (! $post instanceof WP_Post || '' === $post->post_content) {
		return array();
	}

	$data = json_decode($post->post_content, true);

	if (! is_array($data)) {
		return array();
	}

	$overrides = array_filter(
		array(
			'settings' => $data['settings'] ?? array(),
			'styles'   => $data['styles'] ?? array(),
		)
	);

	return $overrides ? array('post_id' => $post_id) + $overrides : array();
}
