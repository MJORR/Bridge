<?php

/**
 * Every palette colour has to make a complete button.
 *
 * The four grounds have always been contrast-checked. A painted button is the
 * same arithmetic pointed at a palette colour instead — see
 * bridge_button_palette_schemes() — and the thing worth asserting is that it
 * really is the same arithmetic: every slug answered, every colour a colour,
 * and a label that can be read on the fill it sits on.
 */

declare(strict_types=1);

final class PaletteButtonTest extends BridgeTestCase
{
	public function test_every_palette_colour_makes_a_readable_button(): void
	{
		$tokens  = bridge_sanitize_tokens(array());
		$schemes = bridge_button_palette_schemes($tokens);

		$this->assertSame(
			array_keys(bridge_palette_slugs()),
			array_keys($schemes),
			'Every palette slug should have a button scheme, in palette order.'
		);

		foreach ($schemes as $slug => $scheme) {
			foreach (array('bg', 'fg', 'hoverBg', 'hoverFg', 'ring') as $key) {
				$this->assertMatchesRegularExpression(
					'/^#[0-9a-f]{6}$/i',
					(string) $scheme[$key],
					"$slug's $key should be a hex colour."
				);
			}

			$this->assertGreaterThanOrEqual(
				4.5,
				bridge_contrast_ratio($scheme['bg'], $scheme['fg']),
				"$slug's label should be readable on its own fill."
			);

			$this->assertGreaterThanOrEqual(
				4.5,
				bridge_contrast_ratio($scheme['hoverBg'], $scheme['hoverFg']),
				"$slug's label should still be readable once hovered."
			);
		}
	}
}
