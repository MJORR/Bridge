<?php

/**
 * Bridge theme options — REST endpoints.
 *
 * A dedicated namespace rather than `register_setting( show_in_rest )`, and
 * that choice is load-bearing: WP_REST_Settings_Controller authorises on
 * `manage_options`, which every Administrator holds. Exposing the token
 * record through /wp/v2/settings would let any Administrator PUT straight
 * past the operator allowlist — the one thing phase 2 exists to prevent.
 *
 *   GET  /bridge/v1/tokens   Resolved tokens, constraints and font sets.
 *   POST /bridge/v1/tokens   Merge a partial fragment and save.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

define('BRIDGE_REST_NAMESPACE', 'bridge/v1');

/**
 * Only operators may read or write the design system.
 *
 * @return true|WP_Error
 */
function bridge_rest_permission_check()
{
	if (current_user_can(BRIDGE_OPERATOR_CAP)) {
		return true;
	}

	return new WP_Error(
		'bridge_forbidden',
		__('You are not permitted to manage this site\'s design system.', 'bridge'),
		array('status' => is_user_logged_in() ? 403 : 401)
	);
}

/**
 * Register the theme options routes.
 */
function bridge_register_rest_routes(): void
{
	register_rest_route(
		BRIDGE_REST_NAMESPACE,
		'/tokens',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'bridge_rest_get_tokens',
				'permission_callback' => 'bridge_rest_permission_check',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'bridge_rest_update_tokens',
				'permission_callback' => 'bridge_rest_permission_check',
				'args'                => array(
					'tokens' => array(
						'type'        => 'object',
						'required'    => true,
						'description' => __('A partial or complete token set to merge into the saved values.', 'bridge'),
					),
				),
			),
		)
	);

	register_rest_route(
		BRIDGE_REST_NAMESPACE,
		'/tokens/preview',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'bridge_rest_preview_tokens',
			'permission_callback' => 'bridge_rest_permission_check',
			'args'                => array(
				'tokens' => array(
					'type'        => 'object',
					'required'    => true,
					'description' => __('A candidate token set to compile without saving.', 'bridge'),
				),
			),
		)
	);
}
add_action('rest_api_init', 'bridge_register_rest_routes');

/**
 * Compile a candidate token set without saving it.
 *
 * The options page draws its live preview from this rather than
 * reimplementing the modular scale in JavaScript. Two implementations of the
 * same typographic maths drift, and the version the operator is looking at
 * would be the wrong one. The server stays the single source of truth, and
 * the round trip buys accurate clamping feedback for free — an out-of-range
 * value visibly snaps back as it is typed.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function bridge_rest_preview_tokens(WP_REST_Request $request): WP_REST_Response
{
	$candidate = $request->get_param('tokens');

	// Sanitised as given rather than merged over what is stored: the page
	// always submits a complete draft, and merging would mask a field the UI
	// intended to clear.
	$tokens   = bridge_sanitize_tokens(is_array($candidate) ? $candidate : array());
	$compiled = bridge_compile_theme_json($tokens);

	return rest_ensure_response(
		array(
			'tokens'       => $tokens,
			'palette'      => $compiled['settings']['color']['palette'],
			'fontSizes'    => $compiled['settings']['typography']['fontSizes'],
			'fontFamilies' => $compiled['settings']['typography']['fontFamilies'],
			'layout'       => $compiled['settings']['layout'],
			// The spacing presets and the custom properties, so the page can
			// draw a card at the padding, radius and shadow being chosen
			// rather than describing them. wp-admin prints no global styles of
			// its own, so a preview that named `var(--wp--preset--spacing--40)`
			// would resolve to nothing on this screen.
			'spacingSizes' => $compiled['settings']['spacing']['spacingSizes'],
			'custom'       => $compiled['settings']['custom'],
			'styles'       => $compiled['styles'],
			// Every button colour with the contrast ratio behind it. Computed
			// here rather than in the page for the reason the type scale is:
			// a second implementation of the WCAG formula would be the one the
			// operator is looking at, and it would be the one that drifted.
			'buttons'      => bridge_button_schemes($tokens),
		)
	);
}

/**
 * The full payload the options page needs to render itself.
 *
 * Constraints and font sets travel with the values so the UI never has to
 * hardcode a range the server might disagree with.
 *
 * @return array<string, mixed>
 */
function bridge_rest_tokens_payload(): array
{
	$font_sets = array();

	foreach (bridge_font_sets() as $slug => $set) {
		$font_sets[] = array(
			'slug'        => $slug,
			'name'        => (string) ($set['name'] ?? $slug),
			'description' => (string) ($set['description'] ?? ''),
			'families'    => $set['families'],
		);
	}

	$installed = array();

	foreach (bridge_installed_fonts() as $entry) {
		if (! empty($entry['family'])) {
			$installed[] = array(
				'family' => $entry['family'],
				'faces'  => count($entry['faces'] ?? array()),
			);
		}
	}

	return array(
		'tokens'         => bridge_get_tokens(),
		'constraints'    => bridge_token_constraints(),
		'paletteSlugs'   => bridge_palette_slugs(),
		'spacingSlugs'   => bridge_spacing_slugs(),
		// Slug => display name. Sent as well as the slugs because the order of
		// the scale is meaning here — a control offers XS through 3XL, and the
		// slug behind each is an implementation detail an operator never types.
		'spacingSteps'   => bridge_spacing_steps(),
		'fontSets'       => $font_sets,
		'googleFonts'    => bridge_google_catalogue(),
		'installedFonts' => $installed,
		'fontErrors'     => bridge_font_errors(),
		// The icon geometry travels with the payload so the options page can
		// draw a real comparison of the weights rather than describing them.
		'icons'          => bridge_icon_library(),
		'iconWeights'    => bridge_icon_weights(),
		'templates'      => bridge_template_inventory(),
		// The site's menus, so the header can be pointed at one by name rather
		// than by an id the operator would have to go and look up.
		'menus'          => bridge_navigation_menus(),
		// Every insertable block, grouped as the inserter groups them, with
		// what the theme ships on by default so the screen can say what a
		// switch is changing rather than only what it is set to.
		'blockLibrary'   => bridge_block_library(),
		'sectionSkins'   => bridge_section_skins(),
		// The three button designs, each with the geometry behind it, so the
		// options page can draw a real button in each skin rather than
		// describing three of them in prose.
		'buttonSkins'    => bridge_button_skin_choices(),
		// The grounds a button sits on, each naming the palette slugs its band
		// wears, so every scheme can be shown on its real background.
		'buttonGrounds'  => bridge_button_ground_choices(),
		// The named elevation steps, with the CSS behind each, so the card
		// control can show the shadows rather than list four adjectives.
		'cardShadows'    => bridge_card_shadow_choices(),
		// The grounds a card sits on, each naming the palette slugs its band
		// wears, so the page can draw every card on its real background.
		'cardGrounds'    => bridge_card_ground_choices(),
		'version'        => bridge_tokens_version(),
	);
}

/**
 * GET handler.
 *
 * @return WP_REST_Response
 */
function bridge_rest_get_tokens(): WP_REST_Response
{
	return rest_ensure_response(bridge_rest_tokens_payload());
}

/**
 * POST handler — merge a fragment and save.
 *
 * Returns the resolved result rather than an acknowledgement, so a UI that
 * submitted an out-of-range value sees the clamped truth immediately instead
 * of drifting out of sync with the server.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function bridge_rest_update_tokens(WP_REST_Request $request): WP_REST_Response
{
	$fragment = $request->get_param('tokens');

	bridge_patch_tokens(is_array($fragment) ? $fragment : array());

	return rest_ensure_response(bridge_rest_tokens_payload());
}
