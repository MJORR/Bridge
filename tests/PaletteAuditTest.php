<?php

/**
 * inc/tokens.php — bridge_palette_audit() and bridge_card_audit().
 *
 * The Design tab prints these numbers next to a pass badge, so they are a
 * claim the theme makes to a client about their site. The tests hold two
 * things: that the figure shown is the figure the pair actually produces, and
 * that the pairs listed are ones the theme genuinely renders.
 *
 * The second matters more than it looks. An audit that measured combinations
 * the stylesheet never puts together would be noise wearing the authority of a
 * standard, and the first thing an operator learns from noise is to skip it.
 *
 * @package Bridge
 */

declare(strict_types=1);

final class PaletteAuditTest extends BridgeTestCase
{
	// ---- The pairs -------------------------------------------------------

	public function test_every_pair_names_real_palette_slugs(): void
	{
		$slugs = array_keys(bridge_palette_slugs());

		foreach (bridge_palette_pairs() as $pair) {
			$this->assertContains($pair['fg'], $slugs);
			$this->assertContains($pair['bg'], $slugs);
		}
	}

	/**
	 * Every pair is body-sized text, so every threshold is 4.5:1.
	 *
	 * If a pair is ever added at 3:1 it will be because it is large text or a
	 * non-text boundary, and that is a decision worth having to change a test
	 * to make.
	 */
	public function test_every_pair_is_held_to_the_body_text_threshold(): void
	{
		foreach (bridge_palette_pairs() as $pair) {
			$this->assertSame(4.5, $pair['min']);
		}
	}

	public function test_the_pairs_cover_all_three_section_skins(): void
	{
		$grounds = array();

		foreach (bridge_palette_pairs() as $pair) {
			$grounds[$pair['bg']] = true;
		}

		// The page itself, plus Surface, Accent and Inverted — the three skins
		// in components/_sections.scss. A skin missing from here is a band
		// whose legibility nothing on the screen reports.
		foreach (array('background', 'surface', 'accent', 'primary') as $slug) {
			$this->assertArrayHasKey($slug, $grounds, "No pair lands on {$slug}");
		}
	}

	// ---- The measurement -------------------------------------------------

	/**
	 * The reported ratio is the ratio of the two colours reported with it.
	 *
	 * @dataProvider provide_palettes
	 */
	public function test_the_audit_measures_the_colours_it_names(array $overrides): void
	{
		$tokens = bridge_sanitize_tokens($this->palette($overrides));

		foreach (bridge_palette_audit($tokens) as $check) {
			$this->assertSame(
				bridge_contrast_ratio($check['fg'], $check['bg']),
				$check['ratio'],
				"{$check['label']} {$check['on']}"
			);

			$this->assertSame($check['ratio'] >= $check['min'], $check['pass']);
		}
	}

	/** @return array<string, array{0: array<string, string>}> */
	public static function provide_palettes(): array
	{
		return array(
			'the shipped defaults' => array(array()),
			'a purple brand'       => array(array('primary' => '#63085f', 'surface' => '#faf7f0')),
			'a pale text colour'   => array(array('text' => '#cccccc')),
			'a dark background'    => array(array('background' => '#101014')),
		);
	}

	/**
	 * The shipped palette passes every check.
	 *
	 * A regression guard on the defaults themselves: they are what a new
	 * client site opens with, and shipping a default brand that fails its own
	 * audit would be the most embarrassing possible bug in this screen.
	 */
	public function test_the_shipped_palette_passes_every_check(): void
	{
		foreach (bridge_palette_audit(bridge_sanitize_tokens(array())) as $check) {
			$this->assertTrue(
				$check['pass'],
				sprintf(
					'%s %s is %s:1',
					$check['label'],
					$check['on'],
					$check['ratio']
				)
			);
		}
	}

	/** And a palette that genuinely fails is reported as failing. */
	public function test_an_illegible_palette_fails(): void
	{
		$tokens = bridge_sanitize_tokens($this->palette(array('text' => '#eeeeee')));
		$failed = array_filter(
			bridge_palette_audit($tokens),
			static fn ($check) => ! $check['pass']
		);

		// Body text on Background, on Surface and on Accent all use `text`.
		$this->assertCount(3, $failed);
	}

	public function test_the_audit_reports_lowercase_hex_colours(): void
	{
		$tokens = bridge_sanitize_tokens($this->palette(array('primary' => '#0F172A')));

		foreach (bridge_palette_audit($tokens) as $check) {
			$this->assertMatchesRegularExpression('/^#[0-9a-f]{3,6}$/', $check['fg']);
			$this->assertMatchesRegularExpression('/^#[0-9a-f]{3,6}$/', $check['bg']);
		}
	}

	// ---- Cards -----------------------------------------------------------

	public function test_there_is_a_card_check_for_every_ground(): void
	{
		$checks = bridge_card_audit(bridge_sanitize_tokens(array()));

		$this->assertSame(
			array_keys(bridge_card_grounds()),
			array_column($checks, 'key')
		);
	}

	/**
	 * The card's text ratio is measured against the band's foreground, not the
	 * card's own — a card has no foreground of its own, which is the whole
	 * reason this check exists.
	 */
	public function test_card_text_is_measured_against_the_bands_foreground(): void
	{
		$tokens  = bridge_sanitize_tokens(array());
		$palette = $tokens['brand']['palette'];
		$grounds = bridge_card_grounds();

		foreach (bridge_card_audit($tokens) as $check) {
			$entry = $grounds[$check['key']];

			$this->assertSame(
				bridge_contrast_ratio(
					$check['card'],
					$palette[$entry['text']]['color']
				),
				$check['text']['ratio']
			);
		}
	}

	public function test_the_shipped_card_colours_carry_their_bands_text(): void
	{
		foreach (bridge_card_audit(bridge_sanitize_tokens(array())) as $check) {
			$this->assertTrue($check['text']['pass'], $check['label']);
		}
	}

	/**
	 * The band figure is reported and not graded.
	 *
	 * The shipped Light card is 1.09:1 against the page. Held to the 3:1 a
	 * control owes, all four defaults would fail — which is the bug this
	 * shape of the check exists to avoid, so the absence of a `pass` key is
	 * asserted rather than assumed.
	 */
	public function test_the_band_figure_carries_no_pass_or_fail(): void
	{
		foreach (bridge_card_audit(bridge_sanitize_tokens(array())) as $check) {
			$this->assertArrayHasKey('ratio', $check['band']);
			$this->assertArrayNotHasKey('pass', $check['band']);
			$this->assertArrayNotHasKey('min', $check['band']);
		}
	}

	public function test_the_shipped_cards_are_not_flagged_as_flat(): void
	{
		foreach (bridge_card_audit(bridge_sanitize_tokens(array())) as $check) {
			$this->assertFalse($check['band']['flat'], $check['label']);
		}
	}

	/** A card the colour of its band, with nothing lifting it, is invisible. */
	public function test_an_invisible_card_is_flagged(): void
	{
		$checks = bridge_card_audit(
			bridge_sanitize_tokens(
				array(
					'cards' => array(
						'shadow' => 'none',
						// The Light card sits on `background`, which is white.
						'colors' => array('light' => '#ffffff'),
					),
				)
			)
		);

		$light = array_column($checks, null, 'key')['light'];

		$this->assertTrue($light['band']['flat']);
	}

	/** The same card is fine once a shadow lifts it off the page. */
	public function test_a_shadow_rescues_a_card_that_matches_its_band(): void
	{
		$checks = bridge_card_audit(
			bridge_sanitize_tokens(
				array(
					'cards' => array(
						'shadow' => 'soft',
						'colors' => array('light' => '#ffffff'),
					),
				)
			)
		);

		$light = array_column($checks, null, 'key')['light'];

		$this->assertFalse($light['band']['flat']);
		$this->assertSame(1.0, $light['band']['ratio']);
	}
}
