<?php

/**
 * Bridge — the site's identity, in the vocabulary a search engine reads.
 *
 * One `@graph` in the head of every front-end page, holding two nodes: the
 * Organization the site is about, and the WebSite it is published as. Nothing
 * here is per-page — a page's own description is the page's job, and the block
 * that has something to say about itself says it beside its own markup, the
 * way `bridge/testimonial` and `bridge/breadcrumb` do.
 *
 * ---- Why this is worth having, and what it is not ---------------------------
 *
 * It will not draw stars, prices or an FAQ accordion into a search result.
 * Google restricted FAQ rich results to health and government sites in 2023,
 * and it has ignored an organisation's reviews of itself since 2019 — a theme
 * that promised otherwise would be selling something it cannot deliver.
 *
 * What an Organization node does is name the entity behind the site and hand
 * over the identifiers that confirm it: the logo, the phone number, the
 * address, and the social profiles that corroborate all three. That is what a
 * knowledge panel is assembled from, and it is what any consumer parsing the
 * page — Google, Bing, or a model answering a question about the business —
 * reads to work out who this is. The alternative is leaving them to infer it
 * from the footer.
 *
 * ---- Where the data comes from ---------------------------------------------
 *
 * Site Options, every field of it, and nothing is invented here. A site that
 * has not filled in a phone number publishes no `telephone` rather than an
 * empty one: an absent property is a fact not stated, an empty property is a
 * claim that the fact is nothing.
 *
 * The address is published as text rather than a PostalAddress with
 * `streetAddress`, `addressLocality` and `postalCode` broken out, because the
 * field it comes from is one textarea written "as it would be written on an
 * envelope" and there is no reliable way to tell a town from a county from a
 * building name. Splitting the field in Site Options is the upgrade path, and
 * it is what a LocalBusiness node would need before it was worth having — that
 * type also wants opening hours and coordinates, neither of which the site
 * records.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The id of a node in the graph.
 *
 * Fragment ids on the home URL, which is the convention every consumer of
 * JSON-LD expects: `#organization` is the same entity on every page of the
 * site, so a node that refers to it — the WebSite's publisher, a breadcrumb's
 * `itemListElement` — points at one identity rather than describing a fresh
 * one per page and leaving them to be reconciled.
 *
 * @param string $fragment Node name, without the hash.
 */
function bridge_schema_id(string $fragment): string
{
	return home_url('/#' . $fragment);
}

/**
 * A JSON-LD block, ready to print.
 *
 * `JSON_HEX_TAG` is the part that matters and is not decoration. Everything in
 * the graph is authored — a company name, a testimonial, a page title — and
 * PHP's json_encode leaves `<` and `>` alone by default, so a title containing
 * `</script>` would close this element and put whatever followed it into the
 * document as markup. The flag encodes both as `\u003C` / `\u003E`, which is
 * the same string to a JSON parser and inert to an HTML one.
 *
 * The other two flags are legibility rather than safety: slashes in URLs stay
 * slashes, and a name with an accent in it stays readable in the source.
 *
 * @param array<string, mixed> $data The node, or a graph of them.
 */
function bridge_schema_json(array $data): string
{
	$json = wp_json_encode(
		$data,
		JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	if (false === $json) {
		return '';
	}

	return '<script type="application/ld+json">' . $json . '</script>';
}

/**
 * The site's logo as an ImageObject, or an empty array when there is none.
 *
 * The same image the header and footer draw, resolved the same way: the design
 * system's logo first, core's `custom_logo` as the fallback for a site set up
 * through the Customizer, and the light variant for a site that uploaded only
 * that one. The dark/light pair is a rendering decision — which one reads on
 * the ground it is standing on — and the entity has one logo, so this takes
 * whichever image the site considers its primary.
 *
 * Width and height are published because Google asks for them and because they
 * are free: the attachment already knows.
 *
 * @return array<string, mixed>
 */
function bridge_schema_logo(): array
{
	$header = bridge_get_tokens()['header'];
	$id     = (int) $header['logo']['id'];

	if ($id <= 0) {
		$id = (int) get_theme_mod('custom_logo');
	}

	if ($id <= 0) {
		$id = (int) $header['logo']['lightId'];
	}

	if ($id <= 0) {
		return array();
	}

	$image = wp_get_attachment_image_src($id, 'full');

	if (! $image || empty($image[0])) {
		return array();
	}

	return array(
		'@type'  => 'ImageObject',
		'@id'    => bridge_schema_id('logo'),
		'url'    => $image[0],
		'width'  => (int) $image[1],
		'height' => (int) $image[2],
	);
}

/**
 * The Organization node: who the site is about.
 *
 * `name` prefers the trading name from Site Options over the site title, for
 * the reason the footer's copyright line does — the title is what the site is
 * called, the company name is what the business is called, and they are not
 * always the same string.
 *
 * @return array<string, mixed>
 */
function bridge_schema_organization(): array
{
	$company = bridge_site_option('company');
	$name    = '' !== $company ? $company : (string) get_bloginfo('name', 'display');

	$node = array(
		'@type' => 'Organization',
		'@id'   => bridge_schema_id('organization'),
		'name'  => $name,
		'url'   => home_url('/'),
	);

	$logo = bridge_schema_logo();

	if ($logo) {
		$node['logo'] = $logo;
		// The same image again under the property a consumer looking for a
		// picture of the entity reads. A reference rather than a second copy:
		// one ImageObject, named twice.
		$node['image'] = array('@id' => $logo['@id']);
	}

	$phone = bridge_site_option('phone');

	if ('' !== $phone) {
		$node['telephone'] = $phone;
	}

	$address = bridge_site_option('address');

	if ('' !== $address) {
		// One line, comma separated. The field is a textarea holding an
		// envelope address; the newlines are how it is laid out, not part of
		// what it says.
		$lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $address) ?: array())));

		if ($lines) {
			$node['address'] = implode(', ', $lines);
		}
	}

	/*
	 * The social profiles, which are what make the rest of this checkable.
	 *
	 * `sameAs` is the claim "this entity is also that page over there", and it
	 * is the property a search engine uses to tie a name and a logo to an
	 * account it already knows about. It is the highest-value line in the node
	 * and it costs an operator four paste operations in Site Options.
	 */
	$social = bridge_site_social_links();

	if ($social) {
		$node['sameAs'] = array_values($social);
	}

	return $node;
}

/**
 * The WebSite node: the publication, as distinct from the publisher.
 *
 * Two nodes rather than one because they are two things — the organisation
 * outlives the website, and a search result is about a page of the site rather
 * than about the company. `publisher` is the reference that joins them.
 *
 * No `potentialAction` / SearchAction. That described the sitelinks search box,
 * which Google stopped supporting in 2024, and a property no consumer reads is
 * a line of markup to keep in step with a search URL for no return.
 *
 * @return array<string, mixed>
 */
function bridge_schema_website(): array
{
	$node = array(
		'@type'      => 'WebSite',
		'@id'        => bridge_schema_id('website'),
		'url'        => home_url('/'),
		'name'       => (string) get_bloginfo('name', 'display'),
		'publisher'  => array('@id' => bridge_schema_id('organization')),
		'inLanguage' => (string) get_bloginfo('language'),
	);

	$tagline = (string) get_bloginfo('description', 'display');

	if ('' !== $tagline) {
		$node['description'] = $tagline;
	}

	return $node;
}

/**
 * Print the site's graph into the head.
 *
 * One script element holding both nodes rather than one element each: a graph
 * is how the two are said to be related, and a consumer reading two separate
 * blocks has to work out that the publisher in one is the organisation in the
 * other.
 *
 * Feeds and embeds are skipped — neither is a page a search engine reads this
 * from, and an oEmbed iframe carrying the whole site's identity would be
 * describing the host page as the business.
 */
function bridge_print_site_schema(): void
{
	if (is_feed() || is_embed()) {
		return;
	}

	$graph = array(
		bridge_schema_organization(),
		bridge_schema_website(),
	);

	/**
	 * Filters the site-wide JSON-LD graph.
	 *
	 * The hook a build uses to say something this theme cannot know: that the
	 * organisation is a LocalBusiness with opening hours, that it has a second
	 * address, that the site is a Blog as well as a WebSite. Return an empty
	 * array to print nothing at all — a site whose SEO plugin already emits an
	 * Organization node wants one of them, not both.
	 *
	 * @param array<int, array<string, mixed>> $graph The nodes to publish.
	 */
	$graph = (array) apply_filters('bridge_schema_graph', $graph);

	if (! $graph) {
		return;
	}

	echo bridge_schema_json( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — encoded for this context by bridge_schema_json().
		array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values($graph),
		)
	), "\n";
}
add_action('wp_head', 'bridge_print_site_schema');
