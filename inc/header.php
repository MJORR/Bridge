<?php

/**
 * Bridge — the site header.
 *
 * The header is one server-rendered block, not a family of template parts.
 * Three layouts, a top bar, a logo, a menu and a button, all resolved from the
 * design system at render time.
 *
 * That shape is deliberate. A block theme renders template parts twice: PHP
 * renders them for visitors, and the editor renders them again in JavaScript
 * from the REST API. Anything decided in a PHP filter — which part to load,
 * which body class to add, which custom property to print — exists only in the
 * first of those, so the editor drifts from the front end the moment a setting
 * does any work. Rendering the whole header from one PHP file removes the
 * second renderer: the editor previews this block through the same code path,
 * so what an operator sees while editing is what ships.
 *
 * The cost is that the header is no longer assembled from blocks in the Site
 * Editor. For this theme that is the point — the design system owns the
 * header, and menus stay editable where menus are edited.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * How much of the header's padding survives at the narrow end.
 *
 * A header is one of the few places where a desktop measurement cannot simply
 * be reused on a phone: the padding an operator picks to frame a logo on a
 * wide screen is the same number of pixels on a 375px viewport, where it is a
 * far larger share of what the visitor can see. So the chosen value is the
 * value at full width, and narrow viewports interpolate down to a fraction of
 * it.
 *
 * A flat fraction rather than the spacing scale's taper, because this value is
 * a free number an operator typed rather than a step whose position on a scale
 * says how big it is meant to feel. Where the interpolation starts and stops is
 * not decided here at all — that is BRIDGE_FLUID_MIN_VW and the site's wide
 * width, shared with the type scale and every spacing preset.
 */
define('BRIDGE_HEADER_PADDING_MIN_SCALE', 0.6);

/**
 * Compile the header's vertical padding into a fluid length.
 *
 * A clamp rather than a media query: the header has no breakpoint of its own,
 * and a padding that jumps at 782px reads as a bug on a tablet held sideways.
 * The clamp itself is built by bridge_fluid_clamp(), the same function behind
 * every spacing preset — this decides only how far the value falls, never how
 * it gets there.
 *
 * @param int $padding The chosen full-width padding, in pixels.
 * @return string A CSS length, ready to publish as a custom property.
 */
function bridge_header_padding_block(int $padding): string
{
	// Zero is flush, and a clamp that interpolates between nothing and nothing
	// is three ways of writing 0.
	if ($padding <= 0) {
		return '0';
	}

	$max_rem = $padding / 16;

	return bridge_fluid_clamp(
		$max_rem * BRIDGE_HEADER_PADDING_MIN_SCALE,
		$max_rem,
		(float) bridge_get_tokens()['layout']['wideSize']
	);
}

/**
 * Whether the header being rendered sits over the page rather than above it.
 *
 * The two answers the header block itself asks, minus the per-instance
 * attribute: what the template asks for, then what Theme Options says. A
 * caller outside the header — the hero slider, which has to know whether the
 * header is taking window height away from it or floating over it — has no
 * instance to ask about, only the page it is being rendered into.
 */
function bridge_header_overlays(): bool
{
	$background = bridge_template_header_background();

	if (! in_array($background, array('solid', 'transparent'), true)) {
		$background = (string) bridge_get_tokens()['header']['background'];
	}

	return 'transparent' === $background;
}

/**
 * A server-side estimate of the header's rendered height, as a CSS length.
 *
 * header.js measures the real thing and publishes it as `--bridge-header-height`,
 * which is exact and counts a top bar without being told about one, because it
 * measures the header rather than adding up what is inside it. But it is a
 * deferred script, so its answer arrives after the first paint — and anything
 * sized against the header would visibly jump at that moment.
 *
 * So this is the value that holds until then: the same sum `_header.scss`
 * already falls back to, one row of padding above and below whichever is
 * taller — the logo or the 44px minimum touch target — plus the utility strip
 * when there is one. Consumers name it as the fallback inside
 * `var(--bridge-header-height, …)`, so the measurement supersedes it the
 * moment it exists.
 *
 * The top-bar term is the one approximation here: its height is a line of
 * small text whose leading depends on the font that ends up loading, and that
 * is not knowable server-side. Being a few pixels out for one paint is the
 * cost of not being ~40px out for one paint.
 *
 * @return string A CSS length expression.
 */
function bridge_header_height_estimate(): string
{
	$header = bridge_get_tokens()['header'];

	$logo    = max(1, (int) ($header['logo']['height'] ?? 40));
	$padding = bridge_header_padding_block((int) $header['paddingBlock']);

	$row = sprintf('max(%dpx, 2.75rem)', $logo);

	// A flush header returns '0' — a bare number, which cannot be added to a
	// length inside calc(). There is nothing to add anyway.
	if ('0' !== $padding) {
		$row .= sprintf(' + 2 * (%s)', $padding);
	}

	$parts = array($row);

	// The strip renders only when it has a menu to put in it, so the estimate
	// asks the same question the block does rather than trusting the toggle.
	if (! empty($header['topBar']) && bridge_header_menu_id('utility') > 0) {
		$parts[] = '2 * var(--wp--preset--spacing--20) + var(--wp--preset--font-size--small, 0.875rem) * 1.6 + 1px';
	}

	return 'calc(' . implode(' + ', $parts) . ')';
}

/**
 * Every navigation menu on the site, for the options page to choose from.
 *
 * Menus are `wp_navigation` posts in a block theme, not the classic nav-menu
 * objects, so this is a post query rather than wp_get_nav_menus().
 *
 * @return array<int, array<string, mixed>> Id and title, oldest first.
 */
function bridge_navigation_menus(): array
{
	$menus = array();

	$posts = get_posts(
		array(
			'post_type'        => 'wp_navigation',
			'post_status'      => array('publish', 'draft'),
			'numberposts'      => 100,
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'suppress_filters' => false,
		)
	);

	foreach ($posts as $post) {
		$menus[] = array(
			'id'    => (int) $post->ID,
			'title' => '' !== trim($post->post_title)
				? $post->post_title
				/* translators: %d: menu post id. */
				: sprintf(__('Menu %d', 'bridge'), (int) $post->ID),
		);
	}

	return $menus;
}

/**
 * The menu chosen for a header slot, if it still exists.
 *
 * Validated here rather than in the sanitiser: a menu deleted after it was
 * chosen should fall back quietly, not rewrite the stored setting. 0 means no
 * choice — callers decide whether that leaves core to pick or renders nothing.
 *
 * @param string $slot `primary` or `utility`.
 */
function bridge_header_menu_id(string $slot): int
{
	$id = (int) (bridge_get_tokens()['header']['menus'][$slot] ?? 0);

	if ($id <= 0 || 'wp_navigation' !== get_post_type($id)) {
		return 0;
	}

	return $id;
}

/**
 * The templates whose header overlays what follows it.
 *
 * A landing page opens on a hero and the header belongs over it, not above
 * it. That is a fact about the template, not about the site, so it is stated
 * here rather than saved as a setting an operator has to keep in step.
 *
 * This list is the reason there is one header template part instead of two.
 * A part cannot be handed attributes by the template that includes it, so
 * every header variant used to need its own part — two one-line files saying
 * the same thing twice, both of them turning up in the Site Editor's parts
 * list as though an operator were meant to choose between them. Naming the
 * templates here keeps the part single and the choice out of their way.
 *
 * @return string[] Template slugs, without the theme prefix.
 */
function bridge_overlay_header_templates(): array
{
	return array('page-landing');
}

/**
 * The header background the template being rendered asks for.
 *
 * WordPress records which block template it resolved in a global, which is
 * how the admin bar knows what "Edit template" should open. The header reads
 * the same global rather than sniffing the queried object: the template is
 * the thing that decided to overlay its header, so the template is what the
 * question should be asked about.
 *
 * @return string `transparent`, or an empty string for "no opinion".
 */
function bridge_template_header_background(): string
{
	global $_wp_current_template_id;

	// Unset on any request that did not resolve a block template — a REST
	// preview, most notably, which is why the editor is told separately.
	if (! is_string($_wp_current_template_id) || '' === $_wp_current_template_id) {
		return '';
	}

	// Ids are `theme//slug`; a child theme changes the first half and not the
	// second, so match on the slug alone.
	$parts = explode('//', $_wp_current_template_id);
	$slug  = (string) end($parts);

	return in_array($slug, bridge_overlay_header_templates(), true) ? 'transparent' : '';
}

/**
 * Resolve the contrast treatment for the current header.
 *
 * A solid header sits on a known palette colour, so the readable foreground
 * can be worked out. A transparent one sits on whatever is beneath it — often
 * a photograph, whose brightness cannot be known server-side — which is why
 * `auto` resolves to light there: heroes are dark far more often than not,
 * and an operator who needs otherwise says so explicitly.
 *
 * @param string $background Background treatment being rendered, when a
 *                           template overrides the configured one.
 * @return string `light` or `dark`.
 */
function bridge_header_contrast(string $background = ''): string
{
	$header = bridge_get_tokens()['header'];

	if ('' === $background) {
		$background = $header['background'];
	}

	if ('auto' !== $header['contrast']) {
		return $header['contrast'];
	}

	if ('transparent' === $background) {
		return 'light';
	}

	$palette = bridge_get_tokens()['brand']['palette'];
	$hex     = $palette[$header['backgroundColor']]['color'] ?? '#ffffff';

	return bridge_is_light_color($hex) ? 'dark' : 'light';
}

/**
 * Which logo a scrolled sticky header wants.
 *
 * A sticky header goes solid on the site's own background once it leaves the
 * top of the page — that rule lives in the stylesheet and applies whatever the
 * header sat on a moment earlier. So the ground under the logo at that point
 * is one known colour, and the question is only whether it is light.
 *
 * Deliberately ignores the header's `contrast` override: the scrolled state
 * already overrides colour the same way, so a forced treatment describes the
 * header at rest, not after it has changed ground.
 *
 * @return string `light` or `dark`, matching bridge_header_contrast().
 */
function bridge_header_scrolled_contrast(): string
{
	$palette = bridge_get_tokens()['brand']['palette'];
	$hex     = (string) ($palette['background']['color'] ?? '#ffffff');

	return bridge_is_light_color($hex) ? 'dark' : 'light';
}

/**
 * The light logo's attachment ID, or 0 when the site has not uploaded one.
 *
 * The light variant only, never a fallback to the dark one. A caller asks for
 * this because it has a dark ground to put a logo on, and the dark logo on a
 * dark ground is not a degraded answer — it is an invisible one. A site with
 * no light logo is better served by whatever neutral thing the caller draws
 * instead, and by an operator uploading the light variant.
 *
 * Core's `custom_logo` is not consulted for the same reason: the Customizer
 * stores one logo and says nothing about which ground it was drawn for.
 *
 * @return int Attachment ID, or 0.
 */
function bridge_light_logo_id(): int
{
	$tokens = bridge_get_tokens();

	return max(0, (int) ($tokens['header']['logo']['lightId'] ?? 0));
}

/**
 * The header's site logo, or the site name when no logo is set.
 *
 * Two images of one wordmark: a dark logo drawn for a light ground and a light
 * one for a dark ground. Which is wanted is a question about the header's
 * background, and bridge_header_contrast() has already answered it — a header
 * that needs light text needs the light logo. Either image alone is a complete
 * answer; the pair is an upgrade, not a requirement.
 *
 * Reads the design system's logo before core's `custom_logo`. The options page
 * has always offered a logo picker; until the header was rendered here it
 * pointed at a setting nothing read, because `core/site-logo` only ever knew
 * about the Customizer's copy. Core's value is still honoured as a fallback so
 * a site configured the usual way keeps working.
 *
 * Only a sticky header prints both images. Its ground changes during the
 * page's life rather than at render time, so the choice has to be left to CSS;
 * every other header knows what it is sitting on now, and shipping the image
 * that lost is a download nobody ever sees.
 *
 * @param array<string, mixed> $header     The header token group.
 * @param string               $background Background treatment being rendered.
 */
function bridge_header_logo(array $header, string $background): string
{
	$home = esc_url(home_url('/'));
	$name = get_bloginfo('name', 'display');

	$id       = (int) $header['logo']['id'];
	$light_id = (int) $header['logo']['lightId'];

	if ($id <= 0) {
		$id = (int) get_theme_mod('custom_logo');
	}

	// A site that uploaded only the light variant still has a logo. Promoting
	// it to the main image here means nothing below has to special-case it.
	if ($id <= 0) {
		$id       = $light_id;
		$light_id = 0;
	}

	// The same file chosen twice is one logo, not a pair, and swapping it for
	// itself would only cost a second request.
	if ($light_id === $id) {
		$light_id = 0;
	}

	$fallback = sprintf(
		'<a class="bridge-header__logo bridge-header__logo--text" href="%s" rel="home">%s</a>',
		$home,
		esc_html($name)
	);

	if ($id <= 0) {
		return $fallback;
	}

	// Sticky is the only header whose ground moves under it, so it is the only
	// one that needs both images in the markup.
	$dual = $light_id > 0 && ! empty($header['sticky']);

	if (! $dual) {
		if ($light_id > 0 && 'light' === bridge_header_contrast($background)) {
			$id = $light_id;
		}

		$light_id = 0;
	}

	// Both images are decorative: the accessible name sits on the link, below.
	$image = wp_get_attachment_image($id, 'full', false, array('class' => 'bridge-header__logo-img', 'alt' => ''));

	if ('' === $image) {
		return $fallback;
	}

	$light = $light_id > 0
		? wp_get_attachment_image($light_id, 'full', false, array('class' => 'bridge-header__logo-light', 'alt' => ''))
		: '';

	// An attachment deleted since it was chosen leaves one image and no swap.
	if ('' === $light) {
		$dual = false;
	}

	// The name is on the link rather than on an image because which image is
	// visible can change after the page has loaded, and alt text inside a
	// `display: none` image is announced to nobody.
	return sprintf(
		'<a class="bridge-header__logo%s" href="%s" rel="home" aria-label="%s">%s%s</a>',
		$dual ? ' bridge-header__logo--dual' : '',
		$home,
		esc_attr($name),
		$image,
		$light
	);
}

/**
 * Render a navigation menu through core.
 *
 * Built as a block rather than by hand so the header's menus get the same
 * submenu behaviour, overlay and accessibility as any other navigation. With
 * no menu chosen the `ref` is left off and core falls back to its own
 * selection, which is what a site with exactly one menu wants.
 *
 * @param int    $menu    Menu post id, or 0 to let core choose.
 * @param string $justify Flex justification: `left`, `center` or `right`.
 * @param array<string, mixed> $extra Additional block attributes.
 */
function bridge_header_nav(int $menu, string $justify, array $extra = array()): string
{
	$attrs = array_merge(
		array(
			'className'   => 'bridge-nav bridge-nav--primary',
			'overlayMenu' => 'mobile',
			'layout'      => array(
				'type'          => 'flex',
				'justifyContent' => $justify,
				'flexWrap'      => 'wrap',
			),
		),
		$extra
	);

	if ($menu > 0) {
		$attrs['ref'] = $menu;
	}

	return do_blocks('<!-- wp:navigation ' . wp_json_encode($attrs) . ' /-->');
}

/**
 * Append the header's call to action to a rendered navigation, as its last row.
 *
 * The mobile panel is core's navigation block, and there is no way into it from
 * outside: the sheet is a dialog with its own focus trap, so a button rendered
 * beside the nav — which is where the header's call to action lives — cannot be
 * moved into the open menu by CSS, and a visitor on a phone would never see it.
 * Below 600px the header hides it outright, so before this the site's one
 * conversion had no mobile home at all.
 *
 * So the item is put in the list core built. Inserted before the last `</ul>`,
 * which is the top-level container's: submenus are nested inside it and close
 * first, so the last close tag in the markup is always the outer list's,
 * whatever depth the menu happens to have. Cheaper and steadier than parsing —
 * WP_HTML_Tag_Processor can walk this document but cannot insert a node into
 * it, and the alternative is a second HTML parser for one `<li>`.
 *
 * One item, not two: it renders into the menu bar as well, where the header's
 * own button is already showing, and the stylesheet hides it above 599px. A
 * second copy of the markup would mean two links to the same page in the
 * accessibility tree at every width — this way exactly one of the two is ever
 * displayed.
 *
 * @param string $nav   Navigation markup, as returned by bridge_header_nav().
 * @param string $label The button's label. Already trimmed by the caller.
 * @param string $url   Where it goes.
 */
function bridge_header_nav_cta(string $nav, string $label, string $url): string
{
	$close = strrpos($nav, '</ul>');

	if (false === $close) {
		return $nav;
	}

	$item = sprintf(
		'<li class="wp-block-navigation-item bridge-nav__cta">' .
			'<a class="wp-block-navigation-item__content" href="%s">' .
				'<span class="wp-block-navigation-item__label">%s</span>' .
			'</a>' .
		'</li>',
		esc_url($url),
		esc_html($label)
	);

	return substr_replace($nav, $item, $close, 0);
}

/**
 * Register the header block and the script that previews it in the editor.
 *
 * A dynamic block with no `edit` implementation renders as an error in the
 * canvas, so the editor script is not optional decoration — it is what makes
 * the block previewable at all.
 */
function bridge_register_header(): void
{
	$registered = bridge_register_script(
		'bridge-header-editor',
		'header-editor.js',
		array('wp-blocks', 'wp-block-editor', 'wp-data', 'wp-element', 'wp-i18n', 'wp-server-side-render')
	);

	// The REST preview the canvas fetches resolves no template, so the block
	// cannot work out on the server that it is being drawn inside a landing
	// page. The editor knows which template is open, so it is given the list
	// and passes the answer down with the preview request — one list, stated
	// once, and a canvas that agrees with the front end.
	if ($registered) {
		wp_add_inline_script(
			'bridge-header-editor',
			'window.bridgeOverlayTemplates = ' . wp_json_encode(bridge_overlay_header_templates()) . ';',
			'before'
		);
	}

	bridge_register_block('header');
}
add_action('init', 'bridge_register_header');
