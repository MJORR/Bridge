<?php

/**
 * Bridge theme options — the admin screen.
 *
 * A top-level menu rather than a child of Appearance, deliberately: it sits
 * outside the client's mental map of "things I can change", and Appearance
 * itself is hidden from non-operators anyway once `edit_theme_options` is
 * withheld.
 *
 * Phase 2 registers the screen, its capability gate and a read-only view of
 * the live design system. The editing UI mounts into #bridge-options-root in
 * phase 3; until then this screen is still worth opening, because it is where
 * a stranded Global Styles override becomes visible.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

define('BRIDGE_OPTIONS_SLUG', 'bridge-theme-options');

/**
 * Register the options screen.
 */
function bridge_register_options_page(): void
{
	$GLOBALS['bridge_options_hook'] = add_menu_page(
		__('Theme Options', 'bridge'),
		BRIDGE_BRAND,
		BRIDGE_OPERATOR_CAP,
		BRIDGE_OPTIONS_SLUG,
		'bridge_render_options_page',
		'dashicons-admin-customizer',
		// Just above Appearance (60), so it reads as the design entry point
		// that replaces it. Fractional to avoid colliding with a plugin.
		59.7
	);
}
add_action('admin_menu', 'bridge_register_options_page');

/**
 * Load the options app, on this screen only.
 *
 * `wp-components` brings the stylesheet the controls need; without it the
 * screen renders as unstyled markup, which is the usual cause of a
 * "broken-looking" settings page.
 *
 * @param string $hook_suffix Current admin screen.
 */
function bridge_enqueue_options_app(string $hook_suffix): void
{
	if (($GLOBALS['bridge_options_hook'] ?? null) !== $hook_suffix) {
		return;
	}

	if (! bridge_register_script('bridge-options-app', 'options-app.js', array('wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n'))) {
		return;
	}

	wp_enqueue_script('bridge-options-app');
	wp_enqueue_style('wp-components');

	// The logo pickers open the media modal, which needs its own scripts and
	// templates printed on the page.
	wp_enqueue_media();

	if (bridge_register_style('bridge-options-app', 'options-app.css', array('wp-components'))) {
		wp_enqueue_style('bridge-options-app');
	}

	wp_set_script_translations('bridge-options-app', 'bridge');
}
add_action('admin_enqueue_scripts', 'bridge_enqueue_options_app');

/**
 * Render the options screen.
 */
function bridge_render_options_page(): void
{
	// add_menu_page() already gates the screen, but a render callback is a
	// public function — never rely on the caller for authorisation.
	if (! current_user_can(BRIDGE_OPERATOR_CAP)) {
		wp_die(esc_html__('You are not permitted to manage this site\'s design system.', 'bridge'));
	}

	$tokens    = bridge_get_tokens();
	$overrides = bridge_user_global_styles_overrides();
	?>
	<div class="wrap">
		<h1 class="bridge-options__title"><?php echo esc_html(BRIDGE_BRAND . ' ' . __('Theme Options', 'bridge')); ?></h1>

		<?php if ($overrides) : ?>
			<div class="notice notice-warning">
				<p>
					<strong><?php esc_html_e('Stranded Global Styles found.', 'bridge'); ?></strong>
					<?php
					printf(
						/* translators: %d: post ID of the wp_global_styles record. */
						esc_html__('Someone saved in the Site Editor, leaving overrides in global styles record #%d. Those values are now ignored, because the design system below is authoritative. Run "wp bridge conflicts" to see exactly what they were.', 'bridge'),
						(int) $overrides['post_id']
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<div id="bridge-options-root"></div>

		<noscript>
		<h2><?php esc_html_e('Brand palette', 'bridge'); ?></h2>
		<table class="widefat striped" style="max-width:40rem">
			<thead>
				<tr>
					<th><?php esc_html_e('Slot', 'bridge'); ?></th>
					<th><?php esc_html_e('Name', 'bridge'); ?></th>
					<th><?php esc_html_e('Colour', 'bridge'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($tokens['brand']['palette'] as $slug => $entry) : ?>
					<tr>
						<td><code><?php echo esc_html($slug); ?></code></td>
						<td><?php echo esc_html($entry['name']); ?></td>
						<td>
							<span style="display:inline-block;width:1.1em;height:1.1em;vertical-align:-0.2em;margin-right:.5em;border:1px solid rgba(0,0,0,.2);background:<?php echo esc_attr($entry['color']); ?>"></span>
							<code><?php echo esc_html($entry['color']); ?></code>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e('Typography and layout', 'bridge'); ?></h2>
		<table class="widefat striped" style="max-width:40rem">
			<tbody>
				<?php
				$rows = array(
					__('Font set', 'bridge')          => $tokens['typography']['fontSet'],
					__('Base size', 'bridge')         => $tokens['typography']['baseSize'] . 'rem',
					__('Scale ratio', 'bridge')       => $tokens['typography']['scaleRatio'],
					__('Heading weight', 'bridge')    => $tokens['typography']['headingWeight'],
					__('Heading case', 'bridge')      => $tokens['typography']['headingCase'],
					__('Body line height', 'bridge')  => $tokens['typography']['bodyLineHeight'],
					__('Content width', 'bridge')     => $tokens['layout']['contentSize'] . 'px',
					__('Wide width', 'bridge')        => $tokens['layout']['wideSize'] . 'px',
					__('Spacing base', 'bridge')      => $tokens['layout']['spacingBase'] . 'rem',
					__('Spacing increment', 'bridge') => $tokens['layout']['spacingIncrement'],
				);

				foreach ($rows as $label => $value) :
					?>
					<tr>
						<td style="width:14rem"><?php echo esc_html($label); ?></td>
						<td><code><?php echo esc_html((string) $value); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="description" style="margin-top:1.5rem">
			<?php
			printf(
				/* translators: %s: WP-CLI command name. */
				esc_html__('The editing controls need JavaScript. Without it, these values can still be set in design/tokens.json or with %s.', 'bridge'),
				'<code>wp bridge set</code>'
			);
			?>
		</p>
		</noscript>
	</div>
	<?php
}
