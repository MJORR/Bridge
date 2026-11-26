<?php
/**
 * Server-side render for `bridge/tab-feature` — a small two-line feature in a
 * tab: a title and a line of detail. Drawn in the Tab Block's Panel style.
 *
 * @package Bridge
 *
 * @var array $attributes Block attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title = trim( (string) ( $attributes['title'] ?? '' ) );
$text  = trim( (string) ( $attributes['text'] ?? '' ) );

if ( '' === $title && '' === $text ) {
	return;
}
?>
<div class="bridge-tab-feature">
	<?php if ( '' !== $title ) : ?>
		<p class="bridge-tab-feature__title"><?php echo wp_kses_post( $title ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $text ) : ?>
		<p class="bridge-tab-feature__text"><?php echo wp_kses_post( $text ); ?></p>
	<?php endif; ?>
</div>
