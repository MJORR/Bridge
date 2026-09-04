<?php

/**
 * Bridge — the decorative mask shape a band can carry.
 *
 * The shape's colour used to be a palette slug, which made it a sixth brand
 * colour competing with the five already in the band. It is a signed
 * percentage now — darker one way, lighter the other — drawn as a black or
 * white overlay so one setting reads the same on a colour, a card ground or a
 * photograph. These pin that arithmetic, and the two ways the shape is meant
 * not to be drawn at all.
 *
 * @package Bridge
 */

declare(strict_types=1);

final class BandMaskTest extends BridgeTestCase
{
	/** @param array<string, mixed> $attributes */
	private function mask(array $attributes): array
	{
		$this->setShape(7);

		return bridge_band_mask(array_merge(array('mask' => true), $attributes));
	}

	/** Give the site a mask shape, or take it away with 0. */
	private function setShape(int $id): void
	{
		bridge_update_tokens(
			bridge_sanitize_tokens(array('brand' => array('maskShapeId' => $id)))
		);
		bridge_tokens_memo(null, true);
	}

	public function test_a_negative_shade_darkens_with_black(): void
	{
		list($class, $style) = $this->mask(array('maskShade' => -20));

		$this->assertSame(' has-mask', $class);
		$this->assertStringContainsString('--bridge-band-mask-color:#00000033;', $style);
	}

	public function test_a_positive_shade_lightens_with_white(): void
	{
		list(, $style) = $this->mask(array('maskShade' => 20));

		$this->assertStringContainsString('--bridge-band-mask-color:#ffffff33;', $style);
	}

	public function test_the_extremes_are_fully_opaque(): void
	{
		$this->assertStringContainsString('#000000ff;', $this->mask(array('maskShade' => -100))[1]);
		$this->assertStringContainsString('#ffffffff;', $this->mask(array('maskShade' => 100))[1]);
	}

	public function test_a_shade_outside_the_range_is_clamped(): void
	{
		$this->assertStringContainsString('#000000ff;', $this->mask(array('maskShade' => -400))[1]);
		$this->assertStringContainsString('#ffffffff;', $this->mask(array('maskShade' => 400))[1]);
	}

	/**
	 * Zero is the off position, not a transparent shape.
	 *
	 * A `#00000000` overlay would still add the class, the pseudo-element and
	 * the mask image — a shape nobody can see, costing a paint.
	 */
	public function test_a_zero_shade_draws_nothing(): void
	{
		$this->assertSame(array('', ''), $this->mask(array('maskShade' => 0)));
	}

	public function test_a_band_with_the_shape_switched_off_draws_nothing(): void
	{
		$this->setShape(7);

		$this->assertSame(
			array('', ''),
			bridge_band_mask(array('mask' => false, 'maskShade' => -20))
		);
	}

	/**
	 * A site that has not chosen a shape gets none, whatever the block says.
	 */
	public function test_no_shape_on_the_site_draws_nothing(): void
	{
		$this->setShape(0);

		$this->assertSame(
			array('', ''),
			bridge_band_mask(array('mask' => true, 'maskShade' => -20))
		);
	}

	public function test_the_default_shade_is_used_when_the_block_carries_none(): void
	{
		list(, $style) = $this->mask(array());

		$this->assertStringContainsString('--bridge-band-mask-color:#00000033;', $style);
	}
}
