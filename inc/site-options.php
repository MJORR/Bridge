<?php

/**
 * Bridge site options — the site's own basic information.
 *
 * Deliberately not part of the Theme Options screen, and not reachable by its
 * capability. That screen is agency infrastructure: BRIDGE_OPERATOR_CAP is
 * withheld from Administrators on purpose, and inc/rest.php explains at length
 * why no `manage_options` route may reach the design system. This is the
 * opposite tier — a company address is the client's to change, and every
 * Administrator should be able to change it. Two screens, two capabilities, and
 * no path from one to the other.
 *
 * One option row holding one array, rather than a field per row: these values
 * are read together — a footer wants the whole set — so one autoloaded row is
 * one cache hit instead of eight, and a save is atomic.
 *
 * This file stores and edits. Nothing reads these values into a page yet; the
 * accessors at the foot of the file are what the footer, the map block and
 * anything else will use when they do.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

define('BRIDGE_SITE_OPTIONS_KEY', 'bridge_site_options');
define('BRIDGE_SITE_OPTIONS_SLUG', 'bridge-site-options');
define('BRIDGE_SITE_OPTIONS_GROUP', 'bridge_site_options_group');

/**
 * The capability that gates this screen.
 *
 * `manage_options` — the Administrator tier, and nothing narrower. Named here
 * rather than typed into four call sites so the answer to "who can edit the
 * company address" is in one place.
 */
function bridge_site_options_cap(): string
{
	return (string) apply_filters('bridge_site_options_cap', 'manage_options');
}

/**
 * The social networks offered, as slug => label.
 *
 * Four, fixed. A select of core's fifty-odd services was more chooser than the
 * job needs — the sites this theme builds link to the same four networks, and
 * a long list is a worse control than four labelled boxes.
 *
 * The slugs are core's own, so `bridge_social_icon()` below still finds an icon
 * for each without the theme drawing one.
 *
 * Filterable, so a site that genuinely needs another network gets one without
 * a theme edit.
 *
 * @return array<string, string>
 */
function bridge_social_networks(): array
{
	return (array) apply_filters(
		'bridge_social_networks',
		array(
			'linkedin'  => __('LinkedIn', 'bridge'),
			'facebook'  => __('Facebook', 'bridge'),
			'youtube'   => __('YouTube', 'bridge'),
			'instagram' => __('Instagram', 'bridge'),
		)
	);
}

/**
 * One network's icon, as inline SVG.
 *
 * Core's markup, from the set behind the Social Icons block. Whatever
 * eventually draws these links can call this rather than shipping a second set
 * of brand icons that would drift from the ones core uses on the same site.
 *
 * @param string $network Network slug.
 * @return string SVG markup, or an empty string if core has no icon for it.
 */
function bridge_social_icon(string $network): string
{
	if (! function_exists('block_core_social_link_get_icon')) {
		return '';
	}

	return (string) block_core_social_link_get_icon($network);
}

/**
 * Every field on the screen, in one description.
 *
 * The form is drawn from this and the saved values are sanitised against it, so
 * a field cannot exist in one and be forgotten by the other — which is the
 * usual way an options page ends up storing something unsanitised.
 *
 * `type` picks both the control and the sanitiser. `section` groups it on the
 * screen.
 *
 * @return array<string, array<string, string>>
 */
function bridge_site_options_schema(): array
{
	return array(
		'company' => array(
			'section' => 'business',
			'type'    => 'text',
			'label'   => __('Company name', 'bridge'),
			'help'    => __('The trading name, as it should read in the footer and anywhere else the company is named.', 'bridge'),
		),
		'address' => array(
			'section' => 'business',
			'type'    => 'textarea',
			'label'   => __('Address', 'bridge'),
			'help'    => __('One line per line, as it would be written on an envelope.', 'bridge'),
		),
		'phone'   => array(
			'section' => 'business',
			'type'    => 'text',
			'label'   => __('Phone', 'bridge'),
			'help'    => __('Written the way it should be read. The dialling link is built from it separately, so spaces and brackets are safe here.', 'bridge'),
		),
		'gtm_id'  => array(
			'section' => 'keys',
			'type'    => 'text',
			'label'   => __('Google Tag Manager ID', 'bridge'),
			'help'    => __('The container ID, in the form GTM-XXXXXXX. Stored only — nothing on the site loads Tag Manager yet.', 'bridge'),
		),
		'map_key' => array(
			'section' => 'keys',
			'type'    => 'text',
			'label'   => __('Google Maps API key', 'bridge'),
			'help'    => __('Stored only — the Map block still uses the keyless embed. A key that reaches a page is public, so restrict it to this domain in the Google Cloud console.', 'bridge'),
		),
	);
}

/**
 * The shape of the option when nothing has been saved.
 *
 * @return array<string, mixed>
 */
function bridge_site_options_defaults(): array
{
	$defaults = array();

	foreach (array_keys(bridge_site_options_schema()) as $key) {
		$defaults[$key] = '';
	}

	$defaults['social'] = array_fill_keys(array_keys(bridge_social_networks()), '');

	return $defaults;
}

/**
 * Register the option and its sanitiser.
 *
 * No `show_in_rest`. WP_REST_Settings_Controller authorises on
 * `manage_options`, which is the right tier for this data — but nothing needs
 * it over REST yet, and an option that is exposed before it is needed is a
 * surface that exists for no reason.
 */
function bridge_register_site_options(): void
{
	register_setting(
		BRIDGE_SITE_OPTIONS_GROUP,
		BRIDGE_SITE_OPTIONS_KEY,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'bridge_sanitize_site_options',
			'default'           => bridge_site_options_defaults(),
			// Read on the front end once anything displays it, and small
			// enough that loading it with the rest of the autoloaded options
			// is cheaper than a query of its own.
			'autoload'          => true,
		)
	);
}
add_action('admin_init', 'bridge_register_site_options');

/**
 * Report a rejected value, when there is a screen to report it on.
 *
 * `add_settings_error()` lives in wp-admin, and a sanitise callback does not
 * only run there — `wp option update`, or any other update_option() call, goes
 * through the same function and would fatal on a missing one. The value is
 * still corrected either way; only the message needs a screen to land on.
 *
 * @param string $code    Error slug.
 * @param string $message Message for the administrator.
 */
function bridge_site_options_error(string $code, string $message): void
{
	if (function_exists('add_settings_error')) {
		add_settings_error(BRIDGE_SITE_OPTIONS_KEY, $code, $message);
	}
}

/**
 * Sanitise a submitted set of site options.
 *
 * Built from the schema rather than field by field, so an unknown key in the
 * submission is dropped instead of stored. Bad values are reported rather than
 * silently discarded: an administrator who mistypes a container ID should be
 * told, not left wondering why nothing happened.
 *
 * @param mixed $input Raw submission.
 * @return array<string, mixed>
 */
function bridge_sanitize_site_options($input): array
{
	$input  = is_array($input) ? $input : array();
	$clean  = bridge_site_options_defaults();
	$schema = bridge_site_options_schema();

	foreach ($schema as $key => $field) {
		$value = isset($input[$key]) ? (string) $input[$key] : '';

		$clean[$key] = 'textarea' === $field['type']
			? sanitize_textarea_field($value)
			: sanitize_text_field($value);
	}

	// A container ID is a fixed shape, and one that is wrong is worse than one
	// that is missing: it loads nothing and looks configured.
	if ('' !== $clean['gtm_id'] && ! preg_match('/^GTM-[A-Z0-9]{4,}$/i', $clean['gtm_id'])) {
		bridge_site_options_error(
			'bridge_bad_gtm',
			__('That does not look like a Tag Manager container ID. It should read GTM- followed by letters and numbers, for example GTM-ABC1234. The old value has been kept.', 'bridge')
		);

		$clean['gtm_id'] = (string) (bridge_get_site_options()['gtm_id'] ?? '');
	}

	$clean['gtm_id'] = strtoupper($clean['gtm_id']);

	// Google's keys are URL-safe; anything else is a paste that went wrong —
	// a whole URL, or a key with a stray quote around it.
	if ('' !== $clean['map_key'] && ! preg_match('/^[A-Za-z0-9_-]{20,}$/', $clean['map_key'])) {
		bridge_site_options_error(
			'bridge_bad_map_key',
			__('That does not look like a Google Maps API key — they are a long run of letters, numbers, hyphens and underscores with nothing around them. The old value has been kept.', 'bridge')
		);

		$clean['map_key'] = (string) (bridge_get_site_options()['map_key'] ?? '');
	}

	$networks = bridge_social_networks();

	foreach ($networks as $slug => $label) {
		$url = isset($input['social'][$slug]) ? trim((string) $input['social'][$slug]) : '';

		if ('' === $url) {
			$clean['social'][$slug] = '';
			continue;
		}

		// A scheme-less entry is a domain someone typed, and esc_url_raw()
		// would make it `http://`. Assume the secure one — no social network
		// has served plain http in years.
		if (! preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
			$url = 'https://' . ltrim($url, '/');
		}

		// http and https only. A social link is a link to a website, and the
		// list of schemes a stored URL may carry is not the place to be
		// generous — `javascript:` is the reason.
		$safe = esc_url_raw($url, array('http', 'https'));

		if ('' === $safe) {
			bridge_site_options_error(
				'bridge_bad_social_' . $slug,
				sprintf(
					/* translators: %s: social network name. */
					__('The %s address was not a valid web link, so it was left empty. It needs to start with https://.', 'bridge'),
					$label
				)
			);
		}

		$clean['social'][$slug] = $safe;
	}

	return $clean;
}

/**
 * Register the screen.
 *
 * Top level rather than a child of Settings, because the people who need it are
 * the client's administrators and Settings is where they never look. Sits just
 * under the Bridge menu, which most of them will never see at all.
 */
function bridge_register_site_options_page(): void
{
	$GLOBALS['bridge_site_options_hook'] = add_menu_page(
		__('Site Options', 'bridge'),
		__('Site Options', 'bridge'),
		bridge_site_options_cap(),
		BRIDGE_SITE_OPTIONS_SLUG,
		'bridge_render_site_options_page',
		'dashicons-store',
		59.8
	);
}
add_action('admin_menu', 'bridge_register_site_options_page');

/**
 * One field's control.
 *
 * @param string               $key   Field key.
 * @param array<string,string> $field Field description from the schema.
 * @param array<string,mixed>  $value Saved options.
 */
function bridge_site_options_field(string $key, array $field, array $value): void
{
	$name    = BRIDGE_SITE_OPTIONS_KEY . '[' . $key . ']';
	$id      = 'bridge-site-' . str_replace('_', '-', $key);
	$current = (string) ($value[$key] ?? '');
	?>
	<tr>
		<th scope="row">
			<label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($field['label']); ?></label>
		</th>
		<td>
			<?php if ('textarea' === $field['type']) : ?>
				<textarea
					id="<?php echo esc_attr($id); ?>"
					name="<?php echo esc_attr($name); ?>"
					rows="4"
					class="large-text"
					aria-describedby="<?php echo esc_attr($id . '-help'); ?>"><?php echo esc_textarea($current); ?></textarea>
			<?php else : ?>
				<input
					type="text"
					id="<?php echo esc_attr($id); ?>"
					name="<?php echo esc_attr($name); ?>"
					value="<?php echo esc_attr($current); ?>"
					class="regular-text"
					aria-describedby="<?php echo esc_attr($id . '-help'); ?>">
			<?php endif; ?>

			<?php if (! empty($field['help'])) : ?>
				<p class="description" id="<?php echo esc_attr($id . '-help'); ?>">
					<?php echo esc_html($field['help']); ?>
				</p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

/**
 * Render the screen.
 */
function bridge_render_site_options_page(): void
{
	// add_menu_page() gates the screen, but a render callback is a public
	// function — never rely on the caller for authorisation.
	if (! current_user_can(bridge_site_options_cap())) {
		wp_die(esc_html__('You are not permitted to edit this site\'s information.', 'bridge'));
	}

	$value  = bridge_get_site_options();
	$schema = bridge_site_options_schema();
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Site Options', 'bridge'); ?></h1>

		<p class="description" style="max-width:46rem">
			<?php esc_html_e('The site\'s own details, kept in one place so they are written once and correct everywhere. Nothing on the site reads them yet — that comes next.', 'bridge'); ?>
		</p>

		<?php settings_errors(BRIDGE_SITE_OPTIONS_KEY); ?>

		<form method="post" action="options.php">
			<?php settings_fields(BRIDGE_SITE_OPTIONS_GROUP); ?>

			<h2><?php esc_html_e('Business information', 'bridge'); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<?php
					foreach ($schema as $key => $field) {
						if ('business' === $field['section']) {
							bridge_site_options_field($key, $field, $value);
						}
					}
					?>
				</tbody>
			</table>

			<h2><?php esc_html_e('Social links', 'bridge'); ?></h2>
			<p class="description">
				<?php esc_html_e('Leave a network empty and it is simply not stored.', 'bridge'); ?>
			</p>
			<table class="form-table" role="presentation">
				<tbody>
					<?php foreach (bridge_social_networks() as $slug => $label) : ?>
						<?php $social_id = 'bridge-social-' . $slug; ?>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr($social_id); ?>"><?php echo esc_html($label); ?></label>
							</th>
							<td>
								<input
									type="url"
									id="<?php echo esc_attr($social_id); ?>"
									name="<?php echo esc_attr(BRIDGE_SITE_OPTIONS_KEY . '[social][' . $slug . ']'); ?>"
									value="<?php echo esc_attr((string) ($value['social'][$slug] ?? '')); ?>"
									class="regular-text code"
									placeholder="https://">
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e('Integrations', 'bridge'); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<?php
					foreach ($schema as $key => $field) {
						if ('keys' === $field['section']) {
							bridge_site_options_field($key, $field, $value);
						}
					}
					?>
				</tbody>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* ---------------------------------------------------------------------------
 * Reading
 *
 * The whole point of storing this is that something else will draw it. These
 * are what it will call — put here now so the footer, the map block and
 * whatever follows share one reader rather than each doing its own get_option()
 * and its own defaulting.
 * ------------------------------------------------------------------------ */

/**
 * Every stored value, with defaults filled in.
 *
 * @return array<string, mixed>
 */
function bridge_get_site_options(): array
{
	$stored = get_option(BRIDGE_SITE_OPTIONS_KEY, array());
	$stored = is_array($stored) ? $stored : array();

	$value           = array_merge(bridge_site_options_defaults(), $stored);
	$value['social'] = bridge_normalise_social($stored['social'] ?? null);

	return $value;
}

/**
 * One stored value.
 *
 * @param string $key     Field key from the schema.
 * @param string $default Returned when the field is empty or unknown.
 * @return string
 */
function bridge_site_option(string $key, string $default = ''): string
{
	$value = bridge_get_site_options();

	if (! isset($value[$key]) || ! is_string($value[$key]) || '' === $value[$key]) {
		return $default;
	}

	return $value[$key];
}

/**
 * Coerce whatever is stored into a map of network slug => URL.
 *
 * Two older shapes are read here rather than migrated, so no site loses its
 * links to a change of mind about the editing UI:
 *
 *   - the map this returns, which is what the first and current versions store;
 *   - a list of `{ service, url }` rows, which one version in between stored.
 *
 * A row naming a network that is no longer offered is dropped, because there is
 * no field to edit it in and a value nobody can see or change is worse than an
 * absent one.
 *
 * @param mixed $stored Raw stored value.
 * @return array<string, string>
 */
function bridge_normalise_social($stored): array
{
	$clean = array_fill_keys(array_keys(bridge_social_networks()), '');

	if (! is_array($stored)) {
		return $clean;
	}

	foreach ($stored as $key => $entry) {
		// The row shape: [ 'service' => slug, 'url' => string ].
		if (is_array($entry)) {
			$slug = sanitize_key((string) ($entry['service'] ?? ''));
			$url  = (string) ($entry['url'] ?? '');
		} else {
			$slug = sanitize_key((string) $key);
			$url  = (string) $entry;
		}

		if ('' !== $url && array_key_exists($slug, $clean)) {
			$clean[$slug] = $url;
		}
	}

	return $clean;
}

/**
 * The social links that were actually filled in, as slug => URL.
 *
 * Empty networks are dropped, so a caller can loop over the result without
 * testing each one — and a site with no social presence gets an empty array
 * rather than four blanks to skip. The order is the order they are offered in,
 * which is the order they should appear in.
 *
 * @return array<string, string>
 */
function bridge_site_social_links(): array
{
	return array_filter(
		bridge_get_site_options()['social'],
		static function ($url): bool {
			return is_string($url) && '' !== $url;
		}
	);
}
