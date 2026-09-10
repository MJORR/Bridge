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
		'bridge/cards',
		'bridge/contact-form',
		'bridge/section',
		'bridge/downloads',
		'bridge/faqs',
		'bridge/gallery',
		'bridge/goals',
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
 * @param string   $slug  Directory name under src/blocks, which is also the
 *                        dist filename stem and the handle suffix.
 * @param bool     $style Whether the block ships a stylesheet of its own.
 * @param bool     $view  Whether the block ships a frontend script.
 * @param string[] $deps  Extra editor script dependencies, for a block whose
 *                        edit view reaches past the shared set — the FAQs
 *                        block wants `wp-core-data` to list the posts it will
 *                        pull in.
 */
function bridge_register_section_block(string $slug, bool $style = true, bool $view = false, array $deps = array()): void
{
	bridge_register_script(
		'bridge-' . $slug . '-editor',
		$slug . '-editor.js',
		array_merge(bridge_editor_script_deps(), $deps)
	);

	if ($style) {
		bridge_register_style('bridge-' . $slug . '-style', $slug . '.css');
	}

	if ($view) {
		bridge_register_script('bridge-' . $slug . '-view', $slug . '-view.js', array(), true);
	}

	bridge_register_block($slug);
}

/**
 * The attributes a carousel's scrolling track wears.
 *
 * `tabindex` so the row can be reached and scrolled with a keyboard: the items
 * hold a link each, so tabbing already walks the row — this is for the space
 * bar and the arrow keys, which act on the container rather than on a link.
 * The data attribute is what carousel.js finds it by.
 *
 * @param string $label Accessible name for the scrolling region.
 * @return string Ready-to-print attributes.
 */
function bridge_carousel_track_attrs(string $label): string
{
	return sprintf(
		'tabindex="0" role="group" aria-label="%s" data-bridge-carousel-track',
		esc_attr($label)
	);
}

/**
 * The control strip under a carousel: a chevron at each end, dots between.
 *
 * Shared by every block with a swipe layout, so the markup the one script
 * drives is written once. A block prints this after its track and includes
 * `carousel.controls` in its stylesheet; nothing else is needed.
 *
 * The controls are an enhancement over a row that already scrolls: a trackpad,
 * a swipe and the keyboard all work with no script at all. So the buttons ship
 * `hidden` and the dots ship empty, and carousel.js takes the attribute off and
 * fills the strip once it has measured the row. Nothing here is a control that
 * does nothing.
 *
 * The icons are drawn here rather than in JavaScript so there is one icon
 * library, in PHP, wearing the stroke weight set in Theme Options. The dot
 * label is passed as a `%d` pattern for the same reason — the script carries no
 * translations and needs no wp-i18n.
 *
 * @param array<string, string> $labels Accessible names: `prev`, `next`,
 *                                      `dots` and `dot` (a `%d` pattern).
 * @return string Ready-to-print markup.
 */
function bridge_carousel_controls(array $labels = array()): string
{
	$prev = $labels['prev'] ?? __('Previous', 'bridge');
	$next = $labels['next'] ?? __('Next', 'bridge');
	$dots = $labels['dots'] ?? __('Pages', 'bridge');
	/* translators: %d: page number. */
	$dot = $labels['dot'] ?? __('Page %d', 'bridge');

	$button = static function (string $direction, string $icon, string $label): string {
		return sprintf(
			'<button type="button" class="bridge-carousel__arrow bridge-carousel__arrow--%1$s" data-bridge-carousel-%1$s hidden>%2$s</button>',
			esc_attr($direction),
			bridge_render_icon($icon, array('label' => $label))
		);
	};

	return sprintf(
		'<div class="bridge-carousel__controls" data-bridge-carousel-controls>%1$s<div class="bridge-carousel__dots" role="group" aria-label="%2$s" data-dot-label="%3$s" data-bridge-carousel-dots></div>%4$s</div>',
		$button('prev', 'chevron-left', $prev),
		esc_attr($dots),
		esc_attr($dot),
		$button('next', 'chevron-right', $next)
	);
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

	bridge_prime_item_images($block);

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
 * The attributes an item block keeps an attachment id in.
 *
 * One list for every repeating block in the theme, because the thing being
 * primed is the same thing in each: a media library id the item is about to
 * ask WordPress for. A new item block joins by naming its id attribute here.
 *
 * @return string[]
 */
function bridge_item_image_attributes(): array
{
	return array( 'imageId', 'graphicId', 'backgroundId', 'previewId', 'fileId' );
}

/**
 * Fetch every image a band's items are going to draw, in one round trip.
 *
 * The N+1 the Cards band already avoids, closed for the other five. Each item
 * renders itself, so each one was calling `wp_get_attachment_image()` on an id
 * nothing had fetched yet — the attachment post, then its metadata, then its
 * alt text: two queries per image, and a twenty-image gallery spent forty of
 * them where two would do. The items are still the things that draw the
 * pictures; this only makes sure the pictures are already in memory when they
 * do.
 *
 * It has to run before the first item renders, which is why it sits here
 * rather than in each render.php: `bridge_section_split()` is the one place
 * every one of these blocks passes through, and it walks the children once
 * before rendering them anyway.
 *
 * Below two ids there is nothing to save — priming one attachment is the same
 * two queries the item was going to make — so a band with a single image is
 * left alone rather than paying for the arithmetic.
 *
 * @param WP_Block $block The band whose items are about to render.
 */
function bridge_prime_item_images(WP_Block $block): void
{
	$ids  = array();
	$keys = bridge_item_image_attributes();

	foreach ($block->inner_blocks as $inner) {
		foreach ($keys as $key) {
			$id = absint($inner->attributes[$key] ?? 0);

			if ($id > 0) {
				$ids[$id] = true;
			}
		}
	}

	if (count($ids) < 2) {
		return;
	}

	// Posts and their meta, which is what an image needs; no terms, which an
	// attachment rarely has and no item block reads. The same call, and the
	// same two arguments, as the Cards band's.
	_prime_post_caches(array_keys($ids), false, true);
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
 * The class and inline style a band's decorative mask shape needs.
 *
 * The site's mask shape, painted as one flat palette colour behind whatever the
 * band holds. Shared because two bands already want it and the arithmetic is
 * not the interesting part: which colour, how wide, how far off the right edge.
 *
 * Nothing is returned unless there is both a shape to cut and a palette colour
 * to cut it in — a site that has set neither gets no decoration rather than a
 * coloured rectangle.
 *
 * The property names are deliberately not per block. One namespace means one
 * stylesheet mixin, and a third band that wants this is an include rather than
 * a fourth copy of these four declarations.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @return array{0:string,1:string} A class suffix (possibly empty) and a style
 *                                  attribute value (possibly empty).
 */
function bridge_band_mask( array $attributes ): array
{
	$url = ! empty( $attributes['mask'] ) ? bridge_mask_shape_url() : '';

	if ( '' === $url ) {
		return array( '', '' );
	}

	/**
	 * The shape's colour, as a shade of the band rather than a colour of its
	 * own.
	 *
	 * This used to be a palette slug, which made the shape a sixth brand colour
	 * competing with the five already in the band — and left an operator
	 * choosing between six wrong answers when what they wanted was "a bit
	 * darker than this". A signed percentage says that directly: negative
	 * darkens, positive lightens, zero is no shape at all.
	 *
	 * Drawn as black or white at that opacity rather than as a computed blend,
	 * because the band underneath is a CSS class — a section skin, a card
	 * ground, a photograph — and the server does not know what colour it
	 * resolved to. An overlay does not need to know: it shades whatever it
	 * lands on, which is what makes one setting work on all of them.
	 *
	 * Eight-digit hex and not `rgba()`, for the reason bridge_hex_alpha()
	 * records: this lands in a style attribute, and `safecss_filter_attr()`
	 * throws away any declaration with a parenthesis left standing.
	 */
	$shade = (int) ( $attributes['maskShade'] ?? -20 );
	$shade = max( -100, min( 100, $shade ) );

	if ( 0 === $shade ) {
		return array( '', '' );
	}

	$color = bridge_hex_alpha( $shade < 0 ? '#000000' : '#ffffff', abs( $shade ) / 100 );

	/**
	 * Which edge the shape is held against, and how far past it it runs.
	 *
	 * `edge` is the alignment percentage `mask-position` already speaks, so
	 * nothing has to translate it: 100% is the right edge, 0% the left.
	 *
	 * The bleed is signed, and the sign is not a setting — it is the edge.
	 * Pushing a shape "past" the right edge means to the right, and past the
	 * left edge means to the left, so an editor picks a side and a distance
	 * and the direction follows. It is spent in the stylesheet as a share of
	 * the shape's own width rather than of the band's, so it keeps its
	 * proportion as the shape resizes down a phone.
	 *
	 * The size goes out unitless. The stylesheet spends it as a length it can
	 * clamp — `<n>vw`, which on a band that is always the full width of the
	 * window is the same measurement the old `<n>%` was — and a percentage is
	 * the one thing that cannot be held to a floor. See the note in
	 * abstracts/_band-mask.scss.
	 */
	$left  = 'left' === (string) ( $attributes['maskEdge'] ?? 'right' );
	$bleed = max( 0, min( 90, (int) ( $attributes['maskBleed'] ?? 0 ) ) ) / 100;

	return array(
		' has-mask',
		sprintf(
			'--bridge-band-mask-image:url(%s);'
				. '--bridge-band-mask-color:%s;'
				. '--bridge-band-mask-size:%d;'
				. '--bridge-band-mask-edge:%d%%;'
				. '--bridge-band-mask-bleed:%s;',
			esc_url( $url ),
			$color,
			max( 10, min( 200, (int) ( $attributes['maskSize'] ?? 60 ) ) ),
			$left ? 0 : 100,
			// Two decimals, always: a bare `0` and `-0.40` are both valid
			// numbers to `calc()`, and formatting them the same way keeps the
			// style attribute readable when someone views source.
			number_format( $left ? -$bleed : $bleed, 2, '.', '' )
		),
	);
}

/**
 * The data the mask controls need in the editor.
 *
 * Printed against each block's editor script by functions.php. The palette
 * comes from the token record rather than from the editor's own `colors`
 * setting: those are the site's global colours, they are the only ones a mask
 * may take, and reading them from where they are set is what keeps that true.
 *
 * @return array<string, mixed>
 */
function bridge_band_mask_data(): array
{
	$tokens = bridge_get_tokens();

	return array(
		'maskUrl'    => bridge_mask_shape_url(),
		'optionsUrl' => admin_url( 'admin.php?page=' . BRIDGE_OPTIONS_SLUG ),
	);
}

/**
 * Drop the blocks an author started and never filled in.
 *
 * A seeded heading or summary paragraph, left alone, is still a block and still
 * serialises — so the front end printed an empty <p> or an empty <h2> that
 * spent a row of the section's gap on nothing, which reads as a section
 * starting too low for no visible reason.
 *
 * The heading matters as much as the paragraph: a title deleted down to an
 * empty block still reserved its line height and the gap beneath it.
 *
 * @param string $html Rendered inner-block HTML.
 * @return string The same HTML with empty headings and paragraphs removed.
 */
function bridge_strip_empty_blocks(string $html): string
{
	$empty = '(?:\s|&nbsp;|<br\s*/?>)*';

	$html = (string) preg_replace('#<p[^>]*>' . $empty . '</p>#i', '', $html);
	$html = (string) preg_replace('#<h([1-6])[^>]*>' . $empty . '</h\1>#i', '', $html);

	return $html;
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
	$intro = bridge_strip_empty_blocks($intro);

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
 * How much of a logo's desktop size survives on the narrowest screen.
 *
 * Gentler than the header's padding gives up, because a logo is a picture of
 * a name: past a certain point it stops being smaller and starts being
 * unreadable, and the row can afford the width where a band of padding
 * cannot.
 */
define('BRIDGE_LOGO_MIN_SCALE', 0.8);

/**
 * A logo-slider length, fluid.
 *
 * What an editor sets on the block's sliders is the size on a desktop; this
 * puts it on the theme's own fluid curve, so it falls away as the window
 * narrows in step with the type and spacing around it rather than on a curve
 * of its own. bridge_fluid_clamp() is that curve, and it is the only one —
 * see inc/theme-json.php.
 *
 * @param float $max_rem The desktop size, in rem.
 * @return string A CSS length.
 */
function bridge_logo_fluid(float $max_rem): string
{
	return bridge_fluid_clamp(
		$max_rem * BRIDGE_LOGO_MIN_SCALE,
		$max_rem,
		(float) bridge_get_tokens()['layout']['wideSize']
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
	// The display mode reaches the row as well as the image: `contain` leaves
	// every logo at its own width, so the row is what has to open the gaps up.
	//
	// The data attribute is what logo-slider-view.js finds the row by, to
	// decide whether these logos need to travel at all. It renders as a
	// marquee either way; the script only ever adds `is-static`.
	$classes = 'bridge-logos__row is-' . $display . ($reverse ? ' is-reverse' : '');

	// The same round trip the item bands make, for a row that is not made of
	// blocks: the logos are one block's attribute list, so there are no inner
	// blocks for bridge_prime_item_images() to walk. Every logo is drawn twice
	// — the marquee needs a seam copy — and without this the first pass was
	// two queries per logo.
	$logo_ids = array_filter(array_map(
		static function ($image): int {
			return absint(((array) $image)['id'] ?? 0);
		},
		$images
	));

	if (count($logo_ids) > 1) {
		_prime_post_caches(array_values(array_unique($logo_ids)), false, true);
	}
?>
	<div class="<?php echo esc_attr($classes); ?>" style="--bridge-logo-count: <?php echo (int) count($images); ?>;" data-bridge-logos-row>
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
				/*
				 * Eager, both halves of the track.
				 *
				 * The seam copy is parked past the right edge of a row that
				 * cannot be scrolled, so a lazy loader has no reason to fetch
				 * it until the animation carries it in — at which point the
				 * loop shows blank space where a logo should be and then pops
				 * it in, which is the one thing a marquee must not do.
				 *
				 * Lazily loading the first half instead would save nothing:
				 * both halves name the same file, so the eager copy is what
				 * makes the single request either way.
				 */
				'loading'  => 'eager',
				'decoding' => 'async',
			)
		);
		?>
	</li>
<?php
}

/**
 * Whether the trail is drawn on the page being rendered.
 *
 * Only posts can switch it off, and only through the Posts tab, because that
 * is the template the setting was asked for and the only one whose head the
 * tab describes. A page or an archive keeps its trail: those are reached from
 * inside the site, where the trail is saying where you have got to, whereas a
 * post is usually arrived at cold from a search result and the trail is the
 * only thing on it that says what site this is.
 *
 * @return bool
 */
function bridge_breadcrumb_enabled(): bool
{
	if (! is_singular('post')) {
		return true;
	}

	$tokens = bridge_get_tokens();

	// `?? true` rather than `?? false`: a token record written before this
	// setting existed has no key, and a site that upgrades should keep the
	// trail it has been showing rather than lose it silently.
	return ! empty($tokens['posts']['breadcrumb'] ?? true);
}

/**
 * The steps of the current page's trail, from the front page down.
 *
 * The list, not the markup: `bridge_breadcrumb_html()` draws it and
 * `bridge_breadcrumb_schema()` describes it, and both read this so the trail a
 * visitor sees and the trail a search engine is told about cannot drift apart.
 * A breadcrumb in the markup that disagrees with the one in the JSON-LD is
 * worse than neither, because only one of them is checkable by looking.
 *
 * Only ancestors are walked, so this is a hierarchy trail rather than a
 * history one — the same thing the old block drew, minus the plugin.
 *
 * Fewer than two steps is not a trail: the front page, and any page that turns
 * out to sit directly under it, get an empty array rather than a lone "Home"
 * pointing at where the visitor already is.
 *
 * A post whose site has switched the trail off in Theme Options gets the same
 * empty array. Gated here rather than in the block's render, so the markup and
 * the JSON-LD are switched off by one decision — a site that hid the trail but
 * kept telling Google about it would be making a claim about the page that
 * nobody can check by looking, which is the failure this whole pair is built
 * to avoid.
 *
 * @return array<int, array{label:string,url:string}> The last step is the
 *                                                    current page and carries
 *                                                    no URL.
 */
function bridge_breadcrumb_items(): array
{
	if (is_front_page()) {
		return array();
	}

	if (! bridge_breadcrumb_enabled()) {
		return array();
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

	return count($crumbs) < 2 ? array() : $crumbs;
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
 * @param string $nav_attributes Attributes for the <nav>, already escaped —
 *                               `bridge/breadcrumb` passes the block wrapper's,
 *                               so the trail carries the block's own classes
 *                               and anchor rather than being wrapped in a
 *                               second element to hold them. Empty for the
 *                               plain trail.
 * @return string
 */
function bridge_breadcrumb_html(string $nav_attributes = ''): string
{
	$crumbs = bridge_breadcrumb_items();

	if (! $crumbs) {
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

	if ('' === $nav_attributes) {
		$nav_attributes = 'class="bridge-breadcrumb"';
	}

	return sprintf(
		'<nav %s aria-label="%s"><ol class="bridge-breadcrumb__list">%s</ol></nav>',
		$nav_attributes,
		esc_attr__('Breadcrumb', 'bridge'),
		$items
	);
}

/**
 * The same trail as a BreadcrumbList, or an empty array when there is none.
 *
 * Breadcrumbs are one of the few enrichments Google still grants any site, and
 * what it does with them is replace the URL line of the result with the path —
 * so this is the one node in the theme that changes what a search result looks
 * like rather than only what a machine understands about it.
 *
 * `position` counts from 1, and every step carries an `item` except the last.
 * That omission is deliberate and is what the vocabulary asks for: the final
 * entry is the page the result is already about, and giving it a URL that
 * points at itself is the same self-reference the markup avoids by not linking
 * it.
 *
 * @return array<string, mixed>
 */
function bridge_breadcrumb_schema(): array
{
	$crumbs = bridge_breadcrumb_items();

	if (! $crumbs) {
		return array();
	}

	$elements = array();

	foreach (array_values($crumbs) as $index => $crumb) {
		$element = array(
			'@type'    => 'ListItem',
			'position' => $index + 1,
			'name'     => (string) $crumb['label'],
		);

		if ('' !== $crumb['url']) {
			$element['item'] = (string) $crumb['url'];
		}

		$elements[] = $element;
	}

	return array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $elements,
	);
}

/**
 * Which of the three facts the byline states.
 *
 * One record read by three callers — `bridge_post_meta_html()` on the front
 * end, the block's canvas preview through `window.bridgePostMeta`, and the
 * Posts tab that writes it — so what an operator switched, what the editor
 * draws and what a visitor reads cannot disagree.
 *
 * `?? true` on each: a token record written before the byline was separable
 * has none of these keys, and a site upgrading into this should keep the
 * byline it has been printing rather than lose two thirds of it silently.
 *
 * @return array<string, bool>
 */
function bridge_post_meta_parts(): array
{
	$tokens = bridge_get_tokens();
	$meta   = isset($tokens['posts']['meta']) && is_array($tokens['posts']['meta'])
		? $tokens['posts']['meta']
		: array();

	return array(
		'date'     => ! empty($meta['date'] ?? true),
		'category' => ! empty($meta['category'] ?? true),
		'author'   => ! empty($meta['author'] ?? true),
	);
}

/**
 * The byline for the post being rendered.
 *
 * Only the parts that are switched on, and only the ones that have something
 * to say: a post with no categories contributes no item rather than an empty
 * one, which would otherwise show as a slash with nothing after it.
 *
 * The slashes are pseudo-elements on every item but the first — see
 * `_post.scss` — and not text in the markup, for the reason the breadcrumb
 * separates its steps the same way: a screen reader reading "slash" between
 * every fact is describing the punctuation rather than the byline.
 *
 * @param string $attributes Attributes for the wrapper, already escaped.
 *                           `bridge/post-meta` passes the block wrapper's, so
 *                           the byline carries the block's own classes rather
 *                           than being wrapped in a second element to hold
 *                           them. Empty for the plain byline.
 * @return string
 */
function bridge_post_meta_html(string $attributes = ''): string
{
	$parts = bridge_post_meta_parts();
	$items = array();

	if ($parts['date']) {
		$items[] = sprintf(
			'<time datetime="%s">%s</time>',
			esc_attr((string) get_the_date('c')),
			esc_html((string) get_the_date())
		);
	}

	if ($parts['category']) {
		// Already escaped, and already links — the one item that is somewhere
		// to go rather than a fact. `false` on a post with no categories, and
		// a WP_Error on an unregistered taxonomy, so both are tested for
		// rather than assuming a string.
		$terms = get_the_term_list(get_the_ID(), 'category', '', ', ');

		if (is_string($terms) && '' !== $terms) {
			$items[] = $terms;
		}
	}

	if ($parts['author']) {
		$author = (string) get_the_author();

		if ('' !== $author) {
			$items[] = esc_html($author);
		}
	}

	if (! $items) {
		return '';
	}

	$list = '';

	foreach ($items as $item) {
		$list .= '<span class="bridge-article__meta-item">' . $item . '</span>';
	}

	if ('' === $attributes) {
		$attributes = 'class="bridge-article__meta"';
	}

	return sprintf('<div %s>%s</div>', $attributes, $list);
}
