<?php
/**
 * Server-side render for `bridge/call-to-action`.
 *
 * The heading, copy and buttons are inner blocks rather than fields, so they
 * are styled by global styles and edited with the same controls as every other
 * heading and button on the site — the second button is core's own `outline`
 * style, painted by theme.json, not a control of this block's. What stays an
 * attribute is the one thing global styles cannot express: the photograph
 * behind the band and how far it is dimmed.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner-block HTML.
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( '' === trim( (string) $content ) ) {
	return;
}

// Whether the band is a photograph is not a setting: it is whether a
// photograph was chosen. The old `backgroundStyle` select said the same thing
// a second time, and the editor and this file read the two halves of it
// differently — the preview keyed off the URL, this keyed off the id, so a
// block holding one without the other previewed as something it would not
// render as.
$background_id = isset( $attributes['backgroundImage'] ) ? (int) $attributes['backgroundImage'] : 0;
$dim           = max( 0, min( 100, (int) ( $attributes['dimRatio'] ?? 50 ) ) );
$graphic_id    = isset( $attributes['graphic'] ) ? (int) $attributes['graphic'] : 0;

$has_image = $background_id > 0;

$width = 'wide' === ( $attributes['width'] ?? 'narrow' ) ? 'wide' : 'narrow';

$classes = 'bridge-cta bridge-cta--' . $width;

if ( $has_image ) {
	// The old block fell back to the dark skin when the image was missing, so
	// that light text on no background never became light text on white. Same
	// safeguard, expressed as a class the stylesheet can act on.
	$classes .= ' bridge-cta--image';
}

$style = '';

if ( $has_image ) {
	/*
	 * The wash over the photograph is Primary, and is not chosen per block.
	 *
	 * It used to read the block's own Background control: that control existed
	 * anyway, and under a photograph it painted something no visitor could see,
	 * so borrowing it turned a dead control into the one an editor wanted. The
	 * Background control has since been taken off this block, so there is
	 * nothing left to borrow — and one wash colour across every call to action
	 * is the more consistent answer in any case. Darken image still says how
	 * much of it lands.
	 */
	$style = sprintf(
		'--bridge-cta-dim: %s; --bridge-cta-scrim: %s;',
		$dim / 100,
		'var(--wp--preset--color--primary)'
	);
}

echo bridge_section_wrapper( $attributes, $classes, $style ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.

if ( $has_image ) {
	/*
	 * No `loading` or `decoding`. A full-bleed call to action is often the
	 * largest thing above the fold, and naming them here opted it out of
	 * `wp_get_loading_optimization_attributes()` — the one piece of code that
	 * knows whether this image is the page's first and should therefore be
	 * fetched eagerly and at high priority.
	 *
	 * `alt=""` and nothing else: an empty alt already takes an image out of
	 * the accessibility tree, so the `aria-hidden` that used to sit beside it
	 * was saying the same thing twice.
	 */
	echo wp_get_attachment_image(
		$background_id,
		'full',
		false,
		array(
			'class' => 'bridge-cta__background',
			// Decorative: the band's meaning is in the heading over it, and a
			// screen reader announcing the stock photo's filename adds noise.
			'alt'   => '',
		)
	);
	echo '<div class="bridge-cta__scrim" aria-hidden="true"></div>';
}
?>
	<div class="bridge-cta__inner">
		<?php
		/*
		 * A graphic is a core/image block now — centred, sized, and with the
		 * link, caption and alt-text controls an editor already knows. What is
		 * left here renders the ones saved before that was true, so no band
		 * loses its logo on upgrade. Nothing writes these attributes any more.
		 */
		if ( $graphic_id > 0 ) {
			echo wp_get_attachment_image(
				$graphic_id,
				'medium',
				false,
				array(
					'class' => 'bridge-cta__graphic',
					// Unescaped on purpose: wp_get_attachment_image() runs
					// esc_attr() over every attribute it is handed, so data
					// reaches it raw.
					'alt'   => (string) ( $attributes['graphicAlt'] ?? '' ),
				)
			);
		}
		?>
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</section>
