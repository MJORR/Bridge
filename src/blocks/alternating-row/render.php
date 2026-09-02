<?php
/**
 * Server-side render for `bridge/alternating-row`.
 *
 * The copy beside the media is inner blocks, so a row can hold a heading, body
 * copy, a list and a row of buttons — all styled by global styles — where the
 * old block had a single WYSIWYG field and one button of its own. Those four
 * are the whole list: see ALLOWED_BLOCKS in edit.js.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner-block HTML (the row's copy).
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$media_type = (string) ( $attributes['mediaType'] ?? 'image' );
$image_id   = (int) ( $attributes['imageId'] ?? 0 );
$alt        = trim( (string) ( $attributes['alt'] ?? '' ) );
$video_url  = trim( (string) ( $attributes['videoUrl'] ?? '' ) );
$youtube    = trim( (string) ( $attributes['youtubeId'] ?? '' ) );

/*
 * A player needs a name of its own. "Video" on every embed tells a screen-
 * reader user running through the page's frames nothing about which one they
 * have landed in, and the row already contains the answer — its heading. The
 * copy is rendered HTML by this point, so it is read rather than re-parsed.
 */
$heading = '';

if ( preg_match( '#<h[1-6][^>]*>(.*?)</h[1-6]>#is', $content, $matches ) ) {
	// Stripped, not decoded. The heading arrives as HTML, so entities in it are
	// already entities — and esc_attr() leaves a valid one alone rather than
	// encoding it again, so it reaches the attribute intact.
	$heading = trim( wp_strip_all_tags( $matches[1] ) );
}

$media_label = '' !== $heading
	/* translators: %s: the row's heading. */
	? sprintf( __( 'Video: %s', 'bridge' ), $heading )
	: __( 'Video', 'bridge' );

$play_label = '' !== $heading
	/* translators: %s: the row's heading. */
	? sprintf( __( 'Play video: %s', 'bridge' ), $heading )
	: __( 'Play video', 'bridge' );

/*
 * The mask.
 *
 * One shape for the whole site, set in Theme Options → Templates, so a row
 * switches it on rather than choosing its own — the alternative is twelve rows
 * each pointing at a different SVG, which is a collage rather than a design.
 *
 * Empty URL covers both "no shape chosen" and "chosen, then deleted from the
 * library". Either way there is nothing to cut with, so the row keeps its
 * rectangle instead of rendering an image masked to nothing, which is an image
 * that has vanished.
 *
 * Size is a percentage the stylesheet turns into a scale. 100 is the shape at
 * the height of the image area, centred; above that it grows past the area and
 * the box crops it, which is the point of the control.
 */
$mask_url = ! empty( $attributes['mask'] ) ? bridge_mask_shape_url() : '';

$media_class = 'bridge-alternating__media';
$media_style = '';

/*
 * The cut corner.
 *
 * A chamfer taken off the top left of the picture, in place of the rounded
 * corners, whichever side the picture is on — the same corner the cards cut.
 * The class only says "cut this one"; the stylesheet owns the shape.
 *
 * It does nothing on a full-window band, where the picture is already flush to
 * the glass and a chamfer would be cutting the corner off the window. The
 * stylesheet scopes it; the editor greys the switch out and says so.
 */
if ( ! empty( $attributes['cropCorner'] ) ) {
	$media_class .= ' has-crop';
}

if ( '' !== $mask_url ) {
	$mask_size = (int) ( $attributes['maskSize'] ?? 100 );
	$mask_size = max( 50, min( 300, $mask_size ) );

	$media_class .= ' has-mask';
	$media_style  = sprintf(
		' style="--bridge-mask-image:url(%s);--bridge-mask-scale:%s"',
		esc_url( $mask_url ),
		esc_attr( (string) ( $mask_size / 100 ) )
	);
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'bridge-alternating__row' ) );
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core. ?>>
	<div class="<?php echo esc_attr( $media_class ); ?>"<?php echo $media_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from esc_url()/esc_attr() above. ?>>
		<?php if ( 'youtube' === $media_type && '' !== $youtube ) : ?>
			<?php
			/*
			 * youtube-nocookie, and not loaded at all until the visitor asks
			 * for it: the old block dropped a tracking iframe on every page
			 * view whether or not anyone pressed play.
			 *
			 * The frame has no `src`. `srcdoc` holds a poster and a play
			 * button — a document of our own, served from nowhere — and the
			 * button is an ordinary link, so pressing it navigates the frame
			 * to YouTube and the player arrives already playing. Nothing
			 * reaches Google before that click, which is why the poster is the
			 * row's own image rather than the thumbnail YouTube would serve.
			 * No script either: a link inside a frame needs none.
			 */
			$poster = $image_id > 0 ? (string) wp_get_attachment_image_url( $image_id, 'large' ) : '';

			$facade_css = 'html,body{margin:0;height:100%;background:#000}'
				. 'a{display:block;position:relative;height:100%;text-decoration:none}'
				. 'img{display:block;width:100%;height:100%;object-fit:cover;border:0}'
				. 'span{position:absolute;top:50%;left:50%;width:68px;height:48px;margin:-24px 0 0 -34px;'
				. 'border-radius:12px;background:rgba(0,0,0,.75)}'
				. 'span:after{content:"";position:absolute;top:14px;left:26px;border-style:solid;'
				. 'border-width:10px 0 10px 17px;border-color:transparent transparent transparent #fff}'
				. 'a:hover span,a:focus span{background:#c00}'
				. 'a:focus{outline:3px solid #fff;outline-offset:-3px}';

			$facade = sprintf(
				'<!doctype html><meta charset="utf-8"><style>%1$s</style><a href="%2$s" aria-label="%3$s">%4$s<span aria-hidden="true"></span></a>',
				$facade_css,
				esc_url( 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $youtube ) . '?autoplay=1&rel=0' ),
				esc_attr( $play_label ),
				'' !== $poster ? sprintf( '<img src="%s" alt="">', esc_url( $poster ) ) : ''
			);
			?>
			<div class="bridge-alternating__video">
				<iframe
					class="bridge-alternating__embed"
					srcdoc="<?php echo esc_attr( $facade ); ?>"
					title="<?php echo esc_attr( $media_label ); ?>"
					loading="lazy"
					referrerpolicy="strict-origin-when-cross-origin"
					allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
					allowfullscreen></iframe>
			</div>
		<?php elseif ( 'video' === $media_type && '' !== $video_url ) : ?>
			<?php
			// No autoplay: a video that starts on its own is a nuisance on a
			// phone and a data charge on a metered connection.
			?>
			<video class="bridge-alternating__video-file" controls preload="metadata"
				aria-label="<?php echo esc_attr( $media_label ); ?>"
				<?php if ( $image_id > 0 ) : ?>
					poster="<?php echo esc_url( (string) wp_get_attachment_image_url( $image_id, 'large' ) ); ?>"
				<?php endif; ?>>
				<source src="<?php echo esc_url( $video_url ); ?>">
			</video>
		<?php elseif ( $image_id > 0 ) : ?>
			<?php
			/*
			 * `alt` is passed whatever the field holds, empty included: an
			 * empty alt is how a decorative image is spelled, and the field is
			 * seeded from the media library when the image is chosen, so it is
			 * the one place the answer lives. That is the fix — the field used
			 * to fall back to the library's alt text whenever it was empty, so
			 * a decorative image could not be made silent.
			 *
			 * Nothing is escaped on the way in either. wp_get_attachment_image()
			 * runs esc_attr() over every attribute it is handed, so data reaches
			 * it raw. (Escaping first was harmless — esc_attr() leaves an
			 * already-valid entity alone — but pre-escaping for an API that
			 * escapes is the wrong habit for data that may travel elsewhere.)
			 *
			 * No `loading` or `decoding` either. Core decides those per image
			 * now, and it knows the one thing this file does not: whether this
			 * is the first image on the page, which it will then load eagerly
			 * and at high priority instead of lazily. Naming them here opted
			 * every row's image out of that, including the row that is
			 * sometimes the largest thing above the fold.
			 */
			echo wp_get_attachment_image(
				$image_id,
				'large',
				false,
				array(
					'class' => 'bridge-alternating__image',
					'alt'   => $alt,
				)
			);
			?>
		<?php endif; ?>
	</div>

	<div class="bridge-alternating__copy">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</div>
