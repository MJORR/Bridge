<?php

/**
 * Bridge — the site footer.
 *
 * A server-rendered block, for the reason the header is one: everything in it
 * comes from a settings record rather than from markup an editor arranges — the
 * logo, the strapline, the short description, the contact details, the social
 * accounts, three menus, the company name and the year. A static template part
 * could hold none of that, and a part per variation would be a set of files
 * that drift.
 *
 * The two records it reads answer different questions and are deliberately
 * kept apart. Theme Options owns how the footer *looks* — its ground, its
 * layout, which menu goes in which column, and the two brand images. Site
 * Options owns what the business *is* — the company name, the description, the
 * contact details and the social accounts. A rebrand touches one; a change of
 * address touches the other.
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
 * column is one of four, and filling an unconfigured one with a guess puts
 * links under a heading that has nothing to do with them.
 *
 * @param string $slot `explore`, `services` or `legal`.
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
 * collapsing it behind a hamburger would hide three links behind a button. The
 * legal row is horizontal and shorter still, and wants it even less.
 *
 * @param int    $menu        Menu post id.
 * @param string $label       Accessible name, so a screen-reader user stepping
 *                            between landmarks can tell the columns apart.
 * @param string $orientation `vertical` for a menu column, `horizontal` for
 *                            the legal row along the bottom.
 */
function bridge_footer_nav(int $menu, string $label, string $orientation = 'vertical'): string
{
	if ($menu <= 0) {
		return '';
	}

	$horizontal = 'horizontal' === $orientation;

	$attrs = array(
		'ref'         => $menu,
		// A second class on the horizontal one rather than a different first
		// class: everything the stylesheet says about a footer menu — the
		// colours, the underline on hover, the opt-out of the nav pill — is
		// true of both, and only the direction and the separators differ.
		'className'   => $horizontal
			? 'bridge-footer__nav bridge-footer__nav--inline'
			: 'bridge-footer__nav',
		'overlayMenu' => 'never',
		'ariaLabel'   => $label,
		'layout'      => array(
			'type'           => 'flex',
			'orientation'    => $orientation,
			'justifyContent' => $horizontal ? 'right' : 'left',
			'flexWrap'       => $horizontal ? 'wrap' : 'nowrap',
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

	// Decorative in either form: the accessible name is on the link, because
	// which image is drawn is a decision this function made and not something
	// the alt text should be describing.
	//
	// Inline when it is an SVG, so the mark can be painted by the footer's own
	// colour rather than carrying the hex it was drawn in — which is the whole
	// reason a dark footer needed a second logo file in the first place. An
	// <img> for everything else.
	$image = '';

	if ($id > 0) {
		$image = bridge_inline_svg($id, array('class' => 'bridge-footer__logo-img'));
	}

	if ('' === $image && $id > 0) {
		$image = wp_get_attachment_image($id, 'full', false, array('class' => 'bridge-footer__logo-img', 'alt' => ''));
	}

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
 * The site's short description, or an empty string.
 *
 * The paragraph under the logo: a sentence or two saying what the business
 * does, which is the one thing a footer can say that the menus beside it
 * cannot. It comes from Site Options rather than from the WordPress tagline
 * because the two are different lengths of the same idea — a tagline is a
 * handful of words for a browser tab, and this is a sentence for a human.
 *
 * Line breaks are the operator's own and kept: the field is a textarea, and
 * somebody who pressed return meant it.
 */
function bridge_footer_description(): string
{
	$text = bridge_site_option('description');

	if ('' === $text) {
		return '';
	}

	return sprintf(
		'<p class="bridge-footer__description">%s</p>',
		nl2br(esc_html($text))
	);
}

/**
 * The strapline, or an empty string.
 *
 * Two pieces that belong together and arrive separately. The words are typed
 * into Site Options and set in the script face the theme installs for exactly
 * this — see BRIDGE_SCRIPT_FAMILY — because they are wording, and wording
 * should be changeable without opening a drawing program. The stroke beneath
 * them is an uploaded image, because it is a drawn mark and no font stack has
 * one.
 *
 * Either may be missing. Words with no stroke is a strapline; a stroke with no
 * words is a stray line across the footer, so the image is drawn only when
 * there is something for it to sit under.
 *
 * The stroke is decorative — `alt=""` and `aria-hidden` — which is not an
 * oversight: it is an underline, the words above it are already in the
 * document, and an operator's own alt text on the attachment may well describe
 * it for wherever else the image is used. The words themselves are ordinary
 * text and are read out like any other.
 *
 * Line breaks are the operator's own and kept: "Better websites." and "Real
 * results." are two lines in the artwork, and somebody who pressed return meant
 * the same thing.
 */
function bridge_footer_tagline(): string
{
	$text = bridge_site_option('tagline');

	if ('' === $text) {
		return '';
	}

	$swoosh = '';
	$id     = max(0, (int) (bridge_get_tokens()['brand']['swooshId'] ?? 0));

	if ($id > 0) {
		// Inline, and this is the one of the three where it matters most: the
		// stroke should be the brand's accent colour, and the accent is a
		// palette slug an operator can change. Drawn as an <img> it would be
		// whatever colour the Illustrator artboard was, and a rebrand would
		// mean re-exporting it.
		$swoosh = bridge_inline_svg($id, array('class' => 'bridge-footer__swoosh'));
	}

	if ('' === $swoosh && $id > 0) {
		$swoosh = wp_get_attachment_image(
			$id,
			'full',
			false,
			array(
				'class'       => 'bridge-footer__swoosh',
				'alt'         => '',
				'aria-hidden' => 'true',
			)
		);
	}

	return sprintf(
		'<p class="bridge-footer__tagline"><span class="bridge-footer__tagline-text">%s</span>%s</p>',
		nl2br(esc_html($text)),
		$swoosh
	);
}

/**
 * One row of the "Get in touch" column.
 *
 * Every row in that column is the same shape — an icon in the brand colour and
 * a line of text beside it — whether the line is an address, a phone number or
 * a social account. So it is built once here rather than three times in the
 * three functions below, which is also what keeps the rows aligned: the icon
 * cell is a fixed size, and a row that built its own would be a row whose text
 * starts a pixel or two off the others.
 *
 * The icon is always decoration. In every case the text beside it says what it
 * is, and an icon announced as well as its label is the same thing said twice.
 *
 * @param string $icon Pre-rendered SVG markup.
 * @param string $body Pre-escaped row content.
 */
function bridge_footer_contact_row(string $icon, string $body): string
{
	return sprintf(
		'<li class="bridge-footer__contact-item"><span class="bridge-footer__contact-icon" aria-hidden="true">%s</span><span class="bridge-footer__contact-text">%s</span></li>',
		$icon,
		$body
	);
}

/**
 * The social accounts, as labelled rows in the contact column.
 *
 * Rows rather than the row of round chips this used to be. In the four-column
 * footer the accounts sit under the address and the phone number in a column
 * headed "Get in touch", and they are the same kind of thing those are: a way
 * to reach the business. A strip of unlabelled circles beneath two labelled
 * lines reads as a different section that lost its heading — and it asks a
 * visitor to recognise a glyph where the two rows above it simply say what
 * they are.
 *
 * Icons still come from core's own set through bridge_social_icon(), so they
 * are the same artwork the Social Icons block would draw on the same site
 * rather than a second library that drifts from it.
 *
 * The link carries the network's name as its text, so nothing here needs an
 * aria-label; the "opens in a new tab" warning stays, on the link's title-free
 * accessible description, because `target="_blank"` without a word of warning
 * is the oldest complaint in the accessibility book.
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

		// A network core has no icon for would render as a row with an empty
		// cell where every other row has a mark. The account still exists; it
		// just has nothing to draw, so the row is skipped rather than drawn
		// ragged.
		if ('' === $icon) {
			continue;
		}

		$label = (string) ($networks[$slug] ?? $slug);

		$items .= bridge_footer_contact_row(
			$icon, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — core's own icon markup.
			sprintf(
				'<a class="bridge-footer__contact-link" href="%1$s" rel="noopener noreferrer" target="_blank">%2$s<span class="screen-reader-text"> %3$s</span></a>',
				esc_url($url),
				esc_html($label),
				esc_html__('(opens in a new tab)', 'bridge')
			)
		);
	}

	if ('' === $items) {
		return '';
	}

	return sprintf(
		'<ul class="bridge-footer__contact-list" role="list" aria-label="%s">%s</ul>',
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
 * being run together or parsed into parts nobody typed — and a site that
 * writes a single line, "Edinburgh, UK", gets a single line.
 *
 * Both rows use the same shape as the social ones above, so the column reads
 * as one list of ways to reach the business rather than as two blocks that
 * happen to be stacked.
 */
function bridge_footer_contact(): string
{
	$address = bridge_site_option('address');
	$phone   = bridge_site_option('phone');

	if ('' === $address && '' === $phone) {
		return '';
	}

	$rows  = '';
	$lines = '' !== $address
		? array_values(array_filter(array_map('trim', preg_split('/\R/', $address) ?: array())))
		: array();

	if ($lines) {
		$rows .= bridge_footer_contact_row(
			bridge_render_icon('map-pin', array('size' => 'medium')),
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

		$rows .= bridge_footer_contact_row(
			bridge_render_icon('phone', array('size' => 'medium')),
			'' !== $dial
				? sprintf(
					'<a class="bridge-footer__contact-link" href="%s">%s</a>',
					esc_url('tel:' . $dial),
					esc_html($phone)
				)
				// A "number" with no digits in it is not one to dial, but it
				// may still be something an operator meant to say.
				: esc_html($phone)
		);
	}

	return sprintf(
		'<address class="bridge-footer__contact"><ul class="bridge-footer__contact-list" role="list">%s</ul></address>',
		$rows
	);
}

/**
 * The footer's background image, as a CSS `url()`, or an empty string.
 *
 * A full-size URL rather than a sized one. The image runs the whole width of
 * the footer — it is a horizon, not a photograph in a column — so on a wide
 * screen every intermediate size is already too small, and an image scaled up
 * to fill a band is the one case where the largest file is also the right one.
 *
 * Empty covers both "none chosen" and "chosen, then deleted from the media
 * library". The caller has one thing to test, and either way there is no image
 * to draw, so the footer stays the flat colour it was.
 */
function bridge_footer_image(): string
{
	$id = (int) bridge_site_option('footer_image');

	if ($id <= 0) {
		return '';
	}

	$url = wp_get_attachment_image_url($id, 'full');

	if (! is_string($url) || '' === $url) {
		return '';
	}

	// Quoted, because a URL is allowed to contain characters — brackets, a
	// space from a filename nobody renamed — that end a bare `url()` early and
	// leave the declaration invalid.
	return sprintf('url("%s")', esc_url($url));
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
