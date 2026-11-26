<?php
/**
 * Server-side render for `bridge/tabs` — the Tab Block.
 *
 * A section band: the intro, a row of tabs, one panel per tab, and an
 * optional Previous / Next strip with "Step 1 of 5" between. The tabs are
 * `bridge/tab` children; each renders its own panel body (see tab/render.php)
 * and this file wraps it in the tab panel and builds the tab row from the
 * children's labels, so the label is typed once.
 *
 * Every panel is in the HTML, so search engines and screen readers get all of
 * it; src/js/tabs-view.js shows one at a time. The first is open on load and
 * the rest carry `hidden`. With scripting off, the <noscript> rule shows every
 * panel in order and hides the controls that would need a script.
 *
 * The section-level settings are the Feature Blocks ones, spelled the same
 * way: width, text alignment, step numbers, panel style (here for the
 * features in each tab), image corners, button style and size, the rounded
 * band corners with the colour behind them, and the mask shape.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner-block HTML (unused).
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$width        = 'narrow' === ( $attributes['width'] ?? 'wide' ) ? 'narrow' : 'wide';
$alignment    = 'left' === ( $attributes['textAlignment'] ?? 'center' ) ? 'left' : 'center';
$show_numbers = ! isset( $attributes['showNumbers'] ) || ! empty( $attributes['showNumbers'] );
$show_steps   = ! isset( $attributes['showSteps'] ) || ! empty( $attributes['showSteps'] );
$image_side   = 'left' === ( $attributes['imageSide'] ?? 'right' ) ? 'left' : 'right';
$panel_style  = sanitize_key( $attributes['panelStyle'] ?? 'card' );
$panel_style  = in_array( $panel_style, array( 'card', 'plain', 'outline' ), true ) ? $panel_style : 'card';
$prev_label   = trim( (string) ( $attributes['prevLabel'] ?? '' ) );
$next_label   = trim( (string) ( $attributes['nextLabel'] ?? '' ) );
$prev_label   = '' !== $prev_label ? $prev_label : __( 'Previous', 'bridge' );
$next_label   = '' !== $next_label ? $next_label : __( 'Next step', 'bridge' );

// Split the children: tabs, and the intro (heading, summary, buttons) above.
bridge_prime_item_images( $block );

$intro = '';
$tabs  = array();

foreach ( $block->inner_blocks as $inner ) {
	if ( 'bridge/tab' === $inner->name ) {
		$tabs[] = array(
			'label' => trim( wp_strip_all_tags( (string) ( $inner->attributes['label'] ?? '' ) ) ),
			'html'  => $inner->render(),
		);
	} else {
		$intro .= $inner->render();
	}
}

if ( ! $tabs && '' === trim( $intro ) ) {
	return;
}

$classes = sprintf(
	'bridge-tabs bridge-tabs--%s bridge-tabs--text-%s bridge-tabs--image-%s bridge-tabs--features-%s',
	$width,
	$alignment,
	$image_side,
	$panel_style
);

if ( 'round' === ( $attributes['imageCorners'] ?? 'card' ) ) {
	$classes .= ' bridge-tabs--image-round';
}

if ( 'small' === ( $attributes['buttonSize'] ?? 'default' ) ) {
	$classes .= ' bridge-tabs--button-small';
}

if ( 'none' !== ( $attributes['transition'] ?? 'fade' ) ) {
	$classes .= ' bridge-tabs--fade';
}

// Rounded band corners with the colour behind each curve, as Feature Blocks.
$corner_style = '';

if ( ! empty( $attributes['roundCorners'] ) ) {
	$classes     .= ' bridge-tabs--corners';
	$palette      = bridge_palette_slugs();
	$behind       = static function ( $slug ) use ( $palette ): string {
		$slug = (string) $slug;

		return isset( $palette[ $slug ] ) ? $slug : 'background';
	};
	$corner_style = sprintf(
		'--bridge-corner-top: var(--wp--preset--color--%1$s);--bridge-corner-bottom: var(--wp--preset--color--%2$s);',
		$behind( $attributes['cornerTop'] ?? '' ),
		$behind( $attributes['cornerBottom'] ?? '' )
	);
}

list( $intro_html, $label_id ) = bridge_section_intro( $intro, 'bridge-tabs__intro' );
list( $mask_class, $mask_style ) = bridge_band_mask( $attributes );

$uid   = wp_unique_id( 'bridge-tabs-' );
$count = count( $tabs );

$next_class = 'bridge-tabs__next wp-element-button';
if ( 'outline' === ( $attributes['buttonStyle'] ?? 'fill' ) ) {
	$next_class .= ' is-style-outline';
}

echo bridge_section_wrapper( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
	$attributes,
	$classes . $mask_class,
	$corner_style . $mask_style,
	$label_id
);
?>
	<div class="bridge-tabs__inner" data-bridge-tabs>
		<?php echo $intro_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — rendered inner blocks. ?>

		<?php if ( $tabs ) : ?>
			<div class="bridge-tabs__nav" role="tablist" aria-label="<?php esc_attr_e( 'Steps', 'bridge' ); ?>" data-bridge-tabs-nav>
				<?php foreach ( $tabs as $i => $tab ) : ?>
					<?php
					/* translators: %d: tab number. */
					$label = '' !== $tab['label'] ? $tab['label'] : sprintf( __( 'Step %d', 'bridge' ), $i + 1 );
					?>
					<button type="button" class="bridge-tabs__tab" role="tab"
						id="<?php echo esc_attr( $uid . '-tab-' . $i ); ?>"
						aria-controls="<?php echo esc_attr( $uid . '-panel-' . $i ); ?>"
						aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"
						tabindex="<?php echo 0 === $i ? '0' : '-1'; ?>">
						<?php if ( $show_numbers ) : ?>
							<span class="bridge-tabs__number" aria-hidden="true"><?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
						<?php endif; ?>
						<span class="bridge-tabs__label"><?php echo esc_html( $label ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="bridge-tabs__panels">
				<?php foreach ( $tabs as $i => $tab ) : ?>
					<div class="bridge-tabs__panel" role="tabpanel" tabindex="0"
						id="<?php echo esc_attr( $uid . '-panel-' . $i ); ?>"
						aria-labelledby="<?php echo esc_attr( $uid . '-tab-' . $i ); ?>"
						<?php echo 0 === $i ? '' : 'hidden'; ?>>
						<?php echo $tab['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — rendered inner blocks. ?>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( $show_steps && $count > 1 ) : ?>
				<div class="bridge-tabs__steps">
					<button type="button" class="bridge-tabs__prev wp-element-button is-style-outline" data-bridge-tabs-prev disabled>
						<span class="bridge-tabs__arrow" aria-hidden="true">&larr;</span>
						<span><?php echo esc_html( $prev_label ); ?></span>
					</button>
					<p class="bridge-tabs__count" aria-live="polite" data-bridge-tabs-count data-template="<?php echo esc_attr( /* translators: 1: step number, 2: number of steps. Keep %1$d and %2$d. */ __( 'Step %1$d of %2$d', 'bridge' ) ); ?>">
						<?php echo esc_html( sprintf( /* translators: 1: step number, 2: number of steps. */ __( 'Step %1$d of %2$d', 'bridge' ), 1, $count ) ); ?>
					</p>
					<button type="button" class="<?php echo esc_attr( $next_class ); ?>" data-bridge-tabs-next>
						<span><?php echo esc_html( $next_label ); ?></span>
						<span class="bridge-tabs__arrow" aria-hidden="true">&rarr;</span>
					</button>
				</div>
			<?php endif; ?>

			<noscript>
				<style>
					.bridge-tabs__panel[hidden] { display: block !important; }
					.bridge-tabs__nav, .bridge-tabs__steps { display: none !important; }
				</style>
			</noscript>
		<?php endif; ?>
	</div>
</section>
