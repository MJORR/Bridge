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
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block HTML (unused — no inner blocks).
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

// ---- Wrapper attributes (shared by populated + empty states) -----------
// The `bridge-cards-grid` class is what CSS targets. The auto-added
// `wp-block-bridge-cards` lives on BOTH the editor's outer block wrapper
// and our inner output here, so styling that class causes a double-grid.
// Keeping the auto class for WP plumbing, but scoping CSS to our own class.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'bridge-cards-grid',
		'style' => sprintf( '--columns:%d;', $columns ),
	)
);

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

if ( ! $query->have_posts() ) {
	?>
	<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<p class="bridge-cards__empty">
			<?php esc_html_e( 'No posts found.', 'bridge' ); ?>
		</p>
	</div>
	<?php
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

// Responsive `sizes`. The grid collapses on its own now — it asks how much room
// the container has rather than how wide the window is — so this is the browser's
// hint rather than a description of fixed breakpoints: full width on a phone,
// half on a tablet, and the authored column share above that.
$card_image_sizes = sprintf(
	'(max-width: 480px) 100vw, (max-width: 768px) 50vw, %dvw',
	max( 1, (int) round( 100 / $columns ) )
);
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php
	while ( $query->have_posts() ) :
		$query->the_post();

		$thumbnail_id = get_post_thumbnail_id();
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
wp_reset_postdata();
