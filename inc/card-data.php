<?php

/**
 * Bridge — the card record.
 *
 * One post, flattened into the handful of values a card can draw. Every card
 * style in `bridge/cards` reads this array and nothing else: the styles differ
 * in which keys they use and how they arrange them, never in how they find a
 * title or where a subtitle comes from.
 *
 * ---- Why an array rather than the loop -------------------------------------
 *
 * The block used to set up eight loose variables in render.php's loop and let
 * card.php read them out of scope. That works for one card shape. It stops
 * working at three, because the styles want different fields — Tile wants a
 * badge, Portrait wants a role line and a button — and a loop that sets up
 * every field every style might want is a loop that knows about all three.
 *
 * ---- Why a filter rather than a post-type switch ---------------------------
 *
 * `subtitle` on a team member is a job title; on a venue it might be a locality
 * and on a post it is nothing at all. None of that is the block's business, and
 * a `switch ( $post_type )` here would mean the theme has to be edited every
 * time a site adds a post type — including post types that arrive from a plugin
 * this theme has never heard of.
 *
 * So the defaults are deliberately dumb — two meta keys, read as text — and
 * `bridge_card_data_{$post_type}` is where a site says what it actually means.
 * A post type that stores its role in a taxonomy, or assembles a distance from
 * two coordinates, hooks that filter and the block never learns about it.
 *
 * ---- Why plain meta for the defaults ---------------------------------------
 *
 * `distance` and `subtitle`, read with `get_post_meta()`. No ACF dependency and
 * no field-group lookup: ACF stores simple text fields as ordinary post meta
 * under the field's own name, so these keys keep working unchanged if the site
 * grows an ACF field group later, and they work right now with meta registered
 * by hand. A theme that hard-required `get_field()` would fatal on any install
 * without the plugin.
 *
 * @package Bridge
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Every key a card partial may read, and what it holds when nothing set it.
 *
 * Kept as its own function because it is used twice: once to build the record
 * and once to repair it after the filters have run. A filter that returns a
 * partial array — or drops a key while rewriting another — must not be able to
 * hand a partial an undefined index.
 *
 * @return array<string, mixed> Defaults for a card record.
 */
function bridge_card_data_defaults(): array
{
	return array(
		// Identity.
		'post_id'   => 0,
		'post_type' => '',

		// The link and its label. Every style makes the title the link; see
		// the partials for why none of them wraps the card in an anchor.
		'permalink' => '',
		'title'     => '',

		// Body copy. `excerpt` is already trimmed to the block's word count and
		// stripped of tags — a partial escapes it, it does not shorten it.
		'excerpt'   => '',

		// The one-line strip under the title. Portrait's role line; empty on
		// most post types, which is why Portrait skips the element entirely
		// rather than printing a blank paragraph.
		'subtitle'  => '',

		// The chip over the corner of the image. Tile's distance; same rule.
		'badge'     => '',

		// The word on Portrait's button. Comes from the block's setting rather
		// than the post, because it is the same on every card in the grid.
		'cta_text'  => '',

		// Imagery. `thumbnail_id` of 0 means the card draws a stand-in, and
		// `placeholder_logo_id` of 0 means that stand-in is the neutral pattern
		// rather than the site's mark.
		'thumbnail_id'        => 0,
		'placeholder_logo_id' => 0,

		// Passed through to wp_get_attachment_image(). `loading` is empty for a
		// card that might be above the fold, which leaves the decision to core
		// — see render.php's `$eager_cards` for why this file does not decide.
		'image_sizes'         => '',
		'loading'             => '',
	);
}

/**
 * Read one meta value as a single line of plain text.
 *
 * Cards have no room for markup and no room for a second line: a badge is a
 * chip a few characters wide and a subtitle is one line under a name. Anything
 * an editor pastes in — a stray <p> from a rich-text field, a newline from a
 * textarea — is flattened here rather than in three partials.
 *
 * Arrays are ignored rather than joined. A meta key holding an array is a field
 * that means something other than what this function is for (an ACF repeater, a
 * relationship), and guessing at a separator would print nonsense; the site's
 * own filter is where that field gets turned into a line.
 *
 * @param int    $post_id Post to read from.
 * @param string $key     Meta key.
 * @return string One line of plain text, or '' if there is nothing usable.
 */
function bridge_card_meta_line(int $post_id, string $key): string
{
	if ($post_id <= 0 || '' === $key) {
		return '';
	}

	$value = get_post_meta($post_id, $key, true);

	if (is_array($value) || is_object($value)) {
		return '';
	}

	// Numeric meta is a legitimate answer — a distance stored as 1.3 — so this
	// casts rather than rejecting anything that is not already a string.
	$value = wp_strip_all_tags((string) $value);

	// Collapse any run of whitespace, newlines included, into single spaces.
	$value = trim((string) preg_replace('/\s+/u', ' ', $value));

	return $value;
}

/**
 * Build the card record for one post.
 *
 * @param array $args {
 *     Optional. Everything the block knows that the post does not.
 *
 *     @type int|WP_Post|null $post                Post to read. Defaults to the
 *                                                 current post in the loop.
 *     @type int              $excerpt_length      Words to trim the excerpt to.
 *     @type string           $cta_text            Label for Portrait's button.
 *     @type string           $image_sizes         Responsive `sizes` attribute.
 *     @type string           $loading             'lazy', or '' to leave the
 *                                                 decision to core.
 *     @type int              $placeholder_logo_id Light logo for a post with no
 *                                                 featured image, or 0.
 * }
 * @return array<string, mixed> A card record, or the defaults if there is no
 *                              post to read — a caller in a loop always has
 *                              one, so an empty record means something upstream
 *                              is wrong and the card should draw nothing.
 */
function bridge_card_data(array $args = array()): array
{
	$post = get_post($args['post'] ?? null);

	if (! $post instanceof WP_Post) {
		return bridge_card_data_defaults();
	}

	$excerpt_length = max(1, (int) ($args['excerpt_length'] ?? 20));

	$card = array_merge(
		bridge_card_data_defaults(),
		array(
			'post_id'   => (int) $post->ID,
			'post_type' => (string) $post->post_type,
			'permalink' => (string) get_permalink($post),
			'title'     => (string) get_the_title($post),
			// Trimmed here so every style gets the same sentence and the block's
			// one excerpt-length setting means the same thing in all of them.
			'excerpt'   => wp_trim_words(
				wp_strip_all_tags((string) get_the_excerpt($post)),
				$excerpt_length,
				'…'
			),
			'subtitle'  => bridge_card_meta_line((int) $post->ID, 'subtitle'),
			'badge'     => bridge_card_meta_line((int) $post->ID, 'distance'),
			'cta_text'  => (string) ($args['cta_text'] ?? ''),

			'thumbnail_id'        => (int) get_post_thumbnail_id($post),
			'placeholder_logo_id' => (int) ($args['placeholder_logo_id'] ?? 0),
			'image_sizes'         => (string) ($args['image_sizes'] ?? ''),
			'loading'             => (string) ($args['loading'] ?? ''),
		)
	);

	/**
	 * Filter the card record for every post type.
	 *
	 * For changes that are about the card rather than about a particular kind
	 * of post — a site that wants every excerpt to end in a full stop, or that
	 * reads subtitles from a different key throughout.
	 *
	 * @param array   $card The record.
	 * @param WP_Post $post The post it was built from.
	 * @param array   $args The arguments bridge_card_data() was called with.
	 */
	$card = apply_filters('bridge_card_data', $card, $post, $args);

	/**
	 * Filter the card record for one post type.
	 *
	 * Where a post type says what its fields mean:
	 *
	 *     add_filter( 'bridge_card_data_team', function ( $card, $post ) {
	 *         $card['subtitle'] = get_post_meta( $post->ID, 'job_title', true );
	 *         return $card;
	 *     }, 10, 2 );
	 *
	 * @param array   $card The record, after the generic filter above.
	 * @param WP_Post $post The post it was built from.
	 * @param array   $args The arguments bridge_card_data() was called with.
	 */
	$card = apply_filters("bridge_card_data_{$post->post_type}", $card, $post, $args);

	// A filter is a stranger's code, and a partial reading `$card['title']` has
	// no way to defend itself. Anything that came back malformed is discarded;
	// anything merely incomplete is topped up.
	if (! is_array($card)) {
		return bridge_card_data_defaults();
	}

	return array_merge(bridge_card_data_defaults(), $card);
}
