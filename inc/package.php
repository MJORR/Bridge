<?php

/**
 * Bridge — Export / Import between sites.
 *
 * Moves a site's design and pages from one WordPress install to another as a
 * single .zip "package":
 *
 *   settings   Theme Options (design tokens), Site Options, Google Fonts,
 *              site icon.
 *   menus      Navigation menus (wp_navigation posts).
 *   pages      Every page (not trashed), its template, featured image, meta
 *              description, Title Banner settings, parent; and the front page / posts page choice.
 *   posts      Every blog post (not trashed), its featured image, excerpt,
 *              categories and tags, meta description. Matched by slug.
 *
 * Plus every media file any of the above points at, so logos and page images
 * arrive with them. Files over BRIDGE_PACKAGE_MAX_MEDIA_BYTES are listed but
 * not packed: the target uses its own copy, or the import warns.
 *
 * ---- Why it is not WordPress's own Tools > Export ---------------------------
 *
 * Nearly everything here points at something else by database ID: the header
 * menu is a menu ID in Theme Options, a menu link is a page ID, a logo or a
 * hero image is an attachment ID. IDs differ between sites. The package
 * therefore records *what* each ID was — a page by its path, a menu by its
 * slug, an image by its file — and the import finds or creates the same thing
 * on the target and rewrites every reference to the target's ID.
 *
 * Existing pages and menus are matched by path / slug and **updated in
 * place**, never duplicated. Nothing on the target is deleted. Updated pages
 * and menus keep a revision; the previous settings are saved and can be
 * restored from the Export / Import screen or `wp bridge package restore`.
 *
 * Admin:  Theme Options > Export / Import
 * CLI:    wp bridge package export <file.zip> [--only=settings,menus,pages,posts]
 *         wp bridge package import <file.zip> [--only=…] [--dry-run]
 *         wp bridge package restore
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

const BRIDGE_PACKAGE_FORMAT     = 'bridge-package';
const BRIDGE_PACKAGE_VERSION    = 1;
const BRIDGE_PACKAGE_SECTIONS   = array('settings', 'menus', 'pages', 'posts');
const BRIDGE_PACKAGE_BACKUP     = 'bridge_package_backup';
const BRIDGE_PACKAGE_PAGE_SLUG  = 'bridge-package';
const BRIDGE_PACKAGE_UPLOAD_DIR = 'bridge-packages';

/**
 * Media files larger than this are indexed but not packed (keeps the .zip
 * under typical upload limits; videos are usually already on the target).
 */
const BRIDGE_PACKAGE_MAX_MEDIA_BYTES = 10 * 1024 * 1024;

/* ==========================================================================
 * Block references
 * ======================================================================= */

/**
 * Block attributes that hold an attachment ID, in any block.
 *
 * Bridge's own blocks name them distinctly. Core's media blocks call theirs
 * `id`, which in other blocks means something else, so that one is listed per
 * block below rather than here.
 */
function bridge_package_media_attrs(): array
{
	return array('imageId', 'backgroundImage', 'graphicId', 'backgroundId', 'fileId', 'previewId', 'mediaId', 'featuredImageId');
}

/**
 * Core blocks whose `id` attribute is an attachment.
 */
function bridge_package_core_media_blocks(): array
{
	return array('core/image', 'core/cover', 'core/video', 'core/audio', 'core/file');
}

/**
 * Visit every block and let $fn rewrite its attributes.
 *
 * $fn receives (string $name, array &$attrs) and returns true when it changed
 * something. Returns the blocks and whether anything changed.
 *
 * @return array{0: array, 1: bool}
 */
function bridge_package_walk_blocks(array $blocks, callable $fn): array
{
	$changed = false;

	foreach ($blocks as $i => $block) {
		if (! empty($block['blockName'])) {
			$attrs = $block['attrs'] ?? array();
			if ($fn((string) $block['blockName'], $attrs)) {
				$blocks[$i]['attrs'] = $attrs;
				$changed             = true;
			}
		}

		if (! empty($block['innerBlocks'])) {
			list($inner, $inner_changed) = bridge_package_walk_blocks($block['innerBlocks'], $fn);
			if ($inner_changed) {
				$blocks[$i]['innerBlocks'] = $inner;
				$changed                   = true;
			}
		}
	}

	return array($blocks, $changed);
}

/**
 * Apply $visit to every ID reference in one block's attributes.
 *
 * $visit receives (string $kind, int $id) and returns the replacement ID, or
 * the same ID to leave it. Kinds: media, menu, post, term.
 */
function bridge_package_visit_block_refs(string $name, array &$attrs, callable $visit): bool
{
	$changed = false;
	$swap    = static function (string $kind, $value) use ($visit, &$changed) {
		if (! is_numeric($value) || (int) $value <= 0) {
			return $value;
		}
		$new = (int) $visit($kind, (int) $value);
		if ($new !== (int) $value) {
			$changed = true;
		}
		return $new;
	};

	foreach (bridge_package_media_attrs() as $key) {
		if (isset($attrs[$key])) {
			$attrs[$key] = $swap('media', $attrs[$key]);
		}
	}

	if (isset($attrs['id']) && in_array($name, bridge_package_core_media_blocks(), true)) {
		$attrs['id'] = $swap('media', $attrs['id']);
	}

	if ('core/gallery' === $name && ! empty($attrs['ids']) && is_array($attrs['ids'])) {
		foreach ($attrs['ids'] as $k => $v) {
			$attrs['ids'][$k] = $swap('media', $v);
		}
	}

	// Lists of images inside one block (the logo slider).
	foreach (array('images', 'imagesSecond') as $key) {
		if (! empty($attrs[$key]) && is_array($attrs[$key])) {
			foreach ($attrs[$key] as $k => $item) {
				if (is_array($item) && isset($item['id'])) {
					$attrs[$key][$k]['id'] = $swap('media', $item['id']);
				}
			}
		}
	}

	if ('core/navigation' === $name && isset($attrs['ref'])) {
		$attrs['ref'] = $swap('menu', $attrs['ref']);
	}

	if (in_array($name, array('core/navigation-link', 'core/navigation-submenu'), true) && isset($attrs['id'])) {
		$kind = $attrs['kind'] ?? '';
		if ('post-type' === $kind) {
			$attrs['id'] = $swap('post', $attrs['id']);
		} elseif ('taxonomy' === $kind) {
			$attrs['id'] = $swap('term', $attrs['id']);
		}
	}

	return $changed;
}

/**
 * Every reference in a piece of block content, as [kind => [id, …]].
 */
function bridge_package_content_refs(string $content): array
{
	$refs = array('media' => array(), 'menu' => array(), 'post' => array(), 'term' => array());

	bridge_package_walk_blocks(
		parse_blocks($content),
		static function (string $name, array &$attrs) use (&$refs): bool {
			bridge_package_visit_block_refs(
				$name,
				$attrs,
				static function (string $kind, int $id) use (&$refs): int {
					$refs[$kind][$id] = true;
					return $id;
				}
			);
			return false;
		}
	);

	// Images placed in classic or raw HTML carry the ID only as a class.
	if (preg_match_all('/wp-image-(\d+)/', $content, $m)) {
		foreach ($m[1] as $id) {
			$refs['media'][(int) $id] = true;
		}
	}

	return array_map('array_keys', $refs);
}

/**
 * Rewrite every ID reference and site URL in a piece of content.
 *
 * @param array $map  [kind => [old => new]] — IDs not in the map are kept.
 * @param array $urls [old => new] plain string replacements.
 */
function bridge_package_rewrite_content(string $content, array $map, array $urls): string
{
	list($blocks, $changed) = bridge_package_walk_blocks(
		parse_blocks($content),
		static function (string $name, array &$attrs) use ($map): bool {
			return bridge_package_visit_block_refs(
				$name,
				$attrs,
				static function (string $kind, int $id) use ($map): int {
					return (int) ($map[$kind][$id] ?? $id);
				}
			);
		}
	);

	// Only re-serialise when an attribute changed, so content with nothing to
	// remap comes through byte for byte.
	if ($changed) {
		$content = serialize_blocks($blocks);
	}

	if (! empty($map['media'])) {
		$content = preg_replace_callback(
			'/wp-image-(\d+)/',
			static function ($m) use ($map) {
				return 'wp-image-' . (int) ($map['media'][(int) $m[1]] ?? $m[1]);
			},
			$content
		);
	}

	return $urls ? strtr($content, $urls) : $content;
}

/**
 * Old-site URL => this-site URL, plain and JSON-escaped, http and https.
 */
function bridge_package_url_map(string $source_home, array $extra = array()): array
{
	$target = untrailingslashit(home_url());
	$source = untrailingslashit($source_home);
	$map    = array();

	if ($source && $source !== $target) {
		$host = preg_replace('#^https?://#', '', $source);
		foreach (array('https://', 'http://') as $scheme) {
			$map[$scheme . $host]                         = $target;
			$map[str_replace('/', '\/', $scheme . $host)] = str_replace('/', '\/', $target);
		}
	}

	foreach ($extra as $old => $new) {
		$map[$old] = $new;
		$map[str_replace('/', '\/', $old)] = str_replace('/', '\/', $new);
	}

	// Longest first, so a full file URL is replaced before its host is.
	uksort($map, static fn ($a, $b) => strlen($b) <=> strlen($a));

	return $map;
}

/* ==========================================================================
 * Export
 * ======================================================================= */

/**
 * Build a package and write it to $zip_path.
 *
 * @param string[] $sections Any of BRIDGE_PACKAGE_SECTIONS.
 * @return array Summary counts.
 */
function bridge_package_export(string $zip_path, array $sections)
{
	$sections = array_values(array_intersect(BRIDGE_PACKAGE_SECTIONS, $sections));
	if (! $sections) {
		return new WP_Error('bridge_package_empty', 'Choose at least one thing to export.');
	}
	if (! class_exists('ZipArchive')) {
		return new WP_Error('bridge_package_zip', 'This server has no ZipArchive (PHP zip extension).');
	}

	$media = array();
	$refs  = array('post' => array(), 'menu' => array(), 'term' => array());
	$data  = array();

	$add_media = static function ($id) use (&$media) {
		$id = (int) $id;
		if ($id > 0 && 'attachment' === get_post_type($id)) {
			$media[$id] = true;
		}
	};
	$add_refs = static function (array $found) use (&$media, &$refs, $add_media) {
		foreach ($found['media'] as $id) {
			$add_media($id);
		}
		foreach (array('post', 'menu', 'term') as $kind) {
			foreach ($found[$kind] as $id) {
				$refs[$kind][$id] = true;
			}
		}
	};

	if (in_array('settings', $sections, true)) {
		$tokens = bridge_get_tokens();
		$site   = get_option(BRIDGE_SITE_OPTIONS_KEY, array());

		foreach (bridge_package_token_media_paths() as $path) {
			$add_media(bridge_package_array_get($tokens, $path));
		}
		foreach (bridge_package_token_menu_paths($tokens) as $path) {
			$id = (int) bridge_package_array_get($tokens, $path);
			if ($id > 0) {
				$refs['menu'][$id] = true;
			}
		}
		$add_media($site['footer_image'] ?? 0);
		$add_media(get_option('site_icon'));

		$data['settings'] = array(
			'tokens'       => $tokens,
			'site_options' => is_array($site) ? $site : array(),
			'fonts'        => array_values(array_filter(array_map(
				static fn ($f) => is_array($f) ? (string) ($f['family'] ?? '') : '',
				bridge_installed_fonts()
			))),
			'site_icon'    => (int) get_option('site_icon'),
		);
	}

	if (in_array('menus', $sections, true)) {
		$data['menus'] = array();
		foreach (get_posts(array('post_type' => 'wp_navigation', 'post_status' => array('publish', 'draft', 'private'), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC')) as $menu) {
			$add_refs(bridge_package_content_refs($menu->post_content));
			$data['menus'][] = array(
				'id'      => (int) $menu->ID,
				'slug'    => $menu->post_name,
				'title'   => $menu->post_title,
				'status'  => $menu->post_status,
				'content' => $menu->post_content,
			);
		}
	}

	if (in_array('pages', $sections, true)) {
		$data['pages'] = array();
		$pages = get_posts(array('post_type' => 'page', 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'), 'numberposts' => -1, 'orderby' => 'menu_order ID', 'order' => 'ASC'));

		foreach ($pages as $page) {
			$add_refs(bridge_package_content_refs($page->post_content));
			$thumb = (int) get_post_thumbnail_id($page);
			$add_media($thumb);

			$meta = array();
			foreach (bridge_package_page_meta_keys() as $key) {
				$value = get_post_meta($page->ID, $key, true);
				if ('' !== $value && null !== $value) {
					$meta[$key] = $value;
				}
			}

			$data['pages'][] = array(
				'id'             => (int) $page->ID,
				'path'           => get_page_uri($page),
				'slug'           => $page->post_name,
				'parent'         => (int) $page->post_parent,
				'title'          => $page->post_title,
				'content'        => $page->post_content,
				'excerpt'        => $page->post_excerpt,
				'status'         => $page->post_status,
				'menu_order'     => (int) $page->menu_order,
				'comment_status' => $page->comment_status,
				'template'       => (string) get_page_template_slug($page),
				'thumbnail'      => $thumb,
				'meta'           => $meta,
			);
		}

		// Parents before children, so the import can always place a child.
		usort($data['pages'], static fn ($a, $b) => substr_count($a['path'], '/') <=> substr_count($b['path'], '/'));

		$data['front'] = array(
			'show_on_front'  => (string) get_option('show_on_front'),
			'page_on_front'  => (int) get_option('page_on_front'),
			'page_for_posts' => (int) get_option('page_for_posts'),
		);
		foreach (array('page_on_front', 'page_for_posts') as $key) {
			if ($data['front'][$key] > 0) {
				$refs['post'][$data['front'][$key]] = true;
			}
		}
	}

	if (in_array('posts', $sections, true)) {
		$data['posts'] = array();
		$posts = get_posts(array('post_type' => 'post', 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC'));

		foreach ($posts as $post) {
			$add_refs(bridge_package_content_refs($post->post_content));
			$thumb = (int) get_post_thumbnail_id($post);
			$add_media($thumb);

			$meta = array();
			foreach (array(BRIDGE_META_DESCRIPTION_KEY, '_yoast_wpseo_title', '_yoast_wpseo_metadesc') as $key) {
				$value = get_post_meta($post->ID, $key, true);
				if ('' !== $value && null !== $value) {
					$meta[$key] = $value;
				}
			}

			$terms = array();
			foreach (array('category', 'post_tag') as $taxonomy) {
				$found = get_the_terms($post, $taxonomy);
				$terms[$taxonomy] = array();
				foreach (is_array($found) ? $found : array() as $term) {
					$terms[$taxonomy][] = array('slug' => $term->slug, 'name' => $term->name);
				}
			}

			$data['posts'][] = array(
				'id'             => (int) $post->ID,
				'slug'           => $post->post_name,
				'title'          => $post->post_title,
				'content'        => $post->post_content,
				'excerpt'        => $post->post_excerpt,
				'status'         => $post->post_status,
				'date'           => $post->post_date,
				'date_gmt'       => $post->post_date_gmt,
				'comment_status' => $post->comment_status,
				'sticky'         => is_sticky($post->ID),
				'thumbnail'      => $thumb,
				'terms'          => $terms,
				'meta'           => $meta,
			);
		}
	}

	// What each referenced ID *is*, so the target can find its own copy.
	$index = array('post' => array(), 'menu' => array(), 'term' => array(), 'media' => array());

	foreach (array_keys($refs['post']) as $id) {
		$post = get_post($id);
		if ($post) {
			$index['post'][$id] = array(
				'type' => $post->post_type,
				'path' => is_post_type_hierarchical($post->post_type) ? get_page_uri($post) : $post->post_name,
			);
		}
	}
	foreach (array_keys($refs['menu']) as $id) {
		$post = get_post($id);
		if ($post && 'wp_navigation' === $post->post_type) {
			$index['menu'][$id] = array('slug' => $post->post_name, 'title' => $post->post_title);
		}
	}
	foreach (array_keys($refs['term']) as $id) {
		$term = get_term($id);
		if ($term && ! is_wp_error($term)) {
			$index['term'][$id] = array('taxonomy' => $term->taxonomy, 'slug' => $term->slug);
		}
	}

	$zip = new ZipArchive();
	if (true !== $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
		return new WP_Error('bridge_package_zip', 'Could not create the package file.');
	}

	$missing = array();
	$large   = array();
	foreach (array_keys($media) as $id) {
		$file = (string) get_post_meta($id, '_wp_attached_file', true);
		$abs  = get_attached_file($id);
		if (! $file || ! $abs || ! is_file($abs)) {
			$missing[] = $id;
			continue;
		}
		$index['media'][$id] = array(
			'file'        => $file,
			'size'        => (int) filesize($abs),
			'mime'        => get_post_mime_type($id),
			'title'       => get_the_title($id),
			'caption'     => (string) get_post_field('post_excerpt', $id),
			'description' => (string) get_post_field('post_content', $id),
			'alt'         => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
		);
		if ($index['media'][$id]['size'] > BRIDGE_PACKAGE_MAX_MEDIA_BYTES) {
			$large[] = $file;
			continue; // Indexed, so a target that has it still maps it.
		}
		$zip->addFile($abs, 'media/' . $file);
	}

	$manifest = array(
		'format'      => BRIDGE_PACKAGE_FORMAT,
		'version'     => BRIDGE_PACKAGE_VERSION,
		'created'     => gmdate('c'),
		'source_home' => home_url(),
		'source_name' => get_bloginfo('name'),
		'theme'       => wp_get_theme()->get('Version'),
		'sections'    => $sections,
	);

	$json = static fn ($v) => (string) wp_json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

	$zip->addFromString('manifest.json', $json($manifest));
	$zip->addFromString('data.json', $json($data));
	$zip->addFromString('index.json', $json($index));
	$zip->close();

	return array(
		'sections' => $sections,
		'pages'    => count($data['pages'] ?? array()),
		'posts'    => count($data['posts'] ?? array()),
		'menus'    => count($data['menus'] ?? array()),
		'media'    => count($index['media']),
		'missing'  => $missing,
		'large'    => $large,
	);
}

/**
 * Paths in the token set that hold an attachment ID.
 */
function bridge_package_token_media_paths(): array
{
	return array(
		array('brand', 'maskShapeId'),
		array('brand', 'swooshId'),
		array('header', 'logo', 'id'),
		array('header', 'logo', 'lightId'),
		array('header', 'logo', 'faviconId'),
	);
}

/**
 * Paths in the token set that hold a menu ID: header and every footer slot.
 */
function bridge_package_token_menu_paths(array $tokens): array
{
	$paths = array();
	foreach (array('header', 'footer') as $area) {
		foreach (array_keys((array) ($tokens[$area]['menus'] ?? array())) as $slot) {
			$paths[] = array($area, 'menus', $slot);
		}
	}
	return $paths;
}

function bridge_package_array_get(array $array, array $path)
{
	foreach ($path as $key) {
		if (! is_array($array) || ! array_key_exists($key, $array)) {
			return null;
		}
		$array = $array[$key];
	}
	return $array;
}

function bridge_package_array_set(array &$array, array $path, $value): void
{
	$ref = &$array;
	foreach ($path as $key) {
		if (! isset($ref[$key]) || ! is_array($ref[$key])) {
			$ref[$key] = $ref[$key] ?? array();
		}
		$ref = &$ref[$key];
	}
	$ref = $value;
}

/* ==========================================================================
 * Import
 * ======================================================================= */

/**
 * Read and validate a package.
 *
 * @return array{manifest: array, data: array, index: array, zip: string}|WP_Error
 */
function bridge_package_read(string $zip_path)
{
	if (! class_exists('ZipArchive')) {
		return new WP_Error('bridge_package_zip', 'This server has no ZipArchive (PHP zip extension).');
	}

	$zip = new ZipArchive();
	if (true !== $zip->open($zip_path)) {
		return new WP_Error('bridge_package_invalid', 'That file is not a readable .zip.');
	}

	$read = static function (string $name) use ($zip) {
		$raw = $zip->getFromName($name);
		return false === $raw ? null : json_decode($raw, true);
	};

	$manifest = $read('manifest.json');
	$data     = $read('data.json');
	$index    = $read('index.json');
	$zip->close();

	if (! is_array($manifest) || BRIDGE_PACKAGE_FORMAT !== ($manifest['format'] ?? '') || ! is_array($data) || ! is_array($index)) {
		return new WP_Error('bridge_package_invalid', 'That file is not a Bridge export package.');
	}
	if ((int) ($manifest['version'] ?? 0) > BRIDGE_PACKAGE_VERSION) {
		return new WP_Error('bridge_package_version', 'This package was made by a newer version of Bridge. Update the theme on this site first.');
	}

	foreach (array('post', 'menu', 'term', 'media') as $kind) {
		$index[$kind] = isset($index[$kind]) && is_array($index[$kind]) ? $index[$kind] : array();
	}

	return array('manifest' => $manifest, 'data' => $data, 'index' => $index, 'zip' => $zip_path);
}

/**
 * Import (or, with $dry_run, only plan) a package.
 *
 * @param string[] $sections Sections to apply; others in the package are ignored.
 * @return array|WP_Error Report: ['actions' => [[section, item, action, note]], 'warnings' => [], 'counts' => []]
 */
function bridge_package_import(string $zip_path, array $sections, bool $dry_run = true)
{
	$package = bridge_package_read($zip_path);
	if (is_wp_error($package)) {
		return $package;
	}

	$data     = $package['data'];
	$index    = $package['index'];
	$sections = array_values(array_intersect($sections, $package['manifest']['sections'] ?? array()));

	$report = array('actions' => array(), 'warnings' => array(), 'dry_run' => $dry_run, 'sections' => $sections, 'source' => $package['manifest']['source_home'] ?? '');
	$note   = static function (string $section, string $item, string $action, string $detail = '') use (&$report) {
		$report['actions'][] = array($section, $item, $action, $detail);
	};
	$warn   = static function (string $message) use (&$report) {
		$report['warnings'][] = $message;
	};

	if (! $sections) {
		return new WP_Error('bridge_package_empty', 'Nothing to import: choose at least one section the package contains.');
	}

	// Content from the package may carry markup (iframes, inline SVG) that
	// kses would strip on save. Only for users trusted with unfiltered HTML,
	// or WP-CLI, which runs as no user.
	$unfiltered = (defined('WP_CLI') && WP_CLI) || current_user_can('unfiltered_html');
	if (! $dry_run && $unfiltered) {
		kses_remove_filters();
	}

	$map  = array('media' => array(), 'menu' => array(), 'post' => array(), 'term' => array());
	$urls = array();

	if (! $dry_run) {
		bridge_package_save_backup($sections);
	}

	/* ---- Media -------------------------------------------------------- */

	// Only the files the chosen sections use.
	$needed = bridge_package_needed_media($data, $index, $sections);

	foreach ($needed as $old_id) {
		$info = $index['media'][$old_id] ?? null;
		if (! $info) {
			continue;
		}
		$result = bridge_package_import_media($package['zip'], (int) $old_id, $info, $dry_run);
		if (is_wp_error($result)) {
			$warn('Media ' . $info['file'] . ': ' . $result->get_error_message());
			continue;
		}
		$map['media'][(int) $old_id] = $result['id'];
		if (! empty($result['url_from'])) {
			$urls[$result['url_from']] = $result['url_to'];
		}
		$note('media', $info['file'], $result['action'], $result['note'] ?? '');
	}

	/* ---- Posts and terms referenced but not in the package -------------- */

	foreach ($index['post'] as $old_id => $info) {
		$found = bridge_package_find_post((string) $info['type'], (string) $info['path']);
		if ($found) {
			$map['post'][(int) $old_id] = $found;
		}
	}
	foreach ($index['term'] as $old_id => $info) {
		$term = get_term_by('slug', (string) $info['slug'], (string) $info['taxonomy']);
		if ($term) {
			$map['term'][(int) $old_id] = (int) $term->term_id;
		}
	}
	foreach ($index['menu'] as $old_id => $info) {
		$found = bridge_package_find_menu((string) $info['slug'], (string) $info['title']);
		if ($found) {
			$map['menu'][(int) $old_id] = $found;
		}
	}

	/* ---- Pages, pass 1: make sure each one exists ---------------------- */

	$do_pages      = in_array('pages', $sections, true) && ! empty($data['pages']);
	$page_ids      = array();
	$created_pages = array();

	if ($do_pages) {
		foreach ($data['pages'] as $page) {
			$existing = bridge_package_find_post('page', (string) $page['path']);
			if ($existing) {
				$page_ids[$page['id']] = $existing;
				$map['post'][(int) $page['id']] = $existing;
				continue;
			}

			$parent = (int) ($map['post'][(int) $page['parent']] ?? 0);
			if ($page['parent'] && ! $parent && ! $dry_run) {
				$warn(sprintf('Page "%s": its parent was not found, so it was created at the top level.', $page['path']));
			}

			if ($dry_run) {
				// A stand-in ID so later lookups treat it as present.
				$page_ids[$page['id']]          = -(int) $page['id'];
				$map['post'][(int) $page['id']] = -(int) $page['id'];
				$note('pages', $page['path'], 'create');
				continue;
			}

			$new_id = wp_insert_post(
				wp_slash(array(
					'post_type'   => 'page',
					'post_title'  => $page['title'],
					'post_name'   => $page['slug'],
					'post_parent' => $parent,
					'post_status' => 'draft', // Set to its real status in pass 2.
					'menu_order'  => (int) $page['menu_order'],
				)),
				true
			);
			if (is_wp_error($new_id)) {
				$warn(sprintf('Page "%s" could not be created: %s', $page['path'], $new_id->get_error_message()));
				continue;
			}
			$page_ids[$page['id']]          = (int) $new_id;
			$map['post'][(int) $page['id']] = (int) $new_id;
			$created_pages[$page['id']]     = true;
			$note('pages', $page['path'], 'create');
		}
	}

	/* ---- Menus --------------------------------------------------------- */

	$url_map = bridge_package_url_map((string) $report['source'], $urls);

	if (in_array('menus', $sections, true) && ! empty($data['menus'])) {
		// Every packaged menu gets its target ID before any content is
		// rewritten, so a menu can point at another menu.
		$created_menus = array();
		foreach ($data['menus'] as $menu) {
			$target = bridge_package_find_menu((string) $menu['slug'], (string) $menu['title']);

			if (! $target) {
				$created_menus[$menu['id']] = true;
				$note('menus', $menu['title'], 'create');

				if ($dry_run) {
					$target = -(int) $menu['id'];
				} else {
					$target = wp_insert_post(wp_slash(array('post_type' => 'wp_navigation', 'post_title' => $menu['title'], 'post_name' => $menu['slug'], 'post_status' => $menu['status'])), true);
					if (is_wp_error($target)) {
						$warn(sprintf('Menu "%s" could not be created: %s', $menu['title'], $target->get_error_message()));
						continue;
					}
				}
			}
			$map['menu'][(int) $menu['id']] = (int) $target;
		}

		foreach ($data['menus'] as $menu) {
			$target = (int) ($map['menu'][(int) $menu['id']] ?? 0);
			if ($target <= 0) {
				continue; // Dry run of a new menu, or creation failed.
			}
			$content = bridge_package_rewrite_content((string) $menu['content'], $map, $url_map);
			$current = get_post($target);
			$same    = $current && $current->post_content === $content && $current->post_title === $menu['title'];

			if (! $same && ! $dry_run) {
				wp_update_post(wp_slash(array('ID' => $target, 'post_content' => $content, 'post_title' => $menu['title'], 'post_status' => $menu['status'])));
			}
			if (empty($created_menus[$menu['id']])) {
				$note('menus', $menu['title'], $same ? 'unchanged' : 'update');
			}
		}
	}

	/* ---- Pages, pass 2: content and details ----------------------------- */

	if ($do_pages) {
		foreach ($data['pages'] as $page) {
			$target  = (int) ($page_ids[$page['id']] ?? 0);
			$current = $target > 0 ? get_post($target) : null;
			if (! $current) {
				continue; // Dry run of a new page (reported as "create"), or creation failed.
			}

			$content = bridge_package_rewrite_content((string) $page['content'], $map, $url_map);

			$parent  = (int) ($map['post'][(int) $page['parent']] ?? 0);
			$changes = array();
			if ($current->post_content !== $content) {
				$changes[] = 'content';
			}
			if ($current->post_title !== $page['title']) {
				$changes[] = 'title';
			}
			if ($current->post_status !== $page['status']) {
				$changes[] = 'status';
			}
			if ((int) $current->post_parent !== $parent && $page['parent']) {
				$changes[] = 'parent';
			}
			if ((string) $current->post_excerpt !== (string) $page['excerpt']) {
				$changes[] = 'excerpt';
			}
			if ((int) $current->menu_order !== (int) $page['menu_order']) {
				$changes[] = 'order';
			}
			if ((string) get_page_template_slug($target) !== (string) $page['template']) {
				$changes[] = 'template';
			}
			$thumb = (int) ($map['media'][(int) $page['thumbnail']] ?? 0);
			if ($page['thumbnail'] && (int) get_post_thumbnail_id($target) !== $thumb) {
				$changes[] = 'featured image';
			}
			foreach ((array) $page['meta'] as $key => $value) {
				if (get_post_meta($target, $key, true) !== $value) {
					$changes[] = 'meta';
					break;
				}
			}

			$was_created = ! empty($created_pages[$page['id']]);

			if (! $changes) {
				if (! $was_created) {
					$note('pages', $page['path'], 'unchanged');
				}
				continue;
			}

			if (! $was_created) {
				$note('pages', $page['path'], 'update', implode(', ', array_unique($changes)));
			}

			if ($dry_run) {
				continue;
			}

			$update = array(
				'ID'             => $target,
				'post_content'   => $content,
				'post_title'     => $page['title'],
				'post_excerpt'   => $page['excerpt'],
				'post_status'    => $page['status'],
				'menu_order'     => (int) $page['menu_order'],
				'comment_status' => $page['comment_status'],
			);
			if ($page['parent'] && $parent > 0) {
				$update['post_parent'] = $parent;
			}
			wp_update_post(wp_slash($update));

			update_post_meta($target, '_wp_page_template', $page['template'] ? $page['template'] : 'default');
			if ($thumb > 0) {
				set_post_thumbnail($target, $thumb);
			}
			foreach ((array) $page['meta'] as $key => $value) {
				update_post_meta($target, $key, wp_slash($value));
			}
		}

		// Front page and posts page.
		if (! empty($data['front'])) {
			$front = $data['front'];

			if ((string) get_option('show_on_front') !== (string) $front['show_on_front']) {
				$note('pages', 'show_on_front', 'update', (string) $front['show_on_front']);
				if (! $dry_run) {
					update_option('show_on_front', (string) $front['show_on_front']);
				}
			}

			foreach (array('page_on_front', 'page_for_posts') as $key) {
				$old = (int) $front[$key];
				$new = $old > 0 ? (int) ($map['post'][$old] ?? 0) : 0;

				if ($old > 0 && 0 === $new) {
					$warn(sprintf('%s: that page was not found on this site, so the setting was left as it is.', $key));
					continue;
				}
				if ((int) get_option($key) === $new || $new < 0) {
					if ($new < 0) {
						$note('pages', $key, 'update', 'to the new page');
					}
					continue;
				}
				$note('pages', $key, 'update');
				if (! $dry_run) {
					update_option($key, $new);
				}
			}
		}
	}

	/* ---- Posts --------------------------------------------------------- */

	if (in_array('posts', $sections, true) && ! empty($data['posts'])) {
		foreach ($data['posts'] as $post) {
			$label  = '' !== (string) $post['slug'] ? (string) $post['slug'] : '(no slug) ' . $post['title'];
			$target = bridge_package_find_post('post', (string) $post['slug']);
			if (! $target && '' === (string) $post['slug']) {
				// Unsaved-slug drafts: the same title, only if exactly one.
				$ids    = get_posts(array('post_type' => 'post', 'title' => (string) $post['title'], 'post_status' => array('draft', 'pending', 'private', 'future'), 'numberposts' => 2, 'fields' => 'ids'));
				$target = 1 === count($ids) ? (int) $ids[0] : 0;
			}

			$content = bridge_package_rewrite_content((string) $post['content'], $map, $url_map);
			$thumb   = (int) ($map['media'][(int) $post['thumbnail']] ?? 0);

			if (! $target) {
				$note('posts', $label, 'create');
				if ($dry_run) {
					continue;
				}
				$target = wp_insert_post(
					wp_slash(array(
						'post_type'      => 'post',
						'post_title'     => $post['title'],
						'post_name'      => $post['slug'],
						'post_content'   => $content,
						'post_excerpt'   => $post['excerpt'],
						'post_status'    => $post['status'],
						'post_date'      => $post['date'],
						'post_date_gmt'  => $post['date_gmt'],
						'comment_status' => $post['comment_status'],
					)),
					true
				);
				if (is_wp_error($target)) {
					$warn(sprintf('Post "%s" could not be created: %s', $label, $target->get_error_message()));
					continue;
				}
				$changes = array('terms', 'featured image', 'meta', 'sticky');
			} else {
				$current = get_post($target);
				$changes = array();
				if ($current->post_content !== $content) {
					$changes[] = 'content';
				}
				if ($current->post_title !== $post['title']) {
					$changes[] = 'title';
				}
				if ($current->post_status !== $post['status']) {
					$changes[] = 'status';
				}
				if ((string) $current->post_excerpt !== (string) $post['excerpt']) {
					$changes[] = 'excerpt';
				}
				if ($post['thumbnail'] && (int) get_post_thumbnail_id($target) !== $thumb) {
					$changes[] = 'featured image';
				}
				foreach ((array) $post['terms'] as $taxonomy => $terms) {
					$have = wp_get_post_terms($target, $taxonomy, array('fields' => 'slugs'));
					$want = array_column((array) $terms, 'slug');
					sort($want);
					$have = is_wp_error($have) ? array() : $have;
					sort($have);
					if ($have !== $want) {
						$changes[] = 'terms';
					}
				}
				foreach ((array) $post['meta'] as $key => $value) {
					if (get_post_meta($target, $key, true) !== $value) {
						$changes[] = 'meta';
						break;
					}
				}
				if (is_sticky($target) !== (bool) $post['sticky']) {
					$changes[] = 'sticky';
				}

				if (! $changes) {
					$note('posts', $label, 'unchanged');
					continue;
				}
				$note('posts', $label, 'update', implode(', ', array_unique($changes)));
				if ($dry_run) {
					continue;
				}
				wp_update_post(wp_slash(array(
					'ID'             => $target,
					'post_content'   => $content,
					'post_title'     => $post['title'],
					'post_excerpt'   => $post['excerpt'],
					'post_status'    => $post['status'],
					'comment_status' => $post['comment_status'],
				)));
			}

			$target = (int) $target;
			if (in_array('terms', $changes, true)) {
				foreach ((array) $post['terms'] as $taxonomy => $terms) {
					$ids = array();
					foreach ((array) $terms as $term) {
						$found = get_term_by('slug', (string) $term['slug'], $taxonomy);
						if (! $found) {
							$made  = wp_insert_term((string) $term['name'], $taxonomy, array('slug' => (string) $term['slug']));
							$found = is_wp_error($made) ? null : get_term((int) $made['term_id'], $taxonomy);
						}
						if ($found && ! is_wp_error($found)) {
							$ids[] = (int) $found->term_id;
						}
					}
					wp_set_object_terms($target, $ids, $taxonomy);
				}
			}
			if ($thumb > 0) {
				set_post_thumbnail($target, $thumb);
			} elseif ($post['thumbnail']) {
				$warn(sprintf('Post "%s": its featured image was not found on this site.', $label));
			}
			foreach ((array) $post['meta'] as $key => $value) {
				update_post_meta($target, $key, wp_slash($value));
			}
			if ($post['sticky']) {
				stick_post($target);
			} else {
				unstick_post($target);
			}
		}
	}

	/* ---- Settings ------------------------------------------------------ */

	if (in_array('settings', $sections, true) && ! empty($data['settings'])) {
		$settings = $data['settings'];
		$tokens   = (array) $settings['tokens'];

		// An image or menu that cannot be resolved keeps this site's current
		// value rather than being cleared. In a dry run a not-yet-created
		// item has a negative stand-in ID and shows as a change.
		$current_tokens = bridge_get_tokens();
		$resolve        = static function (string $kind, array $path, string $what) use (&$tokens, $map, $current_tokens, $warn, $dry_run): void {
			$old = (int) bridge_package_array_get($tokens, $path);
			if ($old <= 0) {
				return;
			}
			$new = (int) ($map[$kind][$old] ?? 0);
			if (0 === $new) {
				$new = (int) bridge_package_array_get($current_tokens, $path);
				$warn(sprintf('Theme Options %s: %s not found on this site, kept the current one.', implode('.', $path), $what));
			}
			bridge_package_array_set($tokens, $path, $new < 0 && $dry_run ? -1 : max(0, $new));
		};
		foreach (bridge_package_token_media_paths() as $path) {
			$resolve('media', $path, 'image');
		}
		foreach (bridge_package_token_menu_paths($tokens) as $path) {
			$resolve('menu', $path, 'menu (import Menus too)');
		}

		$changed_tokens = bridge_package_diff_keys(bridge_get_tokens(), bridge_sanitize_tokens($tokens));
		$note('settings', 'Theme Options', $changed_tokens ? 'update' : 'unchanged', $changed_tokens ? implode(', ', array_slice($changed_tokens, 0, 12)) . (count($changed_tokens) > 12 ? ' …' : '') : '');
		if (! $dry_run && $changed_tokens) {
			bridge_update_tokens($tokens);
			bridge_flush_tokens();
		}

		$site = (array) $settings['site_options'];
		if (! empty($site['footer_image'])) {
			$site['footer_image'] = (string) (int) ($map['media'][(int) $site['footer_image']] ?? 0);
		}
		$current_site = get_option(BRIDGE_SITE_OPTIONS_KEY, array());
		$site_same    = is_array($current_site) && wp_json_encode($current_site) === wp_json_encode($site);
		$note('settings', 'Site Options', $site_same ? 'unchanged' : 'update');
		if (! $dry_run && ! $site_same) {
			// Stored as exported: it was valid on the source, and the theme's
			// own sanitiser expects the options form's shape, not the stored one.
			update_option(BRIDGE_SITE_OPTIONS_KEY, $site);
		}

		$installed = array_map(static fn ($f) => is_array($f) ? (string) ($f['family'] ?? '') : '', bridge_installed_fonts());
		foreach ((array) $settings['fonts'] as $family) {
			if (in_array($family, $installed, true)) {
				continue;
			}
			$note('settings', 'Font: ' . $family, 'download');
			if (! $dry_run) {
				$result = bridge_install_google_font((string) $family);
				if (is_wp_error($result)) {
					$warn('Font ' . $family . ': ' . $result->get_error_message());
				}
			}
		}

		if (! empty($settings['site_icon'])) {
			$icon = (int) ($map['media'][(int) $settings['site_icon']] ?? 0);
			if ($icon > 0 && (int) get_option('site_icon') !== $icon) {
				$note('settings', 'Site icon', 'update');
				if (! $dry_run) {
					update_option('site_icon', $icon);
				}
			}
		}
	}

	/* ---- Things the package cannot carry -------------------------------- */

	$scan = array_merge(
		in_array('pages', $sections, true) ? array_column((array) ($data['pages'] ?? array()), 'content', 'path') : array(),
		in_array('posts', $sections, true) ? array_column((array) ($data['posts'] ?? array()), 'content', 'slug') : array(),
		in_array('menus', $sections, true) ? array_column((array) ($data['menus'] ?? array()), 'content', 'title') : array()
	);
	foreach ($scan as $where => $content) {
		if (preg_match_all('/\[([a-z0-9_-]+)[^\]]*\bid=["\']?(\d+)/i', (string) $content, $m, PREG_SET_ORDER)) {
			foreach ($m as $hit) {
				$warn(sprintf('"%s" uses the shortcode [%s id=%s]. IDs in shortcodes are not changed: check it points at the right item on this site.', $where, $hit[1], $hit[2]));
			}
		}
		if (preg_match('/<!-- wp:block \{"ref":(\d+)/', (string) $content, $hit)) {
			$warn(sprintf('"%s" uses a synced pattern (#%s). Synced patterns are not exported.', $where, $hit[1]));
		}
	}

	if (! $dry_run && function_exists('bridge_flush_tokens')) {
		bridge_flush_tokens();
	}

	$report['counts'] = array_count_values(array_column($report['actions'], 2));

	return $report;
}

/**
 * Attachment IDs the chosen sections refer to.
 */
function bridge_package_needed_media(array $data, array $index, array $sections): array
{
	$ids = array();

	if (in_array('settings', $sections, true) && ! empty($data['settings'])) {
		foreach (bridge_package_token_media_paths() as $path) {
			$ids[] = (int) bridge_package_array_get((array) $data['settings']['tokens'], $path);
		}
		$ids[] = (int) ($data['settings']['site_options']['footer_image'] ?? 0);
		$ids[] = (int) ($data['settings']['site_icon'] ?? 0);
	}
	foreach (array('menus', 'pages', 'posts') as $section) {
		if (in_array($section, $sections, true)) {
			foreach ((array) ($data[$section] ?? array()) as $item) {
				$ids = array_merge($ids, bridge_package_content_refs((string) $item['content'])['media']);
				$ids[] = (int) ($item['thumbnail'] ?? 0);
			}
		}
	}

	$ids = array_unique(array_filter(array_map('intval', $ids)));

	return array_values(array_filter($ids, static fn ($id) => isset($index['media'][$id])));
}

/**
 * Find or add one media file.
 *
 * Matched by its path in the uploads folder. An existing attachment with the
 * same file and size is reused; otherwise the file is copied in (SVGs are
 * sanitised first) and registered.
 *
 * @return array{id: int, action: string, note?: string, url_from?: string, url_to?: string}|WP_Error
 */
function bridge_package_import_media(string $zip_path, int $old_id, array $info, bool $dry_run)
{
	$file = ltrim(str_replace('\\', '/', (string) $info['file']), '/');

	// Refuse "../" steps, not dots inside a name ("figure...2020.jpg").
	if ('' === $file || preg_match('#(^|/)\.\.(/|$)#', $file) || ! preg_match('#^[\w\-./ ()@]+$#u', $file)) {
		return new WP_Error('bridge_package_media', 'unsafe file name, skipped.');
	}

	// SVG is allowed whatever the current user may upload: it is run through
	// Bridge's SVG sanitiser below before it is written.
	$type = wp_check_filetype(basename($file), array_merge(get_allowed_mime_types(), array('svg' => 'image/svg+xml')));
	if (empty($type['ext']) || preg_match('/^(php|phtml|phar|pht|js|html?|cgi|pl|py|sh|exe)$/i', (string) $type['ext'])) {
		return new WP_Error('bridge_package_media', 'file type not allowed, skipped.');
	}

	$uploads = wp_get_upload_dir();
	$abs     = trailingslashit($uploads['basedir']) . $file;

	$existing = get_posts(array('post_type' => 'attachment', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_wp_attached_file', 'meta_value' => $file)); // phpcs:ignore WordPress.DB.SlowDBQuery
	$same_size = is_file($abs) && (int) filesize($abs) === (int) $info['size'];

	if ($existing && $same_size) {
		return array('id' => (int) $existing[0], 'action' => 'exists');
	}

	$zip = new ZipArchive();
	if (true !== $zip->open($zip_path)) {
		return new WP_Error('bridge_package_media', 'package could not be read.');
	}
	$bytes = $zip->getFromName('media/' . $file);
	$zip->close();
	if (false === $bytes) {
		return new WP_Error('bridge_package_media', 'not in the package.');
	}

	if ('svg' === strtolower((string) $type['ext'])) {
		$bytes = function_exists('bridge_sanitize_svg') ? bridge_sanitize_svg($bytes) : null;
		if (null === $bytes) {
			return new WP_Error('bridge_package_media', 'SVG refused by the sanitiser.');
		}
	}

	// Same name, different file: keep both, under a new name.
	$url_from = '';
	$url_to   = '';
	if (is_file($abs) && ! $same_size) {
		$dir      = dirname($abs);
		$new_name = wp_unique_filename($dir, basename($abs));
		$new_file = ltrim(dirname($file) . '/' . $new_name, './');
		$url_from = trailingslashit($uploads['baseurl']) . $file;
		$url_to   = trailingslashit($uploads['baseurl']) . $new_file;
		$file     = $new_file;
		$abs      = trailingslashit($dir) . $new_name;
	}

	if ($dry_run) {
		return array('id' => $existing ? (int) $existing[0] : -$old_id, 'action' => 'add', 'note' => $url_from ? 'name taken by a different file, will be renamed' : '');
	}

	if ($existing && ! $url_from) {
		// Registered, but its file is missing: restore it.
		wp_mkdir_p(dirname($abs));
		file_put_contents($abs, $bytes); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return array('id' => (int) $existing[0], 'action' => 'restored');
	}

	if (! is_file($abs)) {
		wp_mkdir_p(dirname($abs));
		if (false === file_put_contents($abs, $bytes)) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new WP_Error('bridge_package_media', 'could not write to the uploads folder.');
		}
	}

	$id = wp_insert_attachment(
		wp_slash(array(
			'post_mime_type' => (string) ($info['mime'] ?: $type['type']),
			'post_title'     => (string) $info['title'],
			'post_excerpt'   => (string) $info['caption'],
			'post_content'   => (string) $info['description'],
			'post_status'    => 'inherit',
		)),
		$abs,
		0,
		true
	);
	if (is_wp_error($id)) {
		return $id;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $abs));
	if ('' !== (string) $info['alt']) {
		update_post_meta($id, '_wp_attachment_image_alt', wp_slash((string) $info['alt']));
	}

	$result = array('id' => (int) $id, 'action' => 'add');
	if ($url_from) {
		$result['url_from'] = $url_from;
		$result['url_to']   = $url_to;
		$result['note']     = 'renamed, name was taken by a different file';
	}

	return $result;
}

/**
 * Per-page meta the package carries: SEO description and the Title Banner
 * panel (style, alignment, colour), which decides whether the page template
 * prints the page title above content that has its own hero banner.
 */
function bridge_package_page_meta_keys(): array
{
	return array(BRIDGE_META_DESCRIPTION_KEY, 'bridge_banner_style', 'bridge_title_align', 'bridge_banner_color', '_yoast_wpseo_title', '_yoast_wpseo_metadesc');
}

/**
 * A post on this site with the given type and path (or slug).
 */
function bridge_package_find_post(string $type, string $path): int
{
	if ('' === $path) {
		return 0;
	}

	if (is_post_type_hierarchical($type)) {
		$post = get_page_by_path($path, OBJECT, $type);
		return $post && 'trash' !== $post->post_status ? (int) $post->ID : 0;
	}

	$ids = get_posts(array('post_type' => $type, 'name' => $path, 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'), 'numberposts' => 1, 'fields' => 'ids'));

	return $ids ? (int) $ids[0] : 0;
}

/**
 * A navigation menu on this site: by slug, else by a title only one menu has.
 */
function bridge_package_find_menu(string $slug, string $title): int
{
	$args = array('post_type' => 'wp_navigation', 'post_status' => array('publish', 'draft', 'private'), 'numberposts' => 2, 'fields' => 'ids');

	if ('' !== $slug) {
		$ids = get_posts($args + array('name' => $slug));
		if ($ids) {
			return (int) $ids[0];
		}
	}
	if ('' !== $title) {
		$ids = get_posts($args + array('title' => $title));
		if (1 === count($ids)) {
			return (int) $ids[0];
		}
	}

	return 0;
}

/**
 * Dotted paths of the leaves that differ between two arrays.
 */
function bridge_package_diff_keys($a, $b, string $prefix = ''): array
{
	if (! is_array($a) || ! is_array($b)) {
		return $a === $b ? array() : array(rtrim($prefix, '.'));
	}

	$out = array();
	foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $key) {
		$out = array_merge($out, bridge_package_diff_keys($a[$key] ?? null, $b[$key] ?? null, $prefix . $key . '.'));
	}

	return $out;
}

/* ==========================================================================
 * Backup and restore (settings; pages and menus keep revisions)
 * ======================================================================= */

function bridge_package_save_backup(array $sections): void
{
	update_option(
		BRIDGE_PACKAGE_BACKUP,
		array(
			'time'           => time(),
			'sections'       => $sections,
			'tokens'         => get_option(BRIDGE_TOKENS_OPTION, array()),
			'site_options'   => get_option(BRIDGE_SITE_OPTIONS_KEY, array()),
			'site_icon'      => (int) get_option('site_icon'),
			'show_on_front'  => (string) get_option('show_on_front'),
			'page_on_front'  => (int) get_option('page_on_front'),
			'page_for_posts' => (int) get_option('page_for_posts'),
		),
		false
	);
}

/**
 * Put the settings back as they were before the last import.
 */
function bridge_package_restore_backup()
{
	$backup = get_option(BRIDGE_PACKAGE_BACKUP);
	if (! is_array($backup) || empty($backup['time'])) {
		return new WP_Error('bridge_package_no_backup', 'There is no saved backup to restore.');
	}

	update_option(BRIDGE_TOKENS_OPTION, $backup['tokens'], true);
	update_option(BRIDGE_SITE_OPTIONS_KEY, $backup['site_options']);
	foreach (array('site_icon', 'show_on_front', 'page_on_front', 'page_for_posts') as $key) {
		update_option($key, $backup[$key]);
	}
	bridge_flush_tokens();
	delete_option(BRIDGE_PACKAGE_BACKUP);

	return (int) $backup['time'];
}

/* ==========================================================================
 * Admin screen: Theme Options > Export / Import
 * ======================================================================= */

function bridge_package_can_import(): bool
{
	return current_user_can(BRIDGE_OPERATOR_CAP) && current_user_can('edit_pages') && current_user_can('edit_others_posts') && current_user_can('upload_files');
}

add_action(
	'admin_menu',
	static function (): void {
		add_submenu_page(
			BRIDGE_OPTIONS_SLUG,
			__('Export / Import', 'bridge'),
			__('Export / Import', 'bridge'),
			BRIDGE_OPERATOR_CAP,
			BRIDGE_PACKAGE_PAGE_SLUG,
			'bridge_package_render_page'
		);
	},
	20
);

/**
 * Private folder for uploaded packages: random names, no web access.
 */
function bridge_package_storage_dir(): string
{
	$uploads = wp_get_upload_dir();
	$dir     = trailingslashit($uploads['basedir']) . BRIDGE_PACKAGE_UPLOAD_DIR;

	if (! is_dir($dir)) {
		wp_mkdir_p($dir);
	}
	if (! is_file($dir . '/.htaccess')) {
		file_put_contents($dir . '/.htaccess', "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n"); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	if (! is_file($dir . '/index.php')) {
		file_put_contents($dir . '/index.php', "<?php\n// Silence is golden.\n"); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	return $dir;
}

function bridge_package_stored_path(string $token): string
{
	return preg_match('/^[a-zA-Z0-9]{32}$/', $token) ? bridge_package_storage_dir() . '/' . $token . '.zip' : '';
}

function bridge_package_admin_url(array $args = array()): string
{
	return add_query_arg($args, admin_url('admin.php?page=' . BRIDGE_PACKAGE_PAGE_SLUG));
}

function bridge_package_posted_sections(): array
{
	$raw = isset($_POST['sections']) ? (array) wp_unslash($_POST['sections']) : array(); // phpcs:ignore WordPress.Security.NonceVerification
	return array_values(array_intersect(BRIDGE_PACKAGE_SECTIONS, array_map('sanitize_key', $raw)));
}

// Export: stream the zip.
add_action(
	'admin_post_bridge_package_export',
	static function (): void {
		if (! current_user_can(BRIDGE_OPERATOR_CAP)) {
			wp_die(esc_html__('You do not have permission to export.', 'bridge'), 403);
		}
		check_admin_referer('bridge_package_export');

		$tmp    = wp_tempnam('bridge-package');
		$result = bridge_package_export($tmp, bridge_package_posted_sections());
		if (is_wp_error($result)) {
			@unlink($tmp); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			wp_die(esc_html($result->get_error_message()));
		}

		$host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
		$name = sprintf('bridge-export-%s-%s.zip', sanitize_title($host), gmdate('Y-m-d-His'));

		nocache_headers();
		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . $name . '"');
		header('Content-Length: ' . filesize($tmp));
		while (ob_get_level()) {
			ob_end_clean();
		}
		readfile($tmp); // phpcs:ignore WordPress.WP.AlternativeFunctions
		@unlink($tmp); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		exit;
	}
);

// Import step 1: upload, store privately, show the dry run.
add_action(
	'admin_post_bridge_package_upload',
	static function (): void {
		if (! bridge_package_can_import()) {
			wp_die(esc_html__('You do not have permission to import.', 'bridge'), 403);
		}
		check_admin_referer('bridge_package_upload');

		$file = $_FILES['package'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if (! $file || UPLOAD_ERR_OK !== ($file['error'] ?? -1) || ! is_uploaded_file($file['tmp_name'])) {
			wp_safe_redirect(bridge_package_admin_url(array('bridge_error' => 'upload')));
			exit;
		}

		$token = wp_generate_password(32, false, false);
		$dest  = bridge_package_stored_path($token);
		if (! move_uploaded_file($file['tmp_name'], $dest)) {
			wp_safe_redirect(bridge_package_admin_url(array('bridge_error' => 'upload')));
			exit;
		}

		$check = bridge_package_read($dest);
		if (is_wp_error($check)) {
			@unlink($dest); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			set_transient('bridge_package_error_' . get_current_user_id(), $check->get_error_message(), 300);
			wp_safe_redirect(bridge_package_admin_url(array('bridge_error' => 'invalid')));
			exit;
		}

		wp_safe_redirect(bridge_package_admin_url(array('package' => $token)));
		exit;
	}
);

// Import step 2: apply.
add_action(
	'admin_post_bridge_package_import',
	static function (): void {
		if (! bridge_package_can_import()) {
			wp_die(esc_html__('You do not have permission to import.', 'bridge'), 403);
		}
		check_admin_referer('bridge_package_import');

		$token = isset($_POST['package']) ? sanitize_text_field(wp_unslash($_POST['package'])) : '';
		$path  = bridge_package_stored_path($token);
		if (! $path || ! is_file($path)) {
			wp_safe_redirect(bridge_package_admin_url(array('bridge_error' => 'expired')));
			exit;
		}

		$report = bridge_package_import($path, bridge_package_posted_sections(), false);
		@unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		if (is_wp_error($report)) {
			set_transient('bridge_package_error_' . get_current_user_id(), $report->get_error_message(), 300);
			wp_safe_redirect(bridge_package_admin_url(array('bridge_error' => 'invalid')));
			exit;
		}

		set_transient('bridge_package_report_' . get_current_user_id(), $report, HOUR_IN_SECONDS);
		wp_safe_redirect(bridge_package_admin_url(array('done' => 1)));
		exit;
	}
);

add_action(
	'admin_post_bridge_package_cancel',
	static function (): void {
		check_admin_referer('bridge_package_import');
		$token = isset($_POST['package']) ? sanitize_text_field(wp_unslash($_POST['package'])) : '';
		$path  = bridge_package_stored_path($token);
		if ($path && is_file($path)) {
			@unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		wp_safe_redirect(bridge_package_admin_url());
		exit;
	}
);

add_action(
	'admin_post_bridge_package_restore',
	static function (): void {
		if (! bridge_package_can_import()) {
			wp_die(esc_html__('You do not have permission to do this.', 'bridge'), 403);
		}
		check_admin_referer('bridge_package_restore');
		$result = bridge_package_restore_backup();
		wp_safe_redirect(bridge_package_admin_url(is_wp_error($result) ? array('bridge_error' => 'nobackup') : array('restored' => 1)));
		exit;
	}
);

/**
 * Report table, shared by the dry run and the result.
 */
function bridge_package_render_report(array $report): void
{
	$labels = array('settings' => 'Settings', 'menus' => 'Menus', 'pages' => 'Pages', 'posts' => 'Posts', 'media' => 'Media');
	$colors = array('create' => '#1a7f37', 'add' => '#1a7f37', 'update' => '#9a6700', 'download' => '#9a6700', 'restored' => '#9a6700', 'unchanged' => '#57606a', 'exists' => '#57606a');

	echo '<p>';
	foreach ($report['counts'] ?? array() as $action => $n) {
		printf('<strong>%d</strong> %s &nbsp; ', (int) $n, esc_html($action));
	}
	echo '</p>';

	if (! empty($report['warnings'])) {
		echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__('Check these:', 'bridge') . '</strong></p><ul style="list-style:disc;margin-left:2em">';
		foreach ($report['warnings'] as $w) {
			echo '<li>' . esc_html($w) . '</li>';
		}
		echo '</ul></div>';
	}

	echo '<table class="widefat striped" style="max-width:960px;margin-top:1em"><thead><tr><th>' . esc_html__('Type', 'bridge') . '</th><th>' . esc_html__('Item', 'bridge') . '</th><th>' . esc_html__('Action', 'bridge') . '</th><th>' . esc_html__('Details', 'bridge') . '</th></tr></thead><tbody>';
	foreach ($report['actions'] as $row) {
		list($section, $item, $action, $detail) = $row;
		printf(
			'<tr><td>%s</td><td><code>%s</code></td><td style="color:%s;font-weight:600">%s</td><td>%s</td></tr>',
			esc_html($labels[$section] ?? $section),
			esc_html($item),
			esc_attr($colors[$action] ?? 'inherit'),
			esc_html($action),
			esc_html($detail)
		);
	}
	echo '</tbody></table>';
}

function bridge_package_render_page(): void
{
	if (! current_user_can(BRIDGE_OPERATOR_CAP)) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$token = isset($_GET['package']) ? sanitize_text_field(wp_unslash($_GET['package'])) : '';
	$error = isset($_GET['bridge_error']) ? sanitize_key($_GET['bridge_error']) : '';
	// phpcs:enable

	$section_labels = array(
		'settings' => __('Theme settings — Theme Options, Site Options, fonts, site icon', 'bridge'),
		'menus'    => __('Menus — header, footer and other navigation menus', 'bridge'),
		'pages'    => __('Pages — every page, with its template, featured image, title banner and SEO description; front page and posts page', 'bridge'),
		'posts'    => __('Posts — every blog post, with its featured image, categories, tags and SEO description', 'bridge'),
	);

	echo '<div class="wrap"><h1>' . esc_html__('Export / Import', 'bridge') . '</h1>';
	echo '<p style="max-width:760px">' . esc_html__('Move this site\'s theme settings, menus, pages and posts to another site running Bridge. Images they use go with them. Pages, posts and menus are matched by their address and updated in place; nothing on the other site is deleted.', 'bridge') . '</p>';

	if ($error) {
		$messages = array(
			'upload'   => __('The file did not upload. Try again.', 'bridge'),
			'expired'  => __('That upload has expired. Upload the package again.', 'bridge'),
			'nobackup' => __('There is no backup to restore.', 'bridge'),
			'invalid'  => (string) get_transient('bridge_package_error_' . get_current_user_id()),
		);
		echo '<div class="notice notice-error"><p>' . esc_html($messages[$error] ?? __('Something went wrong.', 'bridge')) . '</p></div>';
	}
	if (isset($_GET['restored'])) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success"><p>' . esc_html__('Settings restored to how they were before the last import.', 'bridge') . '</p></div>';
	}

	// Result of an import.
	if (isset($_GET['done'])) { // phpcs:ignore WordPress.Security.NonceVerification
		$report = get_transient('bridge_package_report_' . get_current_user_id());
		if (is_array($report)) {
			echo '<div class="notice notice-success"><p>' . esc_html__('Import finished.', 'bridge') . '</p></div>';
			bridge_package_render_report($report);
		}
		echo '<p><a class="button" href="' . esc_url(bridge_package_admin_url()) . '">' . esc_html__('Back', 'bridge') . '</a></p></div>';
		return;
	}

	// Dry run of an uploaded package.
	if ($token) {
		$path = bridge_package_stored_path($token);
		$pkg  = $path && is_file($path) ? bridge_package_read($path) : new WP_Error('expired', __('That upload has expired. Upload the package again.', 'bridge'));

		if (is_wp_error($pkg)) {
			echo '<div class="notice notice-error"><p>' . esc_html($pkg->get_error_message()) . '</p></div></div>';
			return;
		}

		$m      = $pkg['manifest'];
		$report = bridge_package_import($path, (array) $m['sections'], true);

		printf(
			'<h2>%s</h2><p>%s <code>%s</code> · %s · %s</p>',
			esc_html__('Preview — nothing has changed yet', 'bridge'),
			esc_html__('Exported from', 'bridge'),
			esc_html((string) $m['source_home']),
			esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), get_date_from_gmt((string) $m['created']))),
			esc_html(sprintf(__('Bridge %s', 'bridge'), (string) $m['theme']))
		);

		if (is_wp_error($report)) {
			echo '<div class="notice notice-error"><p>' . esc_html($report->get_error_message()) . '</p></div></div>';
			return;
		}

		bridge_package_render_report($report);

		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:1.5em">';
		wp_nonce_field('bridge_package_import');
		echo '<input type="hidden" name="package" value="' . esc_attr($token) . '">';
		echo '<fieldset><legend><strong>' . esc_html__('Import these parts:', 'bridge') . '</strong></legend>';
		foreach ((array) $m['sections'] as $section) {
			if (isset($section_labels[$section])) {
				printf('<p><label><input type="checkbox" name="sections[]" value="%s" checked> %s</label></p>', esc_attr($section), esc_html($section_labels[$section]));
			}
		}
		echo '</fieldset>';
		echo '<p>' . esc_html__('Updated pages, posts and menus keep their previous version as a revision. The current settings are saved first and can be restored from this screen.', 'bridge') . '</p>';
		echo '<p><button type="submit" name="action" value="bridge_package_import" class="button button-primary">' . esc_html__('Import now', 'bridge') . '</button> ';
		echo '<button type="submit" name="action" value="bridge_package_cancel" class="button">' . esc_html__('Cancel', 'bridge') . '</button></p>';
		echo '</form></div>';
		return;
	}

	// Export.
	echo '<h2>' . esc_html__('Export', 'bridge') . '</h2>';
	echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
	wp_nonce_field('bridge_package_export');
	echo '<input type="hidden" name="action" value="bridge_package_export">';
	foreach ($section_labels as $section => $label) {
		// Posts make a much larger file, so they are opt-in.
		printf('<p><label><input type="checkbox" name="sections[]" value="%s"%s> %s</label></p>', esc_attr($section), 'posts' === $section ? '' : ' checked', esc_html($label));
	}
	echo '<p class="description">' . esc_html__('Site Options include the Google Maps key. Keep the downloaded file private.', 'bridge') . '</p>';
	echo '<p><button type="submit" class="button button-primary">' . esc_html__('Download export file', 'bridge') . '</button></p></form>';

	// Import.
	if (bridge_package_can_import()) {
		echo '<hr><h2>' . esc_html__('Import', 'bridge') . '</h2>';
		echo '<p>' . esc_html__('Upload an export file from another site. You will see exactly what will change before anything is saved.', 'bridge') . '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('bridge_package_upload');
		echo '<input type="hidden" name="action" value="bridge_package_upload">';
		echo '<p><input type="file" name="package" accept=".zip,application/zip" required></p>';
		echo '<p><button type="submit" class="button">' . esc_html__('Upload and preview', 'bridge') . '</button></p></form>';

		$backup = get_option(BRIDGE_PACKAGE_BACKUP);
		if (is_array($backup) && ! empty($backup['time'])) {
			echo '<hr><h2>' . esc_html__('Undo settings', 'bridge') . '</h2>';
			printf('<p>%s</p>', esc_html(sprintf(__('Settings were saved before the import on %s. Restoring puts back Theme Options, Site Options, the site icon and the front page choice. Pages and menus are restored from their revisions.', 'bridge'), wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $backup['time']))));
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
			wp_nonce_field('bridge_package_restore');
			echo '<input type="hidden" name="action" value="bridge_package_restore">';
			echo '<p><button type="submit" class="button" onclick="return confirm(\'' . esc_js(__('Restore the settings from before the last import?', 'bridge')) . '\')">' . esc_html__('Restore previous settings', 'bridge') . '</button></p></form>';
		}
	}

	echo '</div>';
}

/* ==========================================================================
 * WP-CLI: wp bridge package …
 * ======================================================================= */

if (defined('WP_CLI') && WP_CLI) {
	/**
	 * Export and import theme settings, menus, pages and posts between sites.
	 */
	class Bridge_Package_CLI_Command
	{
		/**
		 * Write an export package.
		 *
		 * ## OPTIONS
		 *
		 * <file>
		 * : Path of the .zip to write.
		 *
		 * [--only=<sections>]
		 * : Comma-separated: settings, menus, pages, posts. Default: all.
		 */
		public function export(array $args, array $assoc_args): void
		{
			$sections = isset($assoc_args['only']) ? array_map('trim', explode(',', (string) $assoc_args['only'])) : BRIDGE_PACKAGE_SECTIONS;
			$result   = bridge_package_export($args[0], $sections);
			if (is_wp_error($result)) {
				WP_CLI::error($result->get_error_message());
			}
			if ($result['missing']) {
				WP_CLI::warning('Media whose file is missing on disk (skipped): #' . implode(', #', $result['missing']));
			}
			if ($result['large']) {
				WP_CLI::warning('Listed but not packed (over ' . size_format(BRIDGE_PACKAGE_MAX_MEDIA_BYTES) . '): ' . implode(', ', $result['large']));
			}
			WP_CLI::success(sprintf('Wrote %s: %s — %d pages, %d posts, %d menus, %d media files.', $args[0], implode(', ', $result['sections']), $result['pages'], $result['posts'], $result['menus'], $result['media']));
		}

		/**
		 * Import a package. Shows the plan and asks before changing anything.
		 *
		 * ## OPTIONS
		 *
		 * <file>
		 * : The .zip made by `wp bridge package export` or the admin screen.
		 *
		 * [--only=<sections>]
		 * : Comma-separated: settings, menus, pages, posts. Default: all in the package.
		 *
		 * [--dry-run]
		 * : Show what would change and stop.
		 *
		 * [--yes]
		 * : Do not ask for confirmation.
		 */
		public function import(array $args, array $assoc_args): void
		{
			$pkg = bridge_package_read($args[0]);
			if (is_wp_error($pkg)) {
				WP_CLI::error($pkg->get_error_message());
			}
			$sections = isset($assoc_args['only']) ? array_map('trim', explode(',', (string) $assoc_args['only'])) : (array) $pkg['manifest']['sections'];

			$plan = bridge_package_import($args[0], $sections, true);
			if (is_wp_error($plan)) {
				WP_CLI::error($plan->get_error_message());
			}
			$this->print_report($plan);

			if (isset($assoc_args['dry-run'])) {
				return;
			}

			WP_CLI::confirm('Apply these changes?', $assoc_args);
			$report = bridge_package_import($args[0], $sections, false);
			if (is_wp_error($report)) {
				WP_CLI::error($report->get_error_message());
			}
			foreach ($report['warnings'] as $w) {
				WP_CLI::warning($w);
			}
			WP_CLI::success('Imported. ' . $this->counts($report));
		}

		/**
		 * Restore the settings saved before the last import.
		 */
		public function restore(): void
		{
			$result = bridge_package_restore_backup();
			if (is_wp_error($result)) {
				WP_CLI::error($result->get_error_message());
			}
			WP_CLI::success('Settings restored to ' . wp_date('Y-m-d H:i', $result) . '.');
		}

		private function print_report(array $report): void
		{
			$rows = array();
			foreach ($report['actions'] as $row) {
				$rows[] = array('type' => $row[0], 'item' => $row[1], 'action' => $row[2], 'details' => $row[3]);
			}
			if ($rows) {
				WP_CLI\Utils\format_items('table', $rows, array('type', 'item', 'action', 'details'));
			}
			foreach ($report['warnings'] as $w) {
				WP_CLI::warning($w);
			}
			WP_CLI::log('Plan: ' . $this->counts($report));
		}

		private function counts(array $report): string
		{
			$parts = array();
			foreach ($report['counts'] ?? array() as $action => $n) {
				$parts[] = $n . ' ' . $action;
			}
			return $parts ? implode(', ', $parts) : 'nothing to do';
		}
	}

	WP_CLI::add_command('bridge package', 'Bridge_Package_CLI_Command');
}
