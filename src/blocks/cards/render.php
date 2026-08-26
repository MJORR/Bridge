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
$excerpt_length  = max( 10, min( 100, (int) ( $attributes['excerptLength'] ?? 20 ) ) );
$show_read_more  = ! empty( $attributes['showReadMore'] );
$width           = 'narrow' === ( $attributes['width'] ?? 'wide' ) ? 'narrow' : 'wide';
// Set only by the editor's preview request, never saved on the block.
$is_preview      = ! empty( $attributes['isPreview'] );
$overflow        = 'carousel' === ( $attributes['overflowStyle'] ?? 'wrap' ) ? 'carousel' : 'wrap';
// The editor previews a carousel as rows, so it gets neither the track
// attributes nor the controls.
$is_carousel     = 'carousel' === $overflow && ! $is_preview;
// Empty means "use the theme's wording", which is the only wording that can be
// translated: a default written into block.json is a literal string that never
// reaches a .po file, so every site in every language got the English one.
$read_more_text  = trim( (string) ( $attributes['readMoreText'] ?? '' ) );
$read_more_text  = '' !== $read_more_text ? $read_more_text : __( 'Read more', 'bridge' );

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
// adds the band classes, the colour support classes an editor picked, and the
// aria-labelledby that names the landmark. In preview mode there is no band —
// the editor has already drawn one — so neither is called.
$grid_style = sprintf( '--columns:%d;', $columns );

list( $intro_html, $label_id ) = $is_preview
	? array( '', '' )
	: bridge_section_intro( $content, 'bridge-cards__intro' );

$section_open = $is_preview
	? ''
	: bridge_section_wrapper(
		$attributes,
		'bridge-cards bridge-cards--' . $width . ' bridge-cards--' . $overflow,
		'',
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

$query = new WP_Query( $query_args );

// A band with a headline and no posts yet is still a band worth printing: the
// heading is the editor's, and dropping the whole section because a query came
// back empty would take their words off the page with it.
if ( ! $query->have_posts() ) {
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
	echo $section_open, $inner_open, $intro_html;
	?>
	<div class="bridge-cards-grid" style="<?php echo esc_attr( $grid_style ); ?>">
		<p class="bridge-cards__empty">
			<?php esc_html_e( 'No posts found.', 'bridge' ); ?>
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
$card_image_sizes = sprintf(
	'(max-width: 480px) 100vw, (max-width: 768px) 50vw, %dvw',
	max( 1, (int) round( 100 / $columns ) )
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

		$thumbnail_id = get_post_thumbnail_id();
		// Empty for the first row, so core keeps its say over those.
		$card_loading = $card_index >= $eager_cards ? 'lazy' : '';
		$card_index++;
		$permalink    = get_permalink();
		$title        = get_the_title();
		$excerpt      = wp_trim_words(
			wp_strip_all_tags( get_the_excerpt() ),
			$excerpt_length,
			'…'
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

echo $inner_close, $section_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
wp_reset_postdata();
