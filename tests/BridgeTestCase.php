<?php

/**
 * Shared setup: a clean option store and a cleared memo before every test.
 *
 * `bridge_get_tokens()` memoises per request, and a test run is one long
 * request. Without the reset, the first test to resolve the tokens would fix
 * them for every test after it — and the failure would show up as an unrelated
 * test failing whenever the order changed.
 *
 * @package Bridge
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

abstract class BridgeTestCase extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$GLOBALS['bridge_test_options'] = array();
		bridge_tokens_memo(null, true);
	}

	protected function tearDown(): void
	{
		$GLOBALS['bridge_test_options'] = array();
		bridge_tokens_memo(null, true);

		parent::tearDown();
	}

	/**
	 * A complete palette, for tests that need to vary the brand.
	 *
	 * @param array<string, string> $overrides Slug => hex.
	 * @return array<string, mixed> A `brand.palette` fragment.
	 */
	protected function palette(array $overrides = array()): array
	{
		$palette = array(
			'primary'    => '#0f172a',
			'secondary'  => '#2563eb',
			'accent'     => '#f59e0b',
			'background' => '#ffffff',
			'surface'    => '#f5f5f4',
			'text'       => '#1f2937',
		);

		$entries = array();

		foreach (array_merge($palette, $overrides) as $slug => $hex) {
			$entries[$slug] = array('color' => $hex, 'name' => ucfirst($slug));
		}

		return array('brand' => array('palette' => $entries));
	}
}
