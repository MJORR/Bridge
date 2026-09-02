<?php
/**
 * Server-side render for `bridge/contact-form`.
 *
 * The band, the heading and the copy above the form are inner blocks, like
 * every other section block. The form itself is not: it is four controls whose
 * names, lengths and validation are one description in inc/enquiries.php, read
 * by this file, by the handler, by both emails and by the editor's preview. An
 * editor who could rearrange it could build a form the server does not accept.
 *
 * Everything below the markup — where a submission goes, what stops spam, who
 * is emailed — is in inc/enquiries.php. This file draws.
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

/*
 * The one place a form can be on a site without any post having been saved
 * with it in: a block dropped into one of the theme's own template files.
 * inc/enquiries.php watches save_post for every other route, so this is the
 * backstop rather than the mechanism — and it is a single autoloaded option
 * read when the flag is already set, which is every request after the first.
 */
bridge_mark_enquiries_in_use();

$form_id  = bridge_enquiry_form_id();
$response = bridge_enquiry_response( $form_id );

$width = 'wide' === ( $attributes['width'] ?? 'narrow' ) ? 'wide' : 'narrow';

$classes = 'bridge-contact bridge-contact--' . $width;

if ( ! empty( $attributes['panel'] ) ) {
	$classes .= ' bridge-contact--panel';
}

// The intro is whatever the editor wrote above the form. bridge_section_intro()
// drops the empty placeholder paragraph the template seeds, and hands back the
// id of the heading that will name the <section> for assistive technology.
list( $intro, $label_id ) = bridge_section_intro( $content, 'bridge-contact__intro' );

/*
 * Defaults are supplied here rather than in block.json because they are
 * translated, and a default in block.json is a literal that ships in English
 * to every site whatever its language. An editor who types over them stores
 * their own wording; an editor who clears the field gets these back.
 */
$button_label = trim( (string) ( $attributes['buttonLabel'] ?? '' ) );
$button_label = '' !== $button_label ? $button_label : __( 'Send enquiry', 'bridge' );

$success = trim( (string) ( $attributes['successMessage'] ?? '' ) );
$success = '' !== $success
	? $success
	: __( 'Thank you — your enquiry is on its way. We have sent a copy to your email address and will be in touch shortly.', 'bridge' );

echo bridge_section_wrapper( $attributes, $classes, '', $label_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped by core.
?>
	<div class="bridge-contact__inner">
		<?php echo $intro; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — rendered inner blocks. ?>

		<div class="bridge-contact__panel">
			<?php
			echo bridge_enquiry_form_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — escaped as it is assembled.
				array(
					'form_id'         => $form_id,
					'require_phone'   => ! empty( $attributes['requirePhone'] ),
					'button_label'    => $button_label,
					'success_message' => $success,
					'response'        => $response,
				)
			);
			?>
		</div>
	</div>
</section>
