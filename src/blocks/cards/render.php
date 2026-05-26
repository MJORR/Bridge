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
$read_more_text  = (string) ( $attributes['readMoreText'] ?? 'Read more →' );

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
		$image_url    = $thumbnail_id
			? wp_get_attachment_image_url( $thumbnail_id, 'large' )
			: '';
		$image_alt    = $thumbnail_id
			? (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true )
			: '';

		include __DIR__ . '/card.php';
	endwhile;
	?>
</div>
<?php
wp_reset_postdata();
