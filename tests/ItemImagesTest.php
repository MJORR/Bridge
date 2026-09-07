<?php

/**
 * inc/section-blocks.php — the images a band fetches before its items render.
 *
 * `bridge_prime_item_images()` is the difference between a twenty-image gallery
 * costing two queries and costing forty, and it works off a list of attribute
 * names. A list is a thing that goes stale: an item block added next year with
 * its media in `photoId` would keep working perfectly and quietly lose the
 * optimisation, and nothing on the page would look wrong.
 *
 * So the test is against the blocks themselves rather than against the list.
 * It reads every block.json in the theme, and asks that each attribute holding
 * an id is either primed or deliberately exempt — which makes adding a block
 * with an unprimed image a failing test rather than a slow page nobody
 * measures.
 *
 * @package Bridge
 */

declare(strict_types=1);

final class ItemImagesTest extends BridgeTestCase
{
	/**
	 * Id-shaped attributes that are not attachment ids.
	 *
	 * Priming these would be asking the posts table for a row that was never
	 * going to be there. Each one is listed with what it actually holds, so
	 * that adding to this list is a decision rather than a way to quiet a
	 * failure.
	 *
	 * @var array<string, string>
	 */
	private const NOT_ATTACHMENTS = array(
		// A Google Places identifier, resolved by the Maps API in the browser.
		'placeId'   => 'bridge/map',
		// A YouTube video id out of the embed URL.
		'youtubeId' => 'bridge/alternating-row',
	);

	/**
	 * @return array<string, string[]> Attribute name => the blocks declaring it.
	 */
	private function idAttributes(): array
	{
		$found = array();

		foreach (glob(dirname(__DIR__) . '/src/blocks/*/block.json') as $file) {
			$metadata = json_decode((string) file_get_contents($file), true);

			if (! is_array($metadata)) {
				$this->fail('Unreadable block.json: ' . $file);
			}

			foreach (array_keys((array) ($metadata['attributes'] ?? array())) as $name) {
				if (preg_match('/Ids?$/', (string) $name)) {
					$found[$name][] = basename(dirname($file));
				}
			}
		}

		return $found;
	}

	public function test_every_attachment_id_attribute_is_primed(): void
	{
		$primed = bridge_item_image_attributes();

		foreach ($this->idAttributes() as $name => $blocks) {
			if (isset(self::NOT_ATTACHMENTS[$name])) {
				continue;
			}

			$this->assertContains(
				$name,
				$primed,
				sprintf(
					'%s (%s) holds an attachment id that bridge_prime_item_images() will not fetch. '
						. 'Add it to bridge_item_image_attributes(), or to this test\'s NOT_ATTACHMENTS '
						. 'if it is not a media library id.',
					$name,
					implode(', ', $blocks)
				)
			);
		}
	}

	/**
	 * And the list has nothing in it that no block declares.
	 *
	 * A name left behind after a block was renamed costs one `absint()` per
	 * item, which is nothing — but it is also a lie about what the theme
	 * stores, and the next person reading the list would go looking for the
	 * block that uses it.
	 */
	public function test_nothing_is_primed_that_no_block_declares(): void
	{
		$declared = array_keys($this->idAttributes());

		foreach (bridge_item_image_attributes() as $name) {
			$this->assertContains(
				$name,
				$declared,
				$name . ' is primed but no block declares it.'
			);
		}
	}

	/**
	 * The exemptions describe blocks that exist.
	 *
	 * The same argument in the other direction: an exemption for a block that
	 * has gone is an exemption that could hide a real one later.
	 */
	public function test_the_exemptions_are_still_real(): void
	{
		$declared = $this->idAttributes();

		foreach (self::NOT_ATTACHMENTS as $name => $block) {
			$this->assertArrayHasKey(
				$name,
				$declared,
				$name . ' is exempted from priming but no block declares it any more.'
			);
		}
	}

	// ---- The priming itself ------------------------------------------------

	protected function setUp(): void
	{
		parent::setUp();

		$GLOBALS['bridge_test_primed'] = array();
	}

	/**
	 * Build a band with the given item attributes.
	 *
	 * @param array<int, array<string, mixed>> $items
	 */
	private function band(array $items): WP_Block
	{
		return new WP_Block(
			'bridge/gallery',
			array(),
			array_map(
				static function (array $attributes): WP_Block {
					return new WP_Block('bridge/gallery-item', $attributes);
				},
				$items
			)
		);
	}

	public function test_a_band_fetches_all_of_its_images_at_once(): void
	{
		bridge_prime_item_images(
			$this->band(
				array(
					array('imageId' => 11),
					array('imageId' => 12),
					array('graphicId' => 13, 'backgroundId' => 14),
				)
			)
		);

		$this->assertCount(1, $GLOBALS['bridge_test_primed']);
		$this->assertSame(
			array(11, 12, 13, 14),
			$GLOBALS['bridge_test_primed'][0]['ids']
		);
		// Meta, because that is where an image's sizes and its alt text are.
		// No terms: an attachment rarely has any and no item block reads them.
		$this->assertTrue($GLOBALS['bridge_test_primed'][0]['meta']);
		$this->assertFalse($GLOBALS['bridge_test_primed'][0]['terms']);
	}

	public function test_the_same_image_twice_is_fetched_once(): void
	{
		bridge_prime_item_images(
			$this->band(
				array(
					array('imageId' => 11),
					array('imageId' => 11),
					array('imageId' => 12),
				)
			)
		);

		$this->assertSame(array(11, 12), $GLOBALS['bridge_test_primed'][0]['ids']);
	}

	/**
	 * One image is left to fetch itself.
	 *
	 * Priming a single attachment is the same two queries the item was going
	 * to make, so the band pays for the walk and saves nothing.
	 */
	public function test_a_single_image_is_not_worth_priming(): void
	{
		bridge_prime_item_images($this->band(array(array('imageId' => 11))));

		$this->assertSame(array(), $GLOBALS['bridge_test_primed']);
	}

	public function test_items_with_no_images_ask_for_nothing(): void
	{
		bridge_prime_item_images(
			$this->band(
				array(
					array('title' => 'One'),
					array('imageId' => 0),
					// What an unset media field saves.
					array('imageId' => null),
				)
			)
		);

		$this->assertSame(array(), $GLOBALS['bridge_test_primed']);
	}
}
