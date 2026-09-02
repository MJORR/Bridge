<?php
/**
 * Bridge — the search results page.
 *
 * The one PHP template in a block theme, and it is here on purpose.
 *
 * A results page is not a page an operator arranges. Almost all of it is a
 * consequence of the request: how many posts came back, whether there were
 * none, whether the visitor typed anything at all, which page of the results
 * they are on. Block markup can express none of those, so a `search.html`
 * either states one of the outcomes and gets the others wrong, or leaves the
 * whole page to a single query loop and says nothing about the search. This
 * file can answer all four, and it is the only template in the theme that
 * needs to.
 *
 * WordPress prefers a PHP template over a block template of the same
 * specificity — see `locate_block_template()` — so this file supersedes
 * `templates/search.html`, which is why that file is no longer in the theme.
 * Nothing else changes: the header and the footer are the same template parts
 * every other template asks for, and the results grid is the same
 * `bridge/cards` block the archive uses, filled from the main query.
 *
 * Two details are borrowed from `wp-includes/template-canvas.php`, which is
 * what a block template would have been rendered through, so that this page is
 * the same kind of document as every other page on the site:
 *
 *   - The body is built before <head> is printed. Blocks enqueue their styles
 *     and scripts while they render, and anything enqueued after `wp_head()`
 *     has run is printed in the footer instead — a flash of unstyled cards.
 *   - The whole thing is wrapped in `.wp-site-blocks`, which is what the
 *     global stylesheet's root padding, block gap and alignment rules are
 *     written against, and what the theme's own sticky-header rules read.
 *
 * @package Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---- What the request actually asked ------------------------------------
// Unescaped, because every use below escapes for the context it lands in —
// `get_search_query()` escapes for an attribute, which is the wrong answer for
// the two places the term is printed as text.
//
// Trimmed, because a search for a single space satisfies WordPress's "is this
// a search?" test and reaches this template with nothing in it. That is a
// third outcome, distinct from "no results": there is nothing to have found.
$bridge_term = trim( get_search_query( false ) );

$bridge_query = $GLOBALS['wp_query'];
$bridge_found = (int) $bridge_query->found_posts;
$bridge_pages = max( 1, (int) $bridge_query->max_num_pages );
$bridge_page  = max( 1, (int) get_query_var( 'paged' ) );

// The term as it appears in a sentence: quoted, and marked up so the
// stylesheet can lift it out of the line. Built once, because both halves of
// the summary — the one that counts matches and the one that reports none —
// end on it.
$bridge_quoted = sprintf(
	'<span class="bridge-search__term">“%s”</span>',
	esc_html( $bridge_term )
);

// Core's skip link. `wp_enqueue_block_template_skip_link()` bows out on any
// request that did not resolve a block template, so the style it would have
// enqueued has to be asked for here; the link itself is inserted into the
// finished markup at the foot of this file, by the same core helper a block
// template would have gone through.
wp_enqueue_style( 'wp-block-template-skip-link' );

// ---- The page -----------------------------------------------------------
ob_start();
?>

<?php
// The same part every other template asks for, through the same block, so the
// footer-variant filter in inc/structure.php still gets its say and the header
// still arrives wrapped in the `.wp-block-template-part` element the sticky and
// transparent rules are written against.
//
// The header renders its search panel already open on this request, holding the
// term — see `$bridge_search_open` in src/blocks/header/render.php. That is the
// search field for this page, so nothing below draws a second one except where
// there is nothing else to offer.
echo do_blocks( '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — rendered by core.
?>

<main class="bridge-search">

	<section class="bridge-search__head">
		<?php
		// Decorative, and stated as such: the glyph repeats the icon in the
		// header's own search button at a size that reads as texture rather
		// than as a control. It is announced to nobody and hidden outright on
		// narrow screens, where there is no room for it to be quiet.
		echo bridge_render_icon( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from the compiled icon library.
			'search',
			array( 'class' => 'bridge-search__glyph' )
		);
		?>

		<div class="bridge-search__head-inner">
			<p class="bridge-search__eyebrow">
				<?php
				echo bridge_render_icon( 'search', array( 'size' => 'small' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from the compiled icon library.
				?>
				<span><?php esc_html_e( 'Search', 'bridge' ); ?></span>
			</p>

			<h1 class="bridge-search__title">
				<?php esc_html_e( 'Search Results', 'bridge' ); ?>
			</h1>

			<p class="bridge-search__summary">
				<?php
				if ( '' === $bridge_term ) {
					// Reachable only from a query string of nothing but
					// whitespace, but reachable — and "0 results for “”" is a
					// worse answer than saying what happened.
					esc_html_e( 'You didn’t type anything to search for. Add a word or two above and we’ll go looking.', 'bridge' );
				} elseif ( $bridge_found > 0 ) {
					printf(
						/* translators: 1: number of matches, formatted. 2: the search term, already quoted. */
						esc_html( _n( '%1$s match for %2$s', '%1$s matches for %2$s', $bridge_found, 'bridge' ) ),
						'<strong>' . esc_html( number_format_i18n( $bridge_found ) ) . '</strong>',
						$bridge_quoted // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — escaped where it was built.
					);
				} else {
					printf(
						/* translators: %s: the search term, already quoted. */
						esc_html__( 'Nothing on the site matches %s — yet.', 'bridge' ),
						$bridge_quoted // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — escaped where it was built.
					);
				}
				?>
			</p>

			<?php
			// The way out of the results is always worth offering; where in
			// them the visitor is only means something when there is more than
			// one page of them.
			$bridge_paged = $bridge_found > 0 && $bridge_pages > 1;
			?>
			<p class="bridge-search__meta">
				<?php if ( $bridge_paged ) : ?>
					<span class="bridge-search__page">
						<?php
						printf(
							/* translators: 1: current page number. 2: total number of pages. */
							esc_html__( 'Page %1$s of %2$s', 'bridge' ),
							esc_html( number_format_i18n( $bridge_page ) ),
							esc_html( number_format_i18n( $bridge_pages ) )
						);
						?>
					</span>
				<?php endif; ?>

				<a class="bridge-search__home" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php
					echo bridge_render_icon( 'arrow-left', array( 'size' => 'small' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from the compiled icon library.
					?>
					<span><?php esc_html_e( 'Back to the site', 'bridge' ); ?></span>
				</a>
			</p>
		</div>
	</section>

	<?php if ( have_posts() ) : ?>

		<?php
		/*
		 * The results, drawn by the same block as every other listing on the
		 * site. `source: main` hands it the query WordPress already ran for
		 * this request, so the grid, the card style, the excerpt length and
		 * the pagination are all the archive's — one card, filled from the
		 * search end. See src/blocks/cards/render.php.
		 */
		echo do_blocks( '<!-- wp:bridge/cards {"source":"main","width":"wide"} /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — rendered by core.
		?>

	<?php else : ?>

		<section class="bridge-search__empty">
			<div class="bridge-search__empty-inner">
				<p class="bridge-search__mark">
					<?php
					echo bridge_render_icon( 'search', array( 'size' => 'large' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from the compiled icon library.
					?>
				</p>

				<h2 class="bridge-search__empty-title">
					<?php esc_html_e( 'Try a different search', 'bridge' ); ?>
				</h2>

				<p class="bridge-search__empty-copy">
					<?php esc_html_e( 'That doesn’t mean it isn’t here. Search looks at titles and body text, so a broader word often finds what an exact phrase misses.', 'bridge' ); ?>
				</p>

				<?php
				// The one place this template draws a field of its own. The
				// header's panel is open at the top of the page, but a visitor
				// who has read all the way down to "nothing found" has the
				// answer in front of them and the field behind them — and this
				// is also the only field on the page when the header's search
				// icon has been switched off in Theme Options.
				?>
				<form role="search" method="get" class="bridge-search__form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<label class="screen-reader-text" for="bridge-search-again">
						<?php esc_html_e( 'Search this site', 'bridge' ); ?>
					</label>
					<div class="bridge-search__control">
						<?php
						echo bridge_render_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from the compiled icon library.
						?>
						<input
							class="bridge-search__field"
							id="bridge-search-again"
							type="search"
							name="s"
							value="<?php echo esc_attr( $bridge_term ); ?>"
							placeholder="<?php esc_attr_e( 'Try another word…', 'bridge' ); ?>"
						/>
					</div>
					<button class="wp-element-button bridge-search__submit" type="submit">
						<?php esc_html_e( 'Search again', 'bridge' ); ?>
					</button>
				</form>

				<ul class="bridge-search__tips">
					<li><?php esc_html_e( 'Check the spelling, and try it without punctuation.', 'bridge' ); ?></li>
					<li><?php esc_html_e( 'Use one or two words rather than a whole question.', 'bridge' ); ?></li>
					<li><?php esc_html_e( 'Try a broader term — the category rather than the exact product.', 'bridge' ); ?></li>
				</ul>
			</div>
		</section>

		<?php
		/*
		 * A way onward rather than a dead end. Guarded on there being
		 * something to show: the cards block prints "No posts found." when its
		 * query comes back empty, which is a sensible thing for a band an
		 * editor placed to say and a poor way to end a page that has already
		 * apologised once.
		 *
		 * `source` is left at its default, so this is the block's own query —
		 * the main one is exhausted and empty, and is what the visitor is
		 * being offered an alternative to.
		 */
		$bridge_published = wp_count_posts( 'post' );

		if ( ! empty( $bridge_published->publish ) ) :
			echo do_blocks( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — rendered by core.
				'<!-- wp:bridge/cards {"numberOfPosts":3,"columns":3,"className":"bridge-search__more"} -->' .
				'<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">' .
				esc_html__( 'Latest from the site', 'bridge' ) .
				'</h2><!-- /wp:heading -->' .
				'<!-- /wp:bridge/cards -->'
			);
		endif;
		?>

	<?php endif; ?>

</main>

<?php
echo do_blocks( '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — rendered by core.

$bridge_html = '<div class="wp-site-blocks">' . ob_get_clean() . '</div>';

// The skip link, inserted the way a block template's is: core finds the first
// `.wp-site-blocks`, gives the <main> inside it an id if it has none, and puts
// the link in front of it. Guarded because it is a private helper — without it
// the page still renders, one anchor short.
if ( function_exists( '_block_template_add_skip_link' ) ) {
	$bridge_html = _block_template_add_skip_link( $bridge_html );
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<?php
	// A block template gets this from `locate_block_template()`, which never
	// runs on a request a PHP template won. Printed here rather than hooked,
	// because by now `wp_head` is the next thing to happen.
	?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php echo $bridge_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — assembled above from escaped parts and core-rendered blocks. ?>

<?php wp_footer(); ?>
</body>
</html>
