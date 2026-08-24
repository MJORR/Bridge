<?php

/**
 * Bridge access control — who may touch the design system.
 *
 * The options page is agency infrastructure, not a client feature. Access is
 * granted to named people in code rather than by role, so a client who
 * promotes themselves to Administrator still cannot open it.
 *
 * Be honest about what this is: guardrails, not a security boundary. An
 * Administrator can install a plugin or edit users and escalate their way
 * around any of it. The job here is to stop the ordinary case — a client
 * clicking around, an editor exploring — which is what actually degrades a
 * brand over six months.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The capability that gates the theme options page and its REST route.
 *
 * Not held by any role. Granted only by the filter below, so the default for
 * every user — including Administrators — is denial.
 */
define('BRIDGE_OPERATOR_CAP', 'bridge_manage_theme_options');

/**
 * Agency operators, as user logins or email addresses.
 *
 * Prefer defining BRIDGE_OPERATORS in wp-config.php — either an array or a
 * comma-separated string — so staff changes never require a theme deploy.
 * This array is the fallback when the site has not defined it.
 *
 * @return string[] Lowercased identifiers.
 */
function bridge_operator_identifiers(): array
{
	$identifiers = array(
		'magnusjackorr@gmail.com',
	);

	if (defined('BRIDGE_OPERATORS')) {
		$configured = constant('BRIDGE_OPERATORS');

		if (is_string($configured)) {
			$configured = explode(',', $configured);
		}

		if (is_array($configured)) {
			$identifiers = $configured;
		}
	}

	$identifiers = array_map(
		static function ($value): string {
			return strtolower(trim((string) $value));
		},
		$identifiers
	);

	return array_values(array_filter($identifiers));
}

/**
 * Is this user named on the operator list?
 *
 * Deliberately free of any capability lookup — it runs inside the
 * `user_has_cap` filter, and calling current_user_can() or user_can() there
 * would recurse until the stack blows.
 *
 * @param WP_User $user User to test.
 */
function bridge_is_listed_operator(WP_User $user): bool
{
	$identifiers = bridge_operator_identifiers();

	if (! $identifiers) {
		return false;
	}

	$login = strtolower((string) $user->user_login);
	$email = strtolower((string) $user->user_email);

	return in_array($login, $identifiers, true) || in_array($email, $identifiers, true);
}

/**
 * Is the design system locked down on this site?
 *
 * Two escape hatches, both deliberate:
 *
 *   - `define( 'BRIDGE_LOCKDOWN', false )` turns it off during a build or a
 *     migration, without editing theme code.
 *   - An empty operator list fails *open*. A misconfigured allowlist should
 *     not be able to brick a site's design tools for everyone, including the
 *     person trying to fix it. bridge_operator_warning() makes the state
 *     visible rather than silent.
 */
function bridge_lockdown_enabled(): bool
{
	if (defined('BRIDGE_LOCKDOWN') && ! constant('BRIDGE_LOCKDOWN')) {
		return false;
	}

	if (! bridge_operator_identifiers()) {
		return false;
	}

	return (bool) apply_filters('bridge_lockdown_enabled', true);
}

/**
 * Capabilities withheld from everyone who is not an operator.
 *
 * Currently empty, deliberately.
 *
 * `edit_theme_options` is the capability that matters — it opens the Site
 * Editor, the Styles panel, the Customizer and Additional CSS. It also gates
 * navigation, and WordPress ships no standalone menu screen for block themes,
 * so withholding it means clients cannot edit their own menus.
 *
 * That trade was made the other way: clients get the capability so they can
 * edit navigation with WordPress's own UI. See bridge_grant_menu_access().
 *
 * @return string[]
 */
function bridge_locked_capabilities(): array
{
	return (array) apply_filters('bridge_locked_capabilities', array());
}

/**
 * Should clients reach the Site Editor at all?
 *
 * They need `edit_theme_options` to edit navigation, and that one capability
 * also opens Styles, Templates and Patterns. What it does *not* open is the
 * ability to change the design: global-styles writes are refused for everyone
 * in inc/lockdown.php, so the Styles panel previews and then fails to save.
 *
 * Templates and template parts, however, remain writable — that gap is known
 * and deliberate for now. Closing it is a `rest_pre_dispatch` filter on
 * /wp/v2/templates and /wp/v2/template-parts, plus redirects away from
 * customize.php and themes.php.
 *
 * Setting this false restores the sealed arrangement, at the cost of clients
 * no longer being able to edit menus.
 */
function bridge_grant_menu_access(): bool
{
	return (bool) apply_filters('bridge_grant_menu_access', true);
}

/**
 * Grant the operator capability, and withhold the design capabilities.
 *
 * Operator status requires *both* a listing on the allowlist and
 * Administrator-level trust. Either alone is the wrong test: an Administrator
 * who is not listed is exactly who this exists to stop, and a stray allowlist
 * entry on a Subscriber account should not hand out the design system.
 *
 * `manage_options` is read straight from the array being assembled rather
 * than via a capability call, which would recurse through this same filter.
 *
 * @param array<string, bool> $allcaps All capabilities for the user.
 * @param string[]            $caps    Required primitive capabilities.
 * @param array<int, mixed>   $args    Context: [ meta cap, user id, ... ].
 * @param WP_User             $user    The user being tested.
 * @return array<string, bool>
 */
function bridge_filter_user_caps($allcaps, $caps, $args, $user): array
{
	$allcaps = (array) $allcaps;

	if (! $user instanceof WP_User || ! $user->exists()) {
		return $allcaps;
	}

	if (! empty($allcaps['manage_options']) && bridge_is_listed_operator($user)) {
		$allcaps[BRIDGE_OPERATOR_CAP] = true;

		return $allcaps;
	}

	$allcaps[BRIDGE_OPERATOR_CAP] = false;

	if (! bridge_lockdown_enabled()) {
		return $allcaps;
	}

	// Editors do not hold `edit_theme_options` by default, so reaching the
	// Site Editor needs an explicit grant rather than merely not withholding
	// it. Gated on `edit_pages` so it stops at the content-editing tier —
	// a Subscriber or Contributor has no business in there.
	if (! empty($allcaps['edit_pages']) && bridge_grant_menu_access()) {
		$allcaps['edit_theme_options'] = true;
	}

	foreach (bridge_locked_capabilities() as $cap) {
		$allcaps[$cap] = false;
	}

	return $allcaps;
}
add_filter('user_has_cap', 'bridge_filter_user_caps', 10, 4);

/**
 * Does the current user administer the design system?
 *
 * Safe anywhere except inside the `user_has_cap` filter itself.
 */
function bridge_current_user_is_operator(): bool
{
	return current_user_can(BRIDGE_OPERATOR_CAP);
}

/**
 * Warn administrators when the allowlist is empty and lockdown is therefore off.
 *
 * The fail-open behaviour is correct, but it must never be quiet — a site
 * silently running unprotected is worse than one that is obviously misconfigured.
 */
function bridge_operator_warning(): void
{
	if (bridge_operator_identifiers() || ! current_user_can('manage_options')) {
		return;
	}

	if (defined('BRIDGE_LOCKDOWN') && ! constant('BRIDGE_LOCKDOWN')) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
		esc_html__('Bridge design lockdown is off.', 'bridge'),
		esc_html__('No operators are configured, so the design system is unprotected. Define BRIDGE_OPERATORS in wp-config.php with the logins or email addresses that should administer it.', 'bridge')
	);
}
add_action('admin_notices', 'bridge_operator_warning');
