<?php

/**
 * Bridge — the site's locations.
 *
 * A business with more than one site — practices, offices, branches — keeps
 * the list once, in Site Options, and every place that shows it reads it from
 * there: the Locations block, the Hybrid footer's locations bar, and (when its
 * source is set to it) the header's Locations dropdown. Change an email
 * address once and it is right everywhere.
 *
 * Each location is a name, an address and postcode, a phone number, an email
 * address and an optional link to the location's own page. Only the name is
 * required; a row without one is not a location.
 *
 * The markup lives here rather than in the block's render.php, so the block
 * and the footer cannot drift apart: both call bridge_locations_list() and
 * bridge_locations_band().
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The fields one location has, in the order they are edited.
 *
 * @return array<string, string> Field key => label.
 */
function bridge_location_fields(): array
{
	return array(
		'name'     => __('Location name', 'bridge'),
		'address'  => __('Address', 'bridge'),
		'postcode' => __('Postcode', 'bridge'),
		'phone'    => __('Phone', 'bridge'),
		'email'    => __('Email', 'bridge'),
		'url'      => __('Page link', 'bridge'),
		// Read only by the Location Cards block. Optional, so a site that
		// never fills them in sees nothing new anywhere.
		'hours'      => __('Opening hours', 'bridge'),
		'directions' => __('Directions link', 'bridge'),
		'image'      => __('Photo', 'bridge'),
	);
}

/**
 * Reduce one submitted location to its fields, cleaned.
 *
 * @param mixed $raw A row from a form, a block attribute or the stored option.
 * @return array<string, string>
 */
function bridge_sanitize_location($raw): array
{
	$raw = is_array($raw) ? $raw : array();

	return array(
		'name'     => sanitize_text_field((string) ($raw['name'] ?? '')),
		'address'  => sanitize_textarea_field((string) ($raw['address'] ?? '')),
		'postcode' => sanitize_text_field((string) ($raw['postcode'] ?? '')),
		'phone'    => sanitize_text_field((string) ($raw['phone'] ?? '')),
		'email'    => sanitize_email((string) ($raw['email'] ?? '')),
		'url'      => esc_url_raw(trim((string) ($raw['url'] ?? ''))),
		'hours'      => sanitize_textarea_field((string) ($raw['hours'] ?? '')),
		'directions' => esc_url_raw(trim((string) ($raw['directions'] ?? ''))),
		// An attachment id, kept as a string like the other fields.
		'image'      => (string) (absint($raw['image'] ?? 0) ?: ''),
	);
}

/**
 * Clean a list of locations, dropping any without a name.
 *
 * @param mixed $raw List of rows.
 * @return array<int, array<string, string>>
 */
function bridge_sanitize_locations($raw): array
{
	$clean = array();

	foreach (is_array($raw) ? $raw : array() as $row) {
		$location = bridge_sanitize_location($row);

		if ('' !== $location['name']) {
			$clean[] = $location;
		}
	}

	return $clean;
}

/**
 * The locations saved in Site Options.
 *
 * @return array<int, array<string, string>>
 */
function bridge_site_locations(): array
{
	if (! function_exists('bridge_get_site_options')) {
		return array();
	}

	return bridge_sanitize_locations(bridge_get_site_options()['locations'] ?? array());
}

/**
 * The classes and inline style for a locations band.
 *
 * Both colours are palette slugs, never hex, so a rebrand repaints the band.
 * An empty background is no colour of its own — the page's, or the footer's.
 * The text turns light on a dark background; CSS cannot ask whether a colour
 * is light, so it is decided here.
 *
 * @param array<string, mixed> $options background, borderColor, nameSize.
 * @return array{0: string, 1: string} Classes, then inline style.
 */
function bridge_locations_band(array $options): array
{
	$palette = (array) (bridge_get_tokens()['brand']['palette'] ?? array());
	$classes = array('bridge-locations');
	$style   = '';

	// The site's heading weight, which has no custom property of its own.
	$weight = (string) (bridge_get_tokens()['typography']['headingWeight'] ?? '');

	if (preg_match('/^[1-9]00$/', $weight)) {
		$style .= '--bridge-locations-name-weight:' . $weight . ';';
	}

	if ('large' === ($options['nameSize'] ?? 'x-large')) {
		$classes[] = 'bridge-locations--name-large';
	}

	$background = sanitize_key((string) ($options['background'] ?? ''));

	if ('' !== $background && isset($palette[$background]['color'])) {
		$style    .= sprintf('--bridge-locations-bg: var(--wp--preset--color--%s);', $background);
		$classes[] = 'bridge-locations--has-background';
		$classes[] = bridge_is_light_color((string) $palette[$background]['color'])
			? 'bridge-locations--on-light'
			: 'bridge-locations--on-dark';
	}

	$border = sanitize_key((string) ($options['borderColor'] ?? ''));

	if ('' !== $border && isset($palette[$border])) {
		$style    .= sprintf('--bridge-locations-border: var(--wp--preset--color--%s);', $border);
		$classes[] = 'bridge-locations--has-border';
	}

	return array(implode(' ', $classes), $style);
}

/**
 * The list of locations, as markup.
 *
 * Each is the name — a link when it has a page — with its details beside it:
 * the address and postcode when they are asked for, then the phone and email.
 *
 * @param array<int, array<string, string>> $locations    Cleaned locations.
 * @param bool                              $show_address Include the address and postcode.
 */
function bridge_locations_list(array $locations, bool $show_address = false): string
{
	$items = '';

	foreach ($locations as $location) {
		$name = (string) ($location['name'] ?? '');

		if ('' === $name) {
			continue;
		}

		$url   = (string) ($location['url'] ?? '');
		$title = '' !== $url
			? sprintf('<a class="bridge-locations__link" href="%s">%s</a>', esc_url($url), esc_html($name))
			: esc_html($name);

		$details = '';

		if ($show_address) {
			$lines = array_filter(array_map('trim', preg_split('/\R/', (string) ($location['address'] ?? '')) ?: array()));

			if ('' !== trim((string) ($location['postcode'] ?? ''))) {
				$lines[] = trim((string) $location['postcode']);
			}

			if ($lines) {
				$details .= sprintf(
					'<span class="bridge-locations__detail bridge-locations__address">%s</span>',
					implode(', ', array_map('esc_html', $lines))
				);
			}
		}

		$phone = (string) ($location['phone'] ?? '');

		if ('' !== $phone) {
			// Dialled from the digits, written as typed — the footer's rule.
			$dial     = preg_replace('/[^0-9+]/', '', $phone);
			$details .= '' !== $dial
				? sprintf('<a class="bridge-locations__detail" href="%s">%s</a>', esc_url('tel:' . $dial), esc_html($phone))
				: sprintf('<span class="bridge-locations__detail">%s</span>', esc_html($phone));
		}

		$email = sanitize_email((string) ($location['email'] ?? ''));

		if ('' !== $email) {
			// sanitize_email() has reduced it to characters that cannot break
			// out of the attribute; antispambot() only adds entities.
			$details .= sprintf(
				'<a class="bridge-locations__detail" href="mailto:%1$s">%1$s</a>',
				antispambot($email) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — see above.
			);
		}

		$items .= sprintf(
			'<li class="bridge-locations__item"><p class="bridge-locations__name">%s</p>%s</li>',
			$title,
			'' !== $details ? '<div class="bridge-locations__details">' . $details . '</div>' : ''
		);
	}

	if ('' === $items) {
		return '';
	}

	return '<ul class="bridge-locations__list" role="list">' . $items . '</ul>';
}

/**
 * The Site Options locations as header dropdown links: name and page link.
 *
 * Only locations with a page link — a dropdown row has to go somewhere.
 *
 * @return array<int, array{label: string, url: string}>
 */
function bridge_site_locations_links(): array
{
	$links = array();

	foreach (bridge_site_locations() as $location) {
		if ('' !== $location['url']) {
			$links[] = array('label' => $location['name'], 'url' => $location['url']);
		}
	}

	return $links;
}

/**
 * Where "Get directions" goes for a location.
 *
 * The location's own directions link when it has one; otherwise a Google Maps
 * search for its name, address and postcode; otherwise nothing, for a
 * location with no address to search for.
 *
 * @param array<string, string> $location A sanitised location.
 * @return string
 */
function bridge_location_directions_url(array $location): string
{
	if ('' !== ($location['directions'] ?? '')) {
		return (string) $location['directions'];
	}

	$where = trim(implode(', ', array_filter(array(
		(string) ($location['name'] ?? ''),
		preg_replace('/\s*\n\s*/', ', ', (string) ($location['address'] ?? '')),
		(string) ($location['postcode'] ?? ''),
	))));

	if ('' === trim((string) ($location['address'] ?? '') . (string) ($location['postcode'] ?? ''))) {
		return '';
	}

	return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($where);
}

/**
 * A solid phone or envelope for the card's contact lines.
 *
 * Drawn here rather than taken from the icon library, whose icons are all
 * outlines at the site's stroke weight: these are filled, and only this block
 * uses them.
 *
 * @param string $name 'phone' or 'mail'.
 * @return string
 */
function bridge_location_card_icon(string $name): string
{
	$paths = array(
		'phone' => 'M6 2.5h3.2a1 1 0 0 1 .93.63l1.8 4.5a1 1 0 0 1-.41 1.23l-1.97 1.18a12 12 0 0 0 4.41 4.41l1.18-1.97a1 1 0 0 1 1.23-.41l4.5 1.8a1 1 0 0 1 .63.93V18a3.5 3.5 0 0 1-3.5 3.5A16 16 0 0 1 2.5 6 3.5 3.5 0 0 1 6 2.5z',
		'mail'  => 'M4 4h16a2 2 0 0 1 2 2v.4l-10 6.25L2 6.4V6a2 2 0 0 1 2-2zm-2 4.75 9.47 5.92a1 1 0 0 0 1.06 0L22 8.75V18a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2z',
	);

	if (! isset($paths[$name])) {
		return '';
	}

	return sprintf(
		'<svg class="bridge-location-card__icon" viewBox="0 0 24 24" width="1.1em" height="1.1em" fill="currentColor" aria-hidden="true" focusable="false"><path d="%s"/></svg>',
		esc_attr($paths[$name])
	);
}

/**
 * One location as a card: the photo with the name and address over it, the
 * opening hours, the phone and email, and two buttons.
 *
 * @param array<string, string> $location A sanitised location.
 * @param array<string, string> $args     book_label, book_url, directions_label,
 *                                        heading_level.
 * @return string
 */
function bridge_location_card_html(array $location, array $args): string
{
	$name = (string) ($location['name'] ?? '');

	if ('' === $name) {
		return '';
	}

	$level   = in_array($args['heading_level'] ?? 'h3', array('h2', 'h3', 'h4'), true) ? $args['heading_level'] : 'h3';
	$address = trim(implode(', ', array_filter(array(
		preg_replace('/\s*\n\s*/', ', ', trim((string) ($location['address'] ?? ''))),
		(string) ($location['postcode'] ?? ''),
	))));
	$image   = (int) ($location['image'] ?? 0);
	$photo   = $image ? wp_get_attachment_image(
		$image,
		'large',
		false,
		array(
			'class'   => 'bridge-location-card__image',
			'alt'     => '',
			'loading' => 'lazy',
			'sizes'   => '(min-width: 48rem) 50vw, 100vw',
		)
	) : '';

	$head = sprintf(
		'<div class="bridge-location-card__head%1$s">%2$s<div class="bridge-location-card__title"><%3$s class="bridge-location-card__name">%4$s</%3$s>%5$s</div></div>',
		'' !== $photo ? ' has-image' : '',
		$photo, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — core markup.
		$level,
		esc_html($name),
		'' !== $address ? '<p class="bridge-location-card__address">' . esc_html($address) . '</p>' : ''
	);

	$body = '';

	if ('' !== ($location['hours'] ?? '')) {
		$body .= sprintf(
			'<div class="bridge-location-card__hours"><p class="bridge-location-card__label">%1$s</p><p>%2$s</p></div>',
			esc_html__('Opening hours', 'bridge'),
			nl2br(esc_html((string) $location['hours']))
		);
	}

	$contact = '';

	if ('' !== ($location['phone'] ?? '')) {
		$contact .= sprintf(
			'<li>%1$s<a href="tel:%2$s"><span class="screen-reader-text">%3$s </span>%4$s</a></li>',
			bridge_location_card_icon('phone'),
			esc_attr(preg_replace('/[^0-9+]/', '', (string) $location['phone'])),
			esc_html__('Phone:', 'bridge'),
			esc_html((string) $location['phone'])
		);
	}

	if ('' !== ($location['email'] ?? '')) {
		$contact .= sprintf(
			'<li>%1$s<a href="mailto:%2$s"><span class="screen-reader-text">%3$s </span>%4$s</a></li>',
			bridge_location_card_icon('mail'),
			esc_attr(antispambot((string) $location['email'])),
			esc_html__('Email:', 'bridge'),
			esc_html(antispambot((string) $location['email']))
		);
	}

	if ('' !== $contact) {
		$body .= '<ul class="bridge-location-card__contact">' . $contact . '</ul>';
	}

	$book       = '' !== ($args['book_url'] ?? '') ? (string) $args['book_url'] : (string) ($location['url'] ?? '');
	$directions = bridge_location_directions_url($location);
	$buttons    = '';

	if ('' !== $book) {
		$buttons .= sprintf(
			'<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="%1$s">%2$s<span class="screen-reader-text"> %3$s</span></a></div>',
			esc_url($book),
			esc_html((string) $args['book_label']),
			/* translators: %s: location name. */
			esc_html(sprintf(__('at %s', 'bridge'), $name))
		);
	}

	if ('' !== $directions) {
		$buttons .= sprintf(
			'<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="%1$s" target="_blank" rel="noopener">%2$s<span class="screen-reader-text"> %3$s</span></a></div>',
			esc_url($directions),
			esc_html((string) $args['directions_label']),
			/* translators: %s: location name. */
			esc_html(sprintf(__('to %s (opens in a new tab)', 'bridge'), $name))
		);
	}

	if ('' !== $buttons) {
		$body .= '<div class="wp-block-buttons bridge-location-card__actions">' . $buttons . '</div>';
	}

	return sprintf(
		'<article class="bridge-location-card">%1$s<div class="bridge-location-card__body">%2$s</div></article>',
		$head, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from escaped parts.
		$body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from escaped parts.
	);
}
