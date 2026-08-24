<?php
/**
 * Server-side render for `bridge/download-item`.
 *
 * The file's type and size are read from the attachment rather than typed by
 * the editor: "PDF, 2.4 MB" is something the site already knows, and an
 * editor who has to type it will eventually be describing the file they
 * replaced last year. It is also what tells someone on a phone whether to tap
 * the link now or wait — which is why it is inside the link's accessible name
 * rather than only beside it.
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

$title     = trim( (string) ( $attributes['title'] ?? '' ) );
$link_text = trim( (string) ( $attributes['linkText'] ?? '' ) );
$file_id   = (int) ( $attributes['fileId'] ?? 0 );
$file_url  = trim( (string) ( $attributes['fileUrl'] ?? '' ) );
$preview   = (int) ( $attributes['previewId'] ?? 0 );

if ( '' === $file_url && $file_id > 0 ) {
	$file_url = (string) wp_get_attachment_url( $file_id );
}

if ( '' === $file_url ) {
	return;
}

if ( '' === $title ) {
	$title = $file_id > 0 ? (string) get_the_title( $file_id ) : __( 'Download', 'bridge' );
}

if ( '' === $link_text ) {
	$link_text = __( 'Download', 'bridge' );
}

// ---- What the file is --------------------------------------------------
$meta  = array();
$path  = $file_id > 0 ? get_attached_file( $file_id ) : '';
$ext   = strtoupper( (string) pathinfo( wp_parse_url( $file_url, PHP_URL_PATH ) ?? '', PATHINFO_EXTENSION ) );

if ( '' !== $ext ) {
	$meta[] = $ext;
}

if ( $path && file_exists( $path ) ) {
	$meta[] = size_format( (int) filesize( $path ), 1 );
}

$meta_text = implode( ', ', $meta );

// The link's accessible name. A page of six links all reading "Download"
// gives a screen-reader user nothing to choose between.
$label = '' !== $meta_text
	/* translators: 1: link text, 2: document title, 3: file type and size. */
	? sprintf( __( '%1$s: %2$s (%3$s)', 'bridge' ), $link_text, $title, $meta_text )
	/* translators: 1: link text, 2: document title. */
	: sprintf( __( '%1$s: %2$s', 'bridge' ), $link_text, $title );

$wrapper = get_block_wrapper_attributes( array( 'class' => 'bridge-download' ) );
?>
<li <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core. ?>>
	<?php if ( $preview > 0 ) : ?>
		<?php
		echo wp_get_attachment_image(
			$preview,
			'medium',
			false,
			array(
				'class' => 'bridge-download__preview',
				// Decorative: the title beside it already names the document.
				// `loading` and `decoding` are core's to decide — it knows
				// whether this one is the page's first image and this file
				// does not.
				'alt'   => '',
			)
		);
		?>
	<?php endif; ?>

	<div class="bridge-download__body">
		<p class="bridge-download__title"><?php echo esc_html( $title ); ?></p>
		<?php if ( '' !== $meta_text ) : ?>
			<p class="bridge-download__meta"><?php echo esc_html( $meta_text ); ?></p>
		<?php endif; ?>
		<a class="bridge-download__link"
			href="<?php echo esc_url( $file_url ); ?>"
			aria-label="<?php echo esc_attr( $label ); ?>"
			download>
			<?php echo esc_html( $link_text ); ?>
		</a>
	</div>
</li>
