<?php

/**
 * Bridge — colour maths.
 *
 * The arithmetic behind every "is this legible?" question the theme asks
 * itself. Kept apart from the token record and from the blocks that consume
 * it, because it is pure: hex in, number out, no WordPress state involved.
 *
 * `bridge_is_light_color()` in functions.php answers a coarser question — is
 * this colour light — using BT.601 perceived luminance, which is fine for
 * picking a logo variant and wrong for deciding whether a label passes
 * WCAG 1.4.3. What follows is the ratio WCAG actually defines, so a contrast
 * claim this theme makes in the options screen is the same number an auditing
 * tool will read off the rendered page.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Split a hex colour into its three 0–255 channels.
 *
 * Total: anything unparseable comes back black, because every caller here is
 * computing a contrast ratio and black is the answer that fails loudly rather
 * than the one that quietly passes.
 *
 * @param string $hex Colour as #rgb or #rrggbb, with or without the hash.
 * @return array{0: int, 1: int, 2: int} Red, green and blue.
 */
function bridge_hex_channels(string $hex): array
{
	$hex = ltrim(trim($hex), '#');

	if (3 === strlen($hex)) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	if (6 !== strlen($hex) || ! ctype_xdigit($hex)) {
		return array(0, 0, 0);
	}

	return array(
		(int) hexdec(substr($hex, 0, 2)),
		(int) hexdec(substr($hex, 2, 2)),
		(int) hexdec(substr($hex, 4, 2)),
	);
}

/**
 * Reassemble three channels into a `#rrggbb` string.
 *
 * @param array{0: int|float, 1: int|float, 2: int|float} $rgb Channels, clamped here.
 * @return string A lowercase hex colour.
 */
function bridge_channels_hex(array $rgb): string
{
	$hex = '#';

	foreach (array(0, 1, 2) as $i) {
		$channel = (int) round(min(255, max(0, (float) ($rgb[$i] ?? 0))));
		$hex    .= str_pad(dechex($channel), 2, '0', STR_PAD_LEFT);
	}

	return $hex;
}

/**
 * WCAG relative luminance.
 *
 * The sRGB transfer function from WCAG 2.x §relative luminance, not a
 * perceptual approximation. The constants are normative — do not "simplify"
 * them, because the ratio computed from them is what the success criteria are
 * written against.
 *
 * @param string $hex Colour.
 * @return float 0 for black, 1 for white.
 */
function bridge_relative_luminance(string $hex): float
{
	$channels = bridge_hex_channels($hex);
	$linear   = array();

	foreach ($channels as $value) {
		$srgb     = $value / 255;
		$linear[] = $srgb <= 0.04045 ? $srgb / 12.92 : pow(($srgb + 0.055) / 1.055, 2.4);
	}

	return (0.2126 * $linear[0]) + (0.7152 * $linear[1]) + (0.0722 * $linear[2]);
}

/**
 * The WCAG contrast ratio between two colours.
 *
 * Symmetric, and always at least 1. The thresholds a caller compares against:
 * 4.5 for body-sized text (1.4.3), 3 for large text and for the boundary of a
 * control (1.4.11), 3 for a focus indicator against what it sits on (1.4.11,
 * and 2.4.13 at AAA).
 *
 * @param string $one Colour.
 * @param string $two Colour.
 * @return float Ratio, rounded to two places — the precision anyone quotes.
 */
function bridge_contrast_ratio(string $one, string $two): float
{
	$a = bridge_relative_luminance($one);
	$b = bridge_relative_luminance($two);

	$lighter = max($a, $b);
	$darker  = min($a, $b);

	return round(($lighter + 0.05) / ($darker + 0.05), 2);
}

/**
 * The first candidate legible on a given ground, or the most legible of them.
 *
 * Used to pick a button's label colour rather than asking an operator to. A
 * label is not a design decision: it has to clear 4.5:1 against the fill
 * behind it, and among the colours that do, the brand's own is better than a
 * more contrasty one that is not in the palette. So this returns the *first*
 * candidate that passes rather than the highest-scoring — a client's near-black
 * on their white button is their brand, and #000000 is not, even though pure
 * black scores higher.
 *
 * Only when nothing offered passes does it fall back to whichever of black and
 * white scores best, and that fallback always passes: every colour reaches at
 * least 4.58:1 against one of the two, so this function cannot return a label
 * that fails 1.4.3.
 *
 * @param string   $ground     The colour the text is drawn on.
 * @param string[] $candidates Colours to choose between, best-liked first.
 * @param float    $target     Ratio a candidate has to clear to be chosen.
 * @return string The winning candidate.
 */
function bridge_readable_on(string $ground, array $candidates, float $target = 4.5): string
{
	$best  = '#ffffff';
	$score = -1.0;

	foreach ($candidates as $candidate) {
		$candidate = (string) $candidate;

		if ('' === $candidate) {
			continue;
		}

		if (bridge_contrast_ratio($ground, $candidate) >= $target) {
			return $candidate;
		}
	}

	foreach (array('#ffffff', '#000000') as $candidate) {
		$ratio = bridge_contrast_ratio($ground, $candidate);

		if ($ratio > $score) {
			$best  = $candidate;
			$score = $ratio;
		}
	}

	return $best;
}

/**
 * Move a colour towards black or white by a proportion of the distance.
 *
 * A hover state has to be visibly different from rest and still legible under
 * the same label, which rules out `color-mix()` in the stylesheet: the browser
 * would compute a colour no one had checked. Shading server-side means the
 * hover fill is a known hex, so its contrast can be measured and reported
 * beside the resting one.
 *
 * Direction is decided by the colour itself — a dark fill lightens on hover, a
 * light one darkens — so the state reads as "lit up" on every ground rather
 * than disappearing into one of them.
 *
 * @param string $hex    Colour to shade.
 * @param float  $amount 0–1 proportion of the distance to the target.
 * @return string A `#rrggbb` value.
 */
function bridge_shade_hex(string $hex, float $amount): string
{
	$amount   = min(1.0, max(0.0, $amount));
	$channels = bridge_hex_channels($hex);

	// The midpoint of *luminance*, not of the channels: #ffff00 has two maxed
	// channels and is far too bright to lighten further.
	$target = bridge_relative_luminance($hex) > 0.5 ? 0 : 255;

	$shaded = array();

	foreach ($channels as $value) {
		$shaded[] = $value + (($target - $value) * $amount);
	}

	return bridge_channels_hex($shaded);
}

/**
 * A colour with an alpha channel, as an eight-digit hex.
 *
 * `#rrggbbaa` rather than `rgba()` or a `color-mix()`, and the reason is not
 * taste. These values are printed into a `style` attribute, and WordPress runs
 * every inline style through `safecss_filter_attr()`, which strips the CSS
 * functions it knows — var, calc, clamp and a fixed list of others — and then
 * throws away any declaration with a parenthesis still in it. `color-mix()` is
 * not on that list and neither is `rgba()`, so both are silently dropped: no
 * error, no declaration, and a setting that appears to do nothing. A hex has
 * no parentheses to survive the test.
 *
 * @param string $hex   Colour to give an alpha channel.
 * @param float  $alpha 0-1 opacity.
 * @return string A `#rrggbbaa` value.
 */
function bridge_hex_alpha(string $hex, float $alpha): string
{
	$alpha    = min(1.0, max(0.0, $alpha));
	$channels = bridge_hex_channels($hex);

	return sprintf('%s%02x', bridge_channels_hex($channels), (int) round($alpha * 255));
}
