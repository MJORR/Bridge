<?php
/**
 * Server-side render for `bridge/cards`.
 *
 * Used by both the frontend and the editor preview via <ServerSideRender>.
 *
 * IMPORTANT: WordPress wraps this file in its own ob_start()/ob_get_clean()
 * when it's referenced via `render: file:...` in block.json. Therefore we
 * just echo / output HTML directly — no ob_start, no return value (WP
 * discards the included file's return).
 *
 * @package Bridge
 *
 * The block is a band of the page, like every other section block: an optional
 * heading and summary line, then the thing the section is for — here a queried
 * grid rather than authored items. The intro is inner blocks (core/heading and
 * core/paragraph) so it is edited the way every other heading on the site is;
 * the grid is still a query, so it is still server-rendered.
 *
 * That combination is why `isPreview` exists. The editor draws the band and
 * the intro itself, live, and asks <ServerSideRender> for the grid alone —
 * without it the preview would show the section twice, once around the real
 * intro and once around the rendered one.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner-block HTML — the section's intro.
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---- Sanitize attributes -----------------------------------------------
$post_type       = isset( $attributes['postType'] ) ? sanitize_key( $attributes['postType'] ) : 'post';
$number_of_posts = max( 1, min( 24, (int) ( $attributes['numberOfPosts'] ?? 6 ) ) );
$columns         = max( 1, min( 6,  (int) ( $attributes['columns'] ?? 3 ) ) );
$order_by        = sanitize_key( $attributes['orderBy'] ?? 'date' );
$order           = strtoupper( sanitize_key( $attributes['order'] ?? 'desc' ) );
$categories      = array_filter( array_map( 'absint', (array) ( $attributes['categories'] ?? array() ) ) );
/**
 * How many words of the post a card carries.
 *
 * 0 means "whatever the site says", which is the default and what an untouched
 * block saves — so a client who decides their cards are too wordy changes one
 * number in Theme Options and every band on the site follows, rather than
 * opening forty pages. A block that was given a length of its own keeps it:
 * the setting is a default, not an override.
 *
 * The token is read through `bridge_get_tokens()`, which memoises and reads an
 * autoloaded option, so this costs no query however many Cards blocks a page
 * has.
 */
$excerpt_length  = (int) ( $attributes['excerptLength'] ?? 0 );

if ( $excerpt_length <= 0 ) {
	$tokens         = function_exists( 'bridge_get_tokens' ) ? bridge_get_tokens() : array();
	$excerpt_length = (int) ( $tokens['cards']['excerpt'] ?? 20 );
}

$excerpt_length  = max( 10, min( 100, $excerpt_length ) );
$show_read_more  = ! empty( $attributes['showReadMore'] );
$width           = 'narrow' === ( $attributes['width'] ?? 'wide' ) ? 'narrow' : 'wide';
// Set only by the editor's preview request, never saved on the block.
$is_preview      = ! empty( $attributes['isPreview'] );
$overflow        = 'carousel' === ( $attributes['overflowStyle'] ?? 'wrap' ) ? 'carousel' : 'wrap';

/**
 * Which of the three card styles the grid draws.
 *
 * A named attribute rather than a `register_block_style()` entry, even though
 * this theme reaches for those elsewhere. The Styles list on this block is
 * already spent on the section skins — Surface, Inverted, Accent — and those
 * answer "what colour is the band", which is a different question from "what
 * shape is a card". Three more entries in the same list would read as six
 * variants of one thing.
 *
 * Checked against the allowlist here as well as in block.json's enum, because
 * an attribute arrives from the saved post content and from the REST preview
 * request, and neither is validated by the editor that wrote it.
 */
$card_styles     = array( 'summary', 'tile', 'cover', 'team' );
$card_style      = sanitize_key( $attributes['cardStyle'] ?? 'summary' );
$card_style      = in_array( $card_style, $card_styles, true ) ? $card_style : 'summary';

/**
 * Which query fills the grid.
 *
 * `self` is the block as it has always worked: its own WP_Query, built from
 * the attributes above, dropped into a page wherever an editor wants a row of
 * posts. `main` hands the grid the query WordPress has already run for this
 * request — the archive, the category, the search results — which is what the
 * archive templates need and what an authored band must never do.
 *
 * The alternative was a second card: core/query + core/post-template in the
 * templates, styled to match. That is two implementations of one card, and the
 * second one drifts. This is one card, filled from either end.
 *
 * Never in the editor. A ServerSideRender request runs inside the REST API,
 * where the main query is the REST controller's, not the archive's — so a
 * preview asking for it would draw whatever that happened to be. The editor
 * gets the block's own query instead, which shows a real row of real posts and
 * is the honest answer to "what does this look like".
 */
$use_main = 'main' === ( $attributes['source'] ?? 'self' ) && ! $is_preview;
// The editor previews a carousel as rows, so it gets neither the track
// attributes nor the controls. Neither does an archive: a paginated listing
// that scrolls sideways hides the posts the pagination is counting.
$is_carousel     = 'carousel' === $overflow && ! $is_preview && ! $use_main;
// Empty means "use the theme's wording", which is the only wording that can be
// translated: a default written into block.json is a literal string that never
// reaches a .po file, so every site in every language got the English one.
$read_more_text  = trim( (string) ( $attributes['readMoreText'] ?? '' ) );
$read_more_text  = '' !== $read_more_text ? $read_more_text : __( 'Read more', 'bridge' );

// Team's button, on the same terms and for the same reason.
$button_text     = trim( (string) ( $attributes['buttonText'] ?? '' ) );
$button_text     = '' !== $button_text ? $button_text : __( 'View profile', 'bridge' );

$orderby_map = array(
	'date'          => 'date',
	'title'         => 'title',
	'modified_date' => 'modified',
	'rand'          => 'rand',
);
$orderby_key = $orderby_map[ $order_by ] ?? 'date';
$order       = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';

// ---- The band ----------------------------------------------------------
// The grid keeps its own class rather than being styled through the
// auto-added `wp-block-bridge-cards`, which lives on both the editor's outer
// block wrapper and our output here — styling that class made a double grid.
//
// `bridge_section_wrapper()` is what every other section block opens with: it
// adds the band classes and the aria-labelledby that names the landmark. In
// preview mode there is no band — the editor has already drawn one — so
// neither is called.
//
// No colour classes among them. The block declares no `color` support, so
// there is nothing for `get_block_wrapper_attributes()` to add: a band's
// colour is a *skin* — Surface, Inverted, Accent, registered as block styles
// in inc/section-blocks.php — and not a colour picked per band.
//
// That is the whole reason the support came off. A skin resolves to palette
// slugs, so it follows a rebrand and it points `--bridge-card-bg` at the card
// colour that belongs on that ground; a hex an editor chose does neither, and
// a card sitting on one is a card the contrast audit never saw. On WordPress 7
// the single `color` support was also producing four panels in the Styles tab
// — Text moved into Typography, Background into a panel of its own, and Link
// into Elements — which read as four ways to overrule the design system.
$grid_style = sprintf( '--columns:%d;', $columns );

list( $intro_html, $label_id ) = $is_preview
	? array( '', '' )
	: bridge_section_intro( $content, 'bridge-cards__intro' );

/*
 * ---- The mask shape ------------------------------------------------
 *
 * The site's mask shape, painted as one flat colour behind the cards. Not an
 * image and not a pattern: a solid silhouette from the palette, which is why
 * the colour is a slug rather than a value — it moves when the palette moves.
 *
 * Three numbers do the placing, and each does one thing: which palette colour,
 * how wide the shape is as a share of the band, and how far its right edge is
 * held off the band's right edge. Everything else — the anchoring, the
 * stacking, the clipping — is in the stylesheet, where it is the same in the
 * editor.
 *
 * Nothing renders without both a colour and a shape to cut: the shape comes
 * from Theme Options and is shared with every other block that uses it, so a
 * site that has not set one gets no decoration rather than a coloured
 * rectangle.
 */
list( $mask_class, $mask_style ) = bridge_band_mask( $attributes );

$section_open = $is_preview
	? ''
	: bridge_section_wrapper(
		$attributes,
		'bridge-cards bridge-cards--' . $width . ' bridge-cards--' . $overflow . $mask_class,
		$mask_style,
		$label_id
	);

// The content column inside the band. Absent in preview mode for the same
// reason the band is: the editor has drawn one, and a second would spend the
// section's side padding twice.
// `data-bridge-carousel` is what carousel.js scans for. It goes on the inner
// column because that is the one element wrapping both the track and the
// controls — the pairing the script needs — and because the band itself comes
// from bridge_section_wrapper(), which takes classes rather than attributes.
$inner_open = '<div class="bridge-cards__inner"' . ( $is_carousel ? ' data-bridge-carousel' : '' ) . '>';
$inner_open = $is_preview ? '' : $inner_open;
$inner_close = $is_preview ? '' : '</div>';
$section_close = $is_preview ? '' : '</section>';

// ---- Query -------------------------------------------------------------
$query_args = array(
	'post_type'           => $post_type,
	'post_status'         => 'publish',
	'posts_per_page'      => $number_of_posts,
	'orderby'             => $orderby_key,
	'order'               => $order,
	'ignore_sticky_posts' => true,
	'no_found_rows'       => true,
	// Cards render no taxonomy terms — skip the term-cache priming query.
	'update_post_term_cache' => false,
);

if ( 'post' === $post_type && ! empty( $categories ) ) {
	$query_args['category__in'] = $categories;
}

// The main query is taken as it stands — its post type, its ordering, its
// posts-per-page are the site's Reading settings and the archive being viewed,
// none of which is this block's to overrule. `$query_args` is left built but
// unused in that case, which is the price of one branch instead of two.
$query = $use_main ? $GLOBALS['wp_query'] : new WP_Query( $query_args );

// A band with a headline and no posts yet is still a band worth printing: the
// heading is the editor's, and dropping the whole section because a query came
// back empty would take their words off the page with it.
if ( ! $query->have_posts() ) {
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
	echo $section_open, $inner_open, $intro_html;
	?>
	<div class="bridge-cards-grid" style="<?php echo esc_attr( $grid_style ); ?>">
		<p class="bridge-cards__empty">
			<?php
			// A search that found nothing is a different sentence from a band
			// whose category is empty, and the visitor reading it is in a
			// different situation — one of them mistyped something and can fix
			// it, which is worth saying.
			if ( $use_main && is_search() ) {
				esc_html_e( 'No results. Try a different search term.', 'bridge' );
			} else {
				esc_html_e( 'No posts found.', 'bridge' );
			}
			?>
		</p>
	</div>
	<?php
	echo $inner_close, $section_close;
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	wp_reset_postdata();
	return;
}

// Prime the featured-image attachments (post objects + image metadata + alt)
// in a single batch so the per-card wp_get_attachment_image() calls below hit
// the cache instead of querying once per card (avoids an N+1).
$thumbnail_ids = array_values( array_filter( array_map( 'get_post_thumbnail_id', $query->posts ) ) );
if ( $thumbnail_ids ) {
	_prime_post_caches( $thumbnail_ids, false, true );
}

// What a post with no featured image shows instead: the site's light logo on
// the brand's primary colour. Resolved once for the whole grid rather than per
// card — it is the same image every time, and `bridge_light_logo_id()` reads
// the token record.
//
// The light variant only. The tile behind it is Primary, which is a dark
// colour on every brand this theme has shipped, and the dark logo drawn for a
// light ground would disappear into it. A site that has not uploaded a light
// logo keeps the neutral pattern the block drew before this, which is legible
// on anything.
$placeholder_logo_id = function_exists( 'bridge_light_logo_id' ) ? bridge_light_logo_id() : 0;

// Responsive `sizes`. The grid collapses on its own now — it asks how much room
// the container has rather than how wide the window is — so this is the browser's
// hint rather than a description of fixed breakpoints: full width on a phone,
// half on a tablet, and the authored column share above that.
//
// Team is the exception, and the only place the style reaches this far into
// the PHP: its avatar is a circle a fraction of the width of the column, not a
// photograph spanning the whole of it, so the same hint would have every
// browser fetch a file far wider than the slot it lands in.
//
// The fraction is read from the same record that sets the CSS width —
// `bridge_card_avatar_sizes()` publishes `width` for the stylesheet and
// `fraction` for this line — so an operator moving the avatar to Large moves
// the requested file size with it, and the two cannot drift apart.
$card_width_share = 1.0;

if ( 'team' === $card_style && function_exists( 'bridge_card_avatar_sizes' ) ) {
	$card_tokens   = function_exists( 'bridge_get_tokens' ) ? bridge_get_tokens() : array();
	$avatar_slug   = (string) ( $card_tokens['cards']['styles']['team']['avatar'] ?? 'm' );
	$avatar_sizes  = bridge_card_avatar_sizes();
	$card_width_share = (float) ( $avatar_sizes[ $avatar_slug ]['fraction'] ?? 0.72 );
}
$card_image_sizes = sprintf(
	'(max-width: 480px) %1$dvw, (max-width: 768px) %2$dvw, %3$dvw',
	max( 1, (int) round( 100 * $card_width_share ) ),
	max( 1, (int) round( 50 * $card_width_share ) ),
	max( 1, (int) round( 100 / $columns * $card_width_share ) )
);
/**
 * How many cards can be on screen before the rest are certainly not.
 *
 * The cards past this one are lazy-loaded, and the ones before it are left to
 * core, which knows things this file does not — whether this is the page's
 * first image, and so whether it deserves `fetchpriority="high"`.
 *
 * Core alone was not enough here. `wp_omit_loading_attr_threshold()` omits the
 * `loading` attribute for the first three content images and counts them in
 * source order, which assumes the first few are the ones above the fold. In a
 * carousel that assumption breaks: every card after the first screenful is off
 * to the *side*, and horizontal overflow is not something core can see. Three
 * full-size photographs nobody has scrolled to were being fetched on the
 * critical path, and adding cards never made it better — the first few are
 * eager however many there are.
 *
 * `--columns` is a ceiling rather than a promise: the grid drops below it on a
 * narrow screen, so a card counted as visible here may in fact be a row down.
 * That is the safe direction to be wrong in — it loads an image early that
 * could have waited, rather than deferring one that is on screen.
 */
$eager_cards = $columns;
$card_index  = 0;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
echo $section_open, $inner_open, $intro_html;
?>
<div class="bridge-cards-grid" style="<?php echo esc_attr( $grid_style ); ?>"
	<?php
	if ( $is_carousel ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — escaped inside.
		echo ' ' . bridge_carousel_track_attrs( __( 'Cards, scrollable', 'bridge' ) );
	}
	?>>
	<?php
	while ( $query->have_posts() ) :
		$query->the_post();

		// Empty for the first row, so core keeps its say over those.
		$card_loading = $card_index >= $eager_cards ? 'lazy' : '';
		$card_index++;

		// One record per post, built the same way whatever the post type and
		// whatever the style — the styles differ in which of these fields they
		// draw, never in how the fields are found. See inc/card-data.php.
		$card = bridge_card_data(
			array(
				'excerpt_length'      => $excerpt_length,
				'cta_text'            => $button_text,
				'image_sizes'         => $card_image_sizes,
				'loading'             => $card_loading,
				'placeholder_logo_id' => $placeholder_logo_id,
			)
		);

		include __DIR__ . '/card.php';
	endwhile;
	?>
</div>

<?php
if ( $is_carousel ) {
	// One strip for every carousel in the theme, so the markup the single
	// view script drives is written once. See bridge_carousel_controls().
	echo bridge_carousel_controls( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from escaped parts.
		array(
			'prev' => __( 'Previous cards', 'bridge' ),
			'next' => __( 'Next cards', 'bridge' ),
			'dots' => __( 'Card pages', 'bridge' ),
			/* translators: %d: page number. */
			'dot'  => __( 'Page %d', 'bridge' ),
		)
	);
}

/**
 * Pagination, for a grid filled from the main query.
 *
 * Only there: a band an editor dropped onto a page shows the number of posts
 * they asked for, and paging it would take the visitor away from the page it
 * sits on. An archive is the page.
 *
 * `paginate_links()` rather than the block editor's pagination blocks, because
 * the grid is one block rather than a query wrapping a template — and because
 * this way the markup, and so the styling, is the same on every listing the
 * theme has.
 */
if ( $use_main && $query->max_num_pages > 1 ) {
	$links = paginate_links(
		array(
			'total'     => (int) $query->max_num_pages,
			'current'   => max( 1, get_query_var( 'paged' ) ),
			'mid_size'  => 1,
			'type'      => 'array',
			'prev_text' => __( 'Previous', 'bridge' ),
			'next_text' => __( 'Next', 'bridge' ),
		)
	);

	if ( $links ) {
		printf(
			'<nav class="bridge-cards__pagination" aria-label="%s"><ul>',
			esc_attr__( 'Posts', 'bridge' )
		);

		foreach ( $links as $link ) {
			// Pre-escaped by core: paginate_links() builds anchors and spans
			// from esc_url()'d hrefs and esc_html()'d labels.
			echo '<li>', $link, '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '</ul></nav>';
	}
}

echo $inner_close, $section_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

// Looping the main query consumes it. Nothing in the archive templates reads
// it again today, but a second block that did would find it exhausted and
// render nothing — a bug that would look like the block being broken rather
// than like this loop having eaten the posts.
if ( $use_main ) {
	$query->rewind_posts();
}

wp_reset_postdata();
