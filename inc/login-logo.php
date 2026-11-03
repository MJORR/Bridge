<?php

/**
 * Bridge — login screen logo.
 *
 * Swaps the WordPress mark on wp-login.php for the dark logo from Theme
 * Options (`header.logo.id`). The login form sits on a light ground, so the
 * dark variant is the one drawn for it. With no logo set, core's mark stays.
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * The dark logo's attachment ID, or 0 when none is set.
 *
 * @return int
 */
function bridge_login_logo_id(): int
{
	$tokens = bridge_get_tokens();

	return max(0, (int) ($tokens['header']['logo']['id'] ?? 0));
}

add_action('login_enqueue_scripts', function (): void {
	$id  = bridge_login_logo_id();
	$src = $id ? wp_get_attachment_image_url($id, 'full') : '';

	if (!$src) {
		return;
	}

	printf(
		'<style id="bridge-login-logo">
			#login h1 a, .login h1 a {
				background-image: url("%s");
				background-size: contain;
				background-position: center;
				background-repeat: no-repeat;
				width: 100%%;
				height: 80px;
			}
		</style>',
		esc_url($src)
	);
});

add_filter('login_headerurl', function (): string {
	return home_url('/');
});

add_filter('login_headertext', function (): string {
	return get_bloginfo('name');
});
