<?php

/**
 * Custom gradient.
 *
 * The parts that can be got wrong without anyone noticing until a page is
 * built: a background kind the resolver does not know about falls back to a
 * photograph, a list of bands that has to survive whatever is stored against
 * it, the stop list those bands come out as — which is the one piece of this
 * that a browser will silently refuse to parse rather than draw wrong — and the
 * one thing this background does differently from every other: it asks whether
 * there is anything to stand on before it decides which label colour to use.
 */

declare(strict_types=1);

final class HeroGradientTest extends BridgeTestCase
{
	/**
	 * One band, spelled out.
	 *
	 * The example the whole control was drawn for is two of these; this is the
	 * first half of it — solid at the left edge, gone by halfway.
	 *
	 * @param array $overrides Anything this band does differently.
	 * @return array A band.
	 */
	private function band(array $overrides = array()): array
	{
		return array_merge(
			array(
				'start'     => 0,
				'end'       => 50,
				'from'      => 'primary',
				'fromAlpha' => 100,
				'to'        => 'primary',
				'toAlpha'   => 0,
			),
			$overrides
		);
	}

	public function test_the_gradient_is_a_background_the_resolver_knows(): void
	{
		$ground = bridge_hero_banner_ground(array('background' => 'custom-gradient'));

		$this->assertSame('custom-gradient', $ground['kind']);
	}

	public function test_an_unknown_background_is_still_a_photograph(): void
	{
		$this->assertSame(
			'image',
			bridge_hero_banner_ground(array('background' => 'gradients'))['kind']
		);
	}

	public function test_an_empty_block_takes_the_composition_it_was_drawn_for(): void
	{
		$gradient = bridge_hero_gradient(array());

		// Left to right, and no bands: block.json supplies the default band, so
		// a block with literally nothing stored is not the same thing as a new
		// one. What matters here is that it is a gradient with no stops rather
		// than a fatal.
		$this->assertSame(90.0, $gradient['angle']);
		$this->assertSame(array(), $gradient['bands']);
		$this->assertSame('', bridge_hero_gradient_css($gradient));
	}

	/**
	 * The angle wraps rather than clamping, because it is a rotation.
	 *
	 * 370° is 10° and -90° is 270°, and an operator who sweeps the angle handle
	 * past straight up should come round the other side rather than stick.
	 */
	public function test_the_angle_wraps(): void
	{
		$of = fn($angle) => bridge_hero_gradient(array('gradientAngle' => $angle))['angle'];

		$this->assertSame(10.0, $of(370));
		$this->assertSame(270.0, $of(-90));
		$this->assertSame(45.0, $of(45));
		$this->assertSame(0.0, $of(720));
		$this->assertSame(90.0, $of('nonsense'));
	}

	public function test_every_setting_is_clamped_to_something_drawable(): void
	{
		$bands = bridge_hero_gradient(array(
			'gradientStops' => array(
				$this->band(array(
					'start'     => -400,
					'end'       => 4000,
					'fromAlpha' => 900,
					'toAlpha'   => -900,
					'from'      => 'nonsense',
				)),
			),
		))['bands'];

		$this->assertSame(0.0, $bands[0]['start']);
		$this->assertSame(100.0, $bands[0]['end']);
		$this->assertSame(100.0, $bands[0]['fromAlpha']);
		$this->assertSame(0.0, $bands[0]['toAlpha']);
		// A colour that has been renamed or removed falls back to something
		// that exists rather than to a variable that resolves to nothing.
		$this->assertSame('primary', $bands[0]['from']);
	}

	/**
	 * A band never runs backwards.
	 *
	 * `linear-gradient(90deg, red 60%, blue 20%)` is not an error — browsers
	 * clamp the second stop up to the first and paint a hard edge — but it is
	 * not what a band with its end before its start means either, and the
	 * arithmetic downstream assumes the list ascends.
	 */
	public function test_a_band_cannot_end_before_it_starts(): void
	{
		$bands = bridge_hero_gradient(array(
			'gradientStops' => array($this->band(array('start' => 70, 'end' => 20))),
		))['bands'];

		$this->assertSame(70.0, $bands[0]['start']);
		$this->assertSame(70.0, $bands[0]['end']);
	}

	public function test_the_bands_come_out_in_the_order_they_are_painted(): void
	{
		$bands = bridge_hero_gradient(array(
			'gradientStops' => array(
				$this->band(array('start' => 70, 'end' => 100)),
				$this->band(array('start' => 0, 'end' => 50)),
			),
		))['bands'];

		$this->assertSame(0.0, $bands[0]['start']);
		$this->assertSame(70.0, $bands[1]['start']);
	}

	/**
	 * Two bands cannot share a stretch of the line.
	 *
	 * A stop list that goes backwards is one a browser clamps rather than
	 * refuses, so an overlap is not drawn wrong — it is drawn as a hard edge
	 * somewhere nobody chose. The earlier band keeps the ground it already
	 * covers instead, and the later one starts where that one finishes.
	 */
	public function test_bands_do_not_overlap(): void
	{
		// Both bands solid at both ends, so every `%` in the gradient below is a
		// stop position rather than an opacity inside a `color-mix()` — which
		// is what lets the ascending check read the string straight.
		$solid = array('fromAlpha' => 100, 'toAlpha' => 100);

		$bands = bridge_hero_gradient(array(
			'gradientStops' => array(
				$this->band($solid + array('start' => 0, 'end' => 80)),
				$this->band($solid + array('start' => 30, 'end' => 100)),
			),
		))['bands'];

		$this->assertSame(0.0, $bands[0]['start']);
		$this->assertSame(80.0, $bands[0]['end']);
		$this->assertSame(80.0, $bands[1]['start']);
		$this->assertSame(100.0, $bands[1]['end']);

		// And the stop list that comes out of it ascends, which is the whole
		// point of the clamp above.
		$positions = array();

		preg_match_all('/ (\d+(?:\.\d+)?)%/', bridge_hero_gradient_css(array(
			'angle' => 90.0,
			'bands' => $bands,
		)), $positions);

		$sorted = array_map('floatval', $positions[1]);
		$this->assertNotEmpty($sorted);

		$previous = -1.0;

		foreach ($sorted as $position) {
			$this->assertGreaterThanOrEqual($previous, $position);
			$previous = $position;
		}
	}

	public function test_junk_in_the_list_is_dropped_rather_than_drawn(): void
	{
		$gradient = bridge_hero_gradient(array(
			'gradientStops' => array('nonsense', 42, null, $this->band()),
		));

		$this->assertCount(1, $gradient['bands']);

		// And a list that is not a list at all.
		$this->assertSame(
			array(),
			bridge_hero_gradient(array('gradientStops' => 'nonsense'))['bands']
		);
	}

	public function test_no_banner_carries_more_bands_than_it_is_allowed(): void
	{
		$gradient = bridge_hero_gradient(array(
			'gradientStops' => array_fill(0, BRIDGE_HERO_GRADIENT_MAX + 6, $this->band()),
		));

		$this->assertCount(BRIDGE_HERO_GRADIENT_MAX, $gradient['bands']);
	}

	/**
	 * A stop at full strength is a plain `var()`.
	 *
	 * `color-mix(… 100%, transparent)` is the same colour said the long way,
	 * and most stops are at full strength — so the short form is what the
	 * majority of a banner's style attribute is made of.
	 */
	public function test_a_solid_stop_is_not_mixed_and_a_faded_one_is(): void
	{
		$this->assertSame(
			'var(--wp--preset--color--primary)',
			bridge_hero_gradient_color('primary', 100.0)
		);

		$this->assertSame(
			'color-mix(in srgb, var(--wp--preset--color--accent) 80%, transparent)',
			bridge_hero_gradient_color('accent', 80.0)
		);

		// Transparent is an opacity rather than a colour, so a fade to nothing
		// keeps the hue it is fading from.
		$this->assertSame(
			'color-mix(in srgb, var(--wp--preset--color--primary) 0%, transparent)',
			bridge_hero_gradient_color('primary', 0.0)
		);
	}

	/**
	 * The example the control exists for, end to end.
	 *
	 * A 45° gradient that starts solid and fades to transparent at 50%, then
	 * starts again transparent at 70% and blends to 80% at 100%. What has to
	 * come out of it: two stops per band, and a flat pair holding the
	 * transparent across the gap — because a single stop between two others is
	 * one the browser interpolates through, and the gap would become a slope.
	 */
	public function test_two_bands_with_clear_air_between_them(): void
	{
		$css = bridge_hero_gradient_css(bridge_hero_gradient(array(
			'gradientAngle' => 45,
			'gradientStops' => array(
				$this->band(array('start' => 0, 'end' => 50)),
				$this->band(array(
					'start'     => 70,
					'end'       => 100,
					'fromAlpha' => 0,
					'toAlpha'   => 80,
				)),
			),
		)));

		$solid = 'var(--wp--preset--color--primary)';
		$none  = 'color-mix(in srgb, var(--wp--preset--color--primary) 0%, transparent)';
		$most  = 'color-mix(in srgb, var(--wp--preset--color--primary) 80%, transparent)';

		$this->assertSame(
			"linear-gradient(45deg, {$solid} 0%, {$none} 50%, {$none} 50%, {$none} 70%, {$none} 70%, {$most} 100%)",
			$css
		);
	}

	/**
	 * Nothing is emitted for the run before the first band or after the last.
	 *
	 * CSS already holds the first and last stop's colour out to the ends of the
	 * gradient line, which is the same rule the gaps follow — so saying it
	 * again would only be two more stops that cannot change the picture.
	 */
	public function test_the_ends_are_left_to_css(): void
	{
		$css = bridge_hero_gradient_css(bridge_hero_gradient(array(
			'gradientAngle' => 0,
			'gradientStops' => array($this->band(array('start' => 20, 'end' => 60))),
		)));

		$this->assertStringStartsWith('linear-gradient(0deg, var(--wp--preset--color--primary) 20%,', $css);
		$this->assertStringEndsWith('0%, transparent) 60%)', $css);
	}

	/**
	 * The gradient goes in a stylesheet, not a style attribute.
	 *
	 * This is the bug the background shipped with, and it is worth the only
	 * test in this suite that loads a piece of WordPress: the behaviour being
	 * pinned is `safecss_filter_attr()`'s own, and a double of it would prove
	 * nothing at all.
	 *
	 * What it does: a gradient in a custom property is allowed, but the pattern
	 * that matches one reaches only one level of nested functions. A palette
	 * colour at less than full strength is a `color-mix()` with a `var()` inside
	 * it — two levels — so the gradient does not match, the parentheses survive
	 * the test string, and the whole declaration is thrown away. Silently: no
	 * error, no property, and a background that works in the editor, whose
	 * canvas is not filtered, and does nothing on the page.
	 */
	public function test_a_gradient_does_not_survive_an_inline_style(): void
	{
		if (! function_exists('safecss_filter_attr')) {
			$kses = dirname(__DIR__, 4) . '/wp-includes/kses.php';

			if (! is_readable($kses)) {
				// The theme checked out on its own, with no WordPress around
				// it. Everything else in this suite runs without one, and this
				// is the one thing that cannot.
				$this->markTestSkipped('WordPress is not installed alongside the theme.');
			}

			require_once $kses;
		}

		$css = bridge_hero_gradient_css(bridge_hero_gradient(array(
			'gradientStops' => array($this->band()),
		)));

		$this->assertStringContainsString('color-mix(in srgb, var(', $css);

		// What the wrapper would have carried, and what is left of it.
		$this->assertSame(
			'',
			safecss_filter_attr('--bridge-hero-banner-gradient:' . $css)
		);

		// And the shape that does survive, so the test says what the filter
		// objects to rather than only that it objects: the same gradient with
		// one level of nesting passes.
		$this->assertNotSame(
			'',
			safecss_filter_attr('--bridge-hero-banner-gradient:linear-gradient(45deg, var(--a) 0%, var(--b) 100%)')
		);

		// So the value goes into a rule instead, which is not an inline style
		// and is not filtered.
		$this->assertSame(
			'.bridge-hero-x{--bridge-hero-banner-gradient:' . $css . '}',
			bridge_hero_gradient_style('bridge-hero-x', $css)
		);

		// Nothing to scope and nothing to print.
		$this->assertSame('', bridge_hero_gradient_style('bridge-hero-x', ''));
	}

	/**
	 * Half is where a veil becomes a cover.
	 *
	 * Below it the picture is still the thing under the words; at or above it
	 * the colour is, and it is that colour the label has to read against.
	 */
	public function test_the_cover_is_the_strongest_band_that_reaches_half(): void
	{
		$veil = bridge_hero_gradient(array(
			'gradientStops' => array($this->band(array('fromAlpha' => 40, 'toAlpha' => 0))),
		));

		$this->assertNull(bridge_hero_gradient_cover($veil));

		$cover = bridge_hero_gradient(array(
			'gradientStops' => array(
				$this->band(array('from' => 'primary', 'fromAlpha' => 55, 'toAlpha' => 0)),
				$this->band(array(
					'start'     => 60,
					'end'       => 100,
					'from'      => 'accent',
					'fromAlpha' => 0,
					'to'        => 'accent',
					'toAlpha'   => 90,
				)),
			),
		));

		$this->assertSame('accent', bridge_hero_gradient_cover($cover));
	}

	/**
	 * The words sit on the gradient when there is something to sit on, and on
	 * whatever it is laid over when there is not.
	 *
	 * This is the whole reason the kind has a branch in the resolver: every
	 * other background works out its foreground from one setting, and here
	 * which setting that is depends on how much colour has been poured on.
	 */
	public function test_the_foreground_follows_the_cover_and_not_the_band(): void
	{
		// A light cover takes dark type, whatever the band underneath is set to
		// — which a photograph background never does.
		$light = bridge_hero_banner_ground(array(
			'background'    => 'custom-gradient',
			'color'         => 'primary',
			'gradientStops' => array($this->band(array('from' => 'background', 'fromAlpha' => 100))),
		));

		$this->assertFalse($light['inverted']);
		$this->assertSame('var(--wp--preset--color--text)', $light['fg']);

		// And a dark one takes light type.
		$dark = bridge_hero_banner_ground(array(
			'background'    => 'custom-gradient',
			'color'         => 'background',
			'gradientStops' => array($this->band(array('from' => 'primary', 'fromAlpha' => 100))),
		));

		$this->assertTrue($dark['inverted']);
		$this->assertSame('var(--wp--preset--color--background)', $dark['fg']);
	}

	/**
	 * A veil over a photograph is a photograph.
	 *
	 * The bands are too thin to be stood on, so the thing under the words is
	 * the picture — which is dimmed to carry white type, the same answer the
	 * plain image background gives.
	 */
	public function test_a_veil_over_a_picture_takes_the_photograph_answer(): void
	{
		$veiled = bridge_hero_banner_ground(array(
			'background'    => 'custom-gradient',
			// A light band colour, which on its own would ask for dark type.
			'color'         => 'background',
			'imageId'       => 12,
			'gradientStops' => array($this->band(array('fromAlpha' => 30, 'toAlpha' => 0))),
		));

		$this->assertTrue($veiled['inverted']);

		// And with no picture there is nothing to be a veil over, so the band's
		// own colour answers instead.
		$bare = bridge_hero_banner_ground(array(
			'background'    => 'custom-gradient',
			'color'         => 'background',
			'gradientStops' => array($this->band(array('fromAlpha' => 30, 'toAlpha' => 0))),
		));

		$this->assertFalse($bare['inverted']);
	}
}
