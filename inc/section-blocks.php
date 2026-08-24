<?php

/**
 * Bridge — shared plumbing for the section blocks.
 *
 * The ten blocks converted from the old ACF flexible-content layouts are all
 * the same shape: a full-width band of the page, painted with one of the
 * theme's skins, holding a heading, some content and sometimes a repeating
 * set of items. This file holds what all of them need, so each block's
 * render.php is left with only the part that is actually about that block.
 *
 * @package Bridge
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The blocks that are a band of the page, and so take a skin.
 *
 * The old `colour_scheme` field offered four values — white, light, dark and
 * primary. Three of them are the theme's existing skins; the fourth, white, is
 * the page's own background, which is what a section with no skin already
 * looks like. So the field becomes the Styles tab an editor already knows,
 * every value resolving to a palette preset, and the block gains no control of
 * its own to keep in step with the design system.
 *
 * @return string[]
 */
function bridge_section_block_names(): array
{
	return array(
		'bridge/alternating-content',
		'bridge/feature-blocks',
		'bridge/call-to-action',
		'bridge/downloads',
		'bridge/gallery',
		'bridge/logo-slider',
		'bridge/map',
		'bridge/price-table',
		'bridge/split-content',
		'bridge/testimonials',
	);
}

/**
 * Offer the section skins on every block that is a band of the page.
 *
 * `bridge_register_section_skins()` in structure.php does the same for
 * core/group. Kept separate rather than folded into that loop because the two
 * answer different questions — core/group is skinned so an editor can build a
 * band by hand, these are skinned because a band is all they are.
 */
function bridge_register_section_block_skins(): void
{
	if (! function_exists('bridge_section_skins')) {
		return;
	}

	foreach (bridge_section_block_names() as $block) {
		foreach (bridge_section_skins() as $name => $skin) {
			register_block_style(
				$block,
				array(
					'name'  => $name,
					'label' => $skin['label'],
				)
			);
		}
	}
}
add_action('init', 'bridge_register_section_block_skins', 20);

/**
 * The opening tag for a section block.
 *
 * A <section>, not a div: each of these is a titled band of the page, which is
 * the one thing the element exists for — and it gives a screen-reader user a
 * landmark to jump between rather than an undifferentiated run of content.
 * The accessible name comes from the band's own heading via
 * `aria-labelledby`, so a section with no heading gets no name and is not
 * announced as an empty landmark.
 *
 * Always full window width. A band of the page that stopped at the text
 * column would be a panel, not a band, and the width was never the interesting
 * question — what varies is how wide the *content* inside it runs, which each
 * block's own stylesheet decides. So there is no align control here and no
 * align class to keep in step with one.
 *
 * Background and text colour come from core's own colour support, declared in
 * each block.json — the palette is locked to the theme's six slugs
 * (`custom: false` in theme.json), so an editor picks a brand colour or
 * nothing. `get_block_wrapper_attributes()` merges those classes in.
 *
 * @param array  $attributes Block attributes.
 * @param string $class      The block's own root class.
 * @param string $style      Optional inline style declarations.
 * @param string $label_id   Optional id of the heading that names the band.
 * @return string Ready-to-print opening tag.
 */
function bridge_section_wrapper(array $attributes, string $class, string $style = '', string $label_id = ''): string
{
	// `alignfull` is not an alignment choice here — there is no control for
	// one, and every band is full width. It is the class core's constrained
	// layout tests for:
	//
	//   .is-layout-constrained > :where(:not(.alignleft):not(.alignright)
	//     :not(.alignfull)) { max-width: …; margin-left: auto !important;
	//                         margin-right: auto !important }
	//
	// Those auto margins are `!important`, so no breakout of ours can outrank
	// them: the band came out window-wide but pinned to the text column's left
	// edge, overflowing the right of the window. Wearing the class is the only
	// way out of that rule, and it is what the hero blocks already do.
	$args = array(
		'class' => $class . ' bridge-section bridge-band alignfull',
	);

	if ('' !== $style) {
		$args['style'] = $style;
	}

	if ('' !== $label_id) {
		$args['aria-labelledby'] = $label_id;
	}

	return '<section ' . get_block_wrapper_attributes($args) . '>';
}

/**
 * Register a section block's editor script, stylesheet and type in one call.
 *
 * Every one of these blocks registers the same three things under the same
 * three names. Spelling that out ten times in bridge_register_blocks() would
 * be ten chances to mistype one.
 *
 * @param string $slug  Directory name under src/blocks, which is also the
 *                      dist filename stem and the handle suffix.
 * @param bool   $style Whether the block ships a stylesheet of its own.
 * @param bool   $view  Whether the block ships a frontend script.
 */
function bridge_register_section_block(string $slug, bool $style = true, bool $view = false): void
{
	bridge_register_script('bridge-' . $slug . '-editor', $slug . '-editor.js', bridge_editor_script_deps());

	if ($style) {
		bridge_register_style('bridge-' . $slug . '-style', $slug . '.css');
	}

	if ($view) {
		bridge_register_script('bridge-' . $slug . '-view', $slug . '-view.js', array(), true);
	}

	bridge_register_block($slug);
}

/**
 * Split a section block's children into its intro and its items.
 *
 * Every one of these blocks is the same two parts: a full-width intro — a
 * headline and, optionally, a summary line — and then the repeating things the
 * block is actually for. The old layouts made that a "Section Intro" field
 * group behind a show/hide toggle, with its own heading, content and alignment
 * fields; here it is simply the blocks that are not items, so an editor writes
 * a headline by writing a heading.
 *
 * Rendered child by child rather than taking the `$content` blob, because the
 * items need a container the intro must stay out of — a grid, or a row that
 * scrolls sideways — and sorting them here, where the block names are still
 * known, saves the stylesheet from undoing the nesting afterwards.
 *
 * @param WP_Block $block Parsed block instance.
 * @param string   $child Block name of the repeating item.
 * @return array{0:string,1:string} Intro HTML, then items HTML.
 */
function bridge_section_split(WP_Block $block, string $child): array
{
	$intro = '';
	$items = '';

	foreach ($block->inner_blocks as $inner) {
		if ($child === $inner->name) {
			$items .= $inner->render();
		} else {
			$intro .= $inner->render();
		}
	}

	return array($intro, $items);
}

/**
 * The id of the first heading in a fragment, adding one if it has none.
 *
 * What gives a <section> its accessible name. A landmark with no name is worse
 * than no landmark at all — a screen-reader user stepping between them lands
 * somewhere with nothing to say where they are — so every band that has a
 * heading points `aria-labelledby` at it, and a band without one gets no name
 * and is not announced as a region.
 *
 * The fragment is passed by reference because stamping an id changes it. An
 * anchor the editor set already is used as it stands and nothing is rewritten.
 *
 * No `class_exists()` guard on the tag processor: it has been in core since 6.2
 * and the theme asks for 6.6. A guard would not have made an older WordPress
 * work — it would have quietly dropped the name and left nothing to say why.
 *
 * @param string $html Rendered HTML. Modified in place when an id is added.
 * @return string The heading's id, or an empty string when there is no heading.
 */
function bridge_heading_label_id(string &$html): string
{
	if ('' === trim($html)) {
		return '';
	}

	$processor = new WP_HTML_Tag_Processor($html);

	while ($processor->next_tag()) {
		if (! in_array(strtoupper((string) $processor->get_tag()), array('H1', 'H2', 'H3', 'H4', 'H5', 'H6'), true)) {
			continue;
		}

		$existing = $processor->get_attribute('id');

		if (is_string($existing) && '' !== $existing) {
			return $existing;
		}

		$label_id = wp_unique_id('bridge-section-');
		$processor->set_attribute('id', $label_id);
		$html = $processor->get_updated_html();

		return $label_id;
	}

	return '';
}

/**
 * The intro's markup, and the id of the heading that names it.
 *
 * A row of its own, full container width — the headline introduces everything
 * under it, so it is not part of the grid it sits above.
 *
 * The heading also names the <section> for assistive technology, which needs
 * an id to point `aria-labelledby` at. If the editor set an anchor the block
 * already has one; if not, one is generated here. Nothing is added when there
 * is no heading — an unnamed landmark is worse than no landmark, because a
 * screen-reader user stepping through them lands somewhere with nothing to
 * tell them where they are.
 *
 * @param string $intro Rendered intro HTML.
 * @param string $class Block-specific class for the intro wrapper.
 * @return array{0:string,1:string} Intro HTML, then the heading's id.
 */
function bridge_section_intro(string $intro, string $class): array
{
	// Every one of these blocks seeds an optional summary paragraph under its
	// heading. Left untouched it is still a block, and still serialises — so
	// the front end was printing an empty <p> that spent a row of the
	// section's gap on nothing. Dropped here, where every section passes.
	$intro = preg_replace('#<p[^>]*>(?:\s|&nbsp;|<br\s*/?>)*</p>#i', '', $intro);

	if ('' === trim($intro)) {
		return array('', '');
	}

	$label_id = bridge_heading_label_id($intro);

	return array(
		sprintf('<div class="bridge-section__intro %s">%s</div>', esc_attr($class), $intro),
		$label_id,
	);
}

/**
 * One logo-slider row, printed twice.
 *
 * The animation slides the track by exactly half its width, so the second copy
 * stands where the first began at the moment it loops — which is what makes
 * the movement seamless rather than snapping back. The duplicate is
 * `aria-hidden`, so a screen reader hears each logo once.
 *
 * Lives here rather than in the block's render.php because that file is
 * included once per block on the page: a page with two logo sliders would
 * redeclare the function and fatal.
 *
 * @param array  $images    Image attributes from the block.
 * @param string $display   Whether logos are contained or cropped.
 * @param bool   $reverse   Whether the row travels the other way.
 */
function bridge_logo_slider_row(array $images, string $display, bool $reverse = false): void
{
	$classes = 'bridge-logos__row' . ($reverse ? ' is-reverse' : '');
?>
	<div class="<?php echo esc_attr($classes); ?>" style="--bridge-logo-count: <?php echo (int) count($images); ?>;">
		<ul class="bridge-logos__track" role="list">
			<?php foreach ($images as $image) : ?>
				<?php bridge_logo_slider_item((array) $image, $display, false); ?>
			<?php endforeach; ?>
			<?php foreach ($images as $image) : ?>
				<?php bridge_logo_slider_item((array) $image, $display, true); ?>
			<?php endforeach; ?>
		</ul>
	</div>
<?php
}

/**
 * One logo.
 *
 * @param array  $image     Image attributes.
 * @param string $display   Whether logos are contained or cropped.
 * @param bool   $duplicate Whether this is the seam copy.
 */
function bridge_logo_slider_item(array $image, string $display, bool $duplicate): void
{
	$id = (int) ($image['id'] ?? 0);

	if (0 === $id) {
		return;
	}
?>
	<li class="bridge-logos__item" <?php echo $duplicate ? ' aria-hidden="true"' : ''; ?>>
		<?php
		echo wp_get_attachment_image(
			$id,
			'medium',
			false,
			array(
				// Nothing escaped on the way in: wp_get_attachment_image() runs
				// esc_attr() over everything it is handed. Escaping first was
				// not visibly wrong — esc_attr() leaves a valid entity alone —
				// but it is the wrong habit for data entering an API that
				// escapes.
				'class'    => 'bridge-logos__image is-' . $display,
				'alt'      => $duplicate ? '' : (string) get_post_meta($id, '_wp_attachment_image_alt', true),
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		);
		?>
	</li>
<?php
}

/**
 * A breadcrumb trail for the current page, as an ordered list.
 *
 * An <ol> inside a labelled <nav>, because the order is the meaning — each
 * step is one level nearer the front page — and a screen-reader user needs to
 * be able to skip the whole trail, which an unlabelled row of links does not
 * allow. The current page is the last item and is not a link: linking a page
 * to itself gives a keyboard user somewhere to go that they already are.
 *
 * Only ancestors are walked, so this is a hierarchy trail rather than a
 * history one — the same thing the old block drew, minus the plugin.
 *
 * @return string
 */
function bridge_breadcrumb_html(): string
{
	if (is_front_page()) {
		return '';
	}

	$crumbs = array(
		array(
			'label' => __('Home', 'bridge'),
			'url'   => home_url('/'),
		),
	);

	$post_id = get_the_ID();

	if ($post_id && is_singular()) {
		foreach (array_reverse((array) get_post_ancestors($post_id)) as $ancestor) {
			$crumbs[] = array(
				'label' => get_the_title($ancestor),
				'url'   => (string) get_permalink($ancestor),
			);
		}

		$crumbs[] = array(
			'label' => get_the_title($post_id),
			'url'   => '',
		);
	} elseif (is_archive()) {
		$crumbs[] = array(
			'label' => wp_strip_all_tags((string) get_the_archive_title()),
			'url'   => '',
		);
	} elseif (is_search()) {
		$crumbs[] = array(
			/* translators: %s: search term. */
			'label' => sprintf(__('Search: %s', 'bridge'), get_search_query()),
			'url'   => '',
		);
	}

	if (count($crumbs) < 2) {
		return '';
	}

	$items = '';

	foreach ($crumbs as $crumb) {
		$label = esc_html((string) $crumb['label']);

		if ('' === $crumb['url']) {
			$items .= sprintf(
				'<li class="bridge-breadcrumb__item"><span aria-current="page">%s</span></li>',
				$label
			);
			continue;
		}

		$items .= sprintf(
			'<li class="bridge-breadcrumb__item"><a href="%s">%s</a></li>',
			esc_url((string) $crumb['url']),
			$label
		);
	}

	return sprintf(
		'<nav class="bridge-breadcrumb" aria-label="%s"><ol class="bridge-breadcrumb__list">%s</ol></nav>',
		esc_attr__('Breadcrumb', 'bridge'),
		$items
	);
}
