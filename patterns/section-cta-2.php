<?php
/**
 * Title: Call to action 2 — Inline with details
 * Slug: bridge/section-cta-2
 * Categories: bridge
 * Description: A quieter, left-aligned prompt with contact details beside the button.
 * Keywords: cta, call to action, contact, inline
 * Block Types: core/post-content
 *
 * @package Bridge
 */

?>
<!-- wp:group {"tagName":"section","align":"full","templateLock":"contentOnly","className":"bridge-section","layout":{"type":"constrained"}} -->
<section class="wp-block-group bridge-section alignfull">
	<!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"},"style":{"spacing":{"blockGap":"var:preset|spacing|40"}}} -->
	<div class="wp-block-group alignwide">
		<!-- wp:group {"layout":{"type":"default"},"style":{"spacing":{"blockGap":"var:preset|spacing|20"}}} -->
		<div class="wp-block-group">
			<!-- wp:heading {"level":2,"fontSize":"large"} -->
			<h2 class="wp-block-heading has-large-font-size"><?php esc_html_e('Prefer to talk it through?', 'bridge'); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"fontSize":"small"} -->
			<p class="has-small-font-size"><?php esc_html_e('hello@example.com · +44 20 7000 0000', 'bridge'); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:buttons -->
		<div class="wp-block-buttons">
			<!-- wp:button -->
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e('Book a call', 'bridge'); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
