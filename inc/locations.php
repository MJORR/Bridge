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
