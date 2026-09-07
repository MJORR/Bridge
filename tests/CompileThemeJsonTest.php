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
				// No `lift`. It was a vertical nudge under the pointer and it
				// was deliberately removed — see the note in
				// bridge_button_skins() — so asserting it here kept the suite
				// red for a property nothing publishes and nothing reads.
				'sweep',
				'minSize',
			) as $key
		) {
			$this->assertArrayHasKey($key, $button);
			$this->assertIsString($button[$key]);
			$this->assertNotSame('', $button[$key], "Empty value for {$key}");
		}
	}

	/**
	 * The wipe extents are lengths, and never a bare `0`.
	 *
	 * They are published into `calc(100% - <extent>)` in the clip that
	 * reveals the hover fill, and `calc()` cannot subtract a number from a
	 * percentage: a bare zero invalidates the declaration, the clip is
	 * dropped, and the button sits in its hover colour at rest. A filter is
	 * allowed to say `0` — bridge_button_wipe_extent() is what makes it
	 * safe — so this asserts the output, not the skin.
	 */
	public function test_wipe_extents_are_lengths(): void
	{
		add_filter(
			'bridge_button_skins',
			static function (array $skins): array {
				$skins['legacy'] = array_merge(
					$skins['solid'],
					array(
						'name'       => 'Legacy',
						'wipeWidth'  => '0',
						'wipeHeight' => '0',
					)
				);

				return $skins;
			}
		);

		foreach (array_keys(bridge_button_skins()) as $slug) {
			$button = bridge_compile_button_custom(
				bridge_sanitize_tokens(array('buttons' => array('skin' => $slug)))
			);

			foreach (array('wipeWidth', 'wipeHeight') as $key) {
				$this->assertMatchesRegularExpression(
					'/(%|[a-z]+)$/',
					$button[$key],
					"{$slug}: {$key} is not a length or a percentage"
				);
			}
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

	/**
	 * A cut corner and a rounded one are two answers to the same question, and
	 * the cut wins: it squares the card outright rather than leaving a 6px
	 * round on three corners and a notch on the fourth.
	 *
	 * Asserted on the compiled record rather than on the saved one because the
	 * operator's radius is deliberately *not* overwritten — turning the cut off
	 * has to give them back the corner they had, so the override can only live
	 * here.
	 */
	public function test_a_cut_corner_squares_the_card_and_clips_in_container_units(): void
	{
		$card = $this->compile(
			array('cards' => array('radius' => 12, 'cutCorner' => true, 'cutSize' => 8))
		)['settings']['custom']['card'];

		$this->assertSame('0px', $card['radius']);
		$this->assertSame('inline-size', $card['container']);

		// `cqw` and not `%`: a percentage in a polygon resolves per axis, which
		// puts the cut at 51° on a card taller than it is wide. This is the
		// assertion that catches someone "simplifying" the unit away.
		$this->assertSame(
			'polygon(0 8cqw, 8cqw 0, 100% 0, 100% 100%, 0 100%)',
			$card['clip']
		);
	}

	/**
	 * Containment never ships without a width to go with it.
	 *
	 * A regression test with a story: inline-size containment sizes an element
	 * as if it had no contents, which is safe only while something else decides
	 * the width. A grid item is normally stretched to its track — but the post
	 * card and the download centre themselves with `margin-inline: auto`, and an
	 * auto margin opts an item out of that stretch. Their width then came from
	 * contents that containment had just made nothing, so every card in the grid
	 * collapsed to zero and `overflow: hidden` hid what was left: the row kept
	 * its shape and the cards vanished.
	 *
	 * Asserted as a pair rather than as two values, because that is the actual
	 * invariant — either of these alone is a bug.
	 */
	public function test_containment_and_a_definite_width_are_published_together(): void
	{
		$cut = $this->compile(
			array('cards' => array('cutCorner' => true))
		)['settings']['custom']['card'];

		$this->assertSame('inline-size', $cut['container']);
		$this->assertSame('100%', $cut['width']);

		$rounded = $this->compile(
			array('cards' => array('cutCorner' => false))
		)['settings']['custom']['card'];

		// And neither is imposed on a card that is not being contained.
		$this->assertSame('normal', $rounded['container']);
		$this->assertSame('auto', $rounded['width']);
	}

	/**
	 * The surface layer never ships without a stacking context to sit in.
	 *
	 * A second regression test with a story. The surface is a `::before` at
	 * `z-index: -1`, which only stays inside an element that establishes a
	 * stacking context. Two things happened to establish one — a non-`none`
	 * filter, and the containment above — and neither is dependable: Chrome 129
	 * stopped `container-type: inline-size` establishing one, and the filter is
	 * `none` the moment an operator picks Shadow: None. On that combination the
	 * surface escaped the card and painted behind the band, so the card lost its
	 * ground and its shadow and the photograph was left floating on the page.
	 */
	public function test_a_cut_card_always_isolates(): void
	{
		foreach (array('none', 'soft') as $shadow) {
			$cut = $this->compile(
				array('cards' => array('cutCorner' => true, 'shadow' => $shadow))
			)['settings']['custom']['card'];

			$this->assertSame(
				'isolate',
				$cut['isolation'],
				"a cut card with Shadow: {$shadow} must still isolate"
			);
		}

		$this->assertSame(
			'auto',
			$this->compile(
				array('cards' => array('cutCorner' => false))
			)['settings']['custom']['card']['isolation']
		);
	}

	/**
	 * A cut card has no shadow, whatever the Shadow control says.
	 *
	 * A `box-shadow` is drawn around the card's *box*, so on a cut card it
	 * traces the square corner the cut just removed — the one line on the card
	 * that contradicts its shape. So the cut suppresses it, on the same
	 * principle as the radius, and the saved preset is left alone so that
	 * turning the cut off gives back the shadow the operator had.
	 */
	public function test_a_cut_card_has_no_shadow(): void
	{
		$saved = array('cards' => array('shadow' => 'soft', 'cutCorner' => true));

		$card = $this->compile($saved)['settings']['custom']['card'];

		$this->assertSame('none', $card['shadow']);
		$this->assertSame('none', $card['shadowHover']);

		// The preset itself is untouched in the record, so it comes back.
		$this->assertSame('soft', bridge_sanitize_tokens($saved)['cards']['shadow']);

		$rounded = $this->compile(
			array('cards' => array('shadow' => 'soft', 'cutCorner' => false))
		)['settings']['custom']['card'];

		$this->assertNotSame('none', $rounded['shadow']);
		$this->assertNotSame('none', $rounded['shadowHover']);
	}

	/**
	 * The card's ground is never published as a custom property.
	 *
	 * A regression test for a bug that only showed on a skinned band. The
	 * ground is a chain — the band's `--bridge-card-bg`, or the light one — and
	 * a chain inside a custom property is resolved where that property is
	 * *declared*. For a theme.json value that is `:root`, which is above every
	 * section skin, so the chain fell through to the light ground and every
	 * card on the site wore it whatever band it stood on. On a Surface band
	 * that is the band's own colour, so the card vanished into its section.
	 *
	 * The colour therefore lives in abstracts/_card.scss, where it resolves on
	 * the card, and only the on/off switches travel. This asserts the switches
	 * are switches: if a `var()` chain ever reappears in one of these, the bug
	 * is back.
	 */
	public function test_the_layer_switches_carry_no_colour_chain(): void
	{
		foreach (array(true, false) as $on) {
			$card = $this->compile(
				array('cards' => array('cutCorner' => $on))
			)['settings']['custom']['card'];

			foreach (array('ownBg', 'layer') as $key) {
				$this->assertStringNotContainsString(
					'var(',
					$card[$key],
					"card.{$key} must be a switch, not a colour chain"
				);
			}
		}
	}

	/**
	 * And the switches say what they mean: clear the card and show the layer
	 * when the corner is cut, and get out of the way entirely when it is not.
	 *
	 * `initial` is the guaranteed-invalid value, which is what makes the
	 * stylesheet's own ground the answer with the cut off — a plain colour here
	 * would be the bug above in a different costume.
	 */
	public function test_the_cut_moves_the_ground_to_the_layer(): void
	{
		$cut = $this->compile(
			array('cards' => array('cutCorner' => true))
		)['settings']['custom']['card'];

		$this->assertSame('transparent', $cut['ownBg']);
		$this->assertSame('block', $cut['layer']);

		$rounded = $this->compile(
			array('cards' => array('cutCorner' => false))
		)['settings']['custom']['card'];

		$this->assertSame('initial', $rounded['ownBg']);
		$this->assertSame('none', $rounded['layer']);
	}

	/**
	 * With the cut off, a card is exactly the card it was before the feature
	 * existed: no clip, no containment, no filter.
	 *
	 * Containment in particular is not free — it fixes the card's inline size
	 * against its contents — and it is asked for only because the clip is
	 * written in `cqw`. A default card must not pay for it.
	 */
	public function test_no_cut_corner_leaves_the_card_shape_untouched(): void
	{
		$card = $this->compile(
			array('cards' => array('radius' => 12, 'cutCorner' => false, 'cutSize' => 8))
		)['settings']['custom']['card'];

		$this->assertSame('12px', $card['radius']);
		$this->assertSame('none', $card['clip']);
		$this->assertSame('normal', $card['container']);
		$this->assertSame('auto', $card['isolation']);
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
	 * size, a Team card would request a file for a slot it does not have.
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
	public function test_a_team_avatar_compiles_to_its_width(): void
	{
		$card = $this->compile(
			array('cards' => array('styles' => array('team' => array('avatar' => 'l'))))
		)['settings']['custom']['card'];

		$this->assertSame(
			bridge_card_avatar_sizes()['l']['width'],
			$card['team']['avatar']
		);
	}

	/**
	 * A hidden style is not offered, but is still drawn.
	 *
	 * `team` is built for a post type that does not exist yet and is kept out
	 * of the pickers by a flag. The flag must reach the choice list and stop
	 * there: if it reached the compiler too, a site already set to that style
	 * would lose its custom properties and the card would fall back to the base
	 * — which looks like the theme forgetting a setting rather than like a
	 * style being withdrawn.
	 */
	public function test_a_hidden_card_style_is_withheld_from_choices_but_still_compiles(): void
	{
		$hidden = array_keys(
			array_filter(
				bridge_card_styles(),
				static fn(array $style): bool => ! empty($style['hidden'])
			)
		);

		$this->assertNotEmpty($hidden, 'expected at least one hidden style');

		$offered = array_column(bridge_card_style_choices(), 'slug');
		$card    = $this->compile()['settings']['custom']['card'];

		foreach ($hidden as $slug) {
			$this->assertNotContains($slug, $offered, "{$slug} should not be offered");
			$this->assertArrayHasKey($slug, $card, "{$slug} should still compile");
		}
	}

	/** Every offered style is one the compiler publishes properties for. */
	public function test_every_offered_card_style_compiles(): void
	{
		$card = $this->compile()['settings']['custom']['card'];

		foreach (bridge_card_style_choices() as $choice) {
			$this->assertArrayHasKey($choice['slug'], $card);
		}
	}

	/**
	 * The wash is a choice; the ink over it is not.
	 *
	 * Every palette slug is offered as a Cover wash, which is only safe because
	 * the title colour is derived from whichever one was picked. A light wash
	 * with a fixed light title would be white on near-white — so this asserts
	 * the pairing clears WCAG 1.4.3 for *every* slug, not just the default.
	 */
	public function test_a_cover_wash_always_compiles_a_readable_ink(): void
	{
		foreach (array_keys(bridge_palette_slugs()) as $slug) {
			$card = $this->compile(
				array('cards' => array('styles' => array('cover' => array('wash' => $slug))))
			)['settings']['custom']['card'];

			$this->assertSame(
				sprintf('var(--wp--preset--color--%s)', $slug),
				$card['cover']['wash']
			);

			$this->assertGreaterThanOrEqual(
				4.5,
				bridge_contrast_ratio(bridge_palette_hex($slug), $card['cover']['ink']),
				"Cover ink is illegible on the {$slug} wash"
			);
		}
	}

	/** The ink is a resolved colour, never a preset reference. */
	public function test_the_cover_ink_compiles_to_a_literal_colour(): void
	{
		$card = $this->compile()['settings']['custom']['card'];

		$this->assertMatchesRegularExpression(
			'/^#[0-9a-f]{6}$/i',
			$card['cover']['ink']
		);
	}
}
