<?php
/**
 * Title: Features 1 — Three columns with icons
 * Slug: bridge/section-features-1
 * Categories: bridge
 * Description: Three icon-led points in a row. The workhorse services section.
 * Keywords: features, services, columns, icons, three
 * Block Types: core/post-content
 *
 * @package Bridge
 */

?>
<!-- wp:group {"tagName":"section","align":"full","templateLock":"contentOnly","className":"bridge-section","layout":{"type":"constrained"}} -->
<section class="wp-block-group bridge-section alignfull">
	<!-- wp:heading {"textAlign":"center","level":2} -->
	<h2 class="wp-block-heading has-text-align-center"><?php esc_html_e('What we do', 'bridge'); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|60"},"margin":{"top":"var:preset|spacing|60"}}}} -->
	<div class="wp-block-columns alignwide" style="margin-top:var(--wp--preset--spacing--60)">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:bridge/icon {"name":"search","size":"large","style":{"color":{"text":"var:preset|color|secondary"}}} /-->

			<!-- wp:heading {"level":3} -->
			<h3 class="wp-block-heading"><?php esc_html_e('Strategy', 'bridge'); ?></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p><?php esc_html_e('We work out what the site has to achieve before anyone opens a design tool.', 'bridge'); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:bridge/icon {"name":"star","size":"large","style":{"color":{"text":"var:preset|color|secondary"}}} /-->

			<!-- wp:heading {"level":3} -->
			<h3 class="wp-block-heading"><?php esc_html_e('Brand and design', 'bridge'); ?></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p><?php esc_html_e('An identity built as a system, so it holds together everywhere it is used.', 'bridge'); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:bridge/icon {"name":"check","size":"large","style":{"color":{"text":"var:preset|color|secondary"}}} /-->

			<!-- wp:heading {"level":3} -->
			<h3 class="wp-block-heading"><?php esc_html_e('Build and support', 'bridge'); ?></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p><?php esc_html_e('Fast, lean WordPress your team can actually publish to without breaking it.', 'bridge'); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->
