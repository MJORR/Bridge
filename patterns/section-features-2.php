<?php
/**
 * Title: Features 2 — Two columns on surface
 * Slug: bridge/section-features-2
 * Categories: bridge
 * Description: Two larger points on a tinted band. Use when there are only two things to say.
 * Keywords: features, services, columns, two, surface
 * Block Types: core/post-content
 *
 * @package Bridge
 */

?>
<!-- wp:group {"tagName":"section","align":"full","templateLock":"contentOnly","className":"bridge-section is-style-bridge-surface","layout":{"type":"constrained"}} -->
<section class="wp-block-group bridge-section alignfull is-style-bridge-surface">
	<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|60"}}}} -->
	<div class="wp-block-columns alignwide">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":2} -->
			<h2 class="wp-block-heading"><?php esc_html_e('No design drift', 'bridge'); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p><?php esc_html_e('Fonts, colours, spacing and layouts are locked to the design system. An editor cannot pick a rogue hex code, because the picker only ever contains the approved palette.', 'bridge'); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":2} -->
			<h2 class="wp-block-heading"><?php esc_html_e('Lean, fast code', 'bridge'); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p><?php esc_html_e('Semantic HTML with none of the wrapper bloat a page builder leaves behind. Self-hosted fonts, no third-party requests, and markup you can read.', 'bridge'); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->
