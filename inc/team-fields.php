<?php

/**
 * Bridge — team member fields.
 *
 * A content type with no pages of its own (see inc/simple-editor.php) can be
 * given the fields a person needs, from Theme Options → Content types →
 * "Team member fields". The item's title is the name and its body is the
 * profile; this adds:
 *
 *   Position  the role line under the name. Stored as `subtitle`, the key the
 *             Cards block already reads for a card's role line.
 *   Location  one of the Site Options locations, chosen from a list — or typed,
 *             on a site with none.
 *   LinkedIn  the profile address, linked from the person's profile pop-up.
 *
 * Off unless a type asks for it, so nothing changes for any other type.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

const BRIDGE_PERSON_POSITION_KEY = 'subtitle';
const BRIDGE_PERSON_LOCATION_KEY = 'bridge_location';
const BRIDGE_PERSON_LINKEDIN_KEY = 'bridge_linkedin';

/**
 * Whether a post type has the team member fields.
 *
 * @param string $post_type Post type slug.
 * @return bool
 */
function bridge_post_type_has_person(string $post_type): bool
{
	if (! function_exists('bridge_is_simple_editor_post_type') || ! bridge_is_simple_editor_post_type($post_type)) {
		return false;
	}

	foreach (bridge_active_post_types() as $entry) {
		if ($post_type === (string) ($entry['slug'] ?? '')) {
			return ! empty($entry['hasPerson']);
		}
	}

	return false;
}

/**
 * Register the three fields on every type that has them, for the REST API
 * and so their values are typed and sanitised however they are written.
 */
function bridge_register_person_fields(): void
{
	foreach (bridge_simple_editor_post_types() as $post_type) {
		if (! bridge_post_type_has_person($post_type)) {
			continue;
		}

		$object     = get_post_type_object($post_type);
		$capability = $object->cap->edit_posts ?? 'edit_posts';
		$auth       = static function () use ($capability): bool {
			return current_user_can($capability);
		};

		foreach (
			array(
				BRIDGE_PERSON_POSITION_KEY => 'sanitize_text_field',
				BRIDGE_PERSON_LOCATION_KEY => 'sanitize_text_field',
				BRIDGE_PERSON_LINKEDIN_KEY => 'esc_url_raw',
			) as $key => $sanitize
		) {
			register_post_meta(
				$post_type,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'show_in_rest'      => true,
					'sanitize_callback' => $sanitize,
					'auth_callback'     => $auth,
				)
			);
		}
	}
}
add_action('init', 'bridge_register_person_fields', 20);

/**
 * The person's own words for the two editor fields: Name, and Profile.
 *
 * @param array<string, string> $labels    Title and body labels.
 * @param string                $post_type Post type slug.
 * @return array<string, string>
 */
function bridge_person_editor_labels($labels, $post_type)
{
	if (is_array($labels) && is_string($post_type) && bridge_post_type_has_person($post_type)) {
		$labels['title'] = __('Name', 'bridge');
		$labels['body']  = __('Profile', 'bridge');
	}

	return $labels;
}
add_filter('bridge_simple_editor_labels', 'bridge_person_editor_labels', 10, 2);

/**
 * The location names a person can be given: those in Site Options.
 *
 * @return array<int, string>
 */
function bridge_person_location_choices(): array
{
	if (! function_exists('bridge_site_locations')) {
		return array();
	}

	return array_values(array_filter(array_map(
		static fn($location) => (string) ($location['name'] ?? ''),
		bridge_site_locations()
	)));
}

/**
 * Position, Location and LinkedIn, under the name.
 *
 * @param WP_Post $post The item being edited.
 */
function bridge_person_fields_box($post): void
{
	if (! $post instanceof WP_Post || ! bridge_post_type_has_person((string) $post->post_type)) {
		return;
	}

	$position  = (string) get_post_meta($post->ID, BRIDGE_PERSON_POSITION_KEY, true);
	$location  = (string) get_post_meta($post->ID, BRIDGE_PERSON_LOCATION_KEY, true);
	$linkedin  = (string) get_post_meta($post->ID, BRIDGE_PERSON_LINKEDIN_KEY, true);
	$locations = bridge_person_location_choices();

	wp_nonce_field('bridge_person_fields', 'bridge_person_fields_nonce');
	?>
	<div class="bridge-person-fields">
		<p class="bridge-person-fields__field">
			<label class="bridge-simple-editor__label" for="bridge-person-position"><?php esc_html_e('Position', 'bridge'); ?></label>
			<input type="text" id="bridge-person-position" name="bridge_person_position" class="large-text" value="<?php echo esc_attr($position); ?>" autocomplete="off" placeholder="<?php esc_attr_e('e.g. Implant Dentist', 'bridge'); ?>">
		</p>

		<p class="bridge-person-fields__field">
			<label class="bridge-simple-editor__label" for="bridge-person-location"><?php esc_html_e('Location', 'bridge'); ?></label>
			<?php if ($locations) : ?>
				<select id="bridge-person-location" name="bridge_person_location">
					<option value=""><?php esc_html_e('None', 'bridge'); ?></option>
					<?php foreach ($locations as $name) : ?>
						<option value="<?php echo esc_attr($name); ?>" <?php selected($location, $name); ?>><?php echo esc_html($name); ?></option>
					<?php endforeach; ?>
					<?php
					// A saved location that has since been renamed or removed
					// in Site Options stays selectable, so saving the person
					// does not silently clear it.
					if ('' !== $location && ! in_array($location, $locations, true)) :
						?>
						<option value="<?php echo esc_attr($location); ?>" selected><?php echo esc_html($location); ?></option>
					<?php endif; ?>
				</select>
			<?php else : ?>
				<input type="text" id="bridge-person-location" name="bridge_person_location" class="large-text" value="<?php echo esc_attr($location); ?>" autocomplete="off">
				<span class="description"><?php esc_html_e('Add locations in Site Options to choose from a list.', 'bridge'); ?></span>
			<?php endif; ?>
		</p>

		<p class="bridge-person-fields__field">
			<label class="bridge-simple-editor__label" for="bridge-person-linkedin"><?php esc_html_e('LinkedIn profile', 'bridge'); ?></label>
			<input type="url" id="bridge-person-linkedin" name="bridge_person_linkedin" class="large-text" value="<?php echo esc_attr($linkedin); ?>" autocomplete="off" placeholder="https://www.linkedin.com/in/…">
		</p>
	</div>
	<?php
}
add_action('edit_form_after_title', 'bridge_person_fields_box', 6);

/**
 * Save the three fields.
 *
 * @param int $post_id Post being saved.
 */
function bridge_save_person_fields(int $post_id): void
{
	if (! isset($_POST['bridge_person_fields_nonce'])
		|| ! wp_verify_nonce(sanitize_key(wp_unslash($_POST['bridge_person_fields_nonce'])), 'bridge_person_fields')
	) {
		return;
	}

	if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
		return;
	}

	if (! bridge_post_type_has_person((string) get_post_type($post_id)) || ! current_user_can('edit_post', $post_id)) {
		return;
	}

	$values = array(
		BRIDGE_PERSON_POSITION_KEY => sanitize_text_field(wp_unslash($_POST['bridge_person_position'] ?? '')), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized — sanitised here.
		BRIDGE_PERSON_LOCATION_KEY => sanitize_text_field(wp_unslash($_POST['bridge_person_location'] ?? '')), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized — sanitised here.
		BRIDGE_PERSON_LINKEDIN_KEY => esc_url_raw(trim((string) wp_unslash($_POST['bridge_person_linkedin'] ?? ''))), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized — sanitised here.
	);

	foreach ($values as $key => $value) {
		if ('' === $value) {
			delete_post_meta($post_id, $key);
		} else {
			update_post_meta($post_id, $key, $value);
		}
	}
}
add_action('save_post', 'bridge_save_person_fields');

/**
 * What a person's card carries: the location, and everything the profile
 * pop-up shows — the profile text and the LinkedIn address.
 *
 * With no page of their own, a person's card opens that pop-up rather than
 * linking anywhere: see the Team card partial and src/js/person-dialog.js.
 * The LinkedIn address is a link inside the pop-up.
 *
 * @param array   $card The card record.
 * @param WP_Post $post The post it was built from.
 * @return array
 */
function bridge_card_data_person($card, $post)
{
	if (! is_array($card) || ! $post instanceof WP_Post || ! bridge_post_type_has_person((string) $post->post_type)) {
		return $card;
	}

	$card['location']  = (string) get_post_meta($post->ID, BRIDGE_PERSON_LOCATION_KEY, true);
	$card['permalink'] = '';
	$card['external']  = false;
	$card['person']    = array(
		'linkedin' => (string) get_post_meta($post->ID, BRIDGE_PERSON_LINKEDIN_KEY, true),
		// The Profile field is the item's body.
		'profile'  => trim((string) $post->post_content),
	);

	return $card;
}
add_filter('bridge_card_data', 'bridge_card_data_person', 20, 2);

/**
 * The field layout on the edit screen.
 */
function bridge_person_fields_styles(): void
{
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;

	if (! $screen || 'post' !== $screen->base || ! bridge_post_type_has_person((string) $screen->post_type)) {
		return;
	}

	echo '<style>.bridge-person-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:1rem 1.5rem;margin:1rem 0 1.5rem}.bridge-person-fields__field{margin:0}.bridge-person-fields__field select{width:100%;max-width:none}</style>';
}
add_action('admin_head', 'bridge_person_fields_styles');
