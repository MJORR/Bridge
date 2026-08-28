<?php

/**
 * inc/tokens.php — bridge_button_schemes().
 *
 * The function that turns four palette choices into every button colour on the
 * site, and the one place the theme makes an accessibility promise it can be
 * held to. The options screen prints the ratios these produce next to a
 * pass/fail badge; if the arithmetic drifts, the badge keeps saying pass.
 *
 * The palettes swept below are deliberately hostile — a brand whose primary is
 * nearly its background, a monochrome brand, a brand of mid-greys — because
 * the well-behaved case was already correct before any of this was written.
 *
 * @package Bridge
 */

declare(strict_types=1);

final class ButtonSchemesTest extends BridgeTestCase
{
	/**
	 * Resolve the schemes for a palette, through the real sanitiser.
	 *
	 * @param array<string, string> $palette  Slug => hex.
	 * @param string                $skin     Button skin slug.
	 * @param array<string, string> $fills    Ground key => palette slug.
	 * @return array<string, array<string, mixed>>
	 */
	private function schemes(array $palette = array(), string $skin = 'solid', array $fills = array()): array
	{
		$tokens = bridge_sanitize_tokens(
			array_merge(
				$this->palette($palette),
				array('buttons' => array('skin' => $skin, 'colors' => $fills))
			)
		);

		return bridge_button_schemes($tokens);
	}

	// ---- Shape -----------------------------------------------------------

	public function test_there_is_a_scheme_for_every_ground(): void
	{
		$this->assertSame(
			array_keys(bridge_button_grounds()),
			array_keys($this->schemes())
		);
	}

	public function test_every_scheme_carries_every_colour(): void
	{
		$expected = array(
			'bg',
			'fg',
			'hoverBg',
			'hoverFg',
			'border',
			'hoverBorder',
			'ghost',
			'ghostHover',
			'ring',
			'audit',
		);

		foreach ($this->schemes() as $ground => $scheme) {
			$this->assertSame($expected, array_keys($scheme), "Ground: {$ground}");
		}
	}

	public function test_every_colour_is_a_lowercase_hex(): void
	{
		foreach ($this->schemes() as $ground => $scheme) {
			unset($scheme['audit']);

			foreach ($scheme as $key => $value) {
				$this->assertMatchesRegularExpression(
					'/^#[0-9a-f]{3}([0-9a-f]{3})?$/',
					$value,
					"{$ground}.{$key} is not a lowercase hex: {$value}"
				);
			}
		}
	}

	// ---- The promise -----------------------------------------------------

	/**
	 * Every label clears 4.5:1, on every ground, for every palette.
	 *
	 * This is the claim the Buttons tab makes in prose and the reason the fill
	 * is the only colour an operator picks. It has to hold for palettes nobody
	 * would design on purpose, because the sanitiser will accept them.
	 *
	 * @dataProvider provide_hostile_palettes
	 */
	public function test_labels_are_legible_on_every_ground(array $palette): void
	{
		foreach (bridge_button_skins() as $skin => $_) {
			foreach ($this->schemes($palette, $skin) as $ground => $scheme) {
				$this->assertGreaterThanOrEqual(
					4.5,
					bridge_contrast_ratio($scheme['bg'], $scheme['fg']),
					"Resting label failed on {$ground} ({$skin})"
				);

				$this->assertGreaterThanOrEqual(
					4.5,
					bridge_contrast_ratio($scheme['hoverBg'], $scheme['hoverFg']),
					"Hover label failed on {$ground} ({$skin})"
				);
			}
		}
	}

	/**
	 * And the reported ratio is the real one.
	 *
	 * The audit is what the options screen prints. A number that agreed with
	 * nothing but itself would be worse than printing none at all.
	 *
	 * @dataProvider provide_hostile_palettes
	 */
	public function test_the_audit_matches_the_colours_it_describes(array $palette): void
	{
		foreach ($this->schemes($palette) as $ground => $scheme) {
			$audit = $scheme['audit'];

			$this->assertSame(
				bridge_contrast_ratio($scheme['bg'], $scheme['fg']),
				$audit['label'],
				"Ground: {$ground}"
			);
			$this->assertSame(
				bridge_contrast_ratio($scheme['hoverBg'], $scheme['hoverFg']),
				$audit['hoverLabel'],
				"Ground: {$ground}"
			);
			$this->assertSame(
				bridge_contrast_ratio($scheme['ghost'], $scheme['ghostHover']),
				$audit['ghost'],
				"Ground: {$ground}"
			);
		}
	}

	/**
	 * Every fill either stands off its band, or is given an edge that does.
	 *
	 * WCAG 1.4.11 in one assertion: a control has to have a discernible
	 * boundary. The interesting case is `background` on the Default ground —
	 * a white button on a white page, which has no edge of its own at all.
	 *
	 * @dataProvider provide_hostile_palettes
	 */
	public function test_every_button_has_a_discernible_boundary(array $palette): void
	{
		$grounds = bridge_button_grounds();

		foreach (array_keys(bridge_palette_slugs()) as $fill) {
			$fills   = array_fill_keys(array_keys($grounds), $fill);
			$schemes = $this->schemes($palette, 'solid', $fills);

			foreach ($schemes as $ground => $scheme) {
				$this->assertGreaterThanOrEqual(
					3.0,
					$scheme['audit']['boundary'],
					"Boundary failed on {$ground} with a {$fill} fill"
				);
			}
		}
	}

	/** @return array<string, array{0: array<string, string>}> */
	public static function provide_hostile_palettes(): array
	{
		return array(
			'the shipped defaults' => array(array()),
			'a purple brand'       => array(array('primary' => '#63085f', 'surface' => '#faf7f0')),
			'monochrome'           => array(
				array(
					'primary'    => '#000000',
					'secondary'  => '#000000',
					'accent'     => '#ffffff',
					'background' => '#ffffff',
					'surface'    => '#ffffff',
					'text'       => '#000000',
				),
			),
			'all mid grey'         => array(
				array(
					'primary'    => '#808080',
					'secondary'  => '#7a7a7a',
					'accent'     => '#888888',
					'background' => '#828282',
					'surface'    => '#7e7e7e',
					'text'       => '#818181',
				),
			),
			'a dark brand'         => array(
				array(
					'background' => '#101014',
					'surface'    => '#18181d',
					'text'       => '#f4f4f5',
					'primary'    => '#f4f4f5',
				),
			),
			'near-identical'       => array(
				array('primary' => '#fefefe', 'background' => '#ffffff'),
			),
		);
	}

	// ---- The boundary rule -----------------------------------------------

	/**
	 * A fill that cannot be its own edge is given one, in the band's
	 * foreground — and the audit says so.
	 */
	public function test_a_fill_that_matches_its_band_is_bordered(): void
	{
		// White fill, white page.
		$schemes = $this->schemes(array(), 'solid', array('default' => 'background'));
		$scheme  = $schemes['default'];

		$this->assertTrue($scheme['audit']['bordered']);
		$this->assertLessThan(3.0, $scheme['audit']['fill']);
		$this->assertSame($scheme['ghost'], $scheme['border']);
		$this->assertGreaterThanOrEqual(3.0, $scheme['audit']['boundary']);
	}

	public function test_a_fill_that_stands_off_its_band_is_its_own_edge(): void
	{
		// Near-black fill, white page.
		$schemes = $this->schemes(array(), 'solid', array('default' => 'primary'));
		$scheme  = $schemes['default'];

		$this->assertFalse($scheme['audit']['bordered']);
		$this->assertSame($scheme['bg'], $scheme['border']);
		$this->assertSame($scheme['audit']['fill'], $scheme['audit']['boundary']);
	}

	// ---- The secondary button and the ring -------------------------------

	/**
	 * On a workable palette, both are the band's own foreground — the one
	 * colour known to be legible on it, and the reason a single outline style
	 * works on all four grounds.
	 */
	public function test_the_secondary_button_and_the_ring_are_the_bands_foreground(): void
	{
		$tokens  = bridge_sanitize_tokens($this->palette());
		$palette = $tokens['brand']['palette'];

		foreach (bridge_button_grounds() as $key => $entry) {
			$scheme = bridge_button_schemes($tokens)[$key];

			$this->assertSame(strtolower($palette[$entry['text']]['color']), $scheme['ghost']);
			$this->assertSame(strtolower($palette[$entry['text']]['color']), $scheme['ring']);
			$this->assertSame(strtolower($palette[$entry['ground']]['color']), $scheme['ghostHover']);
		}
	}


	/**
	 * When the palette's own foreground cannot be seen on its band, the
	 * boundary and the ring are promoted rather than drawn invisibly.
	 *
	 * A brand of near-identical greys is not a brand anyone would ship, but it
	 * is one the sanitiser accepts — and a control with no visible edge and no
	 * visible focus ring is the failure that matters most, because a keyboard
	 * user cannot work around it.
	 */
	public function test_an_illegible_foreground_is_promoted(): void
	{
		$grey = array(
			'primary'    => '#808080',
			'background' => '#828282',
			'text'       => '#818181',
		);

		$scheme = $this->schemes($grey, 'solid', array('default' => 'primary'))['default'];

		// The palette's own text would have been 1.01:1 against the band.
		$this->assertLessThan(3.0, bridge_contrast_ratio('#828282', '#818181'));

		$this->assertGreaterThanOrEqual(3.0, $scheme['audit']['ring']);
		$this->assertGreaterThanOrEqual(3.0, $scheme['audit']['boundary']);
		$this->assertGreaterThanOrEqual(4.5, $scheme['audit']['ghost']);
	}

	/**
	 * And the promotion never fires when it is not needed.
	 *
	 * The rule has to be invisible on a normal brand: a secondary button that
	 * turned black on a page whose text is near-black would be a change nobody
	 * asked for.
	 */
	public function test_a_workable_palette_is_left_alone(): void
	{
		$tokens  = bridge_sanitize_tokens($this->palette());
		$palette = $tokens['brand']['palette'];

		foreach (bridge_button_grounds() as $key => $entry) {
			$scheme = bridge_button_schemes($tokens)[$key];

			$this->assertSame(
				strtolower($palette[$entry['text']]['color']),
				$scheme['ring'],
				"Ring was promoted unnecessarily on {$key}"
			);
		}
	}

	// ---- The skin's part in it -------------------------------------------

	/**
	 * The hover fill is the resting fill shaded, and how far is the skin's.
	 *
	 * Edge shades hardest — it answers a pointer with fill and a rule rather
	 * than with a lift — so a change that decoupled the two would show up
	 * here rather than as an Edge button that stopped reacting.
	 */
	public function test_the_skin_decides_how_far_the_hover_fill_shades(): void
	{
		$solid = $this->schemes(array(), 'solid')['default'];
		$edge  = $this->schemes(array(), 'edge')['default'];

		$this->assertSame($solid['bg'], $edge['bg'], 'Resting fill is not the skin\'s business');
		$this->assertNotSame($solid['hoverBg'], $edge['hoverBg']);

		// Both move away from a near-black fill, and Edge moves further.
		$rest = bridge_relative_luminance($solid['bg']);

		$this->assertGreaterThan($rest, bridge_relative_luminance($solid['hoverBg']));
		$this->assertGreaterThan(
			bridge_relative_luminance($solid['hoverBg']),
			bridge_relative_luminance($edge['hoverBg'])
		);
	}

	public function test_the_hover_fill_always_differs_from_the_resting_one(): void
	{
		foreach (array_keys(bridge_button_skins()) as $skin) {
			foreach ($this->schemes(array(), $skin) as $ground => $scheme) {
				$this->assertNotSame(
					$scheme['bg'],
					$scheme['hoverBg'],
					"No hover change on {$ground} ({$skin})"
				);
			}
		}
	}

	/**
	 * The label only changes on hover when the shaded fill stopped carrying
	 * the resting one — a state that changes two colours when it needed to
	 * change one reads as a different button, not a hovered button.
	 */
	public function test_the_hover_label_keeps_the_resting_label_where_it_can(): void
	{
		foreach ($this->schemes() as $ground => $scheme) {
			if (bridge_contrast_ratio($scheme['hoverBg'], $scheme['fg']) >= 4.5) {
				$this->assertSame(
					$scheme['fg'],
					$scheme['hoverFg'],
					"Label changed unnecessarily on {$ground}"
				);
			}
		}
	}

	// ---- Following the brand ---------------------------------------------

	/**
	 * The fill is a palette slug, not a colour, so a rebrand moves the buttons
	 * with it. This is the reason the control offers slugs at all.
	 */
	public function test_a_palette_change_moves_the_buttons(): void
	{
		$before = $this->schemes(array('primary' => '#0f172a'))['default'];
		$after  = $this->schemes(array('primary' => '#63085f'))['default'];

		$this->assertSame('#0f172a', $before['bg']);
		$this->assertSame('#63085f', $after['bg']);
	}
}
