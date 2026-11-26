<?php
/**
 * A team member's profile, in a pop-up the card opens.
 *
 * A <dialog>, opened with showModal() by src/js/person-dialog.js, so the
 * browser gives it the dimmed backdrop, the focus trap, Escape to close and
 * focus back on the card afterwards. Closed, it is not displayed and takes no
 * room in the grid.
 *
 * Picture on the left, the words on the right (two thirds of the width); one
 * column on a phone, where the whole pop-up scrolls. The script paints it in
 * the card's own background and text colour.
 *
 * @var array  $card           The card record, with `person` set by
 *                             inc/team-fields.php.
 * @var array  $card_person    `profile` (the Profile field) and `linkedin`.
 * @var string $card_dialog_id The dialog's id; the card's button names it.
 *
 * @package Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$person_profile = (string) ( $card_person['profile'] ?? '' );

if ( '' !== $person_profile ) {
	$person_profile = wp_kses_post( has_blocks( $person_profile ) ? do_blocks( $person_profile ) : wpautop( $person_profile ) );
}

$person_linkedin = esc_url( (string) ( $card_person['linkedin'] ?? '' ) );
$person_name_id  = $card_dialog_id . '-name';
?>
<dialog class="bridge-person" id="<?php echo esc_attr( $card_dialog_id ); ?>" aria-labelledby="<?php echo esc_attr( $person_name_id ); ?>">
	<div class="bridge-person__panel">
		<button type="button" class="bridge-person__close" data-bridge-person-close aria-label="<?php esc_attr_e( 'Close', 'bridge' ); ?>">
			<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
		</button>

		<div class="bridge-person__media">
			<?php
			if ( ! empty( $card['thumbnail_id'] ) ) {
				echo wp_get_attachment_image(
					(int) $card['thumbnail_id'],
					'large',
					false,
					array(
						'class'   => 'bridge-person__img',
						'sizes'   => '(min-width: 48rem) 22rem, 100vw',
						'loading' => 'lazy',
						'alt'     => '',
					)
				);
			}
			?>
		</div>

		<div class="bridge-person__body">
			<?php if ( '' !== ( $card['location'] ?? '' ) ) : ?>
				<p class="bridge-person__location"><?php echo esc_html( $card['location'] ); ?></p>
			<?php endif; ?>
			<h2 class="bridge-person__name" id="<?php echo esc_attr( $person_name_id ); ?>"><?php echo esc_html( $card['title'] ); ?></h2>
			<?php if ( '' !== $card['subtitle'] ) : ?>
				<p class="bridge-person__position"><?php echo esc_html( $card['subtitle'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $person_profile ) : ?>
				<div class="bridge-person__profile"><?php echo $person_profile; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — wp_kses_post above. ?></div>
			<?php endif; ?>
			<?php if ( '' !== $person_linkedin ) : ?>
				<p class="bridge-person__links">
					<a class="bridge-person__linkedin" href="<?php echo $person_linkedin; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — esc_url above. ?>" target="_blank" rel="noopener">
						<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9.75h4v11H3zM9.5 9.75h3.8v1.5h.05c.53-1 1.83-2.05 3.77-2.05 4.03 0 4.78 2.65 4.78 6.1v5.45h-4v-4.83c0-1.15-.02-2.63-1.6-2.63-1.6 0-1.85 1.25-1.85 2.55v4.91h-4z"/></svg>
						<span><?php esc_html_e( 'View on LinkedIn', 'bridge' ); ?></span>
						<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'bridge' ); ?></span>
					</a>
				</p>
			<?php endif; ?>
		</div>
	</div>
</dialog>
