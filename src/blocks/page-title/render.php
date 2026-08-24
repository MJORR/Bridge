<?php
/**
 * Server-side render for `bridge/page-title`.
 *
 * Renders the current page's title, optionally wrapped in a banner. The
 * banner treatment is stored as per-page post meta (set from the "Title
 * Banner" document panel), so a single instance of this block in the
 * default Page template produces a different result for every page:
 *
 *   - hidden     No heading at all; the page opens on its own content.
 *   - none       Plain title, sits in the constrained content column.
 *   - contained  Title inside a padded, coloured banner at wide width.
 *   - fullwidth  Full-window-width coloured banner, title in a container.
 *
 * The banner background is one of the theme-palette colours; the title
 * colour is chosen automatically (black/white) for contrast against it.
 *
 * Used by both the frontend and the editor preview via <ServerSideRender>.
 * WordPress wraps this file in its own output buffer (render: file:...),
 * so we echo HTML directly — no ob_start, no return value.
 *
 * @package Bridge
 *
 * @var array    $attributes Block attributes (none — config lives in meta).
 * @var string   $content    Inner block HTML (unused).
 * @var WP_Block $block      Parsed block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---- Resolve the current page -----------------------------------------
$post_id = get_the_ID();

$title = $post_id ? get_the_title( $post_id ) : __( 'Page title', 'bridge' );

// In the editor the block forwards the live (unsaved) meta as preview
// attributes so the choice shows immediately; on the frontend these are
// absent and we read the saved post meta instead.
if ( ! empty( $attributes['isEditorPreview'] ) ) {
	$style = (string) ( $attributes['previewStyle'] ?? 'none' );
	$align = (string) ( $attributes['previewAlign'] ?? 'left' );
	$color = (string) ( $attributes['previewColor'] ?? '' );
} else {
	$style = $post_id ? (string) get_post_meta( $post_id, 'bridge_banner_style', true ) : '';
	$align = $post_id ? (string) get_post_meta( $post_id, 'bridge_title_align', true ) : '';
	$color = $post_id ? (string) get_post_meta( $post_id, 'bridge_banner_color', true ) : '';
}

$style = in_array( $style, array( 'hidden', 'contained', 'fullwidth' ), true ) ? $style : 'none';
$align = ( 'center' === $align ) ? 'center' : 'left';

// ---- Hidden: the page carries no heading of its own -------------------
// The title itself is untouched — it still names the page in menus, the
// browser tab and search results. Only this block's output goes away, and
// with it the block's slot in the template's layout, so whatever follows
// becomes the first child of `main` and starts flush under the header.
if ( 'hidden' === $style ) {
	// One exception: an empty render in the template editor shows up as
	// "Block rendered as empty", and a block with no box is a block that
	// cannot be clicked — the operator would have no way back to the panel
	// to undo the choice. The note never reaches the front end; it is
	// printed only for the editor's own preview request.
	if ( ! empty( $attributes['isEditorPreview'] ) ) {
		printf(
			'<p %1$s>%2$s</p>',
			get_block_wrapper_attributes( array( 'class' => 'bridge-page-title--hidden' ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — get_block_wrapper_attributes() is pre-escaped.
			esc_html__( 'Title hidden on this page', 'bridge' )
		);
	}

	return;
}

// ---- No banner: plain post-title, standard setup ----------------------
if ( 'none' === $style ) {
	$wrapper = get_block_wrapper_attributes(
		array(
			'class' => 'bridge-page-title has-text-align-' . $align,
		)
	);
	printf(
		'<h1 %1$s>%2$s</h1>',
		$wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — get_block_wrapper_attributes() is pre-escaped.
		esc_html( $title )
	);
	return;
}

// ---- Banner styles: resolve palette colour + contrasting text ---------
$bg_var   = '';
$text_var = '';

if ( $color ) {
	// wp_get_global_settings() may return the palette as a flat list or keyed
	// by origin (theme/default/custom) depending on the WP version — flatten.
	$palette = (array) wp_get_global_settings( array( 'color', 'palette' ) );
	if ( isset( $palette['theme'] ) || isset( $palette['default'] ) || isset( $palette['custom'] ) ) {
		$palette = array_merge(
			(array) ( $palette['theme'] ?? array() ),
			(array) ( $palette['default'] ?? array() ),
			(array) ( $palette['custom'] ?? array() )
		);
	}

	$hex = '';
	foreach ( $palette as $entry ) {
		if ( isset( $entry['slug'] ) && $entry['slug'] === $color ) {
			$hex = isset( $entry['color'] ) ? (string) $entry['color'] : '';
			break;
		}
	}

	if ( $hex ) {
		$bg_var       = sprintf( 'var(--wp--preset--color--%s)', sanitize_key( $color ) );
		$is_light_bg  = bridge_is_light_color( $hex );
		$text_var     = $is_light_bg
			? 'var(--wp--preset--color--text)'
			: 'var(--wp--preset--color--background)';
	}
}

$style_attr = '';
if ( $bg_var ) {
	$style_attr = sprintf( 'background-color:%s;color:%s;', $bg_var, $text_var );
}

$classes = array(
	'bridge-title-banner',
	'is-style-' . $style,
	'has-text-align-' . $align,
);

if ( 'fullwidth' === $style ) {
	// Full-window background that breaks out of the constrained main, with the
	// title constrained to the site content width. `has-global-padding` +
	// `is-layout-constrained` are the same WordPress layout classes used by
	// post-content and the footer, so the title lines up identically.
	$classes[] = 'alignfull';
	$classes[] = 'has-global-padding';
	$classes[] = 'is-layout-constrained';
} else {
	$classes[] = 'alignwide';
}

$wrapper = get_block_wrapper_attributes(
	array(
		'class' => implode( ' ', $classes ),
		'style' => $style_attr,
	)
);
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — get_block_wrapper_attributes() is pre-escaped. ?>>
	<div class="bridge-title-banner__inner">
		<h1 class="bridge-title-banner__title"><?php echo esc_html( $title ); ?></h1>
	</div>
</div>
<?php
