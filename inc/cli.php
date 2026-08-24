<?php

/**
 * Bridge design tokens — WP-CLI commands.
 *
 * The admin options page arrives in phase 3. Until then this is how tokens
 * are read and written, and it stays useful afterwards for deployment,
 * migration between environments, and standing up a new client build from a
 * committed token file.
 *
 *   wp bridge tokens                Print the resolved token set.
 *   wp bridge compile               Print the theme.json fragment it produces.
 *   wp bridge set '<json>'          Merge a partial token fragment and save.
 *   wp bridge export > tokens.json  Write the current set to a seed file.
 *   wp bridge reset                 Drop saved values, falling back to the seed.
 *   wp bridge flush                 Rebuild caches without changing anything.
 *   wp bridge fonts                 List the available font sets.
 *   wp bridge access                Show operators and the lockdown state.
 *   wp bridge conflicts             Show Global Styles overrides being discarded.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

if (! defined('WP_CLI') || ! WP_CLI) {
	return;
}

/**
 * Inspect and edit the Bridge design tokens.
 */
class Bridge_CLI_Command
{
	/**
	 * Print the resolved token set — seed and saved values, sanitised.
	 *
	 * ## OPTIONS
	 *
	 * [--stored]
	 * : Print only what is saved in the database, not the resolved result.
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function tokens(array $args, array $assoc_args): void
	{
		$data = isset($assoc_args['stored'])
			? get_option(BRIDGE_TOKENS_OPTION, array())
			: bridge_get_tokens();

		$this->output($data);
	}

	/**
	 * Print the theme.json fragment the current tokens compile into.
	 *
	 * The fastest way to see why a change did or did not land: if a value is
	 * correct here but wrong in the browser, the problem is caching or
	 * origin precedence, not compilation.
	 */
	public function compile(): void
	{
		$this->output(bridge_compile_theme_json(bridge_get_tokens()));
	}

	/**
	 * Merge a partial token fragment into the saved set.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bridge set '{"brand":{"palette":{"primary":{"color":"#B91C1C"}}}}'
	 *     wp bridge set '{"typography":{"scaleRatio":1.333,"headingCase":"uppercase"}}'
	 *     wp bridge set '{"layout":{"contentSize":840,"wideSize":1320}}'
	 *
	 * @param array<int, string> $args Positional arguments; the JSON fragment.
	 */
	public function set(array $args): void
	{
		if (empty($args[0])) {
			WP_CLI::error('Pass a JSON fragment, e.g. \'{"typography":{"scaleRatio":1.25}}\'');
		}

		$fragment = json_decode($args[0], true);

		if (! is_array($fragment)) {
			WP_CLI::error('Could not parse that as a JSON object: ' . json_last_error_msg());
		}

		$before = bridge_get_tokens();
		bridge_patch_tokens($fragment);
		$after = bridge_get_tokens();

		if ($before === $after) {
			WP_CLI::warning('Saved, but nothing changed — the values were already in effect, or were clamped back to what they were.');

			return;
		}

		foreach ($this->differences($before, $after) as $path => $change) {
			WP_CLI::log(sprintf('  %s: %s → %s', $path, $change[0], $change[1]));
		}

		WP_CLI::success('Tokens updated. Version ' . bridge_tokens_version());
	}

	/**
	 * Print the resolved tokens as a design/tokens.json seed file.
	 *
	 * Redirect it over design/tokens.json to promote a site's live design
	 * system into the committed per-client seed.
	 */
	public function export(): void
	{
		$tokens = bridge_get_tokens();
		$tokens = array_merge(array('version' => 1), $tokens);

		$this->output($tokens);
	}

	/**
	 * Drop saved token values, falling back to design/tokens.json.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function reset(array $args, array $assoc_args): void
	{
		WP_CLI::confirm('Discard all saved design tokens and fall back to the committed seed?', $assoc_args);

		delete_option(BRIDGE_TOKENS_OPTION);

		WP_CLI::success('Reset to the seed in design/tokens.json.');
	}

	/**
	 * Rebuild the token caches without changing any value.
	 */
	public function flush(): void
	{
		bridge_flush_tokens();

		WP_CLI::success('Caches cleared. Version ' . bridge_tokens_version());
	}

	/**
	 * List the curated font sets a site can choose between.
	 */
	public function fonts(): void
	{
		$current = bridge_get_tokens()['typography']['fontSet'];
		$rows    = array();

		foreach (bridge_font_sets() as $slug => $set) {
			$rows[] = array(
				'slug'        => $slug,
				'name'        => (string) ($set['name'] ?? $slug),
				'active'      => $slug === $current ? 'yes' : '',
				'description' => (string) ($set['description'] ?? ''),
			);
		}

		WP_CLI\Utils\format_items('table', $rows, array('slug', 'name', 'active', 'description'));

		$type = bridge_get_tokens()['typography'];

		WP_CLI::log('');
		WP_CLI::log('Google Fonts: ' . (! empty($type['googleFonts']) ? 'ON' : 'off'));

		if (! empty($type['googleFonts'])) {
			WP_CLI::log('  heading : ' . ($type['googleHeading'] ?: '(font set)'));
			WP_CLI::log('  body    : ' . ($type['googleBody'] ?: '(font set)'));
		}

		$installed = bridge_installed_fonts();

		if ($installed) {
			$paths = bridge_fonts_dir();
			$bytes = 0;

			foreach ($installed as $entry) {
				foreach ($entry['faces'] as $face) {
					$file = $paths ? $paths['dir'] . '/' . $face['file'] : '';
					$bytes += ($file && file_exists($file)) ? (int) filesize($file) : 0;
				}

				WP_CLI::log(sprintf('  self-hosted: %s (%d files)', $entry['family'], count($entry['faces'])));
			}

			WP_CLI::log(sprintf('  on disk    : %s', size_format($bytes)));
		}

		foreach (bridge_font_errors() as $family => $message) {
			WP_CLI::warning($family . ': ' . $message);
		}
	}

	/**
	 * Show who may administer the design system, and what is locked.
	 */
	public function access(): void
	{
		$operators = bridge_operator_identifiers();
		$locked    = bridge_lockdown_enabled();

		WP_CLI::log('Lockdown : ' . ($locked ? 'ON' : 'OFF'));
		WP_CLI::log('Operators: ' . ($operators ? implode(', ', $operators) : '(none configured)'));
		WP_CLI::log('Withheld : ' . implode(', ', bridge_locked_capabilities()));
		WP_CLI::log('');

		$rows = array();

		foreach (get_users(array('fields' => array('ID', 'user_login', 'user_email'))) as $user) {
			$wp_user = new WP_User($user->ID);

			$rows[] = array(
				'login'     => $user->user_login,
				'email'     => $user->user_email,
				'roles'     => implode(',', $wp_user->roles),
				'operator'  => user_can($wp_user, BRIDGE_OPERATOR_CAP) ? 'YES' : '',
				'site edit' => user_can($wp_user, 'edit_theme_options') ? 'yes' : 'blocked',
			);
		}

		WP_CLI\Utils\format_items('table', $rows, array('login', 'email', 'roles', 'operator', 'site edit'));

		if (! $locked) {
			WP_CLI::warning('Lockdown is off — the design system is unprotected.');
		}
	}

	/**
	 * Show what the user-origin Global Styles record overrides.
	 *
	 * User-origin data outranks the theme origin, so anything listed here was
	 * silently beating the options page before lockdown discarded it. Move
	 * values worth keeping into design/tokens.json.
	 */
	public function conflicts(): void
	{
		$overrides = bridge_user_global_styles_overrides();

		if (! $overrides) {
			WP_CLI::success('No user-origin Global Styles overrides. The options page is authoritative.');

			return;
		}

		WP_CLI::log(sprintf('Global styles record #%d carries overrides:', $overrides['post_id']));
		unset($overrides['post_id']);
		$this->output($overrides);

		if (bridge_lockdown_enabled()) {
			WP_CLI::success('Lockdown is ON, so these are discarded at render time and the options page wins.');
		} else {
			WP_CLI::warning('Lockdown is OFF, so these still override the options page.');
		}
	}

	/**
	 * Flatten two token sets and report the leaves that differ.
	 *
	 * @param array<string, mixed> $before Previous tokens.
	 * @param array<string, mixed> $after  Current tokens.
	 * @return array<string, array{0: string, 1: string}> Dot path => [old, new].
	 */
	private function differences(array $before, array $after): array
	{
		$flatten = static function (array $data, string $prefix = '') use (&$flatten): array {
			$flat = array();

			foreach ($data as $key => $value) {
				$path = '' === $prefix ? (string) $key : $prefix . '.' . $key;

				if (is_array($value)) {
					$flat += $flatten($value, $path);
					continue;
				}

				$flat[$path] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
			}

			return $flat;
		};

		$flat_before = $flatten($before);
		$flat_after  = $flatten($after);
		$changes     = array();

		foreach ($flat_after as $path => $value) {
			$old = $flat_before[$path] ?? '(unset)';

			if ($old !== $value) {
				$changes[$path] = array($old, $value);
			}
		}

		return $changes;
	}

	/**
	 * Print a structure as readable JSON.
	 *
	 * @param mixed $data Anything json-encodable.
	 */
	private function output($data): void
	{
		WP_CLI::line((string) wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}
}

WP_CLI::add_command('bridge', 'Bridge_CLI_Command');
