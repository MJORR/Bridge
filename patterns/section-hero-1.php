<?php
/**
 * Title: Hero 1 — Centred statement
 * Slug: bridge/section-hero-1
 * Categories: bridge
 * Description: A centred headline, supporting line and two buttons. The plainest opening.
 * Keywords: hero, header, intro, banner
 * Block Types: core/post-content
 *
 * templateLock contentOnly is the load-bearing attribute in every pattern
 * here: inner blocks lose their toolbars and styling panels, so an editor
 * replaces the words and images and cannot restructure the section or reach
 * for a colour. Removing it turns this back into a blank canvas.
 *
 * @package Bridge
 */

?>
<!-- wp:group {"tagName":"section","align":"full","templateLock":"contentOnly","className":"bridge-section is-style-bridge-surface","layout":{"type":"constrained"}} -->
<section class="wp-block-group bridge-section alignfull is-style-bridge-surface">
	<!-- wp:group {"layout":{"type":"constrained","contentSize":"640px"},"style":{"spacing":{"blockGap":"var:preset|spacing|40"}}} -->
	<div class="wp-block-group">
		<!-- wp:heading {"textAlign":"center","level":1} -->
		<h1 class="wp-block-heading has-text-align-center"><?php esc_html_e('Marketing that earns its keep', 'bridge'); ?></h1>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"align":"center","fontSize":"large"} -->
		<p class="has-text-align-center has-large-font-size"><?php esc_html_e('We build brands that stay sharp years after launch — because the system holds, not because someone keeps tidying up after it.', 'bridge'); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
		<div class="wp-block-buttons">
			<!-- wp:button -->
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e('Start a project', 'bridge'); ?></a></div>
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
