<?php

/**
 * inc/color.php — the WCAG arithmetic.
 *
 * The most testable code in the theme and the least forgiving: every contrast
 * figure the options screen shows a client, and every automatic label colour,
 * comes out of these four functions. A regression here is silent — the site
 * still renders, the numbers are just wrong.
 *
 * The fixtures are chosen to be checkable by hand against the spec rather than
 * captured from the implementation. A test that asserts whatever the code
 * currently returns locks in the bug along with the behaviour.
 *
 * @package Bridge
 */

declare(strict_types=1);

final class ColorTest extends BridgeTestCase
{
	// ---- bridge_hex_channels ---------------------------------------------

	public function test_channels_read_a_six_digit_hex(): void
	{
		$this->assertSame(array(255, 128, 0), bridge_hex_channels('#ff8000'));
	}

	public function test_channels_expand_a_three_digit_hex(): void
	{
		// #abc is #aabbcc, not #0a0b0c.
		$this->assertSame(array(170, 187, 204), bridge_hex_channels('#abc'));
	}

	public function test_channels_do_not_require_the_hash(): void
	{
		$this->assertSame(bridge_hex_channels('#ff8000'), bridge_hex_channels('ff8000'));
	}

	/**
	 * Unparseable input reads as black.
	 *
	 * Deliberate, and the reason is in the file: every caller is computing a
	 * contrast ratio, and black is the answer that fails loudly against a dark
	 * ground rather than the one that quietly passes.
	 *
	 * @dataProvider provide_unparseable_colors
	 */
	public function test_channels_fall_back_to_black(string $input): void
	{
		$this->assertSame(array(0, 0, 0), bridge_hex_channels($input));
	}

	/** @return array<string, array{0: string}> */
	public static function provide_unparseable_colors(): array
	{
		return array(
			'empty'         => array(''),
			'not hex'       => array('#gggggg'),
			'four digits'   => array('#abcd'),
			'eight digits'  => array('#aabbccdd'),
			'a colour name' => array('rebeccapurple'),
		);
	}

	// ---- bridge_relative_luminance ---------------------------------------

	public function test_luminance_of_black_is_zero(): void
	{
		$this->assertSame(0.0, bridge_relative_luminance('#000000'));
	}

	public function test_luminance_of_white_is_one(): void
	{
		$this->assertEqualsWithDelta(1.0, bridge_relative_luminance('#ffffff'), 0.0001);
	}

	/**
	 * The channel weights are the spec's, and they are not equal.
	 *
	 * Green carries roughly three times the luminance of red and ten times
	 * blue. A "simplified" implementation that averaged the channels would
	 * pass a black-and-white test and fail this one.
	 */
	public function test_luminance_weights_green_above_red_above_blue(): void
	{
		$red   = bridge_relative_luminance('#ff0000');
		$green = bridge_relative_luminance('#00ff00');
		$blue  = bridge_relative_luminance('#0000ff');

		$this->assertGreaterThan($red, $green);
		$this->assertGreaterThan($blue, $red);

		// The coefficients themselves, since each primary at full strength is
		// exactly its own weight.
		$this->assertEqualsWithDelta(0.2126, $red, 0.0001);
		$this->assertEqualsWithDelta(0.7152, $green, 0.0001);
		$this->assertEqualsWithDelta(0.0722, $blue, 0.0001);
	}

	// ---- bridge_contrast_ratio -------------------------------------------

	public function test_black_on_white_is_the_maximum_ratio(): void
	{
		$this->assertSame(21.0, bridge_contrast_ratio('#000000', '#ffffff'));
	}

	public function test_a_colour_against_itself_is_one(): void
	{
		$this->assertSame(1.0, bridge_contrast_ratio('#2563eb', '#2563eb'));
	}

	public function test_the_ratio_is_symmetric(): void
	{
		$this->assertSame(
			bridge_contrast_ratio('#1f2937', '#f59e0b'),
			bridge_contrast_ratio('#f59e0b', '#1f2937')
		);
	}

	/**
	 * The canonical boundary case.
	 *
	 * #767676 is the grey WCAG's own materials use as the darkest that still
	 * clears 4.5:1 against white — 4.54:1. One step lighter fails. If the
	 * transfer function were linear rather than the sRGB curve, this would
	 * come out near 3.9 and the test would catch it.
	 */
	public function test_the_documented_grey_sits_just_above_the_threshold(): void
	{
		$ratio = bridge_contrast_ratio('#767676', '#ffffff');

		$this->assertEqualsWithDelta(4.54, $ratio, 0.01);
		$this->assertGreaterThanOrEqual(4.5, $ratio);

		// And one step lighter does not.
		$this->assertLessThan(4.5, bridge_contrast_ratio('#777777', '#ffffff'));
	}

	// ---- bridge_readable_on ----------------------------------------------

	/**
	 * The first candidate that passes wins — not the highest-scoring one.
	 *
	 * This is the whole point of the function. Pure black scores 21:1 on a
	 * white button and the brand's near-black scores 17:1; both are legible,
	 * and the brand's is the right answer. An implementation that maximised
	 * the ratio would return #000000 and quietly take the brand off the page.
	 */
	public function test_the_first_passing_candidate_wins_over_a_higher_scoring_one(): void
	{
		$label = bridge_readable_on('#ffffff', array('#1f2937', '#000000'));

		$this->assertSame('#1f2937', $label);
	}

	public function test_a_candidate_that_fails_is_skipped(): void
	{
		// #cccccc on white is about 1.6:1 — offered first, and rejected.
		$label = bridge_readable_on('#ffffff', array('#cccccc', '#1f2937'));

		$this->assertSame('#1f2937', $label);
	}

	public function test_black_or_white_backs_up_a_candidate_list_that_all_fails(): void
	{
		$label = bridge_readable_on('#ffffff', array('#eeeeee', '#dddddd'));

		$this->assertSame('#000000', $label);
	}

	public function test_the_backstop_picks_the_better_of_black_and_white(): void
	{
		$this->assertSame('#ffffff', bridge_readable_on('#000000', array()));
		$this->assertSame('#000000', bridge_readable_on('#ffffff', array()));
	}

	/**
	 * The guarantee the buttons tab makes to a client, checked exhaustively.
	 *
	 * Every colour reaches at least 4.5:1 against black or white, so a label
	 * that fails WCAG 1.4.3 is not reachable from this function whatever the
	 * palette becomes. The sweep is coarse enough to run in milliseconds and
	 * fine enough that the worst case — mid-grey, around luminance 0.18 — is
	 * inside it.
	 */
	public function test_no_ground_can_produce_an_illegible_label(): void
	{
		$worst = 21.0;

		for ($r = 0; $r <= 255; $r += 15) {
			for ($g = 0; $g <= 255; $g += 15) {
				for ($b = 0; $b <= 255; $b += 15) {
					$ground = sprintf('#%02x%02x%02x', $r, $g, $b);
					$label  = bridge_readable_on($ground, array());
					$worst  = min($worst, bridge_contrast_ratio($ground, $label));
				}
			}
		}

		$this->assertGreaterThanOrEqual(4.5, $worst);
	}

	// ---- bridge_hex_alpha ------------------------------------------------

	/**
	 * Eight-digit hex, because an inline style cannot carry a function.
	 *
	 * `safecss_filter_attr()` strips the CSS functions it knows and then throws
	 * away any declaration with a parenthesis left in it. `color-mix()` and
	 * `rgba()` are both absent from its list, so both vanish silently — which
	 * is what this function exists to avoid, and why the shape of the return
	 * value is the thing worth pinning.
	 */
	public function test_an_alpha_is_appended_as_two_hex_digits(): void
	{
		$this->assertSame('#08505999', bridge_hex_alpha('#085059', 0.6));
		$this->assertSame('#085059ff', bridge_hex_alpha('#085059', 1.0));
		$this->assertSame('#08505900', bridge_hex_alpha('#085059', 0.0));
	}

	public function test_an_alpha_outside_the_range_is_clamped(): void
	{
		$this->assertSame('#085059ff', bridge_hex_alpha('#085059', 4.0));
		$this->assertSame('#08505900', bridge_hex_alpha('#085059', -1.0));
	}

	public function test_a_short_hex_is_expanded_before_the_alpha(): void
	{
		$this->assertSame('#00445599', bridge_hex_alpha('#045', 0.6));
	}

	public function test_an_alpha_hex_carries_no_parentheses(): void
	{
		$this->assertDoesNotMatchRegularExpression(
			'/[()]/',
			bridge_hex_alpha('#ffcb2e', 0.6)
		);
	}

	// ---- bridge_shade_hex ------------------------------------------------

	public function test_shading_by_zero_changes_nothing(): void
	{
		$this->assertSame('#2563eb', bridge_shade_hex('#2563eb', 0.0));
	}

	public function test_a_dark_colour_lightens(): void
	{
		$shaded = bridge_shade_hex('#0f172a', 0.5);

		$this->assertGreaterThan(
			bridge_relative_luminance('#0f172a'),
			bridge_relative_luminance($shaded)
		);
	}

	public function test_a_light_colour_darkens(): void
	{
		$shaded = bridge_shade_hex('#f5f5f4', 0.5);

		$this->assertLessThan(
			bridge_relative_luminance('#f5f5f4'),
			bridge_relative_luminance($shaded)
		);
	}

	/**
	 * Direction is decided by luminance, not by the channels.
	 *
	 * Yellow has two channels at full strength and is far too bright to
	 * lighten. A midpoint test on the raw channels would send it the wrong
	 * way; this is the case that catches it.
	 */
	public function test_a_bright_two_channel_colour_darkens(): void
	{
		$this->assertLessThan(
			bridge_relative_luminance('#ffff00'),
			bridge_relative_luminance(bridge_shade_hex('#ffff00', 0.5))
		);
	}

	public function test_shading_all_the_way_reaches_the_target(): void
	{
		$this->assertSame('#ffffff', bridge_shade_hex('#0f172a', 1.0));
		$this->assertSame('#000000', bridge_shade_hex('#f5f5f4', 1.0));
	}

	public function test_the_amount_is_clamped(): void
	{
		$this->assertSame(bridge_shade_hex('#0f172a', 1.0), bridge_shade_hex('#0f172a', 4.0));
		$this->assertSame(bridge_shade_hex('#0f172a', 0.0), bridge_shade_hex('#0f172a', -2.0));
	}

	public function test_shading_always_returns_a_six_digit_hex(): void
	{
		foreach (array('#000', '#fff', '#2563eb', 'nonsense') as $input) {
			$this->assertMatchesRegularExpression(
				'/^#[0-9a-f]{6}$/',
				bridge_shade_hex($input, 0.3)
			);
		}
	}
}
