<?php

/**
 * Bridge — the site footer.
 *
 * A server-rendered block, for the reason the header is one: everything in it
 * comes from a settings record rather than from markup an editor arranges — the
 * logo, the social links, two menus, the company name and the year. A static
 * template part could hold none of that, and a part per variation would be a
 * set of files that drift.
 *
 * The two records it reads answer different questions and are deliberately
 * kept apart. Theme Options owns how the footer *looks* — its ground, its
 * layout, which menu goes in which column. Site Options owns what the business
 * *is* — the company name and the social accounts. A rebrand touches one; a
 * change of address touches the other.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Whether the footer's ground wants light text on it.
 *
 * The same question the header answers, and answered the same way, so a site
 * with a dark header and a dark footer treats them alike. There is no manual
 * override here: the header has one because a transparent header sits over a
 * hero image whose brightness nothing can compute, and a footer always sits on
 * a colour this function can read.
 *
 * @return string `light` or `dark` — the colour the *text* has to be.
 */
function bridge_footer_contrast(): string
{
	$tokens  = bridge_get_tokens();
	$slug    = (string) ($tokens['footer']['backgroundColor'] ?? 'surface');
	$palette = $tokens['brand']['palette'];
	$hex     = $palette[$slug]['color'] ?? '#ffffff';

	return bridge_is_light_color($hex) ? 'dark' : 'light';
}

/**
 * The menu chosen for a footer column, if it still exists.
 *
 * 0 for "nothing chosen", and unlike the header's primary slot that is the end
 * of it — there is no fallback to whichever menu core would pick. A footer
 * column is one of three, and filling an unconfigured one with a guess puts
 * links under a heading that has nothing to do with them.
 *
 * @param string $slot `quick` or `legal`.
 */
function bridge_footer_menu_id(string $slot): int
{
	$id = (int) (bridge_get_tokens()['footer']['menus'][$slot] ?? 0);

	if ($id <= 0 || 'wp_navigation' !== get_post_type($id)) {
		return 0;
	}

	return $id;
}

/**
 * One footer column's menu, rendered through core.
 *
 * A navigation block rather than a hand-built list, for the reason the header
 * gives: core brings the markup, the accessibility and the editing experience,
 * and a second implementation here would be a second thing to keep in step.
 *
 * `overlayMenu: never` because a footer column is already a vertical list —
 * collapsing it behind a hamburger would hide three links behind a button.
 *
 * @param int    $menu  Menu post id.
 * @param string $label Accessible name, so a screen-reader user stepping
 *                      between landmarks can tell the two columns apart.
 */
function bridge_footer_nav(int $menu, string $label): string
{
	if ($menu <= 0) {
		return '';
	}

	$attrs = array(
		'ref'         => $menu,
		'className'   => 'bridge-footer__nav',
		'overlayMenu' => 'never',
		'ariaLabel'   => $label,
		'layout'      => array(
			'type'           => 'flex',
			'orientation'    => 'vertical',
			'justifyContent' => 'left',
		),
	);

	return do_blocks('<!-- wp:navigation ' . wp_json_encode($attrs) . ' /-->');
}

/**
 * The footer's logo.
 *
 * Which of the two images is wanted is a question about the ground, not about
 * the footer: a dark footer needs the light wordmark, and the light wordmark on
 * a light footer is invisible rather than merely wrong. So this asks
 * bridge_footer_contrast() and never simply takes the light one.
 *
 * A site with only one logo uses it on both grounds — a single image is a
 * complete answer, and the pair is an upgrade. With no logo at all the site
 * name stands in, because a footer that opens on nothing reads as broken.
 *
 * Never a dual-image swap like the sticky header's: the footer's ground is
 * fixed at render time, so shipping the image that lost would be a download
 * nobody ever sees.
 */
function bridge_footer_logo(): string
{
	$header = bridge_get_tokens()['header'];
	$home   = esc_url(home_url('/'));
	$name   = get_bloginfo('name', 'display');

	$id       = (int) $header['logo']['id'];
	$light_id = (int) $header['logo']['lightId'];

	if ($id <= 0) {
		$id = (int) get_theme_mod('custom_logo');
	}

	if ('light' === bridge_footer_contrast() && $light_id > 0) {
		$id = $light_id;
	}

	// A site that uploaded only the light variant still has a logo.
	if ($id <= 0) {
		$id = $light_id;
	}

	$image = $id > 0
		// Decorative: the accessible name is on the link, because which image
		// is drawn is a decision this function made and not something the alt
		// text should be describing.
		? wp_get_attachment_image($id, 'full', false, array('class' => 'bridge-footer__logo-img', 'alt' => ''))
		: '';

	if ('' === $image) {
		return sprintf(
			'<a class="bridge-footer__logo bridge-footer__logo--text" href="%s" rel="home">%s</a>',
			$home,
			esc_html($name)
		);
	}

	return sprintf(
		'<a class="bridge-footer__logo" href="%s" rel="home" aria-label="%s">%s</a>',
		$home,
		esc_attr($name),
		$image
	);
}

/**
 * The social row, or an empty string when the site has no accounts.
 *
 * Icons come from core's own set through bridge_social_icon(), so they are the
 * same artwork the Social Icons block would draw on the same site rather than
 * a second library that drifts from it.
 *
 * Each link is named for a screen reader and marked `rel="noopener"`; the icon
 * itself is hidden from assistive technology because the label beside it in the
 * accessible name already says which network it is.
 */
function bridge_footer_social(): string
{
	$links = bridge_site_social_links();

	if (! $links) {
		return '';
	}

	$networks = bridge_social_networks();
	$items    = '';

	foreach ($links as $slug => $url) {
		$icon = bridge_social_icon($slug);

		// A network core has no icon for would render as an unlabelled empty
		// link. Skipped rather than drawn as a bullet: the site still has the
		// account, it just has nothing to draw for it.
		if ('' === $icon) {
			continue;
		}

		$label = (string) ($networks[$slug] ?? $slug);

		$items .= sprintf(
			'<li class="bridge-footer__social-item"><a class="bridge-footer__social-link" href="%1$s" rel="noopener noreferrer" target="_blank" aria-label="%2$s"><span class="bridge-footer__social-icon" aria-hidden="true">%3$s</span></a></li>',
			esc_url($url),
			/* translators: %s: social network name. */
			esc_attr(sprintf(__('%s, opens in a new tab', 'bridge'), $label)),
			$icon // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — core's own icon markup.
		);
	}

	if ('' === $items) {
		return '';
	}

	return sprintf(
		'<ul class="bridge-footer__social" role="list" aria-label="%s">%s</ul>',
		esc_attr__('Social media', 'bridge'),
		$items
	);
}

/**
 * The address and phone number, or an empty string when Site Options holds
 * neither.
 *
 * Both have been in Site Options since the theme shipped and neither reached
 * the front end: the contact details were being collected and then kept. A
 * business address in the footer is the first thing a visitor looks for when
 * they want to know whether a company is near them, and it is what the
 * Organization node in the head is asserting — a claim in the markup with
 * nothing on the page to back it up is the one shape of structured data worth
 * avoiding. See inc/schema.php.
 *
 * An <address> element, which is what it is for: the contact details of the
 * document it sits in. Not for postal addresses in general — a location
 * mentioned in an article is not one — which is why this is the only place in
 * the theme that uses it.
 *
 * The lines are the operator's own. The field says "one line per line, as it
 * would be written on an envelope", so they are printed that way rather than
 * being run together or parsed into parts nobody typed.
 */
function bridge_footer_contact(): string
{
	$address = bridge_site_option('address');
	$phone   = bridge_site_option('phone');

	if ('' === $address && '' === $phone) {
		return '';
	}

	$parts = '';
	$lines = '' !== $address
		? array_values(array_filter(array_map('trim', preg_split('/\R/', $address) ?: array())))
		: array();

	if ($lines) {
		$parts .= sprintf(
			'<p class="bridge-footer__address">%s</p>',
			implode('<br>', array_map('esc_html', $lines))
		);
	}

	if ('' !== $phone) {
		// The dialling link is built from the number rather than asked for
		// separately, which is what the field's help text promises: a phone
		// number is written to be read — "01234 567 890", "+44 (0)20 7946
		// 0000" — and `tel:` takes digits and a leading plus. Typing it twice
		// would be two chances to mistype the one that matters.
		$dial = preg_replace('/[^0-9+]/', '', $phone);

		$parts .= '' !== $dial
			? sprintf(
				'<p class="bridge-footer__phone"><a href="%s">%s</a></p>',
				esc_url('tel:' . $dial),
				esc_html($phone)
			)
			// A "number" with no digits in it is not one to dial, but it may
			// still be something an operator meant to say.
			: sprintf('<p class="bridge-footer__phone">%s</p>', esc_html($phone));
	}

	return sprintf('<address class="bridge-footer__contact">%s</address>', $parts);
}

/**
 * The copyright line.
 *
 * The year is the site's own current year, not the server's: wp_date() applies
 * the timezone set in Settings, and a site in Auckland rolls over to January
 * half a day before a server in London does.
 *
 * The company name comes from Site Options, falling back to the site title. A
 * footer that reads "© 2026" with no name is a footer that looks unfinished,
 * and every site has a title.
 */
function bridge_footer_copyright(): string
{
	$company = bridge_site_option('company');

	if ('' === $company) {
		$company = (string) get_bloginfo('name', 'display');
	}

	return sprintf(
		/* translators: 1: four-digit year, 2: company name. */
		__('© %1$s %2$s. All rights reserved.', 'bridge'),
		wp_date('Y'),
		$company
	);
}

/**
 * Register the footer block and the script that previews it in the editor.
 *
 * A dynamic block with no `edit` implementation renders as an error in the
 * canvas, so the editor script is what makes the block previewable at all —
 * the same arrangement the header block uses.
 */
function bridge_register_footer_block(): void
{
	bridge_register_script(
		'bridge-footer-editor',
		'footer-editor.js',
		array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-server-side-render')
	);

	bridge_register_block('footer');
}
add_action('init', 'bridge_register_footer_block');
