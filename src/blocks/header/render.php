<?php
/**
 * Server-side render for `bridge/header`.
 *
 * The whole header: top bar, logo, menu and button, arranged by the layout
 * chosen in Theme Options. The template part that holds this block is a single
 * line, and the editor previews this same file — so there is exactly one
 * description of what the header is, and no way for the canvas and the front
 * end to disagree.
 *
 * The one thing a template may override is the background: a landing page
 * overlays its header on a hero, and that is a decision the template makes
 * about itself rather than a site-wide setting. Which templates those are is
 * listed in bridge_overlay_header_templates(), so every template can include
 * the same single header part.
 *
 * @package Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bridge_header = bridge_get_tokens()['header'];
$bridge_layout = (string) $bridge_header['layout'];

// Three answers, most specific first: what this instance was told to be, what
// the template being rendered asks for, and — when neither has an opinion —
// what Theme Options says the site's header is.
$bridge_background = (string) ( $attributes['background'] ?? '' );

if ( ! in_array( $bridge_background, array( 'solid', 'transparent' ), true ) ) {
	$bridge_background = bridge_template_header_background();
}

if ( ! in_array( $bridge_background, array( 'solid', 'transparent' ), true ) ) {
	$bridge_background = (string) $bridge_header['background'];
}

$bridge_contrast = bridge_header_contrast( $bridge_background );

// Where the menu sits on the row it shares. The stacked layouts put it under
// the logo, so it follows the logo's alignment rather than opposing it.
$bridge_justify = array(
	'left'    => 'right',
	'centre'  => 'center',
	'two-row' => 'left',
);
$bridge_justify = $bridge_justify[ $bridge_layout ] ?? 'right';

$bridge_classes = array(
	'bridge-header',
	'bridge-header--' . sanitize_html_class( $bridge_layout ),
	'bridge-header--' . sanitize_html_class( $bridge_background ),
	'bridge-header--contrast-' . sanitize_html_class( $bridge_contrast ),
	// Core's own class, so the header picks up root padding from the global
	// stylesheet and stays in step with the rest of the site's gutters.
	'has-global-padding',
);

if ( ! empty( $bridge_header['sticky'] ) ) {
	$bridge_classes[] = 'bridge-header--sticky';
	// Scrolled, this header sits on the site's own background rather than on
	// whatever it started over, which is a different logo. CSS cannot ask
	// whether a colour is light, so the answer is worked out here and travels
	// as a class the `is-scrolled` rules read.
	$bridge_classes[] = 'bridge-header--scrolled-contrast-' . bridge_header_scrolled_contrast();
}

if ( ! empty( $bridge_header['border'] ) ) {
	$bridge_classes[] = 'bridge-header--border';
}

// Published on the element rather than :root so the values travel with the
// markup — the editor canvas renders this block without ever running the
// theme's front-end enqueue hooks.
$bridge_solid = sprintf( 'var(--wp--preset--color--%s)', sanitize_key( (string) $bridge_header['backgroundColor'] ) );

// The dropdown panel opens flush against the header and reads as part of it,
// so it is given the header's own ground rather than a surface of its own.
// A transparent header has no ground to lend — it is borrowing a photograph —
// so the panel falls back to whichever half of the palette the header's text
// was already chosen against, which is the one combination guaranteed to stay
// readable when the image underneath is unknowable.
$bridge_menu_bg = 'transparent' === $bridge_background
	? ( 'light' === $bridge_contrast ? 'var(--wp--preset--color--text)' : 'var(--wp--preset--color--background)' )
	: $bridge_solid;

// The mobile panel is the brand's second colour, and the type on it is
// whichever half of the palette that colour can carry. Worked out here, the
// same way the header decides its own contrast, because CSS cannot ask whether
// a colour is light — and a menu whose labels have gone invisible is worse than
// one that ignored the brand.
$bridge_panel_hex = (string) ( bridge_get_tokens()['brand']['palette']['secondary']['color'] ?? '#2563eb' );
$bridge_panel_fg  = bridge_is_light_color( $bridge_panel_hex ) ? 'text' : 'background';

$bridge_style = sprintf(
	'--bridge-header-logo-height:%dpx;--bridge-header-padding:%s;--bridge-header-bg:%s;--bridge-header-menu-bg:%s;--bridge-nav-panel-bg:%s;--bridge-nav-panel-fg:%s;',
	(int) $bridge_header['logo']['height'],
	bridge_header_padding_block( (int) $bridge_header['paddingBlock'] ),
	'transparent' === $bridge_background ? 'transparent' : $bridge_solid,
	$bridge_menu_bg,
	'var(--wp--preset--color--secondary)',
	sprintf( 'var(--wp--preset--color--%s)', $bridge_panel_fg )
);

$bridge_logo = bridge_header_logo( $bridge_header, $bridge_background );
$bridge_nav  = bridge_header_nav( bridge_header_menu_id( 'primary' ), $bridge_justify );

// The top bar renders only once it has a menu: an empty strip is worse than
// no strip. The call to action is not part of it — there is one button, and
// its home is beside the menu.
$bridge_top = 0;
if ( ! empty( $bridge_header['topBar'] ) ) {
	$bridge_top = bridge_header_menu_id( 'utility' );
}

$bridge_cta   = $bridge_header['cta'];
$bridge_label = trim( (string) $bridge_cta['label'] );
$bridge_url   = (string) $bridge_cta['url'];
$bridge_button = ! empty( $bridge_cta['enabled'] ) && '' !== $bridge_label && '' !== $bridge_url;

$bridge_wrapper = get_block_wrapper_attributes(
	array(
		'class' => implode( ' ', $bridge_classes ),
		'style' => $bridge_style,
	)
);

// The two arrangements differ only in whether the logo and the menu share a
// row or stack; everything inside them is identical.
$bridge_body_class = 'left' === $bridge_layout ? 'bridge-header__row' : 'bridge-header__stack';

?>
<div <?php echo $bridge_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built by core. ?>>
	<?php if ( $bridge_top > 0 ) : ?>
		<div class="bridge-header__top bridge-header__inner">
			<div class="bridge-header__top-inner">
				<?php
				echo bridge_header_nav( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — rendered by core.
					$bridge_top,
					'left',
					array(
						'className'   => 'bridge-nav bridge-nav--utility',
						'overlayMenu' => 'never',
						'fontSize'    => 'small',
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>

	<div class="bridge-header__inner <?php echo esc_attr( $bridge_body_class ); ?>">
		<?php echo $bridge_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — escaped at build. ?>

		<div class="bridge-header__end">
			<?php echo $bridge_nav; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — rendered by core. ?>

			<?php if ( $bridge_button ) : ?>
				<div class="bridge-header__cta">
					<a class="wp-element-button" href="<?php echo esc_url( $bridge_url ); ?>">
						<?php echo esc_html( $bridge_label ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
