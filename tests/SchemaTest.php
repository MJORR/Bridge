<?php

/**
 * Bridge — the JSON-LD the theme publishes.
 *
 * Structured data fails silently. A property spelled wrong, a node that says
 * nothing, a trail whose last step points at itself: none of it throws, none
 * of it shows up on the page, and the first anyone hears of it is a report six
 * months later saying the site has no knowledge panel. That is exactly the
 * shape of code a unit test is for — it is all array building, and every
 * assertion below is a claim the theme is making about a client's business.
 *
 * @package Bridge
 */

declare(strict_types=1);

final class SchemaTest extends BridgeTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->view('front');
	}

	protected function tearDown(): void
	{
		$this->view('front');

		unset(
			$GLOBALS['bridge_test_theme_mods'],
			$GLOBALS['bridge_test_bloginfo']
		);

		parent::tearDown();
	}

	/**
	 * Point the request stubs at one kind of page.
	 *
	 * @param string                $view      front, singular, archive or search.
	 * @param array<string, mixed>  $state     Extra globals for that view.
	 */
	private function view(string $view, array $state = array()): void
	{
		$GLOBALS['bridge_test_view']    = $view;
		$GLOBALS['bridge_test_post_id'] = 0;
		$GLOBALS['bridge_test_titles']  = array();
		unset($GLOBALS['bridge_test_ancestors'], $GLOBALS['bridge_test_search']);

		foreach ($state as $key => $value) {
			$GLOBALS[$key] = $value;
		}
	}

	/**
	 * Store a set of Site Options, the way the options screen would.
	 *
	 * @param array<string, mixed> $values
	 */
	private function siteOptions(array $values): void
	{
		update_option(BRIDGE_SITE_OPTIONS_KEY, $values);
	}

	// ---- The encoder -------------------------------------------------------

	/**
	 * The escaping, which is the one line in the schema layer that is a
	 * security property rather than an SEO one.
	 *
	 * A company name or a testimonial containing `</script>` closed the element
	 * it was printed in and put whatever followed into the document as markup.
	 * `JSON_HEX_TAG` is what stops it, and this is the test that says so —
	 * without it the flag reads as tidiness and the next person to touch the
	 * encoder drops it.
	 */
	public function test_json_escapes_markup_out_of_the_script_element(): void
	{
		$block = bridge_schema_json(array('name' => 'Acme </script><script>alert(1)</script>'));

		$this->assertStringNotContainsString('</script><script>', $block);
		// Both angle brackets encoded, which is what JSON_HEX_TAG does.
		$this->assertStringContainsString('\u003C', $block);
		$this->assertStringContainsString('\u003E', $block);
		// One closing tag: the element's own.
		$this->assertSame(1, substr_count($block, '</script>'));

		// And still valid JSON carrying the original string, because escaping
		// it for HTML must not change what a parser reads.
		$json = substr($block, strlen('<script type="application/ld+json">'), -strlen('</script>'));
		$this->assertSame(
			'Acme </script><script>alert(1)</script>',
			json_decode($json, true)['name']
		);
	}

	// ---- The Organization node --------------------------------------------

	public function test_organization_falls_back_to_the_site_title(): void
	{
		$node = bridge_schema_organization();

		$this->assertSame('Organization', $node['@type']);
		$this->assertSame('Example Site', $node['name']);
		$this->assertSame('https://example.test/#organization', $node['@id']);
	}

	public function test_organization_prefers_the_trading_name(): void
	{
		$this->siteOptions(array('company' => 'Acme Widgets Ltd'));

		$this->assertSame('Acme Widgets Ltd', bridge_schema_organization()['name']);
	}

	/**
	 * A field nobody filled in is a property that is not published.
	 *
	 * An empty `telephone` is not the absence of a phone number, it is the
	 * claim that the number is the empty string — and a node full of those is
	 * a node a consumer has reason to distrust.
	 */
	public function test_organization_publishes_nothing_it_was_not_given(): void
	{
		$node = bridge_schema_organization();

		$this->assertArrayNotHasKey('telephone', $node);
		$this->assertArrayNotHasKey('address', $node);
		$this->assertArrayNotHasKey('sameAs', $node);
		$this->assertArrayNotHasKey('logo', $node);
	}

	public function test_organization_carries_the_contact_details(): void
	{
		$this->siteOptions(
			array(
				'phone'   => '01234 567 890',
				'address' => "Unit 4\nThe Old Mill\nBristol\nBS1 4TR",
			)
		);

		$node = bridge_schema_organization();

		$this->assertSame('01234 567 890', $node['telephone']);
		// The textarea's line breaks are how it is laid out on an envelope,
		// not part of what it says.
		$this->assertSame('Unit 4, The Old Mill, Bristol, BS1 4TR', $node['address']);
	}

	public function test_organization_publishes_the_social_profiles_as_same_as(): void
	{
		$this->siteOptions(
			array(
				'social' => array(
					'linkedin'  => 'https://linkedin.com/company/acme',
					'facebook'  => '',
					'youtube'   => '',
					'instagram' => 'https://instagram.com/acme',
				),
			)
		);

		$node = bridge_schema_organization();

		// A list, not a map: `sameAs` is an array of URLs, and the empty
		// networks are gone rather than published as blanks.
		$this->assertSame(
			array('https://linkedin.com/company/acme', 'https://instagram.com/acme'),
			$node['sameAs']
		);
	}

	public function test_organization_names_the_logo_once_and_refers_to_it(): void
	{
		$GLOBALS['bridge_test_theme_mods'] = array('custom_logo' => 12);

		$node = bridge_schema_organization();

		$this->assertSame('ImageObject', $node['logo']['@type']);
		$this->assertSame('https://example.test/logo-12.png', $node['logo']['url']);
		$this->assertSame(512, $node['logo']['width']);
		// `image` is a reference to the same node, not a second copy of it.
		$this->assertSame(array('@id' => $node['logo']['@id']), $node['image']);
	}

	// ---- The WebSite node --------------------------------------------------

	public function test_website_is_published_by_the_organization(): void
	{
		$node = bridge_schema_website();

		$this->assertSame('WebSite', $node['@type']);
		$this->assertSame(
			array('@id' => 'https://example.test/#organization'),
			$node['publisher']
		);
		// The sitelinks search box was retired by Google in 2024; publishing a
		// SearchAction is markup to maintain for a feature that no longer
		// exists.
		$this->assertArrayNotHasKey('potentialAction', $node);
	}

	// ---- The breadcrumb trail ----------------------------------------------

	public function test_no_breadcrumb_on_the_front_page(): void
	{
		$this->assertSame(array(), bridge_breadcrumb_items());
		$this->assertSame(array(), bridge_breadcrumb_schema());
	}

	/**
	 * A page directly under the front page has no trail either.
	 *
	 * Two steps is the shortest trail worth drawing — "Home / About" tells a
	 * visitor something. One is a link to where they are not, under a heading
	 * that already says where they are.
	 */
	public function test_a_top_level_page_still_has_two_steps(): void
	{
		$this->view(
			'singular',
			array(
				'bridge_test_post_id' => 7,
				'bridge_test_titles'  => array(7 => 'About us'),
			)
		);

		$items = bridge_breadcrumb_items();

		$this->assertCount(2, $items);
		$this->assertSame('Home', $items[0]['label']);
		$this->assertSame('About us', $items[1]['label']);
		// The last step is the page itself, so it carries no URL.
		$this->assertSame('', $items[1]['url']);
	}

	public function test_ancestors_are_walked_from_the_front_page_down(): void
	{
		$this->view(
			'singular',
			array(
				'bridge_test_post_id'   => 9,
				// Core returns the nearest parent first.
				'bridge_test_ancestors' => array(5, 2),
				'bridge_test_titles'    => array(
					2 => 'Services',
					5 => 'Consulting',
					9 => 'Strategy days',
				),
			)
		);

		$this->assertSame(
			array('Home', 'Services', 'Consulting', 'Strategy days'),
			array_column(bridge_breadcrumb_items(), 'label')
		);
	}

	public function test_breadcrumb_schema_numbers_the_steps_from_one(): void
	{
		$this->view(
			'singular',
			array(
				'bridge_test_post_id'   => 9,
				'bridge_test_ancestors' => array(2),
				'bridge_test_titles'    => array(2 => 'Services', 9 => 'Strategy days'),
			)
		);

		$schema = bridge_breadcrumb_schema();

		$this->assertSame('BreadcrumbList', $schema['@type']);
		$this->assertSame(array(1, 2, 3), array_column($schema['itemListElement'], 'position'));
		$this->assertSame(
			array('Home', 'Services', 'Strategy days'),
			array_column($schema['itemListElement'], 'name')
		);
	}

	/**
	 * The last step names no `item`.
	 *
	 * The vocabulary's own rule, and the same decision the markup makes by not
	 * linking the current page: the result is already about this URL, so a
	 * final step pointing at it is a self-reference rather than a destination.
	 */
	public function test_the_current_page_is_the_one_step_with_no_url(): void
	{
		$this->view(
			'singular',
			array(
				'bridge_test_post_id' => 7,
				'bridge_test_titles'  => array(7 => 'About us'),
			)
		);

		$elements = bridge_breadcrumb_schema()['itemListElement'];

		$this->assertSame('https://example.test/', $elements[0]['item']);
		$this->assertArrayNotHasKey('item', $elements[1]);
	}

	public function test_an_archive_is_named_by_its_own_title(): void
	{
		$this->view('archive', array('bridge_test_archive_title' => 'Category: News'));

		$this->assertSame(
			array('Home', 'Category: News'),
			array_column(bridge_breadcrumb_items(), 'label')
		);
	}
}
