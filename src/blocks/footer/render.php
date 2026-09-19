<?php
/**
 * Server-side render for `bridge/footer`.
 *
 * Four columns and a line under them:
 *
 *   1  The logo, the site's short description, and the brand strapline.
 *   2  The Explore menu.
 *   3  The Services menu.
 *   4  Get in touch — the address, the phone number and the social accounts.
 *   —  A bottom row: the copyright on the left, the Legal menu on the right.
 *
 * The legal links are in that bottom row rather than in a column of their own,
 * which is where they were. A privacy policy is not a destination anybody goes
 * looking for in a list of five; it is small print, and small print belongs on
 * the small-print line beside the copyright it keeps company with.
 *
 * Two wrappers, not one. The footer's ground — and the shade the copyright row
 * is allowed to wear — has to reach the window, while the words in it line up
 * with the rest of the page. So the coloured band is the outer element and the
 * gutter and the wide container live on the rows inside it: `__inner` and
 * `__legal-inner` are drawn by the same container mixin every wide section
 * band uses, which is what puts the logo on the same line as the content of
 * the section above it. Putting the padding on the outer element instead —
 * which is what this did — aligned the footer to the window rather than to
 * the page, so the logo sat a couple of hundred pixels to the left of
 * everything else.
 *
 * Every part of it is conditional, and independently so. Columns 2 and 3
 * render only when a menu is chosen for them; column 4 renders only when Site
 * Options holds something to put in it; the strapline and the description are
 * each drawn only when they exist. A heading with nothing under it is worse
 * than a three-column footer, and on a site that has filled none of this in
 * the footer is still a logo and a copyright line — which is a footer.
 *
 * The background image, when there is one, is painted on the outer element
 * rather than on either row — it runs the full height of the footer and
 * continues behind the copyright strip, which is the one part of the band that
 * has a colour of its own to sit over it.
 *
 * The layout is CSS grid, not core/columns: the widths differ between the
 * identity column, the two link columns and the contact column, and the whole
 * thing stacks on a phone. Expressing that as nested column blocks would put a
 * design decision into markup an operator can break by dragging.
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

$bridge_tokens   = bridge_get_tokens();
$bridge_footer   = $bridge_tokens['footer'];
$bridge_header   = $bridge_tokens['header'];
$bridge_contrast = bridge_footer_contrast();

$bridge_explore  = bridge_footer_menu_id( 'explore' );
$bridge_services = bridge_footer_menu_id( 'services' );
$bridge_legal    = bridge_footer_menu_id( 'legal' );

$bridge_description = bridge_footer_description();
$bridge_tagline     = bridge_footer_tagline();
$bridge_social      = bridge_footer_social();
$bridge_contact     = bridge_footer_contact();

// One question asked once. The heading and the column both depend on there
// being something to put under it, and asking twice is how a heading ends up
// alone above nothing.
$bridge_has_contact = '' !== $bridge_contact || '' !== $bridge_social;

// The band along the bottom, if Site Options holds one. A class as well as the
// url, because the stylesheet has to know whether to paint a scrim at all —
// the gradient that keeps the words readable over the image has no business
// being drawn on a footer that has no image under it.
$bridge_image = bridge_footer_image();

$bridge_classes = array(
	'bridge-footer',
	'bridge-footer--columns',
	'bridge-footer--contrast-' . sanitize_html_class( $bridge_contrast ),
);

if ( '' !== $bridge_image ) {
	$bridge_classes[] = 'bridge-footer--has-image';
}

// The same class a skinned section wears, and the same rule paints it —
// components/_sections.scss grades whatever declares `--bridge-band-ground`,
// so a footer asking for the site's section gradient joins that rule rather
// than growing a second one that has to be kept in step with it.
if ( ! empty( $bridge_footer['gradient'] ) ) {
	$bridge_classes[] = 'has-band-gradient';
}

$bridge_slug = sanitize_key( (string) $bridge_footer['backgroundColor'] );

// The labels above the columns are drawn white — the plain white the links
// under them are, rather than a palette slug, so the label and its list read
// as one column instead of two colours.
//
// Only on a footer that is carrying light text. On a light ground the same
// declaration would be a heading painted its own background, so there it
// falls back to `currentcolor`, which is the one colour guaranteed to read on
// whatever the operator chose.
$bridge_label = 'light' === $bridge_contrast ? '#fff' : 'currentcolor';

// The logo's height, taken from the header's own setting rather than from a
// second control or a fixed value here. There is one wordmark and one answer
// to how big it should be drawn; a footer that sized it independently would be
// a site whose logo is two sizes, and the operator would have to keep them in
// step by eye. Theme Options → Templates has the slider, under the header,
// where the logos themselves are chosen.
//
// Published in px because that is the unit the control is calibrated in — see
// the `logoHeight` constraint in inc/tokens.php.
$bridge_logo_height = max(1, (int) $bridge_header['logo']['height']);

// How far the copyright strip sits off the footer's own colour, as an overlay
// rather than a second palette colour: a negative setting lifts it towards
// white and a positive one presses it towards black, so one control covers
// both directions on any of the six grounds — and it lands on top of the
// gradient as readily as on a flat band.
$bridge_shade = (int) $bridge_footer['legalShade'];

// Published on the element rather than :root, because the editor canvas
// renders this block through a REST preview that never runs the theme's
// front-end enqueue hooks — a colour named in a stylesheet would resolve to
// nothing there.
$bridge_ground = sprintf(
	'--bridge-footer-bg: var(--wp--preset--color--%1$s);'
		. '--bridge-band-ground: var(--wp--preset--color--%1$s);'
		. '--bridge-footer-label: %2$s;'
		. '--bridge-footer-legal-ink: %3$s;'
		. '--bridge-footer-legal-shade: %4$d%%;'
		. '--bridge-footer-logo-height: %5$dpx;'
		. '%6$s',
	$bridge_slug,
	$bridge_label,
	$bridge_shade < 0 ? '#fff' : '#000',
	abs( $bridge_shade ),
	$bridge_logo_height,
	// Declared only when there is one. An empty `url()` is not "no image": it
	// is an invalid value that some browsers resolve against the current
	// document, which sends a second request for the page itself.
	'' !== $bridge_image ? '--bridge-footer-image: ' . $bridge_image . ';' : ''
);

$bridge_wrapper = get_block_wrapper_attributes(
	array(
		'class' => implode( ' ', $bridge_classes ),
		'style' => $bridge_ground,
	)
);
?>
<div <?php echo $bridge_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core. ?>>
	<div class="bridge-footer__body">
		<div class="bridge-footer__inner">
			<div class="bridge-footer__col bridge-footer__col--identity">
				<?php echo bridge_footer_logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from escaped parts. ?>
				<?php echo $bridge_description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from escaped parts. ?>
				<?php echo $bridge_tagline; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from escaped parts. ?>
			</div>

			<?php if ( $bridge_explore > 0 ) : ?>
				<div class="bridge-footer__col bridge-footer__col--menu">
					<?php
					// An h2, not a styled paragraph. These are the headings of a
					// landmark's sections, and a screen-reader user listing the
					// headings on a page should find "Explore" among them.
					?>
					<h2 class="bridge-footer__heading"><?php esc_html_e( 'Explore', 'bridge' ); ?></h2>
					<?php echo bridge_footer_nav( $bridge_explore, __( 'Explore', 'bridge' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — core block output. ?>
				</div>
			<?php endif; ?>

			<?php if ( $bridge_services > 0 ) : ?>
				<div class="bridge-footer__col bridge-footer__col--menu">
					<h2 class="bridge-footer__heading"><?php esc_html_e( 'Services', 'bridge' ); ?></h2>
					<?php echo bridge_footer_nav( $bridge_services, __( 'Services', 'bridge' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — core block output. ?>
				</div>
			<?php endif; ?>

			<?php if ( $bridge_has_contact ) : ?>
				<div class="bridge-footer__col bridge-footer__col--contact">
					<h2 class="bridge-footer__heading"><?php esc_html_e( 'Get in touch', 'bridge' ); ?></h2>
					<?php echo $bridge_contact; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from escaped parts. ?>
					<?php echo $bridge_social; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from escaped parts. ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="bridge-footer__legal">
		<div class="bridge-footer__legal-inner">
			<p class="bridge-footer__copyright"><?php echo esc_html( bridge_footer_copyright() ); ?></p>
			<?php echo bridge_footer_nav( $bridge_legal, __( 'Legal', 'bridge' ), 'horizontal' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — core block output. ?>
		</div>
	</div>
</div>
