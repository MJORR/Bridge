<?php
/**
 * Server-side render for `bridge/testimonial`.
 *
 * A <figure>/<blockquote>/<figcaption> rather than the old block's stack of
 * divs: the quote and its attribution are the one structure HTML already has
 * an element for, and it is what a screen reader needs to tell the two apart.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block HTML (unused).
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title   = trim( (string) ( $attributes['title'] ?? '' ) );
$text    = trim( (string) ( $attributes['text'] ?? '' ) );
$rating  = max( 0, min( 5, (int) ( $attributes['rating'] ?? 0 ) ) );
$name    = trim( (string) ( $attributes['name'] ?? '' ) );
$company = trim( (string) ( $attributes['company'] ?? '' ) );

if ( '' === $text && '' === $title ) {
	return;
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'bridge-testimonial' ) );

/**
 * The same testimonial, said again in the vocabulary a search engine reads.
 *
 * The card already holds every part of a schema.org Review as a separate
 * attribute — quote, rating, who said it, where they work — so the markup is
 * assembled rather than guessed at, and cannot drift from what is on screen.
 *
 * `itemReviewed` is the site itself. A Review with nothing under review is
 * incomplete and is discarded, which would make the whole block a silent
 * no-op; the thing being reviewed here is the business whose page this is.
 *
 * It points at the Organization node in the head by `@id` rather than
 * describing a second one here — see inc/schema.php. One entity named twice
 * was two entities as far as a consumer was concerned, and the copy in this
 * file was the one with no logo, no address and no social profiles.
 *
 * Worth knowing what this will and will not do: Google has ignored an
 * organisation's reviews of itself for rich results since 2019, so these stars
 * will not appear in a search result however the markup is written. It is
 * published for the consumers that do read it, and because the block already
 * holds every field it needs.
 */
$schema = array(
	'@context'     => 'https://schema.org',
	'@type'        => 'Review',
	'itemReviewed' => array(
		'@type' => 'Organization',
		'@id'   => bridge_schema_id( 'organization' ),
		'name'  => get_bloginfo( 'name' ),
	),
);

if ( '' !== $title ) {
	$schema['name'] = $title;
}

if ( '' !== $text ) {
	$schema['reviewBody'] = $text;
}

if ( $rating > 0 ) {
	$schema['reviewRating'] = array(
		'@type'       => 'Rating',
		'ratingValue' => $rating,
		'bestRating'  => 5,
		'worstRating' => 1,
	);
}

// An anonymous quote is not attributed to anyone, and inventing an author to
// satisfy the vocabulary would be describing something the page does not say.
if ( '' !== $name ) {
	$author = array(
		'@type' => 'Person',
		'name'  => $name,
	);

	if ( '' !== $company ) {
		$author['worksFor'] = array(
			'@type' => 'Organization',
			'name'  => $company,
		);
	}

	$schema['author'] = $author;
}
?>
<figure <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core. ?>>
	<?php if ( $rating > 0 ) : ?>
		<?php
		// One text alternative on the group, not five on the stars. A screen
		// reader announcing "star star star star star" is telling the user
		// how the rating is drawn rather than what it is.
		?>
		<p class="bridge-testimonial__rating" role="img"
			aria-label="<?php echo esc_attr( sprintf( /* translators: %d: rating out of five. */ __( 'Rated %d out of 5', 'bridge' ), $rating ) ); ?>">
			<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
				<span class="bridge-testimonial__star<?php echo $i <= $rating ? ' is-filled' : ''; ?>" aria-hidden="true">&#9733;</span>
			<?php endfor; ?>
		</p>
	<?php endif; ?>

	<blockquote class="bridge-testimonial__quote">
		<?php if ( '' !== $title ) : ?>
			<p class="bridge-testimonial__title"><?php echo esc_html( $title ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $text ) : ?>
			<p class="bridge-testimonial__text"><?php echo esc_html( $text ); ?></p>
		<?php endif; ?>
	</blockquote>

	<?php if ( '' !== $name || '' !== $company ) : ?>
		<figcaption class="bridge-testimonial__attribution">
			<?php if ( '' !== $name ) : ?>
				<span class="bridge-testimonial__name"><?php echo esc_html( $name ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $company ) : ?>
				<span class="bridge-testimonial__company"><?php echo esc_html( $company ); ?></span>
			<?php endif; ?>
		</figcaption>
	<?php endif; ?>

	<?php
	// Through the shared encoder, which escapes `<` — a quote containing
	// `</script>` used to close this element and put whatever followed it into
	// the page as markup. See bridge_schema_json().
	echo bridge_schema_json( $schema ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — encoded for this context by bridge_schema_json().
	?>
</figure>
