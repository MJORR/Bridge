<?php

/**
 * Bridge — structural variants and section skins.
 *
 * Templates always ask for `header` and `footer`. Which part actually renders
 * is decided here, from the design system, so a build can offer several header
 * layouts without every template needing to know about them — and so changing
 * the site's header is a setting rather than a template edit.
 *
 * Section skins are registered block styles rather than open styling controls:
 * an editor picks "Inverted", and what inverted *means* stays with the design
 * system.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Footer layouts: token value => template part slug.
 *
 * @return array<string, string>
 */
function bridge_footer_variants(): array
{
	return array(
		'simple'  => 'footer',
		'columns' => 'footer-columns',
	);
}

/**
 * Does a template part with this slug exist in the theme?
 *
 * Guards the swap: a token naming a part that a given build does not ship
 * should fall back to the default rather than render an empty header.
 */
function bridge_template_part_exists(string $slug): bool
{
	return file_exists(get_theme_file_path('parts/' . $slug . '.html'));
}

/**
 * Swap the generic footer slug for the configured variant.
 *
 * Only the bare `footer` slug is rewritten. A template that deliberately asks
 * for something specific keeps exactly what it asked for; rewriting those too
 * would take a considered template decision and quietly override it from a
 * settings screen.
 *
 * The header used to be swapped here too, one part per layout. It is now a
 * single server-rendered block, so there is one header part and nothing to
 * choose between — which is the point: this filter runs when PHP renders a
 * template, and never when the editor renders one, so anything decided here is
 * invisible in the canvas. The footer can live with that until it grows
 * settings of its own; the header could not.
 *
 * @param array<string, mixed> $parsed_block A parsed block.
 * @return array<string, mixed>
 */
function bridge_swap_template_part(array $parsed_block): array
{
	if ('core/template-part' !== ($parsed_block['blockName'] ?? '')) {
		return $parsed_block;
	}

	$slug = (string) ($parsed_block['attrs']['slug'] ?? '');

	if ('footer' !== $slug) {
		return $parsed_block;
	}

	$variants = bridge_footer_variants();
	$choice   = (string) (bridge_get_tokens()['footer']['style'] ?? '');
	$target   = $variants[$choice] ?? $slug;

	if ($target !== $slug && bridge_template_part_exists($target)) {
		$parsed_block['attrs']['slug'] = $target;
	}

	return $parsed_block;
}
add_filter('render_block_data', 'bridge_swap_template_part');

/**
 * What each template is built from, after the variant swap.
 *
 * Makes the consequence of a header or footer choice visible: it is one thing
 * to pick "Centered" from a dropdown, another to see which templates it will
 * actually appear on and which one keeps its own header instead.
 *
 * @return array<int, array<string, mixed>>
 */
function bridge_template_inventory(): array
{
	// Titles for the theme's custom templates come from theme.json; the rest
	// fall back to a humanised filename.
	$declared = array();
	$settings = wp_get_theme()->get_file_path('theme.json');

	if (file_exists($settings)) {
		$data = wp_json_file_decode($settings, array('associative' => true));

		foreach ((array) ($data['customTemplates'] ?? array()) as $template) {
			if (! empty($template['name'])) {
				$declared[(string) $template['name']] = (string) ($template['title'] ?? $template['name']);
			}
		}
	}

	$inventory = array();

	/**
	 * Every file that can win the template hierarchy, in both dialects.
	 *
	 * A block theme's templates are the .html files in templates/, and that is
	 * all this used to look at. `search.php` is the exception the theme now
	 * has — a results page is decided by the request rather than arranged by
	 * an operator, so it is written in PHP — and WordPress prefers a PHP
	 * template over a block template of the same name. Left out of the list it
	 * would be the one page whose header and footer this screen could not
	 * account for, and the screen exists to say where those go.
	 *
	 * `functions.php` is the theme's bootstrap and never a template; nothing
	 * else lives at the theme root today, and anything that arrives there
	 * later will be a template by definition of where it is.
	 */
	$paths = array_merge(
		(array) glob(get_theme_file_path('templates/*.html')),
		array_values(
			array_filter(
				(array) glob(get_theme_file_path('*.php')),
				static function (string $path): bool {
					return 'functions.php' !== basename($path);
				}
			)
		)
	);

	foreach ($paths as $path) {
		// Either extension, so the slug is the template's name in the
		// hierarchy — `search`, whichever dialect it happens to be written in.
		$slug    = pathinfo($path, PATHINFO_FILENAME);
		$content = (string) file_get_contents($path);
		$parts   = array();

		if (preg_match_all('/wp:template-part\s*\{[^}]*"slug"\s*:\s*"([a-z0-9-]+)"/i', $content, $matches)) {
			foreach ($matches[1] as $requested) {
				$resolved = bridge_swap_template_part(
					array(
						'blockName' => 'core/template-part',
						'attrs'     => array('slug' => $requested),
					)
				)['attrs']['slug'];

				$parts[] = array(
					'requested' => $requested,
					'resolved'  => $resolved,
					'fixed'     => $requested === $resolved && ! in_array($requested, array('header', 'footer'), true),
				);
			}
		}

		$inventory[] = array(
			'slug'  => $slug,
			'title' => $declared[$slug] ?? ucwords(str_replace('-', ' ', $slug)),
			'parts' => $parts,
		);
	}

	// glob() sorts within a directory, and the list is now two of them. Sorted
	// by slug so the screen reads alphabetically however a template is written.
	usort(
		$inventory,
		static function (array $a, array $b): int {
			return strcmp((string) $a['slug'], (string) $b['slug']);
		}
	);

	return $inventory;
}

/**
 * Section skins available to layout blocks.
 *
 * Named skins rather than a colour picker: the editor's decision is "this
 * section should stand apart", and the design system decides what standing
 * apart looks like. Every value resolves to a palette preset, so a skin can
 * never drift off-brand and every skin restyles itself when the brand changes.
 *
 * Each skin declares the palette slugs it resolves to. The stylesheet is
 * still where a skin is actually implemented, but declaring the mapping here
 * lets the options page draw a truthful preview without a second copy of the
 * rules in JavaScript — keep the two in step when adding a skin.
 *
 * @return array<string, array<string, string>> Style name => label, background, text.
 */
function bridge_section_skins(): array
{
	return array(
		'bridge-surface'  => array(
			'label'      => __('Surface', 'bridge'),
			'background' => 'surface',
			'text'       => 'text',
		),
		'bridge-inverted' => array(
			'label'      => __('Inverted', 'bridge'),
			'background' => 'primary',
			'text'       => 'background',
		),
		'bridge-accent'   => array(
			'label'      => __('Accent', 'bridge'),
			'background' => 'accent',
			'text'       => 'text',
		),
	);
}

/**
 * Register the section skins on the one block that carries them.
 *
 * Group only, and deliberately. A skin paints a band of the page and gives it
 * the section padding — which is a thing a group does and the other two
 * candidates do not.
 *
 * Columns never needed it: every pattern that wants a skinned split puts the
 * skin on the group *around* the columns, which is the right structure anyway
 * because the colour should reach the full width and the columns should not.
 *
 * Cover was worse than unnecessary. Cover paints an image layer and a dim
 * layer over its own background, both absolutely positioned across the whole
 * box, so a skin's background colour is invisible behind them — and the part
 * that does get through is the section padding, which fights the slide's own
 * height and centring. It offered a client a first control in the Styles tab
 * that could not do the thing it named and could break the layout instead.
 */
function bridge_register_section_skins(): void
{
	foreach (bridge_section_skins() as $name => $skin) {
		register_block_style(
			'core/group',
			array(
				'name'  => $name,
				'label' => $skin['label'],
			)
		);
	}
}
add_action('init', 'bridge_register_section_skins');

/**
 * Carry the chosen article layout onto <body>.
 *
 * The three post layouts are one template and three grids — see
 * `bridge_post_templates()` for why. What tells the stylesheet which of them to
 * draw is this class, and a class on <body> rather than on the article itself
 * because the template is static markup with nowhere to put a dynamic value.
 * The alternative was a `render_block` filter matching on the wrapper's class
 * name, which is a string comparison against markup an editor can change.
 *
 * Singular posts only. The layouts describe the head of an article — a
 * featured image and a title — and a page, an archive or a search result has
 * either no featured image or no single title for one to sit behind. `is_page()`
 * is excluded explicitly rather than left to `is_singular('post')`, which
 * already excludes it, so the intent survives someone widening the check.
 *
 * Two classes, not one: the arrangement and the shape of the photograph are
 * separate decisions and the stylesheet answers them separately.
 */
function bridge_post_body_class(array $classes): array
{
	if (! is_singular('post')) {
		return $classes;
	}

	$tokens   = bridge_get_tokens();
	$template = (string) ($tokens['posts']['template'] ?? 'classic');

	// The sanitiser guarantees the slug is one the theme draws, but a filter
	// that removed a layout after a site had saved it would not — and a class
	// no stylesheet answers is an article with no head layout at all.
	if (! isset(bridge_post_templates()[$template])) {
		$template = 'classic';
	}

	// The corner the lead image is cut to, carried the same way and for the
	// same reason. A second class rather than a compound one: the shape and
	// the arrangement are two independent decisions, and a
	// `bridge-single--feature-cut` would need nine rules to say what four say.
	$image = (string) ($tokens['posts']['image'] ?? 'rounded');

	if (! isset(bridge_post_image_shapes()[$image])) {
		$image = 'rounded';
	}

	$classes[] = 'bridge-single';
	$classes[] = 'bridge-single--' . $template;
	$classes[] = 'bridge-single--image-' . $image;

	return $classes;
}
add_filter('body_class', 'bridge_post_body_class');
