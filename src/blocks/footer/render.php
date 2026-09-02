<?php
/**
 * Server-side render for `bridge/footer`.
 *
 * Three columns and a line under them:
 *
 *   1  The logo, and whatever social accounts Site Options has filled in.
 *   2  The Legal menu.
 *   3  The Quick Links menu.
 *   —  A copyright line: the company name and the site's current year.
 *
 * Columns 2 and 3 render only when a menu is chosen for them. A heading with
 * nothing under it is worse than a two-column footer, and on a site that has
 * not built its menus yet the footer still has a logo and a copyright line —
 * which is a footer.
 *
 * The layout is CSS grid, not core/columns: the widths differ between the
 * identity column and the two link columns, and the whole thing stacks on a
 * phone. Expressing that as nested column blocks would put a design decision
 * into markup an operator can break by dragging.
 *
 * A <div>, not a <footer>. Every template includes this through
 * `wp:template-part {"slug":"footer","tagName":"footer"}`, so the landmark
 * element is already there — emitting another here would nest a contentinfo
 * inside a contentinfo, which is invalid and announces the region twice. Same
 * arrangement as the header block.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes (none).
 * @var string   $content    Inner block HTML (none).
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bridge_footer   = bridge_get_tokens()['footer'];
$bridge_contrast = bridge_footer_contrast();

$bridge_quick = bridge_footer_menu_id( 'quick' );
$bridge_legal = bridge_footer_menu_id( 'legal' );

$bridge_social = bridge_footer_social();

$bridge_classes = array(
	'bridge-footer',
	'bridge-footer--columns',
	'bridge-footer--contrast-' . sanitize_html_class( $bridge_contrast ),
	// Core's own class, so the footer picks up root padding from the global
	// stylesheet and keeps the same gutters as everything above it.
	'has-global-padding',
);

// Published on the element rather than :root, because the editor canvas
// renders this block through a REST preview that never runs the theme's
// front-end enqueue hooks — a colour named in a stylesheet would resolve to
// nothing there.
$bridge_ground = sprintf(
	'--bridge-footer-bg: var(--wp--preset--color--%s);',
	sanitize_key( (string) $bridge_footer['backgroundColor'] )
);

$bridge_wrapper = get_block_wrapper_attributes(
	array(
		'class' => implode( ' ', $bridge_classes ),
		'style' => $bridge_ground,
	)
);
?>
<div <?php echo $bridge_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core. ?>>
	<div class="bridge-footer__inner">
		<div class="bridge-footer__col bridge-footer__col--identity">
			<?php echo bridge_footer_logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from escaped parts. ?>
			<?php echo $bridge_social; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from escaped parts. ?>
		</div>

		<?php if ( $bridge_legal > 0 ) : ?>
			<div class="bridge-footer__col">
				<?php
				// An h2, not a styled paragraph. These are the headings of a
				// landmark's sections, and a screen-reader user listing the
				// headings on a page should find "Legal" among them.
				?>
				<h2 class="bridge-footer__heading"><?php esc_html_e( 'Legal', 'bridge' ); ?></h2>
				<?php echo bridge_footer_nav( $bridge_legal, __( 'Legal', 'bridge' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — core block output. ?>
			</div>
		<?php endif; ?>

		<?php if ( $bridge_quick > 0 ) : ?>
			<div class="bridge-footer__col">
				<h2 class="bridge-footer__heading"><?php esc_html_e( 'Quick Links', 'bridge' ); ?></h2>
				<?php echo bridge_footer_nav( $bridge_quick, __( 'Quick Links', 'bridge' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — core block output. ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="bridge-footer__legal">
		<p class="bridge-footer__copyright"><?php echo esc_html( bridge_footer_copyright() ); ?></p>
	</div>
</div>
