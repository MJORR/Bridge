<?php

/**
 * The focal point, and the zoom that pays for it.
 *
 * The banner moves its picture rather than choosing a crop of it, which is the
 * only thing that works on a photograph the same shape as the band. The cost
 * used to be a hole: the picture is the size of the band, so anything that
 * moves it uncovers the ground behind. bridge_hero_focal_zoom() is what closes
 * that hole, and the arithmetic in it is the kind that is wrong by a factor of
 * two without anyone noticing until a banner is published with a strip of flat
 * colour down one side.
 */

declare(strict_types=1);

final class HeroFocalTest extends BridgeTestCase
{
	/**
	 * @param float $x 0–1.
	 * @param float $y 0–1.
	 * @return array Attributes carrying that focal point.
	 */
	private function point(float $x, float $y): array
	{
		return array('focalPoint' => array('x' => $x, 'y' => $y));
	}

	public function test_a_centred_picture_is_not_moved_and_not_grown(): void
	{
		$this->assertSame('0%,0%', bridge_hero_focal_shift($this->point(0.5, 0.5)));
		$this->assertSame('1', bridge_hero_focal_zoom($this->point(0.5, 0.5)));
		$this->assertSame('50% 50%', bridge_hero_focal_point($this->point(0.5, 0.5)));
	}

	public function test_an_empty_block_is_centred(): void
	{
		$this->assertSame('0%,0%', bridge_hero_focal_shift(array()));
		$this->assertSame('1', bridge_hero_focal_zoom(array()));
	}

	/**
	 * The move and the scale are one sum, said twice.
	 *
	 * The picture reaches half a band times the scale either side of centre, so
	 * a move of `t` leaves its near edge at `t - z / 2`. That has to be at or
	 * past the band's own edge at `-1 / 2`, which is `z = 1 + 2|t|` at the
	 * tightest. Anything less and the ground shows; this is the test that says
	 * so in the same terms the stylesheet spends them.
	 */
	public function test_the_picture_always_still_covers_the_band(): void
	{
		foreach (array(0.0, 0.1, 0.25, 0.4, 0.5, 0.6, 0.75, 0.9, 1.0) as $x) {
			foreach (array(0.0, 0.3, 0.5, 0.8, 1.0) as $y) {
				$zoom = (float) bridge_hero_focal_zoom($this->point($x, $y));

				// What the stylesheet does with the two numbers, as shares of
				// the band: scale about the centre, then translate.
				foreach (array(0.5 - $x, 0.5 - $y) as $shift) {
					$near = $shift - $zoom / 2;
					$far  = $shift + $zoom / 2;

					$this->assertLessThanOrEqual(
						-0.5 + 1e-9,
						$near,
						"Ground showing on the near edge at x=$x, y=$y."
					);
					$this->assertGreaterThanOrEqual(
						0.5 - 1e-9,
						$far,
						"Ground showing on the far edge at x=$x, y=$y."
					);
				}
			}
		}
	}

	/**
	 * And no larger than it has to be.
	 *
	 * A scale that always returned 2 would pass the test above and crop every
	 * banner to half its picture. The tightest answer is the only useful one.
	 */
	public function test_the_picture_is_grown_no_further_than_the_move_needs(): void
	{
		$this->assertSame('1.2', bridge_hero_focal_zoom($this->point(0.4, 0.5)));
		$this->assertSame('1.5', bridge_hero_focal_zoom($this->point(0.5, 0.25)));
		$this->assertSame('2', bridge_hero_focal_zoom($this->point(0.0, 0.5)));
		$this->assertSame('2', bridge_hero_focal_zoom($this->point(1.0, 0.5)));
	}

	/**
	 * The larger of the two axes decides, because one scale has to answer both.
	 */
	public function test_the_further_axis_decides(): void
	{
		// x asks for 1.4, y for 1.8. One picture, one scale.
		$this->assertSame('1.8', bridge_hero_focal_zoom($this->point(0.3, 0.1)));
		$this->assertSame('1.8', bridge_hero_focal_zoom($this->point(0.1, 0.3)));
	}

	public function test_a_stored_point_outside_the_picture_is_clamped(): void
	{
		$this->assertSame('2', bridge_hero_focal_zoom($this->point(-9.0, 0.5)));
		$this->assertSame('2', bridge_hero_focal_zoom($this->point(9.0, 0.5)));
		$this->assertSame('-50%,0%', bridge_hero_focal_shift($this->point(9.0, 0.5)));
		$this->assertSame('100% 50%', bridge_hero_focal_point($this->point(9.0, 0.5)));

		// And junk where the point should be.
		$this->assertSame('1', bridge_hero_focal_zoom(array('focalPoint' => 'nonsense')));
	}
}
