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
 * This file stores and edits. The accessors at the foot of it are what
 * everything else reads through — inc/enquiries.php asks for `notify_email`
 * when it has an enquiry to announce, and the footer and the map block will
 * ask for the rest.
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
			'github'    => __('GitHub', 'bridge'),
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
		'company'      => array(
			'section' => 'business',
			'type'    => 'text',
			'label'   => __('Company name', 'bridge'),
			'help'    => __('The trading name, as it should read in the footer and anywhere else the company is named.', 'bridge'),
		),
		'tagline'      => array(
			'section' => 'business',
			// A textarea for a line of a few words, because where it breaks is
			// part of it. "Better websites. / Real results." is two lines in
			// the artwork and reads as a pair; run together it is a sentence.
			// A single-line input cannot express that at all, and nothing can
			// infer it — breaking after every full stop would be right here
			// and wrong for "J. Smith & Co.", and wrong again for a strapline
			// with no full stop in it.
			//
			// One field rather than two. Two would put the break in the schema
			// and fix the strapline at exactly two lines, so a one-line or
			// three-line one becomes impossible — and an operator who leaves
			// the second box empty gets a stray blank line rather than a short
			// strapline.
			'type'    => 'textarea',
			'label'   => __('Strapline', 'bridge'),
			'help'    => __('The short line the footer signs off with. Press return where it should break — "Better websites." on one line, "Real results." on the next. It is set in a handwriting face, so keep it to a few words; the swoosh underneath it is an image, chosen in Theme Options → Templates.', 'bridge'),
		),
		'description'  => array(
			'section' => 'business',
			'type'    => 'textarea',
			'label'   => __('Short description', 'bridge'),
			'help'    => __('A sentence or two saying what the business does, for the footer and anywhere else the site introduces itself in a line. Not the tagline — this is the plain-English version.', 'bridge'),
		),
		'footer_image' => array(
			'section' => 'business',
			// An attachment id, not a URL: the media library is where an
			// operator's images live, and a pasted URL is a link that breaks
			// the first time the site moves domain.
			'type'    => 'image',
			'label'   => __('Footer background', 'bridge'),
			'help'    => __('An image along the bottom of the footer — a skyline, a landscape, something wide and dark. It fades in over the lower half so the text above it stays readable. Leave it empty and the footer keeps its flat colour.', 'bridge'),
		),
		'address'      => array(
			'section' => 'business',
			'type'    => 'textarea',
			'label'   => __('Address', 'bridge'),
			'help'    => __('One line per line, as it would be written on an envelope. A single line is a perfectly good answer — the footer prints what is typed.', 'bridge'),
		),
		'phone'        => array(
			'section' => 'business',
			'type'    => 'text',
			'label'   => __('Phone', 'bridge'),
			'help'    => __('Written the way it should be read. The dialling link is built from it separately, so spaces and brackets are safe here.', 'bridge'),
		),
		'notify_email' => array(
			'section' => 'notifications',
			'type'    => 'text',
			'label'   => __('Notifications email', 'bridge'),
			'help'    => __('Where enquiries from the site\'s contact forms are sent. Leave it empty and they go to the site administrator\'s address instead.', 'bridge'),
		),
		'gtm_id'       => array(
			'section' => 'keys',
			'type'    => 'text',
			'label'   => __('Google Tag Manager ID', 'bridge'),
			'help'    => __('The container ID, in the form GTM-XXXXXXX. Stored only — nothing on the site loads Tag Manager yet.', 'bridge'),
		),
		'map_key'      => array(
			'section' => 'keys',
			'type'    => 'text',
			'label'   => __('Google Maps API key', 'bridge'),
			'help'    => __('What the Map block draws with. It needs Maps JavaScript API and Places API (New) enabled. The key reaches the page — that is how the API works — so restrict it to this domain in the Google Cloud console.', 'bridge'),
		),
		'map_id'       => array(
			'section' => 'keys',
			'type'    => 'text',
			'label'   => __('Google Map ID', 'bridge'),
			'help'    => __('Optional. A Map ID carries a map style made in the Cloud console, and is what lets the pin be a modern advanced marker. Without one the map is Google’s default styling and a classic pin.', 'bridge'),
		),
	);
}

/**
 * The site's Google Maps API key, or an empty string.
 *
 * One accessor so nothing else has to know which option row it lives in, and so
 * "is there a key" is one question with one answer — the Map block asks it three
 * times: to decide which embed to build, whether to load the editor's map, and
 * what to tell an editor when there is no key to load it with.
 */
function bridge_map_key(): string
{
	$key = bridge_site_option('map_key');

	return is_string($key) ? trim($key) : '';
}

/**
 * The site's Google Map ID, or an empty string.
 *
 * Not a second key and not a secret: a Map ID names a style saved in the Cloud
 * console, and it is also the thing `AdvancedMarkerElement` requires before it
 * will draw. Empty is a working map — Google's own styling, and the classic
 * marker — which is why nothing here treats its absence as a problem.
 */
function bridge_map_id(): string
{
	$id = bridge_site_option('map_id');

	return is_string($id) ? trim($id) : '';
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

		// An image is an attachment id and is stored as one — as a string,
		// because every other value on this screen is a string and a single
		// mixed-type field would make `bridge_site_option()` return two kinds
		// of thing. `absint` so a negative or a word becomes 0, which is how
		// "none" is spelled here as everywhere else in the theme.
		//
		// Not checked against the media library: the same reasoning as the
		// token record's image ids. An id that is real on staging and missing
		// on production would be silently dropped by the first save at the far
		// end, where the theme instead draws nothing and leaves the setting
		// alone — a failure that can be seen and fixed.
		if ('image' === $field['type']) {
			$clean[$key] = (string) absint($value);
			continue;
		}

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

	// The address enquiries are sent to, so a typo here is a client wondering
	// for a fortnight why nobody has been in touch. Kept rather than cleared
	// for the same reason the two below are: an address that is wrong is worse
	// than one that is missing, because the missing one falls back to the
	// administrator and still arrives.
	if ('' !== $clean['notify_email'] && ! is_email($clean['notify_email'])) {
		bridge_site_options_error(
			'bridge_bad_notify_email',
			__('That does not look like an email address, so the old one has been kept. Enquiries go to the site administrator until a valid address is saved here.', 'bridge')
		);

		$clean['notify_email'] = (string) (bridge_get_site_options()['notify_email'] ?? '');
	}

	$clean['notify_email'] = sanitize_email($clean['notify_email']);

	// Google's keys are URL-safe; anything else is a paste that went wrong —
	// a whole URL, or a key with a stray quote around it.
	if ('' !== $clean['map_key'] && ! preg_match('/^[A-Za-z0-9_-]{20,}$/', $clean['map_key'])) {
		bridge_site_options_error(
			'bridge_bad_map_key',
			__('That does not look like a Google Maps API key — they are a long run of letters, numbers, hyphens and underscores with nothing around them. The old value has been kept.', 'bridge')
		);

		$clean['map_key'] = (string) (bridge_get_site_options()['map_key'] ?? '');
	}

	// A Map ID is a short opaque token from the Cloud console, not a URL and
	// not a key. Same reading as above: a paste that went wrong is kept out
	// rather than saved and silently ignored by Google.
	if ('' !== $clean['map_id'] && ! preg_match('/^[A-Za-z0-9_-]{4,}$/', $clean['map_id'])) {
		bridge_site_options_error(
			'bridge_bad_map_id',
			__('That does not look like a Google Map ID — they are a short run of letters, numbers, hyphens and underscores. The old value has been kept.', 'bridge')
		);

		$clean['map_id'] = (string) (bridge_get_site_options()['map_id'] ?? '');
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
 * The media picker's script and the few rules that lay it out.
 *
 * Only on this screen, and only when there is something on it that opens the
 * media modal — `wp_enqueue_media()` prints a good deal of markup and several
 * scripts, and a settings page with nothing but text fields should not pay for
 * them.
 *
 * @param string $hook_suffix Current admin screen.
 */
function bridge_enqueue_site_options(string $hook_suffix): void
{
	if (($GLOBALS['bridge_site_options_hook'] ?? null) !== $hook_suffix) {
		return;
	}

	$has_image = false;

	foreach (bridge_site_options_schema() as $field) {
		if ('image' === $field['type']) {
			$has_image = true;
			break;
		}
	}

	if (! $has_image) {
		return;
	}

	if (bridge_register_script('bridge-site-options', 'site-options.js', array('wp-i18n'), true)) {
		wp_enqueue_script('bridge-site-options');
		wp_set_script_translations('bridge-site-options', 'bridge');
	}

	wp_enqueue_media();

	// Small enough to print rather than ship a stylesheet for: four rules that
	// exist only on this screen and are meaningless anywhere else.
	wp_add_inline_style(
		'common',
		'.bridge-media__preview{display:flex;align-items:center;justify-content:center;'
			. 'inline-size:min(22rem,100%);min-block-size:6rem;margin-block-end:.5rem;padding:.5rem;'
			. 'border:1px solid #dcdcde;border-radius:4px;background:#f6f7f7}'
			. '.bridge-media__preview img{max-inline-size:100%;block-size:auto;display:block}'
			. '.bridge-media__actions{display:flex;gap:.5rem;align-items:center;margin:0}'
	);
}
add_action('admin_enqueue_scripts', 'bridge_enqueue_site_options');

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
			<?php if ('image' === $field['type']) : ?>
				<?php
				/*
				 * A media picker, drawn as plain markup with a hidden input
				 * behind it.
				 *
				 * The id is what the form submits, so it is an <input> like
				 * every other field on the screen and the settings API needs
				 * no special case — but a hidden one, because an attachment id
				 * is a number nobody should have to read. The preview and the
				 * two buttons are the operator's half of it, driven by
				 * src/admin/site-options.js.
				 *
				 * Which does mean the field is inert with JavaScript
				 * unavailable: the buttons do nothing and there is no longer a
				 * box to type an id into. An acceptable trade on a screen whose
				 * only other job is text fields, and worth remembering if this
				 * control is ever reused somewhere it matters more.
				 */
				$preview = $current > '' ? wp_get_attachment_image((int) $current, 'medium', false, array('alt' => '')) : '';
				?>
				<div class="bridge-media" data-bridge-media>
					<div class="bridge-media__preview" data-bridge-media-preview>
						<?php
						echo $preview // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — core markup.
							?: '<em>' . esc_html__('No image chosen', 'bridge') . '</em>';
						?>
					</div>

					<p class="bridge-media__actions">
						<?php
						// The button carries the field's id, so the <label> in
						// the row heading — written by the shared markup above,
						// which does not know what kind of field this is —
						// still points at a real control. A <button> is a
						// labelable element, so this is what it looks like when
						// the control an operator actually uses is not the one
						// holding the value.
						?>
						<button type="button" class="button" id="<?php echo esc_attr($id); ?>" data-bridge-media-choose aria-describedby="<?php echo esc_attr($id . '-help'); ?>">
							<?php echo esc_html($current > '' ? __('Replace image', 'bridge') : __('Choose image', 'bridge')); ?>
						</button>
						<button type="button" class="button-link-delete button-link" data-bridge-media-clear <?php disabled('' === $current || '0' === $current); ?>>
							<?php esc_html_e('Remove', 'bridge'); ?>
						</button>
					</p>

					<?php
					// Hidden, because an attachment id is not something anyone
					// should have to read or type — the preview above says
					// which image is chosen far better than a number does. It
					// is still a real input, so the form submits it and the
					// settings API needs no special case.
					?>
					<input
						type="hidden"
						name="<?php echo esc_attr($name); ?>"
						value="<?php echo esc_attr($current); ?>"
						data-bridge-media-input>
				</div>
			<?php elseif ('textarea' === $field['type']) : ?>
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
			<?php esc_html_e('The site\'s own details, kept in one place so they are written once and correct everywhere. The notifications address is the one that is already in use: it is where enquiries from the contact form are sent.', 'bridge'); ?>
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

			<h2><?php esc_html_e('Notifications', 'bridge'); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<?php
					foreach ($schema as $key => $field) {
						if ('notifications' === $field['section']) {
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
