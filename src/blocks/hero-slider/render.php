<?php
/**
 * Server-side render for `bridge/hero-slider`.
 *
 * Serializes settings as `data-*` attributes (read by slider.js to configure
 * Swiper) plus a `--bridge-slider-height` CSS variable that drives the
 * container height. The first cover's image is upgraded to `fetchpriority`
 * `high` and `loading="eager"` so the browser treats it as the LCP element.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner-block HTML (the cover slides).
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( '' === trim( (string) $content ) ) {
	return;
}

$effect          = isset( $attributes['effect'] ) ? (string) $attributes['effect'] : 'fade';
$autoplay        = ! empty( $attributes['autoplay'] );
$autoplay_delay  = isset( $attributes['autoplayDelay'] ) ? (int) $attributes['autoplayDelay'] : 6;
$loop            = ! empty( $attributes['loop'] );
$show_pagination = ! empty( $attributes['showPagination'] );
$show_navigation = ! empty( $attributes['showNavigation'] );

$height_preset = isset( $attributes['heightPreset'] ) ? (string) $attributes['heightPreset'] : 'full';
$custom_height = isset( $attributes['customHeight'] ) ? (int) $attributes['customHeight'] : 80;
$custom_unit   = isset( $attributes['customHeightUnit'] ) ? (string) $attributes['customHeightUnit'] : 'vh';

switch ( $height_preset ) {
	case 'tall':
		$height_value = '80dvh';
		break;
	case 'medium':
		$height_value = '60dvh';
		break;
	case 'custom':
		$unit          = in_array( $custom_unit, array( 'vh', 'dvh', 'px' ), true ) ? $custom_unit : 'vh';
		$custom_height = max( 20, min( 4000, $custom_height ) );
		$height_value  = $custom_height . $unit;
		break;
	default:
		$height_value = '100dvh';
}

// Prioritize the first slide's background image — it's the LCP element.
// WordPress otherwise defaults to loading="lazy" + fetchpriority="auto",
// which delays the most visible image on the page.
if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
	$processor = new WP_HTML_Tag_Processor( $content );
	if ( $processor->next_tag(
		array(
			'tag_name'   => 'img',
			'class_name' => 'wp-block-cover__image-background',
		)
	) ) {
		$processor->set_attribute( 'fetchpriority', 'high' );
		$processor->set_attribute( 'loading', 'eager' );
		$processor->remove_attribute( 'decoding' );
		$content = $processor->get_updated_html();
	}
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'               => 'bridge-hero-slider swiper alignfull',
		'style'               => '--bridge-slider-height: ' . esc_attr( $height_value ) . ';',
		'data-effect'         => $effect,
		'data-autoplay'       => $autoplay ? '1' : '0',
		'data-autoplay-delay' => (string) ( $autoplay_delay * 1000 ),
		'data-loop'           => $loop ? '1' : '0',
		'data-pagination'     => $show_pagination ? '1' : '0',
		'data-navigation'     => $show_navigation ? '1' : '0',
	)
);
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="swiper-wrapper">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<?php if ( $show_pagination ) : ?>
		<div class="swiper-pagination" aria-hidden="true"></div>
	<?php endif; ?>
	<?php if ( $show_navigation ) : ?>
		<button class="swiper-button-prev" type="button" aria-label="<?php esc_attr_e( 'Previous slide', 'bridge' ); ?>"></button>
		<button class="swiper-button-next" type="button" aria-label="<?php esc_attr_e( 'Next slide', 'bridge' ); ?>"></button>
	<?php endif; ?>
</div>
