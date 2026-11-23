<?php

/**
 * Backward compatibility — the guardrail.
 *
 * Bridge runs on many sites. A theme update must not change a site that has
 * not asked for anything new: every new option ships switched off, and "off"
 * has to mean "exactly what the theme did before the option existed".
 *
 * These tests pin that promise for the parts of it that can be checked
 * without WordPress running:
 *
 *  - The compiled global styles for the four original button skins match a
 *    snapshot taken from the theme before the Hybrid header, Rounded skin and
 *    row-footer work (commit 725c271), key for key.
 *  - Every option value a site could have saved then is still accepted.
 *  - A token record saved before the new keys existed is read back with the
 *    old behaviour.
 *  - Each new block attribute defaults to its "off" value.
 *
 * If one of these fails, the change has altered sites that never opted in.
 * Make the change opt-in instead. Update the fixture only for a change that is
 * deliberately meant to reach every site, and say so in the commit message.
 * See "Backward compatibility" in guide.md.
 *
 * @package Bridge
 */

declare(strict_types=1);

final class BackCompatTest extends BridgeTestCase
{
	/** The skins that existed before any added since, in their original order. */
	private const LEGACY_SKINS = array('solid', 'edge', 'pill', 'block');

	/** @return array<string, mixed> */
	private function legacyFixture(): array
	{
		$json = file_get_contents(__DIR__ . '/fixtures/legacy-theme-json.json');
		$this->assertIsString($json, 'tests/fixtures/legacy-theme-json.json is missing.');

		$data = json_decode($json, true);
		$this->assertIsArray($data);

		return $data;
	}

	/** @return array<string, mixed> */
	private function blockJson(string $slug): array
	{
		$data = json_decode((string) file_get_contents(dirname(__DIR__) . '/src/blocks/' . $slug . '/block.json'), true);
		$this->assertIsArray($data, "src/blocks/{$slug}/block.json did not parse.");

		return $data;
	}

	// ---- Global styles ---------------------------------------------------

	/** @return array<string, array{0: string}> */
	public function legacySkins(): array
	{
		$cases = array();

		foreach (self::LEGACY_SKINS as $skin) {
			$cases[$skin] = array($skin);
		}

		return $cases;
	}

	/**
	 * The whole compiled theme.json fragment, not a few keys of it: a new
	 * setting that leaks into an existing skin's output shows up here
	 * wherever it lands. Compared as JSON so the check matches the fixture's
	 * own encoding.
	 *
	 * @dataProvider legacySkins
	 */
	public function test_an_original_skin_compiles_exactly_as_it_did(string $skin): void
	{
		$expected = $this->legacyFixture()[$skin] ?? null;
		$this->assertNotNull($expected, "No snapshot for the {$skin} skin.");

		$actual = json_decode(
			(string) json_encode(bridge_compile_theme_json(bridge_sanitize_tokens(array('buttons' => array('skin' => $skin)))), JSON_UNESCAPED_SLASHES),
			true
		);

		$this->assertSame(
			$expected,
			$actual,
			"The {$skin} skin's global styles changed. Sites that never opted in to anything new would render differently."
		);
	}

	/**
	 * Skins are stored by slug and offered in list order. New ones go on the
	 * end so the original four keep their slugs and their place.
	 */
	public function test_the_original_skins_keep_their_slugs_and_order(): void
	{
		$this->assertSame(
			self::LEGACY_SKINS,
			array_slice(array_keys(bridge_button_skins()), 0, count(self::LEGACY_SKINS))
		);
	}

	// ---- Saved settings ---------------------------------------------------

	/** @return array<string, array{0: string, 1: string, 2: string}> */
	public function legacyValues(): array
	{
		return array(
			'layout left'        => array('layout', '', 'left'),
			'layout centre'      => array('layout', '', 'centre'),
			'layout two-row'     => array('layout', '', 'two-row'),
			'menu size small'    => array('nav', 'fontSize', 'small'),
			'menu size medium'   => array('nav', 'fontSize', 'medium'),
		);
	}

	/**
	 * A value a site could have saved before must survive a save under the
	 * new theme — a value dropped by the sanitiser silently becomes the
	 * default, which is a site changing on update.
	 *
	 * @dataProvider legacyValues
	 */
	public function test_a_previously_valid_header_value_is_still_accepted(string $key, string $sub, string $value): void
	{
		$raw = '' === $sub ? array($key => $value) : array($key => array($sub => $value));

		$header = bridge_sanitize_tokens(array('header' => $raw))['header'];
		$saved  = '' === $sub ? $header[$key] : $header[$key][$sub];

		$this->assertSame($value, $saved);
	}

	/** @dataProvider legacySkins */
	public function test_a_previously_valid_skin_is_still_accepted(string $skin): void
	{
		$this->assertSame($skin, bridge_sanitize_tokens(array('buttons' => array('skin' => $skin)))['buttons']['skin']);
	}

	/**
	 * A record saved before the new keys existed, read under the new theme.
	 * Each new key has to come back at the value that reproduces what the
	 * theme did without it.
	 */
	public function test_a_record_saved_before_the_new_options_keeps_the_old_behaviour(): void
	{
		$header = bridge_sanitize_tokens(
			array(
				'header' => array(
					'layout' => 'left',
					'menus'  => array('primary' => 3, 'utility' => 0),
					'cta'    => array('enabled' => true, 'label' => 'Book', 'url' => '/book', 'color' => 'accent'),
					'nav'    => array('fontSize' => 'small'),
				),
			)
		)['header'];

		// Weight 500 is what the stylesheet hard-coded, and the header only
		// writes a weight when it differs from it.
		$this->assertSame('500', $header['nav']['weight']);
		// Empty is Auto: the CTA label colour is worked out as it always was.
		$this->assertSame('', $header['cta']['textColor']);
		// The locations dropdown is off.
		$this->assertFalse($header['locations']['enabled']);
		// No extra menus chosen; Hybrid's burger falls back to the main menu.
		$this->assertSame(0, $header['menus']['burger']);
		$this->assertSame(0, $header['menus']['locations']);
		// The saved values themselves are untouched.
		$this->assertSame('left', $header['layout']);
		$this->assertSame(3, $header['menus']['primary']);
	}

	/**
	 * Tertiary was added to the palette after sites had saved theirs. A record
	 * with no Tertiary takes that site's own Primary, so no site changes until
	 * someone picks a colour for it; a saved one is kept.
	 */
	public function test_tertiary_falls_back_to_the_site_primary(): void
	{
		$palette = bridge_sanitize_tokens(
			array('brand' => array('palette' => array('primary' => array('color' => '#123456'))))
		)['brand']['palette'];

		$this->assertSame('#123456', $palette['tertiary']['color']);

		$palette = bridge_sanitize_tokens(
			array('brand' => array('palette' => array(
				'primary'  => array('color' => '#123456'),
				'tertiary' => array('color' => '#34596F'),
			)))
		)['brand']['palette'];

		$this->assertSame('#34596F', $palette['tertiary']['color']);
	}

	/**
	 * The Alternative skin's button fill and card colour arrived after sites
	 * had saved theirs. With none saved they copy the Inverted ones, the other
	 * light-text ground; saved ones are kept.
	 */
	public function test_the_alternative_ground_copies_the_inverted_settings(): void
	{
		$tokens = bridge_sanitize_tokens(
			array(
				'buttons' => array('colors' => array('inverted' => 'secondary')),
				'cards'   => array('colors' => array('primary' => '#223344')),
			)
		);

		$this->assertSame('secondary', $tokens['buttons']['colors']['alternative']);
		$this->assertSame('#223344', $tokens['cards']['colors']['tertiary']);

		$tokens = bridge_sanitize_tokens(
			array(
				'buttons' => array('colors' => array('inverted' => 'secondary', 'alternative' => 'accent')),
				'cards'   => array('colors' => array('primary' => '#223344', 'tertiary' => '#445566')),
			)
		);

		$this->assertSame('accent', $tokens['buttons']['colors']['alternative']);
		$this->assertSame('#445566', $tokens['cards']['colors']['tertiary']);
	}

	/** The original three section skins keep their names and order; new ones go after. */
	public function test_the_original_section_skins_keep_their_names_and_order(): void
	{
		// Not in bootstrap.php's list; it loads cleanly against the stubs.
		require_once dirname(__DIR__) . '/inc/structure.php';

		$this->assertSame(
			array('bridge-surface', 'bridge-inverted', 'bridge-accent'),
			array_slice(array_keys(bridge_section_skins()), 0, 3)
		);
	}

	/** The original six palette slugs keep their names; new ones only add. */
	public function test_the_original_palette_slugs_are_all_still_there(): void
	{
		$slugs = array_keys(bridge_palette_slugs());

		foreach (array('primary', 'secondary', 'accent', 'background', 'surface', 'text') as $slug) {
			$this->assertContains($slug, $slugs);
		}
	}

	/** A fresh site gets the defaults it always got. */
	public function test_new_options_are_off_by_default(): void
	{
		$tokens = bridge_sanitize_tokens(array());

		$this->assertNotSame('hybrid', $tokens['header']['layout']);
		$this->assertNotSame('rounded', $tokens['buttons']['skin']);
		$this->assertSame('small', $tokens['header']['nav']['fontSize']);
		$this->assertSame('500', $tokens['header']['nav']['weight']);
		$this->assertFalse($tokens['header']['locations']['enabled']);
		$this->assertSame('', $tokens['header']['cta']['textColor']);
	}

	// ---- Blocks ------------------------------------------------------------

	/**
	 * New block attributes default to "off", so content saved before they
	 * existed renders as it did — and still validates, because none of these
	 * blocks saves markup of its own.
	 */
	public function test_new_block_attributes_default_to_off(): void
	{
		$hero = $this->blockJson('hero-banner')['attributes'];
		$this->assertSame(0, $hero['contentWidth']['default'], 'Hero content width: 0 is the wide container, as before.');

		$band = $this->blockJson('alternating-content')['attributes'];
		$this->assertSame('ratio', $band['sizing']['default'], 'Alternating rows: sized by the picture, as before.');
		$this->assertSame('16-9', $band['ratio']['default'], 'Alternating rows: 16:9, as before.');

		$goals = $this->blockJson('goals')['attributes'];
		$this->assertSame('bold', $goals['figureStyle']['default'], 'Goals: bold body-font figures, as before.');
		$this->assertSame('default', $goals['labelStyle']['default'], 'Goals: plain labels, as before.');
		$this->assertFalse($goals['dividers']['default'], 'Goals: no dividers, as before.');
		$this->assertFalse($goals['bottomRule']['default'], 'Goals: no bottom line, as before.');
		$this->assertSame('default', $goals['spacing']['default'], 'Goals: the site section padding, as before.');

		// New Goals blocks start in the heading font through the inserter's
		// default variation, which saves the setting into the block. Old
		// blocks never stored it, so they keep the attribute default: bold.
		$variation = $this->blockJson('goals')['variations'][0];
		$this->assertTrue($variation['isDefault']);
		$this->assertSame(array('inserter'), $variation['scope']);
		$this->assertSame(array('figureStyle' => 'heading'), $variation['attributes']);

		$features = $this->blockJson('feature-blocks')['attributes'];
		$this->assertSame('card', $features['panelStyle']['default'], 'Feature Blocks: card panels, as before.');
		$this->assertSame(array('card', 'plain', 'outline'), array_slice($features['panelStyle']['enum'], 0, 3), 'Feature Blocks: the original panel styles keep their names and order.');
		$this->assertSame('card', $features['imageCorners']['default'], 'Feature Blocks: the card radius, as before.');
		$this->assertSame('fill', $features['buttonStyle']['default'], 'Feature Blocks: the filled button, as before.');
		$this->assertSame('default', $features['buttonSize']['default'], 'Feature Blocks: the full-size button, as before.');

		$card = $this->blockJson('price-card')['attributes'];
		foreach (array('period', 'features', 'buttonText', 'buttonUrl') as $key) {
			$this->assertSame('', $card[$key]['default'], "Price card: no {$key} unless one is entered, as before.");
		}
		$this->assertSame('fill', $card['buttonStyle']['default']);
		$this->assertSame('center', $this->blockJson('price-table')['attributes']['textAlignment']['default'], 'Price table: centred cards, as before.');
		$this->assertTrue($this->blockJson('cards')['attributes']['showButton']['default'], 'Cards: Team cards keep their button, as before.');

		$goal = $this->blockJson('goal')['attributes'];
		$this->assertSame('auto', $goal['unitPosition']['default'], 'Goal: currency leads, everything else follows, as before.');
	}

	/** The row footer is added by its Row's switch only, never from the inserter. */
	public function test_the_row_footer_is_not_offered_in_the_inserter(): void
	{
		$footer = $this->blockJson('alternating-footer');

		$this->assertFalse($footer['supports']['inserter']);
		$this->assertSame(array('bridge/alternating-row'), $footer['parent']);
	}

	/**
	 * The alternating band's Set height reads the hero's presets through
	 * bridge_hero_metrics(), so those presets are now shared: changing one
	 * changes both blocks on every site using it.
	 */
	public function test_the_shared_height_presets_are_unchanged(): void
	{
		$this->assertSame('100dvh', bridge_hero_metrics(array('heightPreset' => 'full'))['height']);
		$this->assertSame('80dvh', bridge_hero_metrics(array('heightPreset' => 'tall'))['height']);
		$this->assertSame('60dvh', bridge_hero_metrics(array('heightPreset' => 'medium'))['height']);
		$this->assertSame('600px', bridge_hero_metrics(array('heightPreset' => 'custom', 'customHeight' => 600, 'customHeightUnit' => 'px'))['height']);
	}

	/**
	 * A footer saved before the Hybrid layout keeps its accent rule, has no
	 * button, and its layout names still map to the parts they did.
	 */
	public function test_footer_saved_before_hybrid_is_unchanged(): void
	{
		$tokens = bridge_sanitize_tokens(array('footer' => array('style' => 'columns')));

		$this->assertTrue($tokens['footer']['headingAccent'], 'Footer: the heading accent stays on.');
		$this->assertFalse($tokens['footer']['cta']['enabled'], 'Footer: no button unless switched on.');
		$this->assertTrue($tokens['footer']['legalBorder'], 'Footer: the copyright row keeps its top border.');
		$this->assertSame('', $tokens['footer']['legalColor'], 'Footer: the copyright row stays a shade of the footer.');
		$this->assertSame('columns', $tokens['footer']['style']);
		$this->assertSame('footer', bridge_footer_variants()['simple']);
		$this->assertSame('footer-columns', bridge_footer_variants()['columns']);
		$this->assertSame(array('simple', 'columns', 'hybrid'), bridge_token_constraints()['footer']['style']['options'], 'New layouts are appended.');
	}
}
