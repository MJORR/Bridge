<?php
/**
 * Server-side render for `bridge/header`.
 *
 * The whole header: top bar, logo, menu, search and button, arranged by the layout
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

// The mobile panel is the brand's first colour and its call to action the
// accent, so the one row in the sheet that is an action is the one row not
// painted in the sheet's own colour — and it is painted in the colour this
// theme uses nowhere except to say "here". The type on each is whichever half
// of the palette that ground can carry, worked out here — the same way the
// header decides its own contrast — because CSS cannot ask whether a colour is
// light, and a menu whose labels have gone invisible is worse than one that
// ignored the brand. The accent is the more likely of the two to be a light
// colour, which is the whole reason that question is asked rather than assumed.
$bridge_palette   = bridge_get_tokens()['brand']['palette'];
$bridge_panel_hex = (string) ( $bridge_palette['primary']['color'] ?? '#0f172a' );
$bridge_panel_fg  = bridge_is_light_color( $bridge_panel_hex ) ? 'text' : 'background';

// The call to action's colours: a complete scheme, not just the fill chosen in
// Theme Options. The header is a ground the Buttons tab cannot list, so
// bridge_header_button_scheme() measures the chosen fill against whatever the
// header is actually standing on and derives the rest — label, border, hover
// pair and focus ring — through the same arithmetic as every other button.
$bridge_cta_scheme = bridge_header_button_scheme( $bridge_background, $bridge_contrast );

/**
 * The three colours the menu bar paints, and the labels they can carry.
 *
 * Named palette colours rather than the tints of `currentcolor` these used to
 * mix. That mix followed the header's own contrast for free — it darkened off
 * a light bar and lightened off a dark one from one declaration — and a chosen
 * colour does not, so a light rollover under a transparent header's white
 * labels would be a block with nothing readable on it. The foreground is
 * therefore worked out here for each, the same way the mobile panel's is:
 * whichever half of the palette that ground can carry, asked rather than
 * assumed, because CSS cannot ask whether a colour is light.
 *
 * The marker needs no foreground — it is a 5px bar, not a surface.
 *
 * Published as `--bridge-nav-drop-*` and `--bridge-nav-accent` rather than
 * under the names the stylesheet uses for the same things. `--bridge-nav-tint`,
 * `--bridge-nav-child-bg` and `--bridge-nav-marker` are all declared on
 * `.wp-block-navigation`, which is inside this element — so a value handed down
 * from here under one of those names is shadowed by the stylesheet's own
 * declaration before anything reads it, and the setting silently does nothing.
 * These names are read *by* those declarations instead, which is the only way
 * round a custom property that is redeclared closer to its consumer.
 */
$bridge_nav_colors = $bridge_header['nav'];

$bridge_nav_ground = static function ( string $slug ) use ( $bridge_palette ): array {
	$hex = (string) ( $bridge_palette[ $slug ]['color'] ?? '#f5f5f4' );

	return array(
		sprintf( 'var(--wp--preset--color--%s)', sanitize_key( $slug ) ),
		sprintf( 'var(--wp--preset--color--%s)', bridge_is_light_color( $hex ) ? 'text' : 'background' ),
	);
};

list( $bridge_rollover_bg, $bridge_rollover_fg ) = $bridge_nav_ground( (string) $bridge_nav_colors['rollover'] );

list( $bridge_child_bg, $bridge_child_fg )       = $bridge_nav_ground( (string) $bridge_nav_colors['child'] );

/**
 * A row inside the dropdown, under the pointer.
 *
 * A step off the panel's own colour rather than a tint of `currentcolor` over
 * it. The tint was right while the panel was itself a mix — one idea at two
 * depths — and became the wrong thing the moment the panel started taking a
 * palette colour by name: 20% of the panel's text on nothing is a translucent
 * grey, which is a colour from no part of the design system, sitting on a
 * surface that is.
 *
 * Shaded rather than mixed, so it follows the panel in whichever direction
 * that panel can be followed — a light Surface darkens under the pointer, a
 * Primary one lightens — and so it is a real hex the same way every other
 * hover in the theme is.
 */
$bridge_child_hover = bridge_shade_hex(
	(string) ( $bridge_palette[ (string) $bridge_nav_colors['child'] ]['color'] ?? '#f5f5f4' ),
	0.08
);

$bridge_nav_marker = sprintf(
	'var(--wp--preset--color--%s)',
	sanitize_key( (string) $bridge_nav_colors['accent'] )
);

/**
 * The call to action in the mobile panel, which is a different button.
 *
 * The panel is painted in the brand's first colour, and the one row in that
 * sheet that is an action is the one row not painted in the sheet's own
 * colour — so it takes the accent, and it takes it whatever the header's own
 * call to action was set to. That used to be a hard-coded value here and
 * became a setting; following the setting was the mistake. An operator whose
 * header button is Primary got a Primary row on a Primary panel, which is not
 * a quiet call to action but an invisible one.
 *
 * Through bridge_button_scheme() rather than named straight, so the label is
 * picked by ratio against the accent the same way every other button's is
 * — an accent light enough to need dark text gets it.
 *
 * Two values used of the nine: the row is a flat band with a label, with no
 * boundary to draw and no hover fill to arrive.
 */
$bridge_panel_cta = bridge_button_scheme(
	(string) ( $bridge_palette['accent']['color'] ?? '#f59e0b' ),
	$bridge_panel_hex,
	sprintf( '#%s', ltrim( (string) ( $bridge_palette[ $bridge_panel_fg ]['color'] ?? '#ffffff' ), '#' ) ),
	(float) ( bridge_button_skins()[ bridge_get_tokens()['buttons']['skin'] ]['fill'] ?? 0.12 ),
	array(
		(string) ( $bridge_palette['background']['color'] ?? '#ffffff' ),
		(string) ( $bridge_palette['text']['color'] ?? '#000000' ),
	)
);

$bridge_style = sprintf(
	'--bridge-header-logo-height:%dpx;--bridge-header-padding:%s;--bridge-header-bg:%s;--bridge-header-menu-bg:%s;--bridge-nav-panel-bg:%s;--bridge-nav-panel-fg:%s;--bridge-nav-cta-bg:%s;--bridge-nav-cta-fg:%s;--bridge-nav-rollover-bg:%s;--bridge-nav-rollover-fg:%s;--bridge-nav-drop-bg:%s;--bridge-nav-drop-fg:%s;--bridge-nav-drop-hover-bg:%s;--bridge-nav-accent:%s;',
	(int) $bridge_header['logo']['height'],
	bridge_header_padding_block( (int) $bridge_header['paddingBlock'] ),
	'transparent' === $bridge_background ? 'transparent' : $bridge_solid,
	$bridge_menu_bg,
	'var(--wp--preset--color--primary)',
	sprintf( 'var(--wp--preset--color--%s)', $bridge_panel_fg ),
	$bridge_panel_cta['bg'],
	$bridge_panel_cta['fg'],
	$bridge_rollover_bg,
	$bridge_rollover_fg,
	$bridge_child_bg,
	$bridge_child_fg,
	$bridge_child_hover,
	$bridge_nav_marker
);

/**
 * On an overlaying header, the second answer and the script that chooses.
 *
 * The resting scheme is the dark-backdrop one, because that is what the header
 * already assumes a hero is and what the page paints before any measuring has
 * happened. The alternative rides along beside it for the two states that mean
 * the thing behind the button is light: a bright hero, which
 * header-cta-backdrop.js measures, and a sticky bar that has scrolled solid,
 * which the stylesheet knows on its own.
 *
 * Enqueued here rather than declared as the block's `viewScript`, because the
 * header renders on every page and this is only ever needed on the templates
 * that overlay one.
 */
$bridge_cta_alt = '';

if ( 'transparent' === $bridge_background ) {
	$bridge_cta_schemes = bridge_header_cta_schemes();
	$bridge_cta_scheme  = $bridge_cta_schemes['dark'];
	$bridge_light       = $bridge_cta_schemes['light'];

	$bridge_cta_alt = sprintf(
		'--bridge-cta-alt-bg:%s;--bridge-cta-alt-fg:%s;--bridge-cta-alt-hover-bg:%s;'
			. '--bridge-cta-alt-hover-fg:%s;--bridge-cta-alt-border:%s;'
			. '--bridge-cta-alt-hover-border:%s;--bridge-cta-alt-ring:%s;',
		$bridge_light['bg'],
		$bridge_light['fg'],
		$bridge_light['hoverBg'],
		$bridge_light['hoverFg'],
		$bridge_light['border'],
		$bridge_light['hoverBorder'],
		$bridge_light['ring']
	);

	wp_enqueue_script( 'bridge-header-cta-backdrop' );
}

/**
 * The button's scheme, on the button's own wrapper.
 *
 * Named `--bridge-cta-*` and mapped onto the shared `--bridge-button-*` hooks
 * by the stylesheet, rather than written straight onto those hooks here.
 *
 * That indirection is the whole point. This is a `style` attribute, and an
 * inline declaration beats every stylesheet rule that is not `!important` —
 * so a rule swapping the button to its other scheme, whether because the page
 * measured a bright hero or because a sticky bar has scrolled solid, could
 * never win against a value written here. Both rules existed and neither did
 * anything. Handing the stylesheet raw values under names nothing else reads
 * leaves it in charge of which set is live.
 */
$bridge_cta_style = sprintf(
	'--bridge-cta-bg:%s;--bridge-cta-fg:%s;--bridge-cta-hover-bg:%s;--bridge-cta-hover-fg:%s;'
		. '--bridge-cta-border:%s;--bridge-cta-hover-border:%s;--bridge-cta-ghost:%s;'
		. '--bridge-cta-ghost-hover:%s;--bridge-cta-ring:%s;',
	$bridge_cta_scheme['bg'],
	$bridge_cta_scheme['fg'],
	$bridge_cta_scheme['hoverBg'],
	$bridge_cta_scheme['hoverFg'],
	$bridge_cta_scheme['border'],
	$bridge_cta_scheme['hoverBorder'],
	$bridge_cta_scheme['ghost'],
	$bridge_cta_scheme['ghostHover'],
	$bridge_cta_scheme['ring']
) . $bridge_cta_alt;

$bridge_logo = bridge_header_logo( $bridge_header, $bridge_background );
$bridge_nav  = bridge_header_nav( bridge_header_menu_id( 'primary' ), $bridge_justify );

// The top bar renders only once it has a menu: an empty strip is worse than
// no strip. The call to action is not part of it — there is one button, and
// its home is beside the menu.
$bridge_top = 0;
if ( ! empty( $bridge_header['topBar'] ) ) {
	$bridge_top = bridge_header_menu_id( 'utility' );
}

// The search field belongs to this instance of the header — the editor can
// render the block twice on one screen — so the button and the panel it
// controls are tied together by an id nothing else can collide with.
$bridge_search    = ! empty( $bridge_header['search'] );
$bridge_search_id = wp_unique_id( 'bridge-header-search-' );

/**
 * The one page that opens with the panel already down.
 *
 * A visitor on a results page has just searched, and the commonest next thing
 * they do is search again — so the field they would have to go looking for is
 * put in front of them, holding the term they used. Everywhere else the panel
 * stays shut, because everywhere else the field is not what the page is about.
 *
 * Decided here rather than by header.js so the panel is open in the first
 * paint: a script that opened it afterwards would drop the results down the
 * page a moment after the visitor started reading them. The three things that
 * make it open — the class the stylesheet reads, the button's state, and the
 * `hidden` attribute coming off — are all set below from this one answer, so
 * the markup that arrives is already the state header.js would have produced.
 *
 * `is_search()` is false in the editor's REST preview, which is the honest
 * answer there: the canvas is not a results page, and a header drawn with its
 * search open would be showing an operator a state their site is not in.
 */
$bridge_search_open = $bridge_search && is_search();

$bridge_cta   = $bridge_header['cta'];
$bridge_label = trim( (string) $bridge_cta['label'] );
$bridge_url   = (string) $bridge_cta['url'];
$bridge_button = ! empty( $bridge_cta['enabled'] ) && '' !== $bridge_label && '' !== $bridge_url;

// The same button, once more, as the last row of the mobile menu — the panel
// is a dialog and the header's copy sits outside it, so on a phone the call to
// action is either in the list or nowhere. Only one of the two is ever on
// screen: the header hides its own below 600px and the stylesheet hides this
// one above 599px.
if ( $bridge_button ) {
	$bridge_nav = bridge_header_nav_cta( $bridge_nav, $bridge_label, $bridge_url );
}

// The same class header.js toggles, written into the markup instead. The
// stylesheet has one rule for an open panel and does not care which of the two
// put the class there.
if ( $bridge_search_open ) {
	$bridge_classes[] = 'is-search-open';
}

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

			<?php if ( $bridge_search ) : ?>
				<button
					class="bridge-header__search-toggle"
					type="button"
					aria-expanded="<?php echo $bridge_search_open ? 'true' : 'false'; ?>"
					aria-controls="<?php echo esc_attr( $bridge_search_id ); ?>"
				>
					<?php echo bridge_render_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from the compiled icon library. ?>
					<span class="screen-reader-text"><?php esc_html_e( 'Search', 'bridge' ); ?></span>
				</button>
			<?php endif; ?>

			<?php if ( $bridge_button ) : ?>
				<div class="bridge-header__cta" style="<?php echo esc_attr( $bridge_cta_style ); ?>">
					<a class="wp-element-button" href="<?php echo esc_url( $bridge_url ); ?>">
						<?php echo esc_html( $bridge_label ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $bridge_search ) : ?>
		<?php
		// A sibling of the header's row rather than a child of it: the panel is
		// a full-width band under the whole header, and it is in the flow, so
		// opening it moves the page down instead of covering the top of it.
		//
		// `hidden` rather than a class: with JavaScript off the panel is never
		// opened, and a field a visitor can tab into but not see is worse than
		// no field. header.js takes the attribute off as soon as it runs, and
		// the closed state after that is the stylesheet's.
		//
		// The results page is the exception, and it is the case that proves the
		// rule: there the panel is open from the first paint, so the attribute
		// would be hiding a field the page is deliberately showing — and it
		// stays off with JavaScript disabled, which is the one configuration
		// where a visitor can still search without a script to open anything.
		?>
		<div class="bridge-header__search-panel" id="<?php echo esc_attr( $bridge_search_id ); ?>" <?php echo $bridge_search_open ? '' : 'hidden'; ?>>
			<div class="bridge-header__search-panel-inner">
				<div class="bridge-header__inner">
					<form role="search" method="get" class="bridge-header__search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
						<label class="screen-reader-text" for="<?php echo esc_attr( $bridge_search_id . '-field' ); ?>">
							<?php esc_html_e( 'Search this site', 'bridge' ); ?>
						</label>
						<div class="bridge-header__search-control">
							<?php echo bridge_render_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from the compiled icon library. ?>
							<input
								class="bridge-header__search-field"
								id="<?php echo esc_attr( $bridge_search_id . '-field' ); ?>"
								type="search"
								name="s"
								<?php
								// `false` because the escaping is done on the
								// next line. `get_search_query()` runs esc_attr()
								// itself by default, and the two together turn a
								// search for `A & B` into a field reading
								// `A &amp; B` — visible on the results page,
								// where this field is open and holding the term.
								?>
								value="<?php echo esc_attr( get_search_query( false ) ); ?>"
								placeholder="<?php esc_attr_e( 'Search…', 'bridge' ); ?>"
							/>
						</div>
						<button class="bridge-header__search-submit" type="submit">
							<?php esc_html_e( 'Search', 'bridge' ); ?>
						</button>
					</form>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>
