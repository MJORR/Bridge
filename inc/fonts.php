<?php

/**
 * Bridge — Google Fonts, downloaded and self-hosted.
 *
 * The toggle offers Google's library as a *source*, never as a runtime
 * dependency. Selecting a family downloads its woff2 files into the uploads
 * directory once; from then on the site serves them itself and never contacts
 * Google again.
 *
 * That distinction is the whole point. Embedding fonts.googleapis.com puts a
 * third-party request on the critical path of every page load, hands a
 * visitor's IP address to Google on each one — which German courts have held
 * to be a GDPR violation without consent — and makes the site's typography
 * depend on a host nobody here controls. Self-hosting keeps the convenience
 * and drops all three problems.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Manifest of installed families: slug => array{family: string, faces: array}.
 */
define('BRIDGE_FONTS_OPTION', 'bridge_google_fonts');

/**
 * Weights fetched for each family.
 *
 * Every weight the typography panel can ask for, which is the only number this
 * list has any business being. It used to be `400;500;600;700` — body copy,
 * bold, and the two steps between them — while the heading-weight control in
 * inc/tokens.php offered 300 through 900. Three of its seven options therefore
 * had no face behind them: CSS font matching walks to the nearest weight it
 * actually has, so 800 and 900 both came out as 700 and 300 came out as 400.
 * Three settings, one appearance, and nothing anywhere saying why.
 *
 * The two lists have to agree, and they agree by being checked against each
 * other rather than by being one list: the panel's options are strings in a
 * constraints array and this is a Google API query parameter, and forcing them
 * through one definition would couple the options screen to the font
 * downloader for no gain. If a weight is added there, add it here.
 *
 * Seven weights rather than four costs disk and install time, not page weight.
 * Each `@font-face` is a declaration, and a browser fetches the file behind one
 * only when something on the page matches it — so a site set to 600 headings
 * downloads the same two faces it always did, and the other five sit unused in
 * uploads until somebody changes the setting.
 *
 * Families that lack a weight simply return fewer faces — the progressive
 * fallback in bridge_fetch_google_css() handles static families with narrow
 * ranges.
 */
define('BRIDGE_FONT_WEIGHTS', '300;400;500;600;700;800;900');

/**
 * Unicode subsets kept from Google's response.
 *
 * Google splits each weight into a dozen subsets by unicode-range. Keeping
 * only latin and latin-ext turns roughly 14 files per family into 2 per
 * weight, with no visible difference for the languages these builds target.
 */
define('BRIDGE_FONT_SUBSETS', 'latin,latin-ext');

/**
 * The face the footer's strapline is set in.
 *
 * Fixed rather than picked, and the only family in the theme that is. The two
 * pickers choose what the site *sounds* like — a heading voice and a reading
 * voice — and a strapline written in a hand is neither: it is a signature, and
 * the whole reason it is set in a script is that it should not look like the
 * rest of the page. Offering a third picker would invite a site to set its
 * strapline in the same grotesque as its body copy, which is a strapline that
 * may as well be a paragraph.
 *
 * Installed only when a strapline is actually written — see
 * bridge_sync_google_fonts() — so a site that never fills the field never
 * downloads it.
 */
define('BRIDGE_SCRIPT_FAMILY', 'Caveat');

/**
 * The roles a Google family can be chosen for.
 *
 * These map one-to-one onto the two pickers in the options page. Splitting by
 * job rather than by classification is the useful cut: the question being
 * asked is "what should headlines look like", not "which of these is a
 * grotesque", and a face can legitimately appear in both lists.
 *
 * @return string[]
 */
function bridge_google_roles(): array
{
	return array('heading', 'body');
}

/**
 * The curated catalogue, keyed by role.
 *
 * Each role is a flat list. WordPress's SelectControl has no optgroup support
 * — it maps options straight to <option> elements — so a nested structure
 * renders as its group labels and drops every family inside.
 *
 * @return array<string, array<int, array<string, string>>>
 */
function bridge_google_catalogue(): array
{
	static $catalogue = null;

	if (null !== $catalogue) {
		return $catalogue;
	}

	$catalogue = array();
	$path      = get_theme_file_path('design/google-fonts.json');

	if (file_exists($path)) {
		$data = wp_json_file_decode($path, array('associative' => true));

		if (is_array($data)) {
			foreach (bridge_google_roles() as $role) {
				if (! empty($data[$role]) && is_array($data[$role])) {
					$catalogue[$role] = array_values($data[$role]);
				}
			}
		}
	}

	return $catalogue;
}

/**
 * Families offered for one role.
 *
 * @param string $role `heading` or `body`.
 * @return string[]
 */
function bridge_google_families_for(string $role): array
{
	$families = array();

	foreach (bridge_google_catalogue()[$role] ?? array() as $entry) {
		if (! empty($entry['family'])) {
			$families[] = (string) $entry['family'];
		}
	}

	return $families;
}

/**
 * Every family name in the catalogue, deduplicated across roles.
 *
 * @return string[]
 */
function bridge_google_families(): array
{
	// The strapline face is in the list although it is in no role and no
	// picker. This function is the install allowlist as well as the catalogue
	// — bridge_is_known_google_family() is what stands between a family name
	// and an outbound URL built from it — so a face the theme installs has to
	// be named here whether or not anybody can choose it.
	$families = array(BRIDGE_SCRIPT_FAMILY);

	foreach (bridge_google_catalogue() as $entries) {
		foreach ($entries as $entry) {
			if (! empty($entry['family'])) {
				$families[] = (string) $entry['family'];
			}
		}
	}

	return array_values(array_unique($families));
}

/**
 * Is this a family the theme is willing to install?
 *
 * The allowlist matters: the family name is interpolated into an outbound
 * URL, and the slug derived from it becomes a directory name.
 */
function bridge_is_known_google_family(string $family): bool
{
	return in_array($family, bridge_google_families(), true);
}

/**
 * Directory-safe slug for a family name.
 */
function bridge_font_slug(string $family): string
{
	return sanitize_key(str_replace(' ', '-', $family));
}

/**
 * Where downloaded fonts live.
 *
 * @return array{dir: string, url: string}|null Null when uploads are unusable.
 */
function bridge_fonts_dir(): ?array
{
	$uploads = wp_get_upload_dir();

	if (! empty($uploads['error'])) {
		return null;
	}

	return array(
		'dir' => $uploads['basedir'] . '/bridge-fonts',
		'url' => $uploads['baseurl'] . '/bridge-fonts',
	);
}

/**
 * The installed-font manifest.
 *
 * @return array<string, array<string, mixed>>
 */
function bridge_installed_fonts(): array
{
	$manifest = get_option(BRIDGE_FONTS_OPTION, array());

	return is_array($manifest) ? $manifest : array();
}

/**
 * Ask Google for a family's CSS, narrowing the weight request on failure.
 *
 * Variable families accept the full weight list; older static ones return 400
 * for weights they do not publish. Rather than maintain per-family weight
 * data that would drift as Google updates the library, try progressively
 * simpler requests and take the first that answers.
 *
 * ---- Why there are four rungs and not three -------------------------------
 *
 * The middle one is the reason. When the full list was `400;500;600;700` the
 * next step down was `400;700`, and the drop cost a static family two weights
 * it may well have published. Widening the top of the list to seven made that
 * drop much worse: a family with no 800 would have gone from asking for seven
 * weights to asking for two in a single step, losing 500 and 600 — which it
 * almost certainly has — because of a weight at the other end of the range it
 * does not. Every such family in the catalogue would have come out of this
 * change with *fewer* faces than before it.
 *
 * So the four weights this list used to be are now a rung of their own. A
 * variable family answers the first request and never reaches it; a static
 * family with a normal range answers here and is no worse off than it was; and
 * `400;700` stays as the floor for the genuinely narrow ones.
 *
 * @param string $family Family name.
 * @return string CSS, or an empty string on failure.
 */
function bridge_fetch_google_css(string $family): string
{
	$attempts = array(
		':wght@' . BRIDGE_FONT_WEIGHTS,
		':wght@400;500;600;700',
		':wght@400;700',
		'',
	);

	foreach ($attempts as $weights) {
		$url = add_query_arg(
			array(
				'family'  => rawurlencode($family) . $weights,
				'display' => 'swap',
			),
			'https://fonts.googleapis.com/css2'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				// Google serves woff2 only to user agents it believes support
				// it; WordPress's default UA gets legacy truetype instead.
				'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			)
		);

		if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
			continue;
		}

		$css = wp_remote_retrieve_body($response);

		if ('' !== trim($css)) {
			return $css;
		}
	}

	return '';
}

/**
 * Pull the wanted faces out of a Google CSS response.
 *
 * Each block is preceded by a `/* subset *\/` comment, which is the only
 * indication of which unicode range it covers — the unicode-range property
 * itself is unwieldy to match against.
 *
 * @param string $css Stylesheet from the CSS2 API.
 * @return array<int, array{weight: int, url: string, subset: string}>
 */
function bridge_parse_google_css(string $css): array
{
	$wanted = explode(',', BRIDGE_FONT_SUBSETS);
	$faces  = array();

	// Split on the subset comments, keeping the label with its block.
	$chunks = preg_split('#/\*\s*([a-z0-9-]+)\s*\*/#i', $css, -1, PREG_SPLIT_DELIM_CAPTURE);

	if (! is_array($chunks)) {
		return $faces;
	}

	for ($i = 1; $i < count($chunks); $i += 2) {
		$subset = strtolower(trim($chunks[$i]));
		$block  = $chunks[$i + 1] ?? '';

		if (! in_array($subset, $wanted, true)) {
			continue;
		}

		if (
			preg_match('/font-weight:\s*(\d+)/', $block, $weight)
			&& preg_match('/src:\s*url\((https:[^)]+\.woff2)\)/', $block, $src)
		) {
			// The unicode-range is what makes subsetting work: without it the
			// browser cannot tell the two files apart and only ever fetches
			// the first, so extended characters fall back to a system face.
			preg_match('/unicode-range:\s*([^;}]+)/', $block, $range);

			$faces[] = array(
				'weight' => (int) $weight[1],
				'url'    => $src[1],
				'subset' => $subset,
				'range'  => isset($range[1]) ? trim($range[1]) : '',
			);
		}
	}

	return $faces;
}

/**
 * Download and self-host one family.
 *
 * @param string $family Family name; must be in the catalogue.
 * @return array{family: string, faces: array}|WP_Error
 */
function bridge_install_google_font(string $family)
{
	if (! bridge_is_known_google_family($family)) {
		return new WP_Error('bridge_unknown_font', sprintf('“%s” is not in the curated catalogue.', $family));
	}

	$paths = bridge_fonts_dir();

	if (! $paths) {
		return new WP_Error('bridge_no_uploads', 'The uploads directory is not writable.');
	}

	$css = bridge_fetch_google_css($family);

	if ('' === $css) {
		return new WP_Error('bridge_font_fetch_failed', sprintf('Could not reach Google Fonts for “%s”.', $family));
	}

	$faces = bridge_parse_google_css($css);

	if (! $faces) {
		return new WP_Error('bridge_font_parse_failed', sprintf('No usable woff2 faces found for “%s”.', $family));
	}

	$slug = bridge_font_slug($family);
	$dir  = $paths['dir'] . '/' . $slug;

	if (! wp_mkdir_p($dir)) {
		return new WP_Error('bridge_font_mkdir_failed', sprintf('Could not create %s.', $dir));
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';

	$installed = array();

	foreach ($faces as $face) {
		$filename = sprintf('%d-%s.woff2', $face['weight'], $face['subset']);
		$target   = $dir . '/' . $filename;

		if (! file_exists($target)) {
			$tmp = download_url($face['url'], 30);

			if (is_wp_error($tmp)) {
				continue;
			}

			// copy + unlink rather than rename: the temp file may sit on a
			// different filesystem from uploads on some hosts.
			if (! @copy($tmp, $target)) {
				@unlink($tmp);
				continue;
			}

			@unlink($tmp);
		}

		$installed[] = array(
			'weight' => $face['weight'],
			'subset' => $face['subset'],
			'range'  => $face['range'],
			'file'   => $slug . '/' . $filename,
		);
	}

	if (! $installed) {
		return new WP_Error('bridge_font_download_failed', sprintf('Could not download any faces for “%s”.', $family));
	}

	return array(
		'family'  => $family,
		'faces'   => $installed,
		// What was asked for when these files were fetched — not what came
		// back, which is whichever rung of bridge_fetch_google_css() answered
		// and is a property of the family rather than of this theme's
		// intentions. Stored so the sync can tell a family installed under an
		// older weight list from one that is actually up to date. See the
		// staleness check there.
		'weights' => BRIDGE_FONT_WEIGHTS,
	);
}

/**
 * Ensure exactly the referenced families are installed, and no others.
 *
 * Runs on every token change. Families that are still selected and were
 * installed under the weight list this theme currently asks for are left
 * alone; families that are no longer referenced have their files deleted, so
 * a site that has been through several brand explorations does not
 * accumulate megabytes of abandoned typefaces.
 *
 * A family installed under an older weight list is re-fetched rather than
 * skipped — see the check in the loop, which explains why "is it installed"
 * was the wrong question.
 *
 * @param array<string, mixed> $tokens Resolved tokens.
 */
function bridge_sync_google_fonts(array $tokens): void
{
	$type   = $tokens['typography'];
	$wanted = array();

	if (! empty($type['googleFonts'])) {
		foreach (array($type['googleHeading'] ?? '', $type['googleBody'] ?? '') as $family) {
			if ('' !== $family && bridge_is_known_google_family($family)) {
				$wanted[bridge_font_slug($family)] = $family;
			}
		}
	}

	// The strapline face, wanted whenever there is a strapline to set in it —
	// and independently of the Google toggle, which is a choice about the
	// site's *typography*. The strapline is a piece of brand artwork that
	// happens to be made of letters; a site that has written one has asked for
	// this face by writing it, and switching the toggle off should not silently
	// redraw it in the body font.
	//
	// The field lives in Site Options rather than in the token record, so this
	// runs on that screen's save as well — see the hook at the foot of the
	// function.
	if ('' !== bridge_site_option('tagline')) {
		$wanted[bridge_font_slug(BRIDGE_SCRIPT_FAMILY)] = BRIDGE_SCRIPT_FAMILY;
	}

	$manifest = bridge_installed_fonts();
	$errors   = array();

	foreach ($wanted as $slug => $family) {
		/*
		 * Installed, and installed under the weight list this theme currently
		 * asks for.
		 *
		 * The second half is the whole point. Without it "installed" meant
		 * nothing more than "there is a directory", so widening
		 * BRIDGE_FONT_WEIGHTS changed what every new install fetched and left
		 * every existing site on the faces it already had — silently, because a
		 * missing weight is not an error anywhere: CSS falls back to the
		 * nearest face it has and the type simply comes out at a weight nobody
		 * chose. The only way to pick it up was to know to empty the option by
		 * hand, which is not a thing a theme should require of anyone.
		 *
		 * An entry with no `weights` key predates this check, which means it
		 * predates the widened list, so its absence reads as stale rather than
		 * as unknown.
		 *
		 * Re-installing is cheap and safe: bridge_install_google_font() skips
		 * any file already on disk, so a family that gains three weights
		 * downloads three files and rewrites its manifest entry rather than
		 * fetching the whole family again.
		 */
		$installed_faces = $manifest[$slug]['faces'] ?? array();
		$installed_at    = $manifest[$slug]['weights'] ?? '';

		if ($installed_faces && BRIDGE_FONT_WEIGHTS === $installed_at) {
			continue;
		}

		$result = bridge_install_google_font($family);

		if (is_wp_error($result)) {
			$errors[$family] = $result->get_error_message();
			continue;
		}

		$manifest[$slug] = $result;
	}

	foreach (array_keys($manifest) as $slug) {
		if (! isset($wanted[$slug])) {
			bridge_delete_font_files((string) $slug);
			unset($manifest[$slug]);
		}
	}

	update_option(BRIDGE_FONTS_OPTION, $manifest, true);
	set_transient('bridge_font_errors', $errors, HOUR_IN_SECONDS);
}
add_action('bridge_tokens_changed', 'bridge_sync_google_fonts');

/**
 * Re-sync when Site Options is saved.
 *
 * The strapline is the one thing outside the token record that decides which
 * fonts the site needs, so its screen has to trigger the same sweep the token
 * record does. Without this, writing a strapline would leave the face
 * uninstalled until the next unrelated save in Theme Options, and clearing one
 * would leave the files behind for good.
 *
 * `update_option_*` fires only when the value actually changed, so a save that
 * touched a phone number does not re-run the check for nothing — and even when
 * it does, the sweep is a pair of array comparisons unless something is
 * genuinely missing.
 */
function bridge_sync_fonts_for_site_options(): void
{
	bridge_sync_google_fonts(bridge_get_tokens());
}

/**
 * Hook the above, once the constant naming the option exists.
 *
 * Registered on `init` rather than at this file's top level, which is where it
 * belongs and where it does not work: functions.php loads inc/fonts.php before
 * inc/site-options.php, so BRIDGE_SITE_OPTIONS_KEY is undefined while this
 * file is being read and the hook would be attached to `update_option_` — a
 * name nothing ever fires. `init` is comfortably before the `admin_init` that
 * processes an options.php submission.
 */
function bridge_register_font_sync_hooks(): void
{
	add_action('update_option_' . BRIDGE_SITE_OPTIONS_KEY, 'bridge_sync_fonts_for_site_options');
}
add_action('init', 'bridge_register_font_sync_hooks');

/**
 * Delete an installed family's files.
 */
function bridge_delete_font_files(string $slug): void
{
	$paths = bridge_fonts_dir();

	if (! $paths) {
		return;
	}

	$dir = $paths['dir'] . '/' . $slug;

	if (! is_dir($dir)) {
		return;
	}

	foreach ((array) glob($dir . '/*.woff2') as $file) {
		@unlink($file);
	}

	@rmdir($dir);
}

/**
 * Build the theme.json fontFace declarations for an installed family.
 *
 * @param string $family Family name.
 * @return array<int, array<string, mixed>> Empty when not installed.
 */
function bridge_google_font_faces(string $family): array
{
	$paths = bridge_fonts_dir();
	$slug  = bridge_font_slug($family);
	$entry = bridge_installed_fonts()[$slug] ?? null;

	if (! $paths || ! $entry || empty($entry['faces'])) {
		return array();
	}

	// One declaration per weight *and* subset, each carrying its unicode-range
	// so the browser fetches only the subsets a page actually uses. Collapsing
	// them into one declaration with several `src` entries would be wrong:
	// `src` is a fallback chain, not a set of subsets, so everything after the
	// first would never load.
	$declarations = array();

	foreach ($entry['faces'] as $face) {
		$declaration = array(
			'fontFamily'  => $family,
			'fontStyle'   => 'normal',
			'fontWeight'  => (string) $face['weight'],
			'fontDisplay' => 'swap',
			'src'         => array($paths['url'] . '/' . $face['file']),
		);

		if (! empty($face['range'])) {
			$declaration['unicodeRange'] = $face['range'];
		}

		$declarations[] = $declaration;
	}

	return $declarations;
}

/**
 * Preload the handful of faces that render above the fold.
 *
 * Self-hosted fonts are discovered late: the browser has to fetch the HTML,
 * then the stylesheet, then parse the @font-face rule before it even knows a
 * file exists. Preloading collapses that chain and removes most of the
 * font-swap flash.
 *
 * Deliberately narrow — one face per role, latin subset only. Preloading is a
 * queue-jumping instruction, so preloading everything simply moves the
 * congestion rather than clearing it, and preloading a subset the visitor
 * never needs is pure waste.
 */
function bridge_preload_fonts(): void
{
	if (is_admin() || is_feed() || is_embed()) {
		return;
	}

	$tokens = bridge_get_tokens();
	$type   = $tokens['typography'];

	if (empty($type['googleFonts'])) {
		return;
	}

	$paths = bridge_fonts_dir();

	if (! $paths) {
		return;
	}

	$installed = bridge_installed_fonts();

	// Body copy is set at 400; headings at whatever weight the design system
	// specifies, falling back through 700 to 400 for families that publish a
	// narrower range.
	$roles = array(
		array('family' => (string) ($type['googleBody'] ?? ''), 'weights' => array(400)),
		array(
			'family'  => (string) ($type['googleHeading'] ?? ''),
			'weights' => array((int) $type['headingWeight'], 700, 400),
		),
	);

	$seen = array();

	foreach ($roles as $role) {
		if ('' === $role['family']) {
			continue;
		}

		$entry = $installed[bridge_font_slug($role['family'])] ?? null;

		if (! $entry || empty($entry['faces'])) {
			continue;
		}

		$file = '';

		foreach ($role['weights'] as $weight) {
			foreach ($entry['faces'] as $face) {
				if ((int) $face['weight'] === $weight && 'latin' === $face['subset']) {
					$file = (string) $face['file'];
					break 2;
				}
			}
		}

		if ('' === $file || isset($seen[$file])) {
			continue;
		}

		$seen[$file] = true;

		// crossorigin is required even for same-origin fonts: without it the
		// preload is fetched in a different mode from the CSS request and the
		// browser downloads the file twice.
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin />' . "\n",
			esc_url($paths['url'] . '/' . $file)
		);
	}
}
add_action('wp_head', 'bridge_preload_fonts', 2);

/**
 * The most recent install errors, for surfacing in the options page.
 *
 * @return array<string, string> Family => message.
 */
function bridge_font_errors(): array
{
	$errors = get_transient('bridge_font_errors');

	return is_array($errors) ? $errors : array();
}
