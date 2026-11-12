<?php
/**
 * Server-side render for `bridge/feature-block`.
 *
 * A panel is a card, and this file's job is to make that true rather than
 * merely similar. Both shapes it can take are shapes the Cards band already
 * draws, and both take their measurements from the same place the cards do:
 *
 *   with an image      A Summary card. The photograph fills a frame cut to the
 *                      crop set in Theme Options, the words sit under it in the
 *                      card's own padding, and the button is last.
 *   with a background  A Cover card. The panel *is* the photograph, a gradient
 *                      in the site's wash colour rises from the bottom edge,
 *                      and the words sit on it.
 *
 * Nothing about the shape is set here, which is the point of the rework. The
 * crop, the corner, the padding, the shadow and the lift under the pointer are
 * all the card system's, so a page that stacks a feature band over a news band
 * shows one idea of what a card is instead of two. What the panel keeps for
 * itself is what is genuinely per-panel: its picture, its words, its button,
 * and — because a photograph is a different ground on every panel — the colour
 * and strength of the wash over it.
 *
 * ---- The wash, and the ink that has to survive it --------------------------
 *
 * Resolved here rather than left to a `var()` fallback chain, so that one
 * slug decides all three of the things that depend on it: the gradient's
 * colour, the text colour checked to 4.5:1 against it, and which ground the
 * button paints itself for. A panel that names no colour of its own takes the
 * one the Cover cards use, so the two are the same design by default and
 * differ only where an editor has said so.
 *
 * The contrast check is against the flat wash rather than against the
 * photograph, which is the same approximation `bridge_compile_theme_json()`
 * makes for Cover: the gradient is at full strength exactly where the words
 * are, so that is the ground they are read on.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block HTML (unused).
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * The line above the title — a step number, most often, but any short label
 * that introduces the panel's heading rather than replacing it. Free text and
 * not a counter: `bridge-features--numbered` already draws a counter for a
 * band that wants one, and the two answer different questions. This one is
 * whatever the editor typed, on whichever panel they typed it.
 */
$prefix     = trim( (string) ( $attributes['prefix'] ?? '' ) );
// A palette slug or nothing. Left empty the line takes a quieter shade of the
// band's own foreground, which is the only colour that is right on a light
// band, a dark one and over a photograph at the same time — so an empty value
// here is a working default rather than a missing setting.
$prefix_ink = sanitize_key( (string) ( $attributes['prefixColor'] ?? '' ) );
// RichText values, read back to plain text so a pasted line break is a line
// break rather than a printed `<br>` — see bridge_rich_text_plain().
$title      = bridge_rich_text_plain( (string) ( $attributes['title'] ?? '' ) );
$text       = bridge_rich_text_plain( (string) ( $attributes['text'] ?? '' ) );
// `graphicId` rather than `imageId`, and the name is the only thing left of
// what it used to be: a small icon floated at the top of the panel. It is the
// panel's photograph now, drawn in the card frame. Renaming the attribute
// would have orphaned the image on every panel already saved, for a word only
// a developer ever reads.
$image      = (int) ( $attributes['graphicId'] ?? 0 );
$background = (int) ( $attributes['backgroundId'] ?? 0 );
$link_url   = trim( (string) ( $attributes['linkUrl'] ?? '' ) );
$link_text  = trim( (string) ( $attributes['linkText'] ?? '' ) );
$link_style = 'text' === ( $attributes['linkStyle'] ?? 'button' ) ? 'text' : 'button';
$highlight  = ! empty( $attributes['highlight'] );
$card_bg    = sanitize_key( (string) ( $attributes['cardBackground'] ?? '' ) );
$card_text  = sanitize_key( (string) ( $attributes['cardText'] ?? '' ) );

// The number line is the band's call: printed only when it has step numbers
// switched on, and never on the numbered layout, which draws badges instead.
// A panel left empty takes its position from a CSS counter on the list.
// The arrow at the foot of the panel — the band's call, like the numbers.
$show_arrow = ! empty( $block->context['bridge/featureArrow'] );

// The band can make the panel itself the link: no button, no link text, and
// the covering link below becomes the real, focusable one.
$panel_link = ! empty( $block->context['bridge/featurePanelLink'] );

$show_prefix = ! empty( $block->context['bridge/featureNumbers'] )
	&& 'numbered' !== ( $block->context['bridge/featureLayout'] ?? 'grid' );

if ( '' === $title && '' === $text && 0 === $image && 0 === $background ) {
	return;
}

$classes = 'bridge-feature';
$style   = '';
// Declared before the branch that fills it: the button below reads it, and a
// panel with no background never enters that branch.
$ink     = '';

if ( 0 === $background && $image > 0 ) {
	/*
	 * A panel with a photograph at the top of it is a Summary card, and this
	 * says so in a class because the stylesheet has to be able to tell the two
	 * shapes apart.
	 *
	 * The Cover shape below already had one, because it changes almost
	 * everything about the panel. This one changes a single thing — whether the
	 * panel has a ground — and it does so in the direction the Cover shape does
	 * not: the photograph sits *on* the card here, so the card is still there
	 * and still wants the colour the section's skin gave it, whatever panel
	 * style the band is set to. A Cover panel is the photograph, so it has no
	 * ground to want.
	 */
	$classes .= ' bridge-feature--summary';
}

if ( $background > 0 ) {
	$classes .= ' bridge-feature--cover';

	/*
	 * The wash colour: the panel's own, or the one the site's Cover cards
	 * already use. Read from the token record rather than from a CSS fallback
	 * so the ink and the button ground below are decided by the same slug the
	 * gradient is painted in — three answers to one question, and no way for
	 * them to disagree.
	 */
	$tokens  = function_exists( 'bridge_get_tokens' ) ? bridge_get_tokens() : array();
	$default = (string) ( $tokens['cards']['styles']['cover']['wash'] ?? 'primary' );
	$wash    = sanitize_key( (string) ( $attributes['overlayColor'] ?? '' ) );
	$wash    = '' !== $wash ? $wash : $default;

	$wash_hex = function_exists( 'bridge_palette_hex' ) ? bridge_palette_hex( $wash ) : '';

	// A slug the palette no longer holds — a filter dropped it, a rebrand
	// renamed it — paints nothing rather than an empty custom property.
	if ( '' !== $wash_hex ) {
		$ink = bridge_readable_on(
			$wash_hex,
			array( bridge_palette_hex( 'background' ), bridge_palette_hex( 'text' ) )
		);

		// A text colour chosen on the panel wins over the contrast pick: the
		// editor has said what they want. The button ground follows it.
		$chosen_ink = '' !== $card_text && function_exists( 'bridge_palette_hex' ) ? bridge_palette_hex( $card_text ) : '';

		if ( '' !== $chosen_ink ) {
			$ink = $chosen_ink;
		}

		$style .= sprintf(
			'--bridge-feature-wash:var(--wp--preset--color--%s);--bridge-feature-ink:%s;',
			$wash,
			$ink
		);
	}

	/*
	 * How much of the photograph the wash takes. The gradient's shape is the
	 * stylesheet's — clear at the top, solid where the words are — and this
	 * scales the whole of it, so a light photograph can be pushed darker and a
	 * dark one left almost bare without either becoming a second gradient to
	 * maintain.
	 */
	$opacity = max( 0, min( 100, (int) ( $attributes['overlayOpacity'] ?? 65 ) ) );
	$style  .= sprintf( '--bridge-feature-wash-opacity:%s;', round( $opacity / 100, 2 ) );
}

/*
 * The prefix's colour, on any shape of panel — which is why it is resolved
 * out here rather than in the cover branch above, where the wash lives.
 *
 * Checked against the palette for the same reason the wash is: a slug a
 * rebrand renamed or a filter dropped has to paint nothing and leave the
 * stylesheet's fallback standing, rather than write a custom property that
 * resolves to nothing and paints the line in the browser's initial colour.
 */
/*
 * The panel's own background and text colours. Palette slugs, checked against
 * the palette like the wash, and spent through `var()` so a rebrand carries
 * them. The background is not offered on a cover panel — the photograph is
 * the ground there.
 */
$card_bg_hex = '' !== $card_bg && 0 === $background && function_exists( 'bridge_palette_hex' ) ? bridge_palette_hex( $card_bg ) : '';

if ( '' !== $card_bg_hex ) {
	$classes .= ' has-card-background';
	$style   .= sprintf( '--bridge-feature-bg:var(--wp--preset--color--%s);', $card_bg );
}

if ( '' !== $card_text && function_exists( 'bridge_palette_hex' ) && '' !== bridge_palette_hex( $card_text ) ) {
	$classes .= ' has-card-text';
	$style   .= sprintf( '--bridge-feature-text:var(--wp--preset--color--%s);', $card_text );
}

if ( $show_prefix && '' !== $prefix && '' !== $prefix_ink ) {
	$prefix_ink_hex = function_exists( 'bridge_palette_hex' ) ? bridge_palette_hex( $prefix_ink ) : '';

	if ( '' !== $prefix_ink_hex ) {
		$style .= sprintf(
			'--bridge-feature-prefix-ink:var(--wp--preset--color--%s);',
			$prefix_ink
		);
	}
}

/*
 * The whole panel as a click target, whichever cue it shows.
 *
 * A text link stretches its own `::after` over the panel. A button cannot —
 * both of its pseudo-elements are the button's fill — so a button panel gets
 * a covering link of its own, hidden from the keyboard and screen readers so
 * the button stays the one named, focusable control. The button sits above
 * the cover so its own hover and click still land on it.
 */
if ( '' !== $link_url ) {
	$classes .= ' is-linked';
}

if ( $highlight ) {
	$classes .= ' is-highlighted';
}

$wrapper = get_block_wrapper_attributes(
	array_filter(
		array(
			'class' => $classes,
			'style' => $style,
		)
	)
);
?>
<li <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core. ?>>
	<?php if ( $background > 0 ) : ?>
		<?php
		echo wp_get_attachment_image(
			$background,
			'large',
			false,
			array(
				// `alt=""` and nothing else: an empty alt already takes an
				// image out of the accessibility tree, and `loading` is core's
				// call — a feature band can be the first thing on a page.
				'class' => 'bridge-feature__background',
				'alt'   => '',
			)
		);
		?>
		<div class="bridge-feature__wash" aria-hidden="true"></div>
	<?php elseif ( $image > 0 ) : ?>
		<?php
		// A <figure> for the same reason the cards use one: the frame is the
		// thing that holds the crop, and the image inside it is what gets
		// replaced when the crop changes.
		?>
		<figure class="bridge-feature__media">
			<?php
			echo wp_get_attachment_image(
				$image,
				'large',
				false,
				array(
					// Decorative: the title beside it names the panel, and a
					// screen reader reading a filename between a photograph
					// and a heading is reading out an orphan.
					'alt' => '',
				)
			);
			?>
		</figure>
	<?php endif; ?>

	<div class="bridge-feature__body">
		<?php if ( $show_prefix && '' !== $prefix ) : ?>
			<?php
			/*
			 * A <span> rather than a heading of its own: "01" is part of the
			 * title it sits over, and a second heading above every panel would
			 * put a row of numbers into the document outline for a screen
			 * reader to walk through.
			 */
			?>
			<span class="bridge-feature__prefix"><?php echo esc_html( $prefix ); ?></span>
		<?php elseif ( $show_prefix ) : ?>
			<span class="bridge-feature__prefix bridge-feature__prefix--auto"></span>
		<?php endif; ?>

		<?php if ( '' !== $title ) : ?>
			<h3 class="bridge-feature__title"><?php echo bridge_plain_text_html( $title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — escaped inside. ?></h3>
		<?php endif; ?>

		<?php if ( '' !== $text ) : ?>
			<p class="bridge-feature__text"><?php echo bridge_plain_text_html( $text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — escaped inside. ?></p>
		<?php endif; ?>

		<?php if ( '' !== $link_url && ! $panel_link ) : ?>
			<?php if ( 'button' === $link_style ) : ?>
				<?php
				/*
				 * `wp-element-button` so it is the site's button rather than a
				 * second one drawn here — the same class the header's call to
				 * action wears.
				 *
				 * On a cover panel it also names the ground it is standing on.
				 * The cascade can work out a button on a band; it cannot work
				 * out a button on a photograph, which is what
				 * `bridge-button--on-*` exists to say.
				 *
				 * Which ground is read off the *ink*, not off the wash, and
				 * that is deliberate: the ink is the answer the contrast check
				 * already gave for this panel, so a button derived from it
				 * cannot end up on the other side of the line from the words
				 * beside it. Light ink means a dark ground, which is the
				 * Inverted button.
				 */
				$button_class = 'bridge-feature__button wp-element-button';

				// A panel with its own background is a ground of its own too,
				// so the button reads which side of the line that colour is on.
				if ( '' !== $card_bg_hex ) {
					$button_class .= bridge_is_light_color( $card_bg_hex )
						? ' bridge-button--on-default'
						: ' bridge-button--on-inverted';
				} elseif ( '' !== $ink ) {
					$button_class .= bridge_is_light_color( $ink )
						? ' bridge-button--on-inverted'
						: ' bridge-button--on-default';
				}
				?>
				<a class="<?php echo esc_attr( $button_class ); ?>" href="<?php echo esc_url( $link_url ); ?>">
					<?php echo esc_html( '' !== $link_text ? $link_text : __( 'Find out more', 'bridge' ) ); ?>
				</a>
			<?php else : ?>
				<a class="bridge-feature__link" href="<?php echo esc_url( $link_url ); ?>">
					<?php echo esc_html( '' !== $link_text ? $link_text : __( 'Read more', 'bridge' ) ); ?>
				</a>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( $show_arrow ) : ?>
			<?php
			/*
			 * Decoration: the panel's link, title and words already say where
			 * it goes. In the text colour, because the icon strokes in
			 * `currentColor` — white text, white arrow.
			 */
			?>
			<span class="bridge-feature__arrow" aria-hidden="true">
				<?php echo bridge_render_icon( 'arrow-circle-right', array( 'size' => 'large' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from the theme's own icon library. ?>
			</span>
		<?php endif; ?>
	</div>

	<?php if ( '' !== $link_url && $panel_link ) : ?>
		<?php
		/*
		 * The panel is the link, so the cover is the control: focusable and
		 * named. Named by the title rather than by its whole contents, which
		 * is what wrapping the panel in an anchor would have done.
		 */
		$panel_label = '' !== $title ? bridge_rich_text_plain( $title, false ) : ( '' !== $text ? wp_trim_words( bridge_rich_text_plain( $text, false ), 12 ) : __( 'Read more', 'bridge' ) );
		?>
		<a class="bridge-feature__cover-link" href="<?php echo esc_url( $link_url ); ?>" aria-label="<?php echo esc_attr( $panel_label ); ?>"></a>
	<?php elseif ( '' !== $link_url && 'button' === $link_style ) : ?>
		<a class="bridge-feature__cover-link" href="<?php echo esc_url( $link_url ); ?>" tabindex="-1" aria-hidden="true"></a>
	<?php endif; ?>
</li>
