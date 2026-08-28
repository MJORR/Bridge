<?php

/**
 * inc/theme-json.php — the compiler.
 *
 * The last step before the design system becomes CSS. Everything the token
 * record decides reaches the front end, the editor and the options preview
 * through this one array, so a key dropped here is a design system that
 * silently stops applying — the site keeps rendering, in the wrong colours.
 *
 * These tests assert the *shape of the contract* between PHP and the
 * stylesheets: the custom properties the SCSS spends by name, and the ones the
 * options page reads out of a preview response. A rename on either side
 * without the other is exactly the failure they exist to catch.
 *
 * @package Bridge
 */

declare(strict_types=1);

final class CompileThemeJsonTest extends BridgeTestCase
{
	/** @return array<string, mixed> */
	private function compile(array $fragment = array()): array
	{
		return bridge_compile_theme_json(bridge_sanitize_tokens($fragment));
	}

	// ---- The envelope ----------------------------------------------------

	/**
	 * Version 3, matching the static theme.json.
	 *
	 * Declaring a later version blind would skip whatever migration
	 * WP_Theme_JSON would otherwise apply.
	 */
	public function test_the_fragment_declares_schema_version_three(): void
	{
		$this->assertSame(3, $this->compile()['version']);
	}

	public function test_the_palette_is_the_token_record(): void
	{
		$compiled = $this->compile($this->palette(array('primary' => '#63085f')));
		$palette  = $compiled['settings']['color']['palette'];

		$primary = array_values(
			array_filter($palette, static fn ($entry) => 'primary' === $entry['slug'])
		);

		$this->assertCount(1, $primary);
		$this->assertSame('#63085f', $primary[0]['color']);
	}

	// ---- Button custom properties ----------------------------------------

	/**
	 * The geometry keys the button stylesheet names.
	 *
	 * Each becomes `--wp--custom--button--<kebab>`, and every one of them is
	 * spent by abstracts/_button.scss. Dropping one leaves that rule falling
	 * back to a hardcoded default, which is a skin that half-applies.
	 */
	public function test_every_geometry_property_is_published(): void
	{
		$button = bridge_compile_button_custom(bridge_sanitize_tokens(array()));

		foreach (
			array(
				'radius',
				'paddingBlock',
				'paddingInline',
				'weight',
				'transform',
				'letterSpacing',
				'borderWidth',
				'shadow',
				'shadowHover',
				'lift',
				'sweep',
				'minSize',
			) as $key
		) {
			$this->assertArrayHasKey($key, $button);
			$this->assertIsString($button[$key]);
			$this->assertNotSame('', $button[$key], "Empty value for {$key}");
		}
	}

	public function test_every_ground_is_published_with_every_colour(): void
	{
		$button = bridge_compile_button_custom(bridge_sanitize_tokens(array()));

		foreach (array_keys(bridge_button_grounds()) as $ground) {
			$key = 'on' . ucfirst($ground);

			$this->assertArrayHasKey($key, $button);
			$this->assertSame(
				array(
					'bg',
					'fg',
					'hoverBg',
					'hoverFg',
					'border',
					'hoverBorder',
					'ghost',
					'ghostHover',
					'ring',
				),
				array_keys($button[$key])
			);
		}
	}

	/**
	 * The audit is arithmetic for the options screen, not a value a stylesheet
	 * can spend. It must not reach the compiled output.
	 */
	public function test_the_audit_does_not_leak_into_the_custom_properties(): void
	{
		$button = bridge_compile_button_custom(bridge_sanitize_tokens(array()));

		foreach (array_keys(bridge_button_grounds()) as $ground) {
			$this->assertArrayNotHasKey('audit', $button['on' . ucfirst($ground)]);
		}
	}

	/**
	 * Choosing a skin changes the geometry, and every skin compiles.
	 *
	 * The Edge skin's zero radius is asserted by name because it is the one
	 * value a client asked for outright — a brand that does not want a rounded
	 * anything — and a default creeping back in would be invisible in review.
	 */
	public function test_each_skin_compiles_to_its_own_geometry(): void
	{
		$skins = bridge_button_skins();

		foreach ($skins as $slug => $definition) {
			$button = bridge_compile_button_custom(
				bridge_sanitize_tokens(array('buttons' => array('skin' => $slug)))
			);

			$this->assertSame((string) $definition['radius'], $button['radius']);
			$this->assertSame((string) $definition['transform'], $button['transform']);
			$this->assertSame((string) $definition['sweep'], $button['sweep']);
		}

		$edge = bridge_compile_button_custom(
			bridge_sanitize_tokens(array('buttons' => array('skin' => 'edge')))
		);

		$this->assertSame('0', $edge['radius']);
	}

	/** The tap-target floor is not a skin value and does not move with one. */
	public function test_the_target_size_floor_is_the_same_on_every_skin(): void
	{
		foreach (array_keys(bridge_button_skins()) as $slug) {
			$button = bridge_compile_button_custom(
				bridge_sanitize_tokens(array('buttons' => array('skin' => $slug)))
			);

			$this->assertSame('44px', $button['minSize']);
		}
	}

	public function test_the_button_properties_reach_the_compiled_fragment(): void
	{
		$custom = $this->compile()['settings']['custom'];

		$this->assertArrayHasKey('button', $custom);
		$this->assertArrayHasKey('card', $custom);
		$this->assertSame(
			bridge_compile_button_custom(bridge_sanitize_tokens(array())),
			$custom['button']
		);
	}

	// ---- Card custom properties ------------------------------------------

	public function test_the_card_radius_compiles_to_pixels(): void
	{
		$compiled = $this->compile(array('cards' => array('radius' => 12)));

		$this->assertSame('12px', $compiled['settings']['custom']['card']['radius']);
	}

	public function test_a_card_ground_is_published_for_every_ground(): void
	{
		$card = $this->compile()['settings']['custom']['card'];

		foreach (array_keys(bridge_card_grounds()) as $ground) {
			$this->assertArrayHasKey('on' . ucfirst($ground), $card);
		}
	}

	// ---- Layout and type -------------------------------------------------

	public function test_widths_compile_with_units(): void
	{
		$layout = $this->compile(
			array('layout' => array('contentSize' => 800, 'wideSize' => 1300))
		)['settings']['layout'];

		$this->assertSame('800px', $layout['contentSize']);
		$this->assertSame('1300px', $layout['wideSize']);
	}

	/**
	 * The type scale is generated, not listed.
	 *
	 * Every size is derived from the base size and the ratio, so a larger
	 * ratio has to widen the gap between the smallest and the largest. If this
	 * ever stopped being true, the scale would have quietly become a list of
	 * fixed sizes wearing a ratio control.
	 */
	public function test_a_wider_ratio_produces_a_wider_scale(): void
	{
		$spread = function (float $ratio): float {
			$sizes = $this->compile(
				array('typography' => array('scaleRatio' => $ratio))
			)['settings']['typography']['fontSizes'];

			$values = array();

			foreach ($sizes as $size) {
				$values[$size['slug']] = (float) $size['size'];
			}

			return $values['xx-large'] / $values['small'];
		};

		$this->assertGreaterThan($spread(1.2), $spread(1.6));
	}

	public function test_the_heading_case_is_emitted_even_when_it_is_none(): void
	{
		// Emitted rather than omitted, so switching back off resets the
		// cascade instead of leaving the previous value standing.
		$styles = $this->compile(
			array('typography' => array('headingCase' => 'none'))
		)['styles']['elements']['heading']['typography'];

		$this->assertSame('none', $styles['textTransform']);
	}

	// ---- Card styles -----------------------------------------------------

	/**
	 * Every style publishes every field it declares.
	 *
	 * The stylesheets name these properties one by one — `.post-card--tile`
	 * reads `--wp--custom--card--tile--heading` and nothing else supplies it —
	 * so a field a style declares but the compiler drops is a card that
	 * silently falls back to the base and looks almost right.
	 */
	public function test_every_card_style_publishes_every_field_it_declares(): void
	{
		$card = $this->compile()['settings']['custom']['card'];

		foreach (bridge_card_styles() as $slug => $style) {
			$this->assertArrayHasKey($slug, $card, "card.{$slug} is missing");

			foreach ($style['fields'] as $field) {
				$this->assertArrayHasKey(
					$field,
					$card[$slug],
					"card.{$slug}.{$field} is missing"
				);
				$this->assertNotSame('', $card[$slug][$field]);
			}
		}
	}

	/**
	 * A heading is a preset reference, never a length.
	 *
	 * The whole reason the setting is a slug: a card heading has to move with
	 * the type scale. A compiler that resolved it to a rem here would freeze
	 * the card at whatever the scale said the day it was saved.
	 */
	public function test_a_card_heading_compiles_to_a_font_size_preset(): void
	{
		$card = $this->compile()['settings']['custom']['card'];

		foreach (bridge_card_styles() as $slug => $style) {
			if (! in_array('heading', $style['fields'], true)) {
				continue;
			}

			$this->assertMatchesRegularExpression(
				'/^var\(--wp--preset--font-size--[a-z-]+\)$/',
				$card[$slug]['heading']
			);
		}
	}

	/** The chosen ratio reaches the property as a CSS aspect-ratio. */
	public function test_a_card_ratio_compiles_to_its_css_value(): void
	{
		$card = $this->compile(
			array('cards' => array('styles' => array('tile' => array('ratio' => '1-1'))))
		)['settings']['custom']['card'];

		$this->assertSame('1 / 1', $card['tile']['ratio']);
	}

	/**
	 * The avatar width and the `sizes` fraction come from one record.
	 *
	 * render.php spends `fraction` on the image's `sizes` attribute while the
	 * stylesheet spends `width`; if the two ever stopped describing the same
	 * size, a Portrait card would request a file for a slot it does not have.
	 */
	public function test_every_avatar_size_publishes_a_width_and_a_matching_fraction(): void
	{
		foreach (bridge_card_avatar_sizes() as $slug => $entry) {
			$this->assertArrayHasKey('width', $entry, "avatar {$slug}");
			$this->assertArrayHasKey('fraction', $entry, "avatar {$slug}");

			// The percentage inside the min() is the fraction, written out.
			$this->assertMatchesRegularExpression(
				'/min\(\s*' . (int) round($entry['fraction'] * 100) . '%/',
				$entry['width'],
				"avatar {$slug}: width and fraction disagree"
			);
		}
	}

	/** The chosen avatar size reaches the property as its CSS width. */
	public function test_a_portrait_avatar_compiles_to_its_width(): void
	{
		$card = $this->compile(
			array('cards' => array('styles' => array('portrait' => array('avatar' => 'l'))))
		)['settings']['custom']['card'];

		$this->assertSame(
			bridge_card_avatar_sizes()['l']['width'],
			$card['portrait']['avatar']
		);
	}
}
