<?php

/**
 * Bridge — the icon library.
 *
 * Icons are authored as bare geometry in src/icons/*.svg and compiled by the
 * Vite build into dist/icons.json. Nothing here reads the source directory at
 * runtime: the compiled library is the contract, so adding an icon is a build
 * step rather than something that can happen by dropping a file on a server.
 *
 * Every icon renders in `currentColor` at the design system's stroke weight,
 * which means an icon can never carry an off-brand fill — it inherits from
 * whatever it sits inside, exactly like text.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Stroke widths for the two icon weights.
 *
 * A weight rather than a line/solid pair: a solid set is a second set of
 * artwork to draw and keep in step, whereas stroke weight is the same
 * geometry rendered with more or less presence, and it is what actually needs
 * to change when a brand moves between delicate and confident.
 */
function bridge_icon_weights(): array
{
	return array(
		'regular' => '1.6',
		'bold'    => '2.2',
	);
}

/**
 * Rendered icon sizes, in em so an icon scales with its surrounding text.
 */
function bridge_icon_sizes(): array
{
	return array(
		'small'  => '1em',
		'medium' => '1.5em',
		'large'  => '2.25em',
	);
}

/**
 * The compiled icon library: name => inner SVG markup.
 *
 * @return array<string, string>
 */
function bridge_icon_library(): array
{
	static $icons = null;

	if (null !== $icons) {
		return $icons;
	}

	$icons = array();
	$path  = BRIDGE_DIST_PATH . '/icons.json';

	if (file_exists($path)) {
		$data = wp_json_file_decode($path, array('associative' => true));

		if (is_array($data)) {
			foreach ($data as $name => $markup) {
				$icons[sanitize_key((string) $name)] = (string) $markup;
			}
		}
	}

	return $icons;
}

/**
 * Render one icon as inline SVG.
 *
 * The markup in the library is trusted: it is compiled from files in the
 * theme by the build, never from user input, and the icon name is checked
 * against the library before use.
 *
 * @param string $name  Icon name.
 * @param array  $args  size: small|medium|large, label: accessible name,
 *                      class: extra classes.
 * @return string Empty when the icon is unknown.
 */
function bridge_render_icon(string $name, array $args = array()): string
{
	$library = bridge_icon_library();
	$name    = sanitize_key($name);

	if (! isset($library[$name])) {
		return '';
	}

	$sizes  = bridge_icon_sizes();
	$size   = isset($args['size'], $sizes[$args['size']]) ? (string) $args['size'] : 'medium';
	$label  = isset($args['label']) ? trim((string) $args['label']) : '';
	$weight = bridge_icon_weights();
	$tokens = bridge_get_tokens();
	$stroke = $weight[$tokens['icons']['weight']] ?? $weight['regular'];

	$classes = 'bridge-icon bridge-icon--' . $size;

	if (! empty($args['class'])) {
		$classes .= ' ' . $args['class'];
	}

	// An icon with a label is meaningful content and needs a name; an icon
	// without one is decoration sitting beside text that already says it, and
	// announcing it twice is worse than not announcing it at all.
	$a11y = '' !== $label
		? sprintf('role="img" aria-label="%s"', esc_attr($label))
		: 'aria-hidden="true" focusable="false"';

	return sprintf(
		'<svg class="%1$s" viewBox="0 0 24 24" width="%2$s" height="%2$s" fill="none" stroke="currentColor" stroke-width="%3$s" stroke-linecap="round" stroke-linejoin="round" %4$s>%5$s</svg>',
		esc_attr($classes),
		esc_attr($sizes[$size]),
		esc_attr($stroke),
		$a11y, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — assembled from escaped parts above.
		$library[$name] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — build output from theme source.
	);
}

/**
 * Register the icon block.
 */
function bridge_register_icon_block(): void
{
	if (bridge_register_script('bridge-icon-editor', 'icon-editor.js', bridge_editor_script_deps())) {
		// The picker offers exactly the compiled library and nothing else —
		// an editor can place an icon but can never introduce one.
		wp_add_inline_script(
			'bridge-icon-editor',
			'window.bridgeIcons = ' . wp_json_encode(bridge_icon_library()) . ';',
			'before'
		);
	}

	bridge_register_style('bridge-icon-editor-style', 'icon-editor.css');

	bridge_register_block('icon');
}
add_action('init', 'bridge_register_icon_block');
