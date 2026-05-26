<?php
/**
 * Single card markup for `bridge/cards`.
 *
 * Included once per post inside render.php's loop. Each iteration sets up:
 *
 * @var string $permalink      Post permalink.
 * @var string $title          Post title (raw).
 * @var string $excerpt        Trimmed plain-text excerpt.
 * @var string $image_url      Featured image URL, or '' if none.
 * @var string $image_alt      Featured image alt text.
 * @var bool   $show_read_more Whether to render the read-more label.
 * @var string $read_more_text Read-more label text.
 *
 * @package Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<a href="<?php echo esc_url( $permalink ); ?>" class="post-card">
	<figure class="post-card__image">
		<?php if ( $image_url ) : ?>
			<img
				src="<?php echo esc_url( $image_url ); ?>"
				alt="<?php echo esc_attr( $image_alt ); ?>"
				loading="lazy"
				decoding="async"
			/>
		<?php else : ?>
			<div class="post-card__image-placeholder" aria-hidden="true"></div>
		<?php endif; ?>
	</figure>
	<div class="post-card__body">
		<h3 class="post-card__title"><?php echo esc_html( $title ); ?></h3>
		<p class="post-card__excerpt"><?php echo esc_html( $excerpt ); ?></p>
		<?php if ( $show_read_more ) : ?>
			<span class="post-card__read-more"><?php echo esc_html( $read_more_text ); ?></span>
		<?php endif; ?>
	</div>
</a>
