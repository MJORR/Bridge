<?php

/**
 * inc/tokens.php — bridge_sanitize_tokens().
 *
 * The one place token input is trusted, and the reason the theme can never be
 * left without a valid design system. Its contract is totality: anything
 * missing, malformed or out of range is replaced with a default rather than
 * rejected, so a half-filled REST payload, a hand-edited option row or a
 * migration from another site can never produce a broken record.
 *
 * These tests are written against that contract rather than against the
 * implementation — they assert what a caller may rely on, which is what makes
 * them worth keeping when the inside changes.
 *
 * @package Bridge
 */

declare(strict_types=1);

final class SanitizeTokensTest extends BridgeTestCase
{
	/** Every group the rest of the theme reads off the record. */
	private const GROUPS = array(
		'brand',
		'typography',
		'icons',
		'buttons',
		'cards',
		'header',
		'footer',
		'layout',
		'blocks',
		'site',
	);

	// ---- Totality --------------------------------------------------------

	public function test_an_empty_payload_produces_a_complete_record(): void
	{
		$tokens = bridge_sanitize_tokens(array());

		foreach (self::GROUPS as $group) {
			$this->assertArrayHasKey($group, $tokens, "Missing group: {$group}");
		}
	}

	/**
	 * Garbage of the wrong *shape*, not merely the wrong value.
	 *
	 * A record hand-edited in the database, or restored from a site running an
	 * older version of the theme, can carry a string where a map belongs. The
	 * sanitiser has to survive that without a PHP notice, because the
	 * alternative is a fatal on every page load of a live site.
	 *
	 * @dataProvider provide_malformed_payloads
	 */
	public function test_malformed_input_still_produces_a_complete_record(array $payload): void
	{
		$tokens = bridge_sanitize_tokens($payload);

		foreach (self::GROUPS as $group) {
			$this->assertArrayHasKey($group, $tokens);
		}

		$this->assertSame(
			bridge_token_defaults()['typography']['fontSet'],
			$tokens['typography']['fontSet']
		);
	}

	/** @return array<string, array{0: array<string, mixed>}> */
	public static function provide_malformed_payloads(): array
	{
		return array(
			'scalars where maps belong' => array(
				array(
					'brand'      => 'not an array',
					'typography' => 42,
					'layout'     => null,
					'cards'      => false,
					'buttons'    => 'solid',
				),
			),
			'nested nonsense'           => array(
				array(
					'brand'   => array('palette' => 'red'),
					'header'  => array('logo' => 7, 'cta' => 'yes', 'menus' => 0),
					'buttons' => array('colors' => 'primary'),
				),
			),
			'unknown keys only'         => array(
				array('nonsense' => array('deeply' => array('nested' => true))),
			),
			'lists where maps belong'   => array(
				array('typography' => array('baseSize' => array(1, 2, 3))),
			),
		);
	}

	/**
	 * Sanitising twice changes nothing.
	 *
	 * The record is stored sanitised and re-sanitised on every read, so any
	 * value that moved on a second pass would drift a little further every
	 * time the options page was opened.
	 */
	public function test_sanitising_is_idempotent(): void
	{
		$once  = bridge_sanitize_tokens(array('typography' => array('baseSize' => 3.4)));
		$twice = bridge_sanitize_tokens($once);

		$this->assertSame($once, $twice);
	}

	// ---- Numeric clamping ------------------------------------------------

	/**
	 * @dataProvider provide_out_of_range_numbers
	 */
	public function test_numbers_are_clamped_to_their_declared_range(
		string $group,
		string $key,
		$input,
		string $expect
	): void {
		$tokens     = bridge_sanitize_tokens(array($group => array($key => $input)));
		$constraint = bridge_token_constraints()[$group][$key];
		$value      = $tokens[$group][$key];

		if ('min' === $expect) {
			$this->assertEqualsWithDelta($constraint['min'], $value, 0.0001);
		} elseif ('max' === $expect) {
			$this->assertEqualsWithDelta($constraint['max'], $value, 0.0001);
		} else {
			$this->assertEqualsWithDelta(
				bridge_token_defaults()[$group][$key],
				$value,
				0.0001
			);
		}
	}

	/** @return array<string, array{0: string, 1: string, 2: mixed, 3: string}> */
	public static function provide_out_of_range_numbers(): array
	{
		return array(
			'base size below floor'  => array('typography', 'baseSize', 0.1, 'min'),
			'base size above cap'    => array('typography', 'baseSize', 99, 'max'),
			'base size not a number' => array('typography', 'baseSize', 'large', 'default'),
			'base size null'         => array('typography', 'baseSize', null, 'default'),
			'scale ratio below'      => array('typography', 'scaleRatio', 0.5, 'min'),
			'scale ratio above'      => array('typography', 'scaleRatio', 10, 'max'),
			'line height below'      => array('typography', 'bodyLineHeight', 0.2, 'min'),
			'content size below'     => array('layout', 'contentSize', 10, 'min'),
			'content size above'     => array('layout', 'contentSize', 99999, 'max'),
			'spacing base above'     => array('layout', 'spacingBase', 40, 'max'),
		);
	}

	public function test_a_numeric_string_is_accepted(): void
	{
		$tokens = bridge_sanitize_tokens(array('typography' => array('baseSize' => '1.25')));

		$this->assertEqualsWithDelta(1.25, $tokens['typography']['baseSize'], 0.0001);
	}

	/**
	 * A wide width narrower than the content width is always a mistake.
	 *
	 * `alignwide` would render visibly *smaller* than the default column, so
	 * the sanitiser raises wide to meet content rather than storing a layout
	 * that cannot look right.
	 */
	public function test_wide_width_is_never_narrower_than_content_width(): void
	{
		$tokens = bridge_sanitize_tokens(
			array('layout' => array('contentSize' => 900, 'wideSize' => 500))
		);

		$this->assertSame(900, $tokens['layout']['contentSize']);
		$this->assertSame(900, $tokens['layout']['wideSize']);
	}

	public function test_a_card_radius_is_stored_as_a_whole_number(): void
	{
		$tokens = bridge_sanitize_tokens(array('cards' => array('radius' => 6.4)));

		$this->assertSame(6, $tokens['cards']['radius']);
	}

	// ---- Enumerations ----------------------------------------------------

	/**
	 * @dataProvider provide_enumerated_tokens
	 */
	public function test_an_unknown_option_falls_back_to_the_default(
		string $group,
		string $key
	): void {
		$tokens = bridge_sanitize_tokens(array($group => array($key => 'nonsense')));

		$this->assertSame(
			bridge_token_defaults()[$group][$key],
			$tokens[$group][$key]
		);
	}

	/**
	 * @dataProvider provide_enumerated_tokens
	 */
	public function test_every_declared_option_is_accepted(string $group, string $key): void
	{
		foreach (bridge_token_constraints()[$group][$key]['options'] as $option) {
			$tokens = bridge_sanitize_tokens(array($group => array($key => $option)));

			$this->assertSame($option, $tokens[$group][$key]);
		}
	}

	/** @return array<string, array{0: string, 1: string}> */
	public static function provide_enumerated_tokens(): array
	{
		return array(
			'heading weight' => array('typography', 'headingWeight'),
			'heading case'   => array('typography', 'headingCase'),
			'icon weight'    => array('icons', 'weight'),
			'button skin'    => array('buttons', 'skin'),
			'card padding'   => array('cards', 'padding'),
			'card shadow'    => array('cards', 'shadow'),
			'header layout'  => array('header', 'layout'),
			'footer style'   => array('footer', 'style'),
			'block gap'      => array('layout', 'blockGap'),
		);
	}

	public function test_an_unknown_font_set_falls_back(): void
	{
		$tokens = bridge_sanitize_tokens(array('typography' => array('fontSet' => 'comic')));

		$this->assertSame('system', $tokens['typography']['fontSet']);
	}

	public function test_every_registered_font_set_is_accepted(): void
	{
		foreach (array_keys(bridge_font_sets()) as $slug) {
			$tokens = bridge_sanitize_tokens(array('typography' => array('fontSet' => $slug)));

			$this->assertSame($slug, $tokens['typography']['fontSet']);
		}
	}

	// ---- Palette ---------------------------------------------------------

	public function test_a_valid_hex_survives(): void
	{
		$tokens = bridge_sanitize_tokens($this->palette(array('primary' => '#63085f')));

		$this->assertSame('#63085f', $tokens['brand']['palette']['primary']['color']);
	}

	public function test_a_three_digit_hex_survives(): void
	{
		$tokens = bridge_sanitize_tokens($this->palette(array('accent' => '#abc')));

		$this->assertSame('#abc', $tokens['brand']['palette']['accent']['color']);
	}

	/**
	 * @dataProvider provide_invalid_colors
	 */
	public function test_an_invalid_colour_falls_back_to_the_default(string $color): void
	{
		$tokens = bridge_sanitize_tokens(
			array('brand' => array('palette' => array('primary' => array('color' => $color))))
		);

		$this->assertSame(
			bridge_token_defaults()['brand']['palette']['primary']['color'],
			$tokens['brand']['palette']['primary']['color']
		);
	}

	/** @return array<string, array{0: string}> */
	public static function provide_invalid_colors(): array
	{
		return array(
			'no hash'      => array('63085f'),
			'named colour' => array('rebeccapurple'),
			'rgb function' => array('rgb(1,2,3)'),
			'with alpha'   => array('#63085fff'),
			'empty'        => array(''),
		);
	}

	public function test_the_palette_holds_exactly_the_theme_slugs(): void
	{
		$tokens = bridge_sanitize_tokens(
			array(
				'brand' => array(
					'palette' => array(
						// A slug the theme does not have, which must not survive:
						// palette slugs become class names in post content.
						'tertiary' => array('color' => '#123456', 'name' => 'Tertiary'),
					),
				),
			)
		);

		$this->assertSame(
			array_keys(bridge_palette_slugs()),
			array_keys($tokens['brand']['palette'])
		);
	}

	public function test_an_empty_name_falls_back_to_the_slug_name(): void
	{
		$tokens = bridge_sanitize_tokens(
			array('brand' => array('palette' => array('primary' => array('name' => '   '))))
		);

		$this->assertSame('Primary', $tokens['brand']['palette']['primary']['name']);
	}

	// ---- Buttons ---------------------------------------------------------

	public function test_button_fills_are_keyed_by_ground(): void
	{
		$tokens = bridge_sanitize_tokens(array());

		$this->assertSame(
			array_keys(bridge_button_grounds()),
			array_keys($tokens['buttons']['colors'])
		);
	}

	public function test_an_unknown_button_fill_falls_back_to_the_grounds_default(): void
	{
		$tokens = bridge_sanitize_tokens(
			array('buttons' => array('colors' => array('default' => 'chartreuse')))
		);

		$this->assertSame(
			bridge_button_grounds()['default']['fill'],
			$tokens['buttons']['colors']['default']
		);
	}

	public function test_a_ground_the_theme_does_not_paint_is_dropped(): void
	{
		$tokens = bridge_sanitize_tokens(
			array('buttons' => array('colors' => array('sidebar' => 'primary')))
		);

		$this->assertArrayNotHasKey('sidebar', $tokens['buttons']['colors']);
	}

	public function test_every_palette_slug_is_a_valid_button_fill(): void
	{
		foreach (array_keys(bridge_palette_slugs()) as $slug) {
			$tokens = bridge_sanitize_tokens(
				array('buttons' => array('colors' => array('accent' => $slug)))
			);

			$this->assertSame($slug, $tokens['buttons']['colors']['accent']);
		}
	}

	// ---- Blocks ----------------------------------------------------------

	public function test_block_lists_are_sorted_and_deduplicated(): void
	{
		$tokens = bridge_sanitize_tokens(
			array(
				'blocks' => array(
					'disabled' => array('core/table', 'core/quote', 'core/table'),
				),
			)
		);

		$this->assertSame(array('core/quote', 'core/table'), $tokens['blocks']['disabled']);
	}

	public function test_a_malformed_block_name_is_dropped(): void
	{
		$tokens = bridge_sanitize_tokens(
			array(
				'blocks' => array(
					'disabled' => array('not a block', 'core/', '/table', 42, 'core/quote'),
				),
			)
		);

		$this->assertSame(array('core/quote'), $tokens['blocks']['disabled']);
	}

	/**
	 * The editor stops working without these, so switching one off is not a
	 * choice the record is allowed to hold.
	 */
	public function test_required_blocks_cannot_be_disabled(): void
	{
		$tokens = bridge_sanitize_tokens(
			array('blocks' => array('disabled' => bridge_required_blocks()))
		);

		$this->assertSame(array(), $tokens['blocks']['disabled']);
	}

	/** A block in both lists is a contradiction, and off is the safer reading. */
	public function test_disabled_wins_over_enabled(): void
	{
		$tokens = bridge_sanitize_tokens(
			array(
				'blocks' => array(
					'enabled'  => array('core/table'),
					'disabled' => array('core/table'),
				),
			)
		);

		$this->assertSame(array(), $tokens['blocks']['enabled']);
		$this->assertSame(array('core/table'), $tokens['blocks']['disabled']);
	}

	// ---- Back-compatibility ----------------------------------------------

	/**
	 * Records written before these settings were renamed still resolve.
	 *
	 * Both are one-way reads with no migration behind them, so nothing else
	 * would catch it if the fallback were dropped — the site would simply lose
	 * its top bar, or its light logo, at the next save.
	 */
	public function test_the_legacy_cta_bar_key_still_switches_the_top_bar_on(): void
	{
		$tokens = bridge_sanitize_tokens(array('header' => array('ctaBar' => true)));

		$this->assertTrue($tokens['header']['topBar']);
	}

	public function test_the_legacy_inverse_logo_key_is_read_as_the_light_logo(): void
	{
		$tokens = bridge_sanitize_tokens(
			array('header' => array('logo' => array('inverseId' => 7)))
		);

		$this->assertSame(7, $tokens['header']['logo']['lightId']);
	}

	public function test_the_current_light_logo_key_wins_over_the_legacy_one(): void
	{
		$tokens = bridge_sanitize_tokens(
			array('header' => array('logo' => array('lightId' => 9, 'inverseId' => 7)))
		);

		$this->assertSame(9, $tokens['header']['logo']['lightId']);
	}

	/**
	 * A call to action was implied by having filled both fields, before it had
	 * a switch of its own. Records written under that rule must not have their
	 * button turned off by the upgrade.
	 */
	public function test_a_legacy_cta_with_both_fields_is_inferred_as_enabled(): void
	{
		$tokens = bridge_sanitize_tokens(
			array(
				'header' => array(
					'cta' => array('label' => 'Contact', 'url' => 'https://example.com'),
				),
			)
		);

		$this->assertTrue($tokens['header']['cta']['enabled']);
	}

	public function test_an_explicit_cta_switch_is_respected(): void
	{
		$tokens = bridge_sanitize_tokens(
			array(
				'header' => array(
					'cta' => array(
						'enabled' => false,
						'label'   => 'Contact',
						'url'     => 'https://example.com',
					),
				),
			)
		);

		$this->assertFalse($tokens['header']['cta']['enabled']);
	}

	// ---- The stored round trip -------------------------------------------

	/**
	 * What is written is what comes back, sanitised.
	 *
	 * The memo is cleared by hand here because nothing dispatches hooks in
	 * these tests; on a live site `bridge_flush_tokens()` does it, hung off
	 * `updated_option`.
	 */
	public function test_a_saved_record_is_read_back_sanitised(): void
	{
		bridge_update_tokens(
			array(
				'typography' => array('baseSize' => 99),
				'buttons'    => array('skin' => 'edge'),
			)
		);

		bridge_tokens_memo(null, true);
		$tokens = bridge_get_tokens();

		$this->assertSame('edge', $tokens['buttons']['skin']);
		$this->assertEqualsWithDelta(
			bridge_token_constraints()['typography']['baseSize']['max'],
			$tokens['typography']['baseSize'],
			0.0001
		);
	}

	public function test_a_patch_leaves_the_rest_of_the_record_alone(): void
	{
		bridge_update_tokens(
			bridge_sanitize_tokens(array('buttons' => array('skin' => 'pill')))
		);
		bridge_tokens_memo(null, true);

		bridge_patch_tokens(array('icons' => array('weight' => 'bold')));
		bridge_tokens_memo(null, true);

		$tokens = bridge_get_tokens();

		$this->assertSame('bold', $tokens['icons']['weight']);
		$this->assertSame('pill', $tokens['buttons']['skin']);
	}

	// ---- Card styles -----------------------------------------------------

	/**
	 * A style only ever holds the fields it declares.
	 *
	 * The record is walked from `bridge_card_styles()` rather than from what
	 * was submitted, which is what stops a payload inventing a style or giving
	 * a real style a field it does not take. Both would reach the compiler,
	 * and the compiler trusts the record.
	 */
	public function test_a_card_style_keeps_only_the_fields_it_declares(): void
	{
		$tokens = bridge_sanitize_tokens(
			array(
				'cards' => array(
					'styles' => array(
						// `avatar` is Portrait's, not Tile's.
						'tile'     => array('ratio' => '4-3', 'avatar' => 'l'),
						'invented' => array('heading' => 'small'),
					),
				),
			)
		);

		$styles = $tokens['cards']['styles'];

		$this->assertArrayNotHasKey('invented', $styles);
		$this->assertArrayNotHasKey('avatar', $styles['tile']);
		$this->assertSame('4-3', $styles['tile']['ratio']);
		$this->assertSame(
			array_keys(bridge_card_styles()),
			array_keys($styles)
		);
	}

	/** An unknown slug falls back to the style's own default, not to empty. */
	public function test_an_unknown_card_style_value_falls_back_to_its_default(): void
	{
		$styles = bridge_sanitize_tokens(
			array(
				'cards' => array(
					'styles' => array(
						'summary' => array('heading' => 'gigantic', 'ratio' => '21-9'),
					),
				),
			)
		)['cards']['styles'];

		$defaults = bridge_card_styles()['summary'];

		$this->assertSame($defaults['heading'], $styles['summary']['heading']);
		$this->assertSame($defaults['ratio'], $styles['summary']['ratio']);
	}

	/**
	 * The site's excerpt length is clamped, not trusted.
	 *
	 * It reaches wp_trim_words() in the Cards block, and the block's own
	 * setting is checked against the same bounds — so a record holding 9999
	 * would be the one path to a card carrying an entire post.
	 */
	public function test_the_card_excerpt_length_is_clamped(): void
	{
		$constraint = bridge_token_constraints()['cards']['excerpt'];

		$this->assertSame(
			$constraint['max'],
			bridge_sanitize_tokens(
				array('cards' => array('excerpt' => 9999))
			)['cards']['excerpt']
		);

		$this->assertSame(
			$constraint['min'],
			bridge_sanitize_tokens(
				array('cards' => array('excerpt' => -5))
			)['cards']['excerpt']
		);
	}
}
