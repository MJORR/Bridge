<?php
/**
 * Server-side render for `bridge/faqs`.
 *
 * The questions come from a content type, not from blocks nested inside this
 * one. An FAQ is written once and asked on four pages — a pricing page, a
 * contact page, the FAQ page itself — and blocks-inside-blocks meant four
 * copies of the same answer, three of which go stale the first time it
 * changes. Declaring an "FAQ" type in Theme Options and pointing this block at
 * it makes the answer one record with one editor.
 *
 * The accordion itself is still a <details>: no view script, no inline script,
 * and the "one open at a time" behaviour is the `name` attribute's. See the
 * stylesheet for the open/close animation.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner-block HTML — the intro, if any.
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$width = 'wide' === ( $attributes['width'] ?? 'narrow' ) ? 'wide' : 'narrow';

// The FAQ type, and never anything else. Not an attribute: the block is called
// FAQs and the theme declares the type it reads, so there was never a second
// right answer for an editor to pick — see the note in functions.php.
//
// Empty when the type has been switched off or removed in Theme Options, and
// this renders nothing rather than falling back to another content type.
// Guessing would put somebody else's posts under this heading.
$source = bridge_faq_post_type();

if ( '' === $source || ! post_type_exists( $source ) ) {
	return;
}

// A ceiling, not a page size: an accordion is a list a reader scans, and two
// hundred of them is a page nobody finishes. 0 in the attribute means "all",
// which still means at most this many.
$max   = 100;
$count = (int) ( $attributes['count'] ?? 0 );
$count = $count > 0 ? min( $count, $max ) : $max;

$orders = array(
	// The Order field on each post. The default, because the order of a set of
	// questions is a decision somebody makes — the most asked one goes first,
	// and neither the alphabet nor the publication date knows that.
	'menu_order' => array( 'menu_order title', 'ASC' ),
	'title'      => array( 'title', 'ASC' ),
	'date'       => array( 'date', 'DESC' ),
);

$order = $attributes['order'] ?? 'menu_order';
$order = isset( $orders[ $order ] ) ? $orders[ $order ] : $orders['menu_order'];

/*
 * One category, or all of them.
 *
 * Stored as the term's slug rather than its id, because a block's attributes
 * travel: the same page exported from staging and imported into production
 * carries this string, and term ids are not the same number in two databases
 * while slugs are the same word.
 *
 * A slug that no longer names a term renders nothing rather than falling back
 * to every question — a category that was renamed or deleted is not a reason
 * to put the whole set under a heading that says otherwise. Consistent with
 * how a missing source type is read a few lines up, and left to WP_Query,
 * which returns no posts for a term that is not there.
 *
 * The taxonomy going away is the other case and reads the other way. That
 * happens when categories are switched off for the type in Theme Options,
 * which is not a statement about this block: there are no categories on the
 * site any more, so "one of them" has nothing left to mean and the block shows
 * the whole set. The attribute is kept rather than cleared, so switching
 * categories back on restores every block's filter with it.
 */
$tax_query = array();
$category  = sanitize_title( (string) ( $attributes['category'] ?? '' ) );
$taxonomy  = bridge_post_type_taxonomy( $source );

if ( '' !== $category && taxonomy_exists( $taxonomy ) ) {
	$tax_query[] = array(
		'taxonomy' => $taxonomy,
		'field'    => 'slug',
		'terms'    => $category,
	);
}

$questions = new WP_Query(
	array(
		'post_type'              => $source,
		'post_status'            => 'publish',
		'posts_per_page'         => $count,
		'orderby'                => $order[0],
		'order'                  => $order[1],
		'ignore_sticky_posts'    => true,
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query — one term on one taxonomy, which is the join this block exists to make.
		'tax_query'              => $tax_query,
		// Three queries this block has no use for. `no_found_rows` drops the
		// row count that only exists to build pagination; the two cache
		// primers fetch terms and meta for posts that render neither.
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
	)
);

if ( ! $questions->have_posts() ) {
	return;
}

list( $intro_html, $label_id ) = bridge_section_intro( $content, 'bridge-faqs__intro' );

/*
 * "Only one open at a time", done by the browser.
 *
 * Grouped <details> elements share a `name`, and opening one closes the rest —
 * the whole of what an accordion script used to be for. The name has to be
 * unique to this band, or two FAQ sections on one page would close each
 * other's rows, so it is generated per render rather than authored.
 */
$group = ! empty( $attributes['exclusive'] ) ? wp_unique_id( 'bridge-faqs-' ) : '';

echo bridge_section_wrapper( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
	$attributes,
	sprintf( 'bridge-faqs bridge-faqs--%s', $width ),
	'',
	$label_id
);
?>
	<div class="bridge-faqs__inner">
		<?php echo $intro_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<div class="bridge-faqs__list">
			<?php
			while ( $questions->have_posts() ) :
				$questions->the_post();
				?>
				<details class="bridge-faq"
					<?php echo '' !== $group ? ' name="' . esc_attr( $group ) . '"' : ''; ?>>
					<summary class="bridge-faq__question">
						<span class="bridge-faq__text"><?php the_title(); ?></span>
						<?php
						// No label: the question beside it already says what
						// this opens, and naming the chevron as well would
						// have a screen reader announce the row twice.
						echo bridge_render_icon( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — build output from theme source.
							'chevron-down',
							array(
								'size'  => 'small',
								'class' => 'bridge-faq__marker',
							)
						);
						?>
					</summary>

					<div class="bridge-faq__answer">
						<?php
						// Through the_content, so the answer's own blocks,
						// shortcodes and embeds render as they do anywhere
						// else. It is a post, and this is what a post's body
						// is.
						the_content();
						?>
					</div>
				</details>
				<?php
			endwhile;

			// The loop set the global post to the last answer. Left there, the
			// next block on the page — and the footer — would be describing an
			// FAQ instead of the page they are on.
			wp_reset_postdata();
			?>
		</div>
	</div>
</section>
