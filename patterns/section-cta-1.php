<?php
/**
 * Title: Call to action 1 — Inverted band
 * Slug: bridge/section-cta-1
 * Categories: bridge
 * Description: A full-width inverted band with a primary action and a quieter second one. Use once per page.
 * Keywords: cta, call to action, contact, banner, inverted
 * Block Types: core/post-content
 *
 * @package Bridge
 */

?>
<!-- wp:group {"tagName":"section","align":"full","templateLock":"contentOnly","className":"bridge-section is-style-bridge-inverted","layout":{"type":"constrained"}} -->
<section class="wp-block-group bridge-section alignfull is-style-bridge-inverted">
	<!-- wp:group {"layout":{"type":"constrained","contentSize":"620px"},"style":{"spacing":{"blockGap":"var:preset|spacing|40"}}} -->
	<div class="wp-block-group">
		<!-- wp:heading {"textAlign":"center","level":2} -->
		<h2 class="wp-block-heading has-text-align-center"><?php esc_html_e('Ready to start?', 'bridge'); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"align":"center"} -->
		<p class="has-text-align-center"><?php esc_html_e('Tell us what you are trying to achieve and we will tell you honestly whether we are the right people for it.', 'bridge'); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
		<div class="wp-block-buttons">
			<!-- wp:button -->
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e('Get in touch', 'bridge'); ?></a></div>
			<!-- /wp:button -->

			<!-- wp:button {"className":"is-style-outline"} -->
			<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e('See our work', 'bridge'); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
