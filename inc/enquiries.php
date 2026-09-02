<?php

/**
 * Bridge — the contact form, and the enquiries it files.
 *
 * One block, one content type, one submission handler. The block is in
 * src/blocks/contact-form; everything the server does with it is here.
 *
 * ---- Why the post type is not always registered ---------------------------
 *
 * A brochure site that has no contact form has no enquiries, and an empty
 * "Enquiries" screen in the admin menu is a permanent question a client has to
 * answer for themselves every time they look at it. So the type is registered
 * only once the site actually has a form: `bridge_enquiries_in_use()` reads a
 * flag, and the flag is set the moment a post, pattern or template is saved
 * with the block in it — or the moment a form renders, or a submission
 * arrives, whichever happens first.
 *
 * The flag is never cleared automatically, and that asymmetry is deliberate.
 * Removing the block from a page would otherwise hide every enquiry the site
 * had ever received, which is the same "it deleted my content" failure
 * inc/post-types.php refuses at length. Turning the screen off again is a
 * conversation, not a side effect of an edit.
 *
 * ---- Why it has no pages --------------------------------------------------
 *
 * `public => false`. An enquiry is somebody's phone number and their reason
 * for getting in touch; it has no business having a URL, an archive, or a
 * place in search results. That also means no rewrite rules, so nothing here
 * needs the fingerprint-and-flush dance inc/post-types.php does — the type can
 * be registered late, on the request that discovers it is needed, with no
 * front-end consequence at all.
 *
 * ---- Why there is one endpoint, not two -----------------------------------
 *
 * The form posts to admin-post.php, and it does so whether or not JavaScript
 * is running: without it the browser posts and follows a redirect back
 * (post/redirect/get, so a refresh cannot re-send); with it, contact-form-view.js
 * posts the same body with `X-Requested-With` and gets JSON.
 *
 * A REST route would have been the modern-looking choice and would have been
 * wrong. Nonces are per-user, and a REST request that carries no `X-WP-Nonce`
 * is treated as logged-out — so a *logged-in* editor testing the form on the
 * front end would submit a nonce minted for their account and have it verified
 * as nobody's. admin-post.php runs with the request's own cookies, so one
 * nonce check is correct on both paths.
 *
 * ---- The spam defence -----------------------------------------------------
 *
 * Everything is local. No third-party captcha, nothing that sends a visitor's
 * IP to another company, nothing that asks somebody to identify a bicycle.
 * Five layers, in the order they run:
 *
 *   1. A nonce.        Stops a form posted from somewhere that is not this site.
 *   2. A honeypot.     A field a person never sees and a bot fills in.
 *   3. A signed clock. The form says when it was drawn, signed so it cannot be
 *                      back-dated. Filled in under three seconds is a script;
 *                      filled in a day later is a stale tab, and says so.
 *   4. A rate limit.   Per IP, per hour: a high ceiling on attempts and a low
 *                      one on messages that actually arrive, with a cooldown
 *                      between those.
 *   5. A score.        Link counts, sales vocabulary, whether JavaScript ran.
 *
 * The first four either pass or stop the submission. The fifth never does:
 * it quarantines. A scored submission is still filed — as a draft, with its
 * reasons attached — and simply sends no email. Nothing a visitor writes is
 * ever silently thrown away, because the cost of losing one real enquiry is
 * higher than the cost of a client deleting a spam one.
 *
 * A bot is told the same thing a person is. Every rejection that is about spam
 * rather than about the visitor's typing returns the ordinary success message,
 * so a script cannot use the response to learn which layer it tripped.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The post type key.
 *
 * Prefixed, and listed in bridge_reserved_post_type_slugs() so an operator
 * cannot declare a type in Theme Options that would collide with it. It is
 * never seen: the type has no URLs, so the key appears in the database and
 * nowhere else.
 */
define('BRIDGE_ENQUIRY_POST_TYPE', 'bridge_enquiry');

/**
 * Where the "this site has a form" flag lives.
 */
define('BRIDGE_ENQUIRIES_FLAG', 'bridge_enquiries_in_use');

/**
 * The admin-post action both paths post to.
 */
define('BRIDGE_ENQUIRY_ACTION', 'bridge_enquiry');

/* ---------------------------------------------------------------------------
 * The form's shape
 *
 * One description of the four fields, read by the renderer, the validator, the
 * admin screen, both emails and the editor's preview. A field cannot be
 * rendered in one place and forgotten by another.
 * ------------------------------------------------------------------------ */

/**
 * The fields a contact form asks for.
 *
 * `required` here is the field's own answer. Phone is the one an operator can
 * change, per form, from the block's inspector — some clients want to be able
 * to ring back, some would rather not put a barrier in front of a question.
 *
 * `maxlength` is a real limit and not only a courtesy: it is enforced again in
 * the validator, because an attribute is a suggestion to a browser and nothing
 * at all to a script.
 *
 * @return array<string, array<string, mixed>>
 */
function bridge_enquiry_fields(): array
{
	/**
	 * Filter the four fields.
	 *
	 * For changing what they are called, what they suggest and how long they
	 * may be — a site whose visitors write "Mobile", a placeholder in another
	 * language, a message limit of two thousand rather than five.
	 *
	 * Not for adding a fifth. The validator's rules, the stored meta keys and
	 * the admin screen's columns all name these four, so a field added here
	 * would be shown, sent in both emails, and then not filed anywhere. Adding
	 * one is a change to this file, not a hook.
	 */
	return (array) apply_filters(
		'bridge_enquiry_fields',
		array(
			'name'    => array(
				'label'        => __('Name', 'bridge'),
				'type'         => 'text',
				'autocomplete' => 'name',
				'maxlength'    => 80,
				'required'     => true,
				'placeholder'  => __('Your name', 'bridge'),
			),
			'email'   => array(
				'label'        => __('Email', 'bridge'),
				'type'         => 'email',
				'autocomplete' => 'email',
				'maxlength'    => 100,
				'required'     => true,
				'placeholder'  => __('you@example.com', 'bridge'),
			),
			'phone'   => array(
				'label'        => __('Phone', 'bridge'),
				'type'         => 'tel',
				'autocomplete' => 'tel',
				'maxlength'    => 30,
				'required'     => false,
				'placeholder'  => __('01234 567890', 'bridge'),
			),
			'message' => array(
				'label'        => __('Message', 'bridge'),
				'type'         => 'textarea',
				'autocomplete' => 'off',
				'maxlength'    => 5000,
				'required'     => true,
				'placeholder'  => __('How can we help?', 'bridge'),
				'rows'         => 6,
			),
		)
	);
}

/**
 * The name a field wears in the submitted body.
 *
 * All four sit inside one array, so the handler can take the group and know
 * that anything outside it — the nonce, the honeypot, the signed clock — is
 * plumbing rather than something a visitor typed.
 *
 * @param string $key Field key.
 * @return string
 */
function bridge_enquiry_field_name(string $key): string
{
	return 'bridge_enquiry[' . $key . ']';
}

/* ---------------------------------------------------------------------------
 * The post type
 * ------------------------------------------------------------------------ */

/**
 * Whether this site has a contact form on it.
 *
 * @return bool
 */
function bridge_enquiries_in_use(): bool
{
	return (bool) get_option(BRIDGE_ENQUIRIES_FLAG, false);
}

/**
 * Record that it does.
 *
 * Guarded on the read rather than left to update_option()'s own comparison,
 * because this is called from render.php — on every front-end request that
 * draws a form — and the guard turns that into an autoloaded array lookup
 * instead of a database round trip.
 */
function bridge_mark_enquiries_in_use(): void
{
	if (bridge_enquiries_in_use()) {
		return;
	}

	update_option(BRIDGE_ENQUIRIES_FLAG, '1', true);
}

/**
 * The post type's registration arguments.
 *
 * `create_posts => do_not_allow` is the interesting one. An enquiry is a
 * record of something that happened, not a document somebody authors, so
 * "Add New" is removed everywhere WordPress would otherwise offer it — the
 * admin menu, the list table, the admin bar. Editing and deleting stay: a
 * client needs to be able to annotate one and to clear the spam out.
 *
 * `show_in_rest => false`, so the screen is the classic one. There is no block
 * content here to edit — the message is a paragraph of somebody's plain text —
 * and the block editor would offer a canvas, a pattern inserter and a template
 * switcher for a record that is read and then answered by email.
 *
 * @return array<string, mixed>
 */
function bridge_enquiry_post_type_args(): array
{
	return array(
		'labels'              => array(
			'name'               => __('Enquiries', 'bridge'),
			'singular_name'      => __('Enquiry', 'bridge'),
			'menu_name'          => __('Enquiries', 'bridge'),
			'all_items'          => __('Enquiries', 'bridge'),
			'edit_item'          => __('Enquiry', 'bridge'),
			'view_item'          => __('Enquiry', 'bridge'),
			'search_items'       => __('Search enquiries', 'bridge'),
			'not_found'          => __('No enquiries yet.', 'bridge'),
			'not_found_in_trash' => __('No enquiries in Trash.', 'bridge'),
		),
		// Somebody's phone number and their reason for getting in touch. It
		// has no URL, no archive and no place in search — see the file header.
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_in_nav_menus'   => false,
		'has_archive'         => false,
		'rewrite'             => false,
		'query_var'           => false,
		// Not derived from `public`: register_post_type() defaults both of
		// these to it, and the admin screen is the entire point of the type.
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_admin_bar'   => false,
		'show_in_rest'        => false,
		'hierarchical'        => false,
		'menu_icon'           => 'dashicons-email-alt',
		// Under the declared content types (21+), above Comments (25).
		'menu_position'       => 24,
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
		'capabilities'        => array('create_posts' => 'do_not_allow'),
		// The title carries the sender's name; the message is the content.
		// Everything else about an enquiry is meta, drawn by the panel below.
		'supports'            => array('title', 'editor'),
	);
}

/**
 * Register the post type, if it is not registered already.
 *
 * Called from `init` when the flag is set, and again from the store function
 * for the one request where the two can disagree: a form that has just been
 * added to a page can be submitted before any request has run `init` with the
 * flag on. Registering here is safe precisely because the type has no rewrite
 * rules and no query variable — there is nothing about it that had to be
 * decided before the main query ran.
 */
function bridge_register_enquiry_post_type(): void
{
	if (post_type_exists(BRIDGE_ENQUIRY_POST_TYPE)) {
		return;
	}

	register_post_type(BRIDGE_ENQUIRY_POST_TYPE, bridge_enquiry_post_type_args());
}

/**
 * Register it on init, but only for a site that has a form.
 */
function bridge_maybe_register_enquiry_post_type(): void
{
	if (! bridge_enquiries_in_use()) {
		return;
	}

	bridge_register_enquiry_post_type();
}
add_action('init', 'bridge_maybe_register_enquiry_post_type');

/**
 * Notice the block being saved into a post, a pattern or a template.
 *
 * `save_post` fires for every post type, and templates, template parts and
 * synced patterns are all posts — so this one hook covers every place in
 * WordPress a block can be stored. A block in a *theme file* template is the
 * one case it cannot see, which is why render.php raises the flag too.
 *
 * The string test rather than has_blocks()/parse_blocks(): the serialised
 * comment delimiter is exactly what is in post_content, and parsing a whole
 * document on every save to answer a yes/no question is work nobody asked for.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function bridge_detect_enquiry_block(int $post_id, WP_Post $post): void
{
	if (bridge_enquiries_in_use() || wp_is_post_revision($post_id)) {
		return;
	}

	if (! str_contains((string) $post->post_content, '<!-- wp:bridge/contact-form')) {
		return;
	}

	bridge_mark_enquiries_in_use();
}
add_action('save_post', 'bridge_detect_enquiry_block', 10, 2);

/* ---------------------------------------------------------------------------
 * Validation
 *
 * Pure functions: an array in, an array out, nothing touched on the way. That
 * is what makes them the part of this file with tests behind it.
 * ------------------------------------------------------------------------ */

/**
 * Clean and check a submitted set of answers.
 *
 * Returns both halves, always: the cleaned values so a rejected form can be
 * drawn again with the visitor's typing still in it, and the errors so each
 * field can say what is wrong with it rather than a banner saying something is.
 *
 * @param array $raw           Raw, unslashed submission.
 * @param bool  $require_phone Whether this form asked for a phone number.
 * @return array{values: array<string,string>, errors: array<string,string>}
 */
function bridge_enquiry_validate(array $raw, bool $require_phone = false): array
{
	$fields = bridge_enquiry_fields();
	$values = array();
	$errors = array();

	foreach ($fields as $key => $field) {
		$value = isset($raw[$key]) ? (string) $raw[$key] : '';

		$value = 'textarea' === ($field['type'] ?? 'text')
			? sanitize_textarea_field($value)
			: sanitize_text_field($value);

		// Enforced here as well as in the attribute, because `maxlength` is a
		// request to a browser and means nothing to a script posting directly.
		$value = trim(mb_substr($value, 0, (int) ($field['maxlength'] ?? 200)));

		$values[$key] = $value;
	}

	$required = static function (string $key) use ($fields, $require_phone): bool {
		if ('phone' === $key) {
			return $require_phone;
		}

		return ! empty($fields[$key]['required']);
	};

	if ('' === $values['name']) {
		$errors['name'] = __('Please tell us your name.', 'bridge');
	} elseif (mb_strlen($values['name']) < 2) {
		$errors['name'] = __('That name looks too short — please write it in full.', 'bridge');
	} elseif (preg_match('#https?://|\[url|<#i', $values['name'])) {
		// Nobody's name contains a web address. A submission whose *name* does
		// is not a person who mistyped; it is the commonest shape of link spam
		// there is, and rejecting it here means it never reaches the score.
		$errors['name'] = __('Please write your name without any links or tags in it.', 'bridge');
	}

	if ('' === $values['email']) {
		$errors['email'] = __('Please give us an email address so we can reply.', 'bridge');
	} elseif (! is_email($values['email'])) {
		$errors['email'] = __('That does not look like an email address. Please check it and try again.', 'bridge');
	}

	// Digits, wherever they are and whatever is between them. A phone number is
	// written a dozen ways — spaces, brackets, dots, a leading + — and none of
	// those is a mistake, so the only question worth asking is whether there
	// are enough digits to dial. Seven is the shortest local number in use;
	// fifteen is the E.164 ceiling, international prefix included.
	$digits = preg_replace('/\D+/', '', $values['phone']);
	$digits = is_string($digits) ? $digits : '';

	if ('' === $values['phone']) {
		if ($required('phone')) {
			$errors['phone'] = __('Please give us a phone number.', 'bridge');
		}
	} elseif (strlen($digits) < 7 || strlen($digits) > 15) {
		$errors['phone'] = __('That does not look like a phone number. Please check it and try again.', 'bridge');
	}

	if ('' === $values['message']) {
		$errors['message'] = __('Please tell us what you would like to talk about.', 'bridge');
	} elseif (mb_strlen($values['message']) < 10) {
		$errors['message'] = __('Please give us a little more detail — a sentence or two is plenty.', 'bridge');
	}

	return array(
		'values' => $values,
		'errors' => $errors,
	);
}

/* ---------------------------------------------------------------------------
 * Spam scoring
 *
 * The layer that never rejects. See the file header for why.
 * ------------------------------------------------------------------------ */

/**
 * The score at which a submission is quarantined rather than delivered.
 *
 * Three, and the weights below are chosen against it: no single ordinary
 * signal reaches it alone, so a real enquiry that happens to contain a link,
 * or to arrive from a browser with JavaScript turned off, is still delivered.
 * It takes a combination.
 *
 * @return int
 */
function bridge_enquiry_spam_threshold(): int
{
	return (int) apply_filters('bridge_enquiry_spam_threshold', 3);
}

/**
 * The vocabulary that turns up in link spam and almost nowhere else.
 *
 * Deliberately short, and deliberately not a list of rude words. Each one is
 * worth a single point, so a genuine enquiry from an SEO agency — which is a
 * real thing a marketing site receives — needs to trip something else as well
 * before it is held back.
 *
 * @return string[]
 */
function bridge_enquiry_spam_terms(): array
{
	return (array) apply_filters(
		'bridge_enquiry_spam_terms',
		array(
			'backlink',
			'link building',
			'guest post',
			'crypto',
			'bitcoin',
			'casino',
			'viagra',
			'seo services',
			'first page of google',
			'increase your traffic',
			'cheap loan',
		)
	);
}

/**
 * Weigh a submission, and say why.
 *
 * The reasons are stored on the enquiry and printed on the admin screen, so a
 * client looking at a held-back message can see what it was held back for
 * rather than being asked to trust a number.
 *
 * @param array $values  Cleaned field values.
 * @param array $signals honeypot: bool, elapsed: int seconds, js: bool.
 * @return array{score:int, reasons:string[]}
 */
function bridge_enquiry_spam_report(array $values, array $signals): array
{
	$score   = 0;
	$reasons = array();

	$add = static function (int $points, string $reason) use (&$score, &$reasons): void {
		$score    += $points;
		$reasons[] = $reason;
	};

	// A field no person can see and no browser fills in. On its own this is
	// conclusive, so it is weighted past the threshold by itself.
	if (! empty($signals['honeypot'])) {
		$add(5, __('A hidden field only an automated submission would fill in was filled in.', 'bridge'));
	}

	// Three seconds is faster than a person can read four labels, let alone
	// answer them. It is not faster than a script.
	$elapsed = isset($signals['elapsed']) ? (int) $signals['elapsed'] : 0;

	if ($elapsed >= 0 && $elapsed < 3) {
		$add(5, __('The form was submitted within three seconds of loading.', 'bridge'));
	}

	// Not conclusive on its own, and weighted so that it never is: a real
	// visitor with scripts blocked still gets through on this alone.
	if (empty($signals['js'])) {
		$add(1, __('The submission arrived without the browser having run the form\'s script.', 'bridge'));
	}

	$message = (string) ($values['message'] ?? '');

	// Links are the point of link spam, and one link in a genuine enquiry is
	// ordinary — somebody sending their own site. Two is unusual. Three is a
	// list.
	$links = preg_match_all('#https?://|www\.[a-z0-9-]+\.#i', $message);
	$links = is_int($links) ? $links : 0;

	if ($links >= 3) {
		$add(3, __('The message contains three or more web links.', 'bridge'));
	} elseif (2 === $links) {
		$add(2, __('The message contains two web links.', 'bridge'));
	}

	// Markup for a forum, in a message that is going to be read as plain text.
	// Nothing but a bot writes it here.
	if (preg_match('/\[url[=\]]|\[link[=\]]|<a\s/i', $message)) {
		$add(3, __('The message contains link markup.', 'bridge'));
	}

	$hits = 0;

	foreach (bridge_enquiry_spam_terms() as $term) {
		if (false !== stripos($message, (string) $term)) {
			$hits++;
		}
	}

	if ($hits > 0) {
		$add(
			min(3, $hits),
			sprintf(
				/* translators: %d: number of matched phrases. */
				_n(
					'The message uses %d phrase common in unsolicited sales mail.',
					'The message uses %d phrases common in unsolicited sales mail.',
					$hits,
					'bridge'
				),
				$hits
			)
		);
	}

	// A message that is nothing but the sender's name, or the same word over
	// and over, is a form being probed rather than a question being asked.
	if ('' !== $message && $message === ($values['name'] ?? null)) {
		$add(2, __('The message is identical to the name.', 'bridge'));
	}

	/**
	 * Filter the finished report.
	 *
	 * The hook a site uses to add a rule of its own — a country it never sells
	 * to, a phrase its own spam keeps arriving with — without editing the
	 * theme.
	 */
	return (array) apply_filters(
		'bridge_enquiry_spam_report',
		array(
			'score'   => $score,
			'reasons' => $reasons,
		),
		$values,
		$signals
	);
}

/* ---------------------------------------------------------------------------
 * The signed clock
 *
 * A hidden field saying when the form was drawn, and a signature so it cannot
 * be re-dated by whoever is posting it. The signature also carries whether the
 * form asked for a phone number, because admin-post.php has no block in front
 * of it and would otherwise have to be told by a field a bot could flip.
 * ------------------------------------------------------------------------ */

/**
 * The stamp a form ships, and its signature.
 *
 * @param bool $require_phone Whether this form requires a phone number.
 * @return array{stamp:string, signature:string}
 */
function bridge_enquiry_stamp(bool $require_phone): array
{
	$stamp = wp_json_encode(
		array(
			't' => time(),
			'p' => $require_phone ? 1 : 0,
		)
	);

	$stamp = base64_encode((string) $stamp);

	return array(
		'stamp'     => $stamp,
		'signature' => wp_hash($stamp, 'nonce'),
	);
}

/**
 * Read a stamp back, if it is genuinely ours.
 *
 * `hash_equals` rather than `===`, because a signature comparison that returns
 * early on the first wrong byte is a signature comparison that can be measured.
 *
 * @param string $stamp     Encoded stamp.
 * @param string $signature Its signature.
 * @return array{t:int, p:bool}|null Null when the pair does not verify.
 */
function bridge_enquiry_read_stamp(string $stamp, string $signature): ?array
{
	if ('' === $stamp || '' === $signature) {
		return null;
	}

	if (! hash_equals(wp_hash($stamp, 'nonce'), $signature)) {
		return null;
	}

	$decoded = json_decode((string) base64_decode($stamp, true), true);

	if (! is_array($decoded) || ! isset($decoded['t'])) {
		return null;
	}

	return array(
		't' => (int) $decoded['t'],
		'p' => ! empty($decoded['p']),
	);
}

/* ---------------------------------------------------------------------------
 * Rate limiting
 * ------------------------------------------------------------------------ */

/**
 * The submitting address, or an empty string when there isn't a usable one.
 *
 * Validated as an IP rather than taken on trust: it reaches the rate limiter's
 * cache key and the stored record, and neither wants an arbitrary header value
 * in it. Proxy headers are ignored — they are trivially forged, and a site
 * genuinely behind one should be setting REMOTE_ADDR at the edge.
 *
 * @return string
 */
function bridge_enquiry_client_ip(): string
{
	$raw = isset($_SERVER['REMOTE_ADDR']) ? (string) wp_unslash($_SERVER['REMOTE_ADDR']) : '';
	$ip  = filter_var($raw, FILTER_VALIDATE_IP);

	return is_string($ip) ? $ip : '';
}

/**
 * What one address may do in an hour.
 *
 * Three numbers, because there are two different things worth limiting and
 * they must not share a budget:
 *
 *   attempts  Everything that reaches the handler, refused submissions
 *             included. Flood protection, and deliberately generous — a
 *             visitor who mistypes their email twice, corrects it, then sends
 *             a second enquiry later in the hour has used five of these, and
 *             none of that is unusual behaviour.
 *   sends     Messages that were actually filed. This is the one that means
 *             "you have told us; please let us reply".
 *   cooldown  Seconds between *sends*. Catches a double-click, and stops
 *             anything that has found a way through from sending fifty.
 *
 * @return array{attempts:int, sends:int, cooldown:int}
 */
function bridge_enquiry_limits(): array
{
	$limits = (array) apply_filters(
		'bridge_enquiry_limits',
		array(
			'attempts' => 20,
			'sends'    => 5,
			'cooldown' => 15,
		)
	);

	return array(
		'attempts' => max(1, (int) ($limits['attempts'] ?? 20)),
		'sends'    => max(1, (int) ($limits['sends'] ?? 5)),
		'cooldown' => max(0, (int) ($limits['cooldown'] ?? 15)),
	);
}

/**
 * This address's bucket, and the key it is stored under.
 *
 * One transient holding the whole bucket rather than a transient per question,
 * and that is not only tidiness: `set_transient()` resets the expiry every time
 * it is called, so a counter stored on its own would push its own hour forward
 * on every write and never reset at all.
 *
 * @param string $ip The submitting address.
 * @return array{0:string, 1:array<string,int>} Cache key, then the bucket.
 */
function bridge_enquiry_bucket(string $ip): array
{
	$key    = 'bridge_enq_' . md5($ip);
	$now    = time();
	$bucket = get_transient($key);

	if (! is_array($bucket) || ! isset($bucket['reset']) || (int) $bucket['reset'] <= $now) {
		$bucket = array(
			'attempts' => 0,
			'sends'    => 0,
			'last'     => 0,
			'reset'    => $now + HOUR_IN_SECONDS,
		);
	}

	return array($key, $bucket);
}

/**
 * Write a bucket back, keeping the hour it is already counting.
 *
 * @param string            $key    Cache key.
 * @param array<string,int> $bucket The bucket.
 */
function bridge_enquiry_save_bucket(string $key, array $bucket): void
{
	set_transient($key, $bucket, max(MINUTE_IN_SECONDS, (int) $bucket['reset'] - time()));
}

/**
 * Count this attempt, and say whether the caller should stop.
 *
 * Only the attempt ceiling can stop an ordinary visitor here, and it is set
 * high enough that none does. That is deliberate, and it was not the first
 * version: a cooldown that started on every *attempt* meant somebody who
 * mistyped their email address was told to wait fifteen seconds before they
 * were allowed to correct it — a form punishing a visitor for the one mistake
 * forms exist to catch. The cooldown and the message cap moved to
 * bridge_enquiry_note_send() below, where they count messages that arrived.
 *
 * @return string An explanation when the caller should stop, or '' to continue.
 */
function bridge_enquiry_rate_limit(): string
{
	$ip = bridge_enquiry_client_ip();

	// No usable address — a request through something that never set one.
	// There is nothing to count against, so there is nothing to limit.
	if ('' === $ip) {
		return '';
	}

	$limits             = bridge_enquiry_limits();
	list($key, $bucket) = bridge_enquiry_bucket($ip);

	$bucket['attempts'] = (int) $bucket['attempts'] + 1;

	bridge_enquiry_save_bucket($key, $bucket);

	if ($bucket['attempts'] > $limits['attempts']) {
		return __('There have been a lot of attempts from this connection. Please try again a little later.', 'bridge');
	}

	if ((int) $bucket['sends'] >= $limits['sends']) {
		return __('You have sent us several messages already. Please give us a little time to reply before sending another.', 'bridge');
	}

	if ($limits['cooldown'] > 0 && (int) $bucket['last'] > 0 && (time() - (int) $bucket['last']) < $limits['cooldown']) {
		return __('That has just been sent — please wait a moment before sending another message.', 'bridge');
	}

	return '';
}

/**
 * Record that a message actually went.
 *
 * Called once a submission has been filed, so the two limits a person can
 * meet count messages rather than keystrokes.
 */
function bridge_enquiry_note_send(): void
{
	$ip = bridge_enquiry_client_ip();

	if ('' === $ip) {
		return;
	}

	list($key, $bucket) = bridge_enquiry_bucket($ip);

	$bucket['sends'] = (int) $bucket['sends'] + 1;
	$bucket['last']  = time();

	bridge_enquiry_save_bucket($key, $bucket);
}

/* ---------------------------------------------------------------------------
 * Handling a submission
 * ------------------------------------------------------------------------ */

/**
 * The whole of what happens when a form is posted.
 *
 * One function for both front doors, so the redirect path and the JSON path
 * cannot drift into behaving differently. It returns a description of what
 * happened; turning that into a redirect or a JSON body is the adapter's job.
 *
 * @param array $post The unslashed request body.
 * @return array{ok:bool, errors:array<string,string>, values:array<string,string>, notice:string}
 */
function bridge_process_enquiry(array $post): array
{
	$fail = static function (string $notice, array $errors = array(), array $values = array()): array {
		return array(
			'ok'     => false,
			'errors' => $errors,
			'values' => $values,
			'notice' => $notice,
		);
	};

	// Layer 1. A form posted from anywhere that is not a page of this site.
	$nonce = isset($post['_bridge_nonce']) ? (string) $post['_bridge_nonce'] : '';

	if (! wp_verify_nonce($nonce, BRIDGE_ENQUIRY_ACTION)) {
		return $fail(__('This form has been open for a while and its security token has expired. Please reload the page and send it again.', 'bridge'));
	}

	// Layer 4, before any work is done: an address that is flooding the form
	// should not get four more database reads out of every attempt.
	$limited = bridge_enquiry_rate_limit();

	if ('' !== $limited) {
		return $fail($limited);
	}

	// Layer 3. An unsigned or unreadable stamp means the form was not drawn by
	// this site, which is the same answer as a failed nonce.
	$stamp = bridge_enquiry_read_stamp(
		isset($post['_bridge_stamp']) ? (string) $post['_bridge_stamp'] : '',
		isset($post['_bridge_signature']) ? (string) $post['_bridge_signature'] : ''
	);

	if (null === $stamp) {
		return $fail(__('This form could not be verified. Please reload the page and send it again.', 'bridge'));
	}

	$elapsed = time() - $stamp['t'];

	// A tab left open overnight. Worth its own message: the visitor has done
	// nothing wrong and their typing is still on the screen in front of them.
	if ($elapsed > DAY_IN_SECONDS) {
		return $fail(__('This page has been open for a long time. Please reload it and send your message again.', 'bridge'));
	}

	$raw       = isset($post['bridge_enquiry']) && is_array($post['bridge_enquiry']) ? $post['bridge_enquiry'] : array();
	$validated = bridge_enquiry_validate($raw, $stamp['p']);

	if (! empty($validated['errors'])) {
		return $fail(
			__('Please check the highlighted fields and send the form again.', 'bridge'),
			$validated['errors'],
			$validated['values']
		);
	}

	// Layers 2 and 5. Neither rejects: a scored submission is filed and simply
	// not delivered, and the visitor — or the script — is told the same thing
	// a delivered one is told.
	$report = bridge_enquiry_spam_report(
		$validated['values'],
		array(
			'honeypot' => '' !== trim((string) ($raw['website'] ?? '')),
			'elapsed'  => $elapsed,
			'js'       => '1' === (string) ($post['_bridge_js'] ?? ''),
		)
	);

	$held = (int) $report['score'] >= bridge_enquiry_spam_threshold();

	$post_id = bridge_store_enquiry(
		$validated['values'],
		array(
			'source' => isset($post['_bridge_source']) ? (string) $post['_bridge_source'] : '',
			'held'   => $held,
			'report' => $report,
		)
	);

	if (is_wp_error($post_id)) {
		return $fail(__('Something went wrong at our end and your message was not saved. Please try again in a moment.', 'bridge'));
	}

	// Counted now that something has actually been filed — a held one
	// included, because anything that has found its way past the honeypot must
	// not be given an unlimited budget for having managed it.
	bridge_enquiry_note_send();

	if (! $held) {
		bridge_notify_enquiry((int) $post_id, $validated['values']);
	}

	return array(
		'ok'     => true,
		'errors' => array(),
		'values' => array(),
		'notice' => '',
	);
}

/**
 * File an enquiry.
 *
 * The message is the post content and the rest is meta. Held-back submissions
 * are drafts, which is not a fudge: WordPress's own list table then offers
 * "All | Published | Drafts" for free, so a client's inbox is the published
 * view and the quarantine is one click away and countable.
 *
 * The address is stored anonymised — the last octet of an IPv4 address, the
 * last eighty bits of an IPv6 one, dropped by core's own function. It is kept
 * to answer "is all of this coming from one place", which the anonymised form
 * answers just as well, and not to identify anybody. The un-anonymised address
 * lives only in the rate limiter's transient, and only for an hour.
 *
 * @param array $values Cleaned field values.
 * @param array $args   source: string, held: bool, report: array.
 * @return int|WP_Error
 */
function bridge_store_enquiry(array $values, array $args)
{
	// The one request where the flag and the registration can disagree — see
	// bridge_register_enquiry_post_type().
	bridge_mark_enquiries_in_use();
	bridge_register_enquiry_post_type();

	$held = ! empty($args['held']);

	$post_id = wp_insert_post(
		array(
			'post_type'    => BRIDGE_ENQUIRY_POST_TYPE,
			'post_status'  => $held ? 'draft' : 'publish',
			'post_title'   => $values['name'],
			'post_content' => $values['message'],
			// Nobody authored this. Leaving post_author at 0 says so, rather
			// than attributing a stranger's message to whichever administrator
			// happened to be logged in.
			'post_author'  => 0,
		),
		true
	);

	if (is_wp_error($post_id)) {
		return $post_id;
	}

	$ip = bridge_enquiry_client_ip();

	$meta = array(
		'bridge_enquiry_name'   => $values['name'],
		'bridge_enquiry_email'  => $values['email'],
		'bridge_enquiry_phone'  => $values['phone'],
		'bridge_enquiry_source' => esc_url_raw((string) ($args['source'] ?? '')),
		'bridge_enquiry_ip'     => '' === $ip ? '' : wp_privacy_anonymize_ip($ip),
	);

	foreach ($meta as $key => $value) {
		update_post_meta((int) $post_id, $key, $value);
	}

	if ($held) {
		update_post_meta((int) $post_id, 'bridge_enquiry_score', (int) ($args['report']['score'] ?? 0));
		update_post_meta((int) $post_id, 'bridge_enquiry_reasons', (array) ($args['report']['reasons'] ?? array()));
	}

	/**
	 * Fires once an enquiry has been filed, held back or not.
	 *
	 * The hook for a CRM, a Slack webhook, or anything else a particular site
	 * wants an enquiry to reach.
	 */
	do_action('bridge_enquiry_stored', (int) $post_id, $values, $held);

	return (int) $post_id;
}

/* ---------------------------------------------------------------------------
 * The two emails
 * ------------------------------------------------------------------------ */

/**
 * Where notifications go.
 *
 * The Notifications email from Site Options when one is set, and the site's
 * own administrator address when it is not — so a form works the moment it is
 * added to a page, and improves when somebody fills the field in.
 *
 * @return string
 */
function bridge_enquiry_notification_email(): string
{
	$configured = bridge_site_option('notify_email');

	if (is_email($configured)) {
		return $configured;
	}

	return (string) get_option('admin_email');
}

/**
 * Send one email as HTML, with a plain-text alternative.
 *
 * wp_mail() sends whatever `wp_mail_content_type` says and nothing else, so an
 * HTML email sent through it plainly is an HTML email with no text part — which
 * is one of the oldest reasons for a message to be scored as spam by the thing
 * receiving it. The `phpmailer_init` action is where the text part can be
 * attached, and this is the only place in the theme that needs to know that.
 *
 * Every filter is added immediately before the send and removed immediately
 * after, closures held in variables so `remove_filter` can find them again. A
 * theme that leaves `wp_mail_content_type` filtered turns every *other* email
 * the site sends — password resets, comment notifications — into HTML that
 * shows its own tags.
 *
 * The From address is left alone deliberately. Sending as the visitor would
 * fail SPF at most receivers and land the notification in a spam folder; the
 * visitor's address goes in Reply-To instead, which is what makes hitting
 * reply work.
 *
 * @param string   $to      Recipient.
 * @param string   $subject Subject line.
 * @param string   $html    HTML body.
 * @param string   $text    Plain-text alternative.
 * @param string[] $headers Extra headers.
 * @return bool
 */
function bridge_enquiry_mail(string $to, string $subject, string $html, string $text, array $headers = array()): bool
{
	$as_html   = static function (): string {
		return 'text/html';
	};
	$from_name = static function (): string {
		return wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
	};
	$alt_body  = static function ($phpmailer) use ($text): void {
		$phpmailer->AltBody = $text;
	};

	add_filter('wp_mail_content_type', $as_html);
	add_filter('wp_mail_from_name', $from_name);
	add_action('phpmailer_init', $alt_body);

	$sent = wp_mail($to, $subject, $html, $headers);

	remove_action('phpmailer_init', $alt_body);
	remove_filter('wp_mail_from_name', $from_name);
	remove_filter('wp_mail_content_type', $as_html);

	return (bool) $sent;
}

/**
 * The shell both emails are drawn in.
 *
 * Table layout and inline styles, because that is still what email clients
 * render reliably — an external stylesheet is stripped, a `<style>` block is
 * stripped by some, and flexbox is not supported by others. The brand colour
 * is read from the token record, so the emails a site sends are the colour the
 * site is without anybody maintaining a second palette here.
 *
 * @param string $heading Headline.
 * @param string $body    Body HTML.
 * @return string
 */
function bridge_enquiry_email_shell(string $heading, string $body): string
{
	$brand = bridge_palette_hex('primary');
	$brand = '' === $brand ? '#0f172a' : $brand;

	$ink = bridge_is_light_color($brand) ? '#111111' : '#ffffff';

	return sprintf(
		'<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>%1$s</title></head>' .
			'<body style="margin:0;padding:0;background:#f4f4f5;">' .
			'<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 12px;">' .
			'<tr><td align="center">' .
			'<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:10px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">' .
			'<tr><td style="background:%2$s;color:%3$s;padding:22px 28px;font-size:18px;font-weight:600;">%4$s</td></tr>' .
			'<tr><td style="padding:28px;color:#27272a;font-size:15px;line-height:1.6;">%5$s</td></tr>' .
			'<tr><td style="padding:0 28px 26px;color:#71717a;font-size:12px;line-height:1.5;">%6$s</td></tr>' .
			'</table></td></tr></table></body></html>',
		esc_html($heading),
		esc_attr($brand),
		esc_attr($ink),
		esc_html($heading),
		$body, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — assembled from escaped parts by the callers.
		sprintf(
			/* translators: %s: site name. */
			esc_html__('Sent from the contact form at %s.', 'bridge'),
			esc_html(get_bloginfo('name'))
		)
	);
}

/**
 * The submitted answers, as rows of a table.
 *
 * @param array $values Cleaned field values.
 * @return string
 */
function bridge_enquiry_email_rows(array $values): string
{
	$rows = '';

	foreach (bridge_enquiry_fields() as $key => $field) {
		$value = (string) ($values[$key] ?? '');

		if ('' === $value) {
			continue;
		}

		$rows .= sprintf(
			'<tr>' .
				'<td style="padding:6px 0;color:#71717a;font-size:13px;vertical-align:top;width:110px;">%1$s</td>' .
				'<td style="padding:6px 0;color:#18181b;font-size:15px;vertical-align:top;">%2$s</td>' .
				'</tr>',
			esc_html((string) $field['label']),
			// nl2br over an escaped string, so the message keeps its
			// paragraphing without any of it being markup the sender wrote.
			nl2br(esc_html($value))
		);
	}

	return '<table role="presentation" cellpadding="0" cellspacing="0" width="100%">' . $rows . '</table>';
}

/**
 * The same thing as plain text.
 *
 * @param array $values Cleaned field values.
 * @return string
 */
function bridge_enquiry_text_rows(array $values): string
{
	$lines = array();

	foreach (bridge_enquiry_fields() as $key => $field) {
		$value = (string) ($values[$key] ?? '');

		if ('' === $value) {
			continue;
		}

		$lines[] = $field['label'] . ': ' . $value;
	}

	return implode("\n", $lines);
}

/**
 * Tell the site, and thank the visitor.
 *
 * Both sends are attempted whatever the other one does: a thank-you that fails
 * to leave must not stop the site being told an enquiry arrived, and a
 * notification that bounces must not cost the visitor their acknowledgement.
 *
 * @param int   $post_id Stored enquiry.
 * @param array $values  Cleaned field values.
 */
function bridge_notify_enquiry(int $post_id, array $values): void
{
	$site = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);

	// ---- The site's notification ------------------------------------------

	$body = '<p style="margin:0 0 18px;">' .
		esc_html__('A new enquiry has come in through the website.', 'bridge') .
		'</p>' .
		bridge_enquiry_email_rows($values) .
		sprintf(
			'<p style="margin:24px 0 0;"><a href="%1$s" style="display:inline-block;background:#18181b;color:#ffffff;text-decoration:none;padding:11px 20px;border-radius:6px;font-size:14px;font-weight:600;">%2$s</a></p>',
			esc_url(get_edit_post_link($post_id, 'raw') ?? admin_url()),
			esc_html__('Open this enquiry', 'bridge')
		);

	$text = __('A new enquiry has come in through the website.', 'bridge') . "\n\n" .
		bridge_enquiry_text_rows($values) . "\n\n" .
		(string) get_edit_post_link($post_id, 'raw');

	bridge_enquiry_mail(
		bridge_enquiry_notification_email(),
		sprintf(
			/* translators: 1: site name, 2: sender's name. */
			__('[%1$s] New enquiry from %2$s', 'bridge'),
			$site,
			$values['name']
		),
		bridge_enquiry_email_shell(__('New enquiry', 'bridge'), $body),
		$text,
		array(
			// What makes "reply" work. The From address stays the site's, so
			// the message still passes the receiving server's checks.
			sprintf('Reply-To: %s <%s>', $values['name'], $values['email']),
		)
	);

	// ---- The visitor's acknowledgement ------------------------------------

	$thanks = sprintf(
		'<p style="margin:0 0 18px;">%1$s</p><p style="margin:0 0 22px;">%2$s</p>' .
			'<p style="margin:0 0 10px;color:#71717a;font-size:13px;">%3$s</p>%4$s',
		sprintf(
			/* translators: %s: sender's name. */
			esc_html__('Hello %s,', 'bridge'),
			esc_html($values['name'])
		),
		sprintf(
			/* translators: %s: site name. */
			esc_html__('Thank you for getting in touch with %s. We have your enquiry and someone will come back to you as soon as we can.', 'bridge'),
			esc_html($site)
		),
		esc_html__('For your records, this is what you sent us:', 'bridge'),
		bridge_enquiry_email_rows($values)
	);

	$thanks_text = sprintf(
		/* translators: %s: sender's name. */
		__('Hello %s,', 'bridge'),
		$values['name']
	) . "\n\n" .
		sprintf(
			/* translators: %s: site name. */
			__('Thank you for getting in touch with %s. We have your enquiry and someone will come back to you as soon as we can.', 'bridge'),
			$site
		) . "\n\n" .
		__('For your records, this is what you sent us:', 'bridge') . "\n\n" .
		bridge_enquiry_text_rows($values);

	bridge_enquiry_mail(
		sprintf('%s <%s>', $values['name'], $values['email']),
		sprintf(
			/* translators: %s: site name. */
			__('Thank you for your enquiry — %s', 'bridge'),
			$site
		),
		bridge_enquiry_email_shell(__('Thank you for your enquiry', 'bridge'), $thanks),
		$thanks_text
	);
}

/* ---------------------------------------------------------------------------
 * The front door
 * ------------------------------------------------------------------------ */

/**
 * Whether this request wants JSON back rather than a redirect.
 *
 * @return bool
 */
function bridge_enquiry_wants_json(): bool
{
	$requested = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
		? strtolower((string) wp_unslash($_SERVER['HTTP_X_REQUESTED_WITH']))
		: '';

	return 'xmlhttprequest' === $requested;
}

/**
 * Handle a posted form.
 *
 * Registered for logged-out and logged-in visitors alike: an administrator
 * looking at their own site is a visitor as far as a contact form is concerned,
 * and a form that silently did nothing for the one person most likely to test
 * it would be a form nobody trusted.
 */
function bridge_handle_enquiry_post(): void
{
	// wp_unslash() over the whole body once, here, because WordPress adds
	// slashes to everything in $_POST and every value below is either stored
	// or shown back to somebody.
	$post   = (array) wp_unslash($_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing — verified inside bridge_process_enquiry().
	$result = bridge_process_enquiry($post);

	if (bridge_enquiry_wants_json()) {
		wp_send_json(
			array(
				'ok'     => $result['ok'],
				'notice' => $result['notice'],
				'errors' => $result['errors'],
			),
			$result['ok'] ? 200 : 422
		);
	}

	// Post/redirect/get. Without the redirect, a refresh on the result page
	// re-posts the form, and the visitor sends their message twice.
	$redirect = wp_validate_redirect(
		isset($post['_bridge_source']) ? (string) $post['_bridge_source'] : '',
		home_url('/')
	);

	$form = isset($post['_bridge_form']) ? sanitize_key((string) $post['_bridge_form']) : '';

	$args = array(
		'bridge-enquiry' => $result['ok'] ? 'sent' : 'error',
	);

	if ('' !== $form) {
		// Which form on the page the answer belongs to, so a page carrying two
		// of them does not show the same result under both.
		$args['bridge-form'] = $form;
	}

	if (! $result['ok']) {
		// The errors and the visitor's typing cannot travel in the URL — one
		// is too long and both are theirs. A single-use ticket travels instead,
		// and render.php redeems it.
		$ticket = wp_generate_password(20, false, false);

		set_transient(
			'bridge_enquiry_' . $ticket,
			array(
				'errors' => $result['errors'],
				'values' => $result['values'],
				'notice' => $result['notice'],
			),
			10 * MINUTE_IN_SECONDS
		);

		$args['bridge-ref'] = $ticket;
	}

	$url = add_query_arg($args, $redirect);

	if ('' !== $form) {
		$url .= '#' . $form;
	}

	wp_safe_redirect($url);
	exit;
}
add_action('admin_post_nopriv_' . BRIDGE_ENQUIRY_ACTION, 'bridge_handle_enquiry_post');
add_action('admin_post_' . BRIDGE_ENQUIRY_ACTION, 'bridge_handle_enquiry_post');

/**
 * What the page coming back from a redirect should show, for one form.
 *
 * @param string $form_id The form's own id.
 * @return array{status:string, notice:string, errors:array<string,string>, values:array<string,string>}
 */
function bridge_enquiry_response(string $form_id): array
{
	$empty = array(
		'status' => '',
		'notice' => '',
		'errors' => array(),
		'values' => array(),
	);

	// phpcs:disable WordPress.Security.NonceVerification.Recommended — reading
	// the result of a redirect this site issued; nothing here changes state.
	$status = isset($_GET['bridge-enquiry']) ? sanitize_key((string) wp_unslash($_GET['bridge-enquiry'])) : '';

	if ('sent' !== $status && 'error' !== $status) {
		return $empty;
	}

	// A page may hold more than one form. The answer belongs to the one that
	// was posted, and the others must go on looking like forms.
	$form = isset($_GET['bridge-form']) ? sanitize_key((string) wp_unslash($_GET['bridge-form'])) : '';

	if ('' !== $form && $form !== $form_id) {
		return $empty;
	}

	if ('sent' === $status) {
		return array_merge($empty, array('status' => 'sent'));
	}

	$ticket = isset($_GET['bridge-ref']) ? sanitize_key((string) wp_unslash($_GET['bridge-ref'])) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$stored = '' === $ticket ? false : get_transient('bridge_enquiry_' . $ticket);

	if (! is_array($stored)) {
		// The ticket has been redeemed already, or it expired — which is what
		// a bookmarked or shared error URL looks like. Say nothing rather than
		// showing an error the visitor cannot act on.
		return $empty;
	}

	// Single use: the ticket carries somebody's typing, and it has now arrived.
	delete_transient('bridge_enquiry_' . $ticket);

	return array(
		'status' => 'error',
		'notice' => (string) ($stored['notice'] ?? ''),
		'errors' => (array) ($stored['errors'] ?? array()),
		'values' => (array) ($stored['values'] ?? array()),
	);
}

/**
 * The id of the next form on this page.
 *
 * Deterministic, so the anchor a redirect comes back to is the anchor that was
 * posted from. `wp_unique_id()` would not be: it is a global counter that
 * every other block on the page also advances, so the second render of the
 * same page could number the same form differently.
 *
 * @return string
 */
function bridge_enquiry_form_id(): string
{
	static $count = 0;

	$count++;

	return 1 === $count ? 'bridge-enquiry-form' : 'bridge-enquiry-form-' . $count;
}

/* ---------------------------------------------------------------------------
 * The admin screen
 * ------------------------------------------------------------------------ */

/**
 * The columns of the enquiries list.
 *
 * The message is not among them on purpose: a list of enquiries is for
 * choosing one, and a column of truncated messages makes every row the same
 * height as the longest one and still says nothing useful.
 *
 * @param array<string,string> $columns Default columns.
 * @return array<string,string>
 */
function bridge_enquiry_columns(array $columns): array
{
	return array(
		'cb'                    => $columns['cb'] ?? '',
		'title'                 => __('From', 'bridge'),
		'bridge_enquiry_email'  => __('Email', 'bridge'),
		'bridge_enquiry_phone'  => __('Phone', 'bridge'),
		'bridge_enquiry_source' => __('Page', 'bridge'),
		'date'                  => __('Received', 'bridge'),
	);
}
add_filter('manage_' . BRIDGE_ENQUIRY_POST_TYPE . '_posts_columns', 'bridge_enquiry_columns');

/**
 * One cell.
 *
 * The email and the phone number are links, because the only thing anybody
 * does on this screen is get in touch with the person in the row.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function bridge_enquiry_column(string $column, int $post_id): void
{
	if ('bridge_enquiry_email' === $column) {
		$email = (string) get_post_meta($post_id, 'bridge_enquiry_email', true);

		if ('' !== $email) {
			printf('<a href="%s">%s</a>', esc_url('mailto:' . $email), esc_html($email));
		}

		return;
	}

	if ('bridge_enquiry_phone' === $column) {
		$phone = (string) get_post_meta($post_id, 'bridge_enquiry_phone', true);

		if ('' !== $phone) {
			printf(
				'<a href="%s">%s</a>',
				esc_url('tel:' . preg_replace('/[^0-9+]/', '', $phone)),
				esc_html($phone)
			);
		} else {
			echo '&mdash;';
		}

		return;
	}

	if ('bridge_enquiry_source' === $column) {
		$source = (string) get_post_meta($post_id, 'bridge_enquiry_source', true);

		if ('' === $source) {
			echo '&mdash;';
			return;
		}

		printf(
			'<a href="%s">%s</a>',
			esc_url($source),
			esc_html(wp_parse_url($source, PHP_URL_PATH) ?: $source)
		);
	}
}
add_action('manage_' . BRIDGE_ENQUIRY_POST_TYPE . '_posts_custom_column', 'bridge_enquiry_column', 10, 2);

/**
 * Mark a held-back row in the list, beside the name.
 *
 * `post_states` rather than a column of its own: the states line is where
 * WordPress already says "Draft", and a held enquiry *is* a draft — this only
 * replaces the word with the reason there is one.
 *
 * @param string[] $states  Existing states.
 * @param WP_Post  $post    Post.
 * @return string[]
 */
function bridge_enquiry_post_state(array $states, WP_Post $post): array
{
	if (BRIDGE_ENQUIRY_POST_TYPE !== $post->post_type) {
		return $states;
	}

	if ('' === (string) get_post_meta($post->ID, 'bridge_enquiry_score', true)) {
		return $states;
	}

	unset($states['draft']);

	$states['bridge_spam'] = __('Held as possible spam', 'bridge');

	return $states;
}
add_filter('display_post_states', 'bridge_enquiry_post_state', 10, 2);

/**
 * The panel on a single enquiry.
 *
 * Everything the record holds that the title and the content do not, plus —
 * when there is one — the reason it was held back. Read-only: an enquiry is
 * what somebody sent, and a form for correcting their phone number would be a
 * form for quietly rewriting the evidence.
 */
function bridge_enquiry_meta_box(): void
{
	add_meta_box(
		'bridge-enquiry-details',
		__('Enquiry details', 'bridge'),
		'bridge_render_enquiry_meta_box',
		BRIDGE_ENQUIRY_POST_TYPE,
		'side',
		'high'
	);
}
add_action('add_meta_boxes_' . BRIDGE_ENQUIRY_POST_TYPE, 'bridge_enquiry_meta_box');

/**
 * Draw it.
 *
 * @param WP_Post $post Post.
 */
function bridge_render_enquiry_meta_box(WP_Post $post): void
{
	$email  = (string) get_post_meta($post->ID, 'bridge_enquiry_email', true);
	$phone  = (string) get_post_meta($post->ID, 'bridge_enquiry_phone', true);
	$source = (string) get_post_meta($post->ID, 'bridge_enquiry_source', true);
	$ip     = (string) get_post_meta($post->ID, 'bridge_enquiry_ip', true);
	$score  = (string) get_post_meta($post->ID, 'bridge_enquiry_score', true);
	$why    = (array) get_post_meta($post->ID, 'bridge_enquiry_reasons', true);
	?>
	<p>
		<strong><?php esc_html_e('Email', 'bridge'); ?></strong><br>
		<?php if ('' !== $email) : ?>
			<a href="<?php echo esc_url('mailto:' . $email); ?>"><?php echo esc_html($email); ?></a>
		<?php else : ?>
			&mdash;
		<?php endif; ?>
	</p>

	<p>
		<strong><?php esc_html_e('Phone', 'bridge'); ?></strong><br>
		<?php echo '' === $phone ? '&mdash;' : esc_html($phone); ?>
	</p>

	<p>
		<strong><?php esc_html_e('Sent from', 'bridge'); ?></strong><br>
		<?php if ('' !== $source) : ?>
			<a href="<?php echo esc_url($source); ?>"><?php echo esc_html($source); ?></a>
		<?php else : ?>
			&mdash;
		<?php endif; ?>
	</p>

	<?php if ('' !== $ip) : ?>
		<p>
			<strong><?php esc_html_e('Approximate address', 'bridge'); ?></strong><br>
			<code><?php echo esc_html($ip); ?></code><br>
			<span class="description">
				<?php esc_html_e('Anonymised — kept only to spot several messages arriving from one place.', 'bridge'); ?>
			</span>
		</p>
	<?php endif; ?>

	<?php if ('' !== $score) : ?>
		<div class="notice notice-warning inline" style="margin:12px 0 0;padding:8px 12px;">
			<p style="margin:0 0 6px;">
				<strong><?php esc_html_e('Held as possible spam', 'bridge'); ?></strong>
			</p>
			<ul style="margin:0 0 6px 18px;list-style:disc;">
				<?php foreach ($why as $reason) : ?>
					<li><?php echo esc_html((string) $reason); ?></li>
				<?php endforeach; ?>
			</ul>
			<p style="margin:0;" class="description">
				<?php esc_html_e('No email was sent for this one. If it is genuine, publish it and reply as normal.', 'bridge'); ?>
			</p>
		</div>
	<?php endif; ?>
	<?php
}

/* ---------------------------------------------------------------------------
 * The form's markup
 *
 * Here rather than in the block's render.php because render.php is included
 * once per block on the page: a page carrying two contact forms would redeclare
 * anything defined there and fatal. It is the same reason the logo slider's row
 * helper lives in inc/section-blocks.php.
 * ------------------------------------------------------------------------ */

/**
 * The address of the page the form is on.
 *
 * Where a redirect comes back to, and what is filed against the enquiry so a
 * client can see which page somebody was reading when they wrote. Our own
 * response arguments are stripped, so submitting twice does not accumulate
 * them and a stale ticket cannot be replayed.
 *
 * Built from the home URL's scheme and host with the request path appended,
 * rather than `home_url( $path )`. They are the same thing on most sites and
 * not on the ones that matter: REQUEST_URI is absolute from the domain root, so
 * it already carries the subdirectory a WordPress installed at /site/ lives in
 * — and home_url() would put it in a second time, sending every visitor's
 * redirect to /site/site/page/.
 *
 * @return string
 */
function bridge_enquiry_current_url(): string
{
	$path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
	$home = (array) wp_parse_url(home_url());

	$url = sprintf(
		'%s://%s%s%s',
		(string) ($home['scheme'] ?? 'http'),
		(string) ($home['host'] ?? ''),
		isset($home['port']) ? ':' . (int) $home['port'] : '',
		$path
	);

	return remove_query_arg(array('bridge-enquiry', 'bridge-form', 'bridge-ref'), $url);
}

/**
 * One field: its label, its control, and whatever is wrong with it.
 *
 * The error is joined to the input by `aria-describedby` rather than only
 * being printed near it, so a screen reader announces the reason when the
 * field takes focus instead of leaving it as something only a sighted visitor
 * can see. `aria-invalid` is what makes the field itself announce as wrong.
 *
 * @param string $key    Field key.
 * @param array  $field  Field description.
 * @param array  $args   form_id, required (bool), value, error.
 * @return string
 */
function bridge_enquiry_field_html(string $key, array $field, array $args): string
{
	$id       = $args['form_id'] . '-' . $key;
	$error    = (string) ($args['error'] ?? '');
	$value    = (string) ($args['value'] ?? '');
	$required = ! empty($args['required']);
	$describe = '' !== $error ? $id . '-error' : '';

	// Defaulted rather than read outright: the field list is filterable, and a
	// site that returns three of the four keys should get a plain control, not
	// a warning printed into the middle of its contact form.
	$attributes = sprintf(
		'id="%s" name="%s" maxlength="%d" autocomplete="%s" placeholder="%s"',
		esc_attr($id),
		esc_attr(bridge_enquiry_field_name($key)),
		(int) ($field['maxlength'] ?? 200),
		esc_attr((string) ($field['autocomplete'] ?? 'off')),
		esc_attr((string) ($field['placeholder'] ?? ''))
	);

	if ($required) {
		$attributes .= ' required';
	}

	if ('' !== $error) {
		$attributes .= sprintf(' aria-invalid="true" aria-describedby="%s"', esc_attr($describe));
	}

	$control = 'textarea' === ($field['type'] ?? 'text')
		? sprintf(
			'<textarea class="bridge-field__control" rows="%d" %s>%s</textarea>',
			(int) ($field['rows'] ?? 5),
			$attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — assembled from escaped parts above.
			esc_textarea($value)
		)
		: sprintf(
			'<input class="bridge-field__control" type="%s" value="%s" %s>',
			esc_attr((string) ($field['type'] ?? 'text')),
			esc_attr($value),
			$attributes // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — assembled from escaped parts above.
		);

	return sprintf(
		'<p class="bridge-field bridge-field--%1$s%2$s">' .
			'<label class="bridge-field__label" for="%3$s">%4$s%5$s</label>' .
			'%6$s' .
			'%7$s' .
			'</p>',
		esc_attr($key),
		'' !== $error ? ' is-invalid' : '',
		esc_attr($id),
		esc_html((string) ($field['label'] ?? $key)),
		// The asterisk is decoration: the control already carries `required`,
		// which is what assistive technology reads. Announcing "star" as well
		// would be the same fact twice, in a worse voice.
		$required ? '<span class="bridge-field__required" aria-hidden="true">*</span>' : '',
		$control, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — assembled from escaped parts above.
		'' !== $error
			? sprintf(
				'<span class="bridge-field__error" id="%s">%s</span>',
				esc_attr($describe),
				esc_html($error)
			)
			: ''
	);
}

/**
 * The panel a visitor sees once their message has gone.
 *
 * Replaces the form rather than sitting above it. A form still standing under
 * a "thank you" is an invitation to send the same message again, and the
 * commonest thing a client asks about a contact form is why they received
 * three copies of everything.
 *
 * Printed either way — as the answer on the redirect path, and hidden beside
 * the form for contact-form-view.js to reveal. So the two paths cannot end on
 * different markup, and the script has no copy of this wording, this icon or
 * these classes to keep in step.
 *
 * @param string $message The wording, from the block.
 * @param bool   $hidden  Whether it is waiting rather than showing.
 * @return string
 */
function bridge_enquiry_success_html(string $message, bool $hidden = false): string
{
	return sprintf(
		'<div class="bridge-contact__success" role="status" tabindex="-1" data-bridge-success%1$s>' .
			'<span class="bridge-contact__tick" aria-hidden="true">%2$s</span>' .
			'<p class="bridge-contact__success-text">%3$s</p>' .
			'</div>',
		$hidden ? ' hidden' : '',
		bridge_render_icon('check', array('size' => 'medium')),
		esc_html($message)
	);
}

/**
 * The whole form.
 *
 * @param array $args form_id, require_phone, button_label, success_message,
 *                    response (from bridge_enquiry_response()).
 * @return string
 */
function bridge_enquiry_form_html(array $args): string
{
	$form_id       = (string) $args['form_id'];
	$require_phone = ! empty($args['require_phone']);
	$response      = (array) ($args['response'] ?? array());
	$success       = (string) $args['success_message'];

	if ('sent' === ($response['status'] ?? '')) {
		return bridge_enquiry_success_html($success);
	}

	$errors = (array) ($response['errors'] ?? array());
	$values = (array) ($response['values'] ?? array());
	$notice = (string) ($response['notice'] ?? '');

	$fields = '';

	foreach (bridge_enquiry_fields() as $key => $field) {
		$fields .= bridge_enquiry_field_html(
			$key,
			$field,
			array(
				'form_id'  => $form_id,
				'required' => 'phone' === $key ? $require_phone : ! empty($field['required']),
				'value'    => (string) ($values[$key] ?? ''),
				'error'    => (string) ($errors[$key] ?? ''),
			)
		);
	}

	$stamp = bridge_enquiry_stamp($require_phone);

	$hidden = array(
		'action'            => BRIDGE_ENQUIRY_ACTION,
		'_bridge_nonce'     => wp_create_nonce(BRIDGE_ENQUIRY_ACTION),
		'_bridge_stamp'     => $stamp['stamp'],
		'_bridge_signature' => $stamp['signature'],
		'_bridge_source'    => bridge_enquiry_current_url(),
		'_bridge_form'      => $form_id,
	);

	$hidden_html = '';

	foreach ($hidden as $name => $value) {
		$hidden_html .= sprintf(
			'<input type="hidden" name="%s" value="%s">',
			esc_attr($name),
			esc_attr((string) $value)
		);
	}

	// Empty until contact-form-view.js fills it in. A browser that never runs
	// the script posts it empty, which is one point on the spam score and
	// never enough to stop a submission on its own — see the file header.
	$hidden_html .= '<input type="hidden" name="_bridge_js" value="" data-bridge-js>';

	/*
	 * The honeypot.
	 *
	 * Taken out of the flow by the stylesheet rather than by `type="hidden"`
	 * or `display:none` on the input: a bot that reads the markup skips both
	 * of those, and the field only works if it looks worth filling in. So it
	 * is an ordinary text input with an ordinary name, hidden the way a
	 * scripted reader will not notice — and taken out of the tab order and the
	 * accessibility tree so that a person never meets it either.
	 */
	$trap = sprintf(
		'<div class="bridge-field bridge-field--trap" aria-hidden="true">' .
			'<label for="%1$s">%2$s</label>' .
			'<input type="text" id="%1$s" name="%3$s" value="" tabindex="-1" autocomplete="off">' .
			'</div>',
		esc_attr($form_id . '-website'),
		esc_html__('Website', 'bridge'),
		esc_attr(bridge_enquiry_field_name('website'))
	);

	$banner = '';

	if ('' !== $notice) {
		// `role="alert"` rather than a polite region: this is drawn on a page
		// the visitor has just been returned to, and it has to be announced
		// when it appears rather than when they next happen to move.
		//
		// Not focusable. It was, until the focus style written for it turned
		// out to be a rule nothing could ever match: the alert announces
		// itself, and the caret belongs in the first field that needs fixing.
		$banner = sprintf(
			'<div class="bridge-contact__notice" role="alert" data-bridge-notice>%s</div>',
			esc_html($notice)
		);
	}

	return sprintf(
		'<form class="bridge-contact__form" id="%1$s" method="post" action="%2$s" data-bridge-enquiry>' .
			'%3$s%4$s%5$s' .
			'<div class="bridge-contact__grid">%6$s</div>' .
			'<div class="bridge-contact__actions">' .
			'<button type="submit" class="wp-element-button bridge-contact__submit">' .
			'<span class="bridge-contact__submit-label">%7$s</span>' .
			'<span class="bridge-contact__spinner" aria-hidden="true"></span>' .
			'</button>' .
			'<p class="bridge-contact__legend">%8$s</p>' .
			'</div>' .
			'</form>%9$s',
		esc_attr($form_id),
		esc_url(admin_url('admin-post.php')),
		$banner, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — assembled from escaped parts above.
		$hidden_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — assembled from escaped parts above.
		$trap, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — assembled from escaped parts above.
		$fields, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — assembled from escaped parts above.
		esc_html((string) $args['button_label']),
		esc_html__('* Required. We only use these details to answer your enquiry.', 'bridge'),
		// Waiting beside the form, for the script to reveal in place of it.
		bridge_enquiry_success_html($success, true) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — assembled from escaped parts above.
	);
}
