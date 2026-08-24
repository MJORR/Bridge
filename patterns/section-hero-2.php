<?php
/**
 * Title: Hero 2 — Text beside image
 * Slug: bridge/section-hero-2
 * Categories: bridge
 * Description: Headline and copy on the left, a supporting image on the right.
 * Keywords: hero, header, intro, image, split
 * Block Types: core/post-content
 *
 * @package Bridge
 */

?>
<!-- wp:group {"tagName":"section","align":"full","templateLock":"contentOnly","className":"bridge-section","layout":{"type":"constrained"}} -->
<section class="wp-block-group bridge-section alignfull">
	<!-- wp:columns {"align":"wide","verticalAlignment":"center","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|60"}}}} -->
	<div class="wp-block-columns alignwide are-vertically-aligned-center">
		<!-- wp:column {"verticalAlignment":"center"} -->
		<div class="wp-block-column is-vertically-aligned-center">
			<!-- wp:heading {"level":1} -->
			<h1 class="wp-block-heading"><?php esc_html_e('A design system your client cannot break', 'bridge'); ?></h1>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p><?php esc_html_e('Editors get pre-approved layouts and bulletproof blocks. Every heading, colour and margin comes from one place, so the site still looks like the launch screenshots two years on.', 'bridge'); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:buttons -->
			<div class="wp-block-buttons">
				<!-- wp:button -->
				<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e('How it works', 'bridge'); ?></a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"verticalAlignment":"center"} -->
		<div class="wp-block-column is-vertically-aligned-center">
			<!-- wp:image {"sizeSlug":"large","style":{"border":{"radius":"6px"}}} -->
			<figure class="wp-block-image size-large has-custom-border"><img alt="" style="border-radius:6px"/></figure>
			<!-- /wp:image -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->
