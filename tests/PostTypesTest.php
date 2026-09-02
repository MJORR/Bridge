<?php

/**
 * inc/tokens.php — the `postTypes` branch of bridge_sanitize_tokens().
 *
 * Every other group in the record is a fixed set of keys with a default behind
 * each, so the sanitiser's job there is to clamp. This one is a list an
 * operator grows, and its job is to *reject* — a bad row is dropped rather
 * than corrected into something the operator never typed.
 *
 * That difference is the whole of what these tests are about: a slug that
 * collides with a core post type or a reserved query variable does not produce
 * a broken content type, it produces no content type at all.
 *
 * @package Bridge
 */

declare(strict_types=1);

final class PostTypesTest extends BridgeTestCase
{
	/**
	 * Sanitise a bare list of rows.
	 *
	 * @param array<int, mixed> $rows Candidate rows.
	 * @return array<int, array<string, string>> The rows that survived.
	 */
	private function sanitize(array $rows): array
	{
		return bridge_sanitize_tokens(array('postTypes' => $rows))['postTypes'];
	}

	// ---- The happy path --------------------------------------------------

	public function test_a_complete_row_is_kept_as_written(): void
	{
		$this->assertSame(
			array(
				array(
					'singular'      => 'Project',
					'plural'        => 'Projects',
					'slug'          => 'project',
					'enabled'       => true,
					'hasPages'      => true,
					'hasCategories' => false,
				),
			),
			$this->sanitize(
				array(
					array(
						'singular' => 'Project',
						'plural'   => 'Projects',
						'slug'     => 'project',
					),
				)
			)
		);
	}

	/** The default for the whole group is "this site has none". */
	public function test_no_post_types_by_default(): void
	{
		$this->assertSame(array(), bridge_sanitize_tokens(array())['postTypes']);
	}

	// ---- Pages, or material for blocks -----------------------------------

	/**
	 * Absent means public.
	 *
	 * Load-bearing for upgrades: a record written before the switch existed
	 * described a type with an archive and a page per item, and reading a
	 * missing key as "off" would take a live site's archives away the next
	 * time somebody pressed Save on an unrelated tab.
	 */
	public function test_a_row_with_no_pages_field_keeps_its_pages(): void
	{
		$rows = $this->sanitize(array(array('singular' => 'Project')));

		$this->assertTrue($rows[0]['hasPages']);
	}

	/**
	 * @dataProvider provide_pages_flags
	 */
	public function test_the_pages_flag_is_a_boolean($given, bool $expected): void
	{
		$rows = $this->sanitize(
			array(array('singular' => 'FAQ', 'hasPages' => $given))
		);

		$this->assertSame($expected, $rows[0]['hasPages']);
	}

	/**
	 * @return array<string, array{0:mixed,1:bool}>
	 */
	public static function provide_pages_flags(): array
	{
		return array(
			'true'         => array(true, true),
			'false'        => array(false, false),
			'one'          => array(1, true),
			'zero'         => array(0, false),
			'empty string' => array('', false),
			// `null` reads as absent, not as off — `isset()` cannot tell the
			// two apart, and the safe reading of "I don't know" here is the
			// one that does not take a live site's archives away.
			'null'         => array(null, true),
			// JSON from a form control, which is where this actually comes
			// from. A non-empty string is truthy in PHP, including "false" —
			// worth pinning so nobody later decides to send one.
			'a string'     => array('yes', true),
		);
	}

	// ---- On, or off ------------------------------------------------------

	/**
	 * Absent means on.
	 *
	 * Load-bearing for the same reason `hasPages` is: every record written
	 * before this switch existed described a type that was registered, and
	 * reading a missing key as "off" would take a live site's content type out
	 * of the sidebar the next time anything pressed Save.
	 */
	public function test_a_row_with_no_enabled_field_is_on(): void
	{
		$rows = $this->sanitize(array(array('singular' => 'Project')));

		$this->assertTrue($rows[0]['enabled']);
	}

	/**
	 * Off is stored, not dropped.
	 *
	 * The whole point of the switch: the row keeps its name, its slug and every
	 * other setting so that switching it back on is one click rather than a
	 * retype. A sanitiser that discarded the row would make "off" and "remove"
	 * the same thing.
	 */
	public function test_a_row_that_is_switched_off_is_kept(): void
	{
		$rows = $this->sanitize(
			array(
				array(
					'singular' => 'FAQ',
					'plural'   => 'FAQs',
					'slug'     => 'faq',
					'enabled'  => false,
					'hasPages' => false,
				),
			)
		);

		$this->assertCount(1, $rows);
		$this->assertFalse($rows[0]['enabled']);
		$this->assertSame('faq', $rows[0]['slug']);
		$this->assertSame('FAQs', $rows[0]['plural']);
	}

	/**
	 * A switched-off row still holds its place at the cap.
	 *
	 * Pinned because it is the one consequence of keeping the row that could
	 * be argued the other way. It is the right way round: a type an operator
	 * switched off is one they intend to switch back on, and silently letting
	 * an eleventh type in beside it would make that impossible.
	 */
	public function test_a_switched_off_row_counts_towards_the_limit(): void
	{
		$rows = array();

		for ($i = 0; $i < bridge_post_type_limit(); $i++) {
			$rows[] = array(
				'singular' => 'Type',
				'slug'     => 'type_' . $i,
				'enabled'  => false,
			);
		}

		$rows[] = array('singular' => 'One More', 'slug' => 'one_more');

		$slugs = array_column($this->sanitize($rows), 'slug');

		$this->assertCount(bridge_post_type_limit(), $slugs);
		$this->assertNotContains('one_more', $slugs);
	}

	/**
	 * @dataProvider provide_enabled_flags
	 */
	public function test_the_enabled_flag_is_a_boolean($given, bool $expected): void
	{
		$rows = $this->sanitize(
			array(array('singular' => 'Thing', 'enabled' => $given))
		);

		$this->assertSame($expected, $rows[0]['enabled']);
	}

	/**
	 * @return array<string, array{0:mixed,1:bool}>
	 */
	public static function provide_enabled_flags(): array
	{
		return array(
			'true'         => array(true, true),
			'false'        => array(false, false),
			'zero'         => array(0, false),
			'empty string' => array('', false),
			// `null` reads as absent, not as off — the safe reading of "I
			// don't know" is the one that leaves the site's content types
			// where they are.
			'null'         => array(null, true),
		);
	}

	/** Only the rows that are on are the site's content types. */
	public function test_active_post_types_are_the_ones_switched_on(): void
	{
		bridge_update_tokens(
			array(
				'postTypes' => array(
					array('singular' => 'Team', 'slug' => 'team'),
					array('singular' => 'FAQ', 'slug' => 'faq', 'enabled' => false),
				),
			)
		);
		bridge_tokens_memo(null, true);

		$this->assertSame(
			array('team', 'faq'),
			array_column(bridge_declared_post_types(), 'slug')
		);
		$this->assertSame(
			array('team'),
			array_column(bridge_active_post_types(), 'slug')
		);
	}

	/** The active list is a list, whichever rows were dropped from it. */
	public function test_active_post_types_is_a_list(): void
	{
		bridge_update_tokens(
			array(
				'postTypes' => array(
					array('singular' => 'A', 'slug' => 'a_type', 'enabled' => false),
					array('singular' => 'B', 'slug' => 'b_type'),
				),
			)
		);
		bridge_tokens_memo(null, true);

		$this->assertTrue(array_is_list(bridge_active_post_types()));
	}

	// ---- The FAQ type ----------------------------------------------------

	/**
	 * The FAQs block reads one content type and it is this one.
	 *
	 * There is no source control on the block: "which content type" had one
	 * right answer, which made it a question that only existed to be answered
	 * wrong. A block pointed at Team renders a grid of biographies inside a
	 * disclosure widget and looks, in the editor, like it is working.
	 */
	public function test_the_faq_type_is_the_blocks_only_source(): void
	{
		bridge_update_tokens(
			array(
				'postTypes' => array(
					array('singular' => 'Team Member', 'plural' => 'Team', 'slug' => 'team'),
					array('singular' => 'FAQ', 'plural' => 'FAQs', 'slug' => 'faq'),
					array('singular' => 'Project', 'plural' => 'Projects', 'slug' => 'project'),
				),
			)
		);
		bridge_tokens_memo(null, true);

		$this->assertSame('faq', bridge_faq_post_type());
		$this->assertSame('FAQs', bridge_faq_post_type_entry()['plural']);
	}

	/**
	 * Switched off, there is no source — and no fallback to another type.
	 *
	 * The block reports this rather than working around it. Falling back would
	 * put somebody else's posts under a heading that says "Frequently asked
	 * questions".
	 */
	public function test_a_switched_off_faq_type_is_no_source(): void
	{
		bridge_update_tokens(
			array(
				'postTypes' => array(
					array('singular' => 'FAQ', 'plural' => 'FAQs', 'slug' => 'faq', 'enabled' => false),
					array('singular' => 'Team Member', 'plural' => 'Team', 'slug' => 'team'),
				),
			)
		);
		bridge_tokens_memo(null, true);

		$this->assertSame('', bridge_faq_post_type());
		$this->assertSame(array(), bridge_faq_post_type_entry());
	}

	/** Removed from the record entirely, likewise. */
	public function test_a_site_with_no_faq_type_has_no_source(): void
	{
		bridge_update_tokens(
			array(
				'postTypes' => array(
					array('singular' => 'Project', 'plural' => 'Projects', 'slug' => 'project'),
				),
			)
		);
		bridge_tokens_memo(null, true);

		$this->assertSame('', bridge_faq_post_type());
	}

	/**
	 * An operator's renaming survives, because only the slug identifies it.
	 *
	 * The block shows whatever the type is called; what it reads is fixed.
	 */
	public function test_a_renamed_faq_type_is_still_the_source(): void
	{
		bridge_update_tokens(
			array(
				'postTypes' => array(
					array('singular' => 'Question', 'plural' => 'Common Questions', 'slug' => 'faq'),
				),
			)
		);
		bridge_tokens_memo(null, true);

		$this->assertSame('faq', bridge_faq_post_type());
		$this->assertSame('Common Questions', bridge_faq_post_type_entry()['plural']);
	}

	/** The seeder and the block agree on the slug, because there is one of it. */
	public function test_the_seed_uses_the_canonical_faq_slug(): void
	{
		$this->assertContains(
			BRIDGE_FAQ_POST_TYPE,
			array_column(bridge_seeded_post_types(), 'slug')
		);
	}

	// ---- Categories ------------------------------------------------------

	/**
	 * Absent means no — the opposite reading to the two switches above.
	 *
	 * Deliberate, and the asymmetry is the point: those describe something an
	 * older record already had, where a taxonomy is something it never did.
	 * Reading a missing key as yes would put an empty Categories box on every
	 * content type on every existing site the first time anything saved.
	 */
	public function test_a_row_with_no_categories_field_has_none(): void
	{
		$rows = $this->sanitize(array(array('singular' => 'Project')));

		$this->assertFalse($rows[0]['hasCategories']);
	}

	/**
	 * @dataProvider provide_category_flags
	 */
	public function test_the_categories_flag_is_a_boolean($given, bool $expected): void
	{
		$rows = $this->sanitize(
			array(array('singular' => 'FAQ', 'hasCategories' => $given))
		);

		$this->assertSame($expected, $rows[0]['hasCategories']);
	}

	/**
	 * @return array<string, array{0:mixed,1:bool}>
	 */
	public static function provide_category_flags(): array
	{
		return array(
			'true'         => array(true, true),
			'false'        => array(false, false),
			'one'          => array(1, true),
			'zero'         => array(0, false),
			'empty string' => array('', false),
			'null'         => array(null, false),
		);
	}

	/**
	 * A taxonomy name fits the column WordPress stores it in.
	 *
	 * VARCHAR(32), against a post type slug that may be the full 20. The suffix
	 * is what keeps the longest possible pair inside it, so the arithmetic is
	 * asserted rather than trusted to stay true if the suffix is ever changed.
	 */
	public function test_a_category_taxonomy_name_fits_its_column(): void
	{
		$longest = str_repeat('a', 20);

		$this->assertLessThanOrEqual(
			32,
			strlen(bridge_post_type_taxonomy($longest))
		);
		$this->assertSame('faq_cat', bridge_post_type_taxonomy('faq'));
	}

	/** A pageless type's categories have no archives either. */
	public function test_a_pageless_types_categories_have_no_urls(): void
	{
		$args = bridge_post_type_taxonomy_args(
			array(
				'singular' => 'FAQ',
				'plural'   => 'FAQs',
				'slug'     => 'faq',
				'hasPages' => false,
			)
		);

		$this->assertFalse($args['public']);
		$this->assertFalse($args['rewrite']);
		$this->assertFalse($args['query_var']);

		// The two that are not derived from `public`: a pageless type still
		// needs the box on its editing screen and the panel in the editor.
		$this->assertTrue($args['show_ui']);
		$this->assertTrue($args['show_in_rest']);
	}

	/** A type with pages gets term archives under its own slug. */
	public function test_a_paged_types_categories_have_urls(): void
	{
		$args = bridge_post_type_taxonomy_args(
			array(
				'singular' => 'Project',
				'plural'   => 'Projects',
				'slug'     => 'project',
				'hasPages' => true,
			)
		);

		$this->assertTrue($args['public']);
		$this->assertSame('project-category', $args['rewrite']['slug']);
		$this->assertSame('project_cat', $args['query_var']);
	}

	/** Hierarchical, because that is what "category" means to whoever fills it in. */
	public function test_category_taxonomies_are_hierarchical(): void
	{
		$args = bridge_post_type_taxonomy_args(
			array('singular' => 'FAQ', 'plural' => 'FAQs', 'slug' => 'faq')
		);

		$this->assertTrue($args['hierarchical']);
		$this->assertSame('FAQ Categories', $args['labels']['name']);
		$this->assertSame('FAQ Category', $args['labels']['singular_name']);
	}

	// ---- What the editing screen asks for --------------------------------

	/**
	 * A pageless type is written as two fields.
	 *
	 * An excerpt and a featured image exist to be rendered — on the item's own
	 * page, in a card, in a search result — and a pageless type has none of
	 * those places. So an FAQ's screen asks for a question and an answer, and
	 * nothing an author would fill in for nobody.
	 */
	public function test_a_pageless_type_has_no_excerpt_or_featured_image(): void
	{
		$supports = bridge_post_type_args(
			array(
				'singular' => 'FAQ',
				'plural'   => 'FAQs',
				'slug'     => 'faq',
				'hasPages' => false,
			)
		)['supports'];

		$this->assertSame(
			array('title', 'editor', 'revisions', 'page-attributes'),
			$supports
		);
	}

	/** A type with pages keeps both — it has somewhere to draw them. */
	public function test_a_type_with_pages_keeps_its_excerpt_and_image(): void
	{
		$supports = bridge_post_type_args(
			array(
				'singular' => 'Team Member',
				'plural'   => 'Team',
				'slug'     => 'team',
				'hasPages' => true,
			)
		)['supports'];

		$this->assertContains('excerpt', $supports);
		$this->assertContains('thumbnail', $supports);
	}

	/** Never the meta panel, on either kind. */
	public function test_no_type_supports_custom_fields(): void
	{
		foreach (array(true, false) as $has_pages) {
			$this->assertNotContains(
				'custom-fields',
				bridge_post_type_args(
					array(
						'singular' => 'Thing',
						'plural'   => 'Things',
						'slug'     => 'thing',
						'hasPages' => $has_pages,
					)
				)['supports']
			);
		}
	}

	// ---- The two fields that are filled in for you -----------------------

	/**
	 * A missing plural is the singular.
	 *
	 * "Staff" and "Series" are the same word twice, and an operator who leaves
	 * the field alone means that rather than meaning nothing.
	 */
	public function test_a_missing_plural_falls_back_to_the_singular(): void
	{
		$rows = $this->sanitize(array(array('singular' => 'Staff')));

		$this->assertSame('Staff', $rows[0]['plural']);
	}

	/** A missing slug is derived from the singular, the same way the UI does. */
	public function test_a_missing_slug_is_derived_from_the_singular(): void
	{
		$rows = $this->sanitize(array(array('singular' => 'Case Study')));

		$this->assertSame('case_study', $rows[0]['slug']);
	}

	/**
	 * A row with no singular is not a content type.
	 *
	 * Dropped rather than saved as "Untitled": the alternative puts an unnamed
	 * item in the admin sidebar of a live site, which is harder to explain than
	 * a row that did not save.
	 */
	public function test_a_row_with_no_singular_is_dropped(): void
	{
		$this->assertSame(
			array(),
			$this->sanitize(array(array('plural' => 'Things', 'slug' => 'thing')))
		);
	}

	// ---- Slug normalisation ----------------------------------------------

	/**
	 * @dataProvider provide_slugs
	 */
	public function test_a_slug_is_normalised(string $given, string $expected): void
	{
		$rows = $this->sanitize(
			array(array('singular' => 'Thing', 'slug' => $given))
		);

		$this->assertSame($expected, $rows[0]['slug']);
	}

	/**
	 * @return array<string, array{0:string,1:string}>
	 */
	public static function provide_slugs(): array
	{
		return array(
			'upper case'      => array('Project', 'project'),
			'spaces'          => array('case study', 'case_study'),
			'dashes'          => array('case-study', 'case_study'),
			'punctuation'     => array('pro!ject', 'project'),
			'repeated joins'  => array('case   study', 'case_study'),
			'leading digits'  => array('2024projects', 'projects'),
			'trailing join'   => array('case_study_', 'case_study'),
			// The cut is what usually leaves a trailing separator, so the trim
			// has to happen after it rather than before.
			'cut on a join'   => array('abcdefghijklmnopqrs_tuv', 'abcdefghijklmnopqrs'),
		);
	}

	/**
	 * A post type name is stored in a VARCHAR(20).
	 *
	 * Truncated here rather than left to MySQL, which does it silently — and
	 * the truncated name is what every later lookup misses.
	 */
	public function test_a_slug_is_truncated_to_twenty_characters(): void
	{
		$rows = $this->sanitize(
			array(
				array(
					'singular' => 'Long',
					'slug'     => 'abcdefghijklmnopqrstuvwxyz',
				),
			)
		);

		$this->assertSame(20, strlen($rows[0]['slug']));
		$this->assertSame('abcdefghijklmnopqrst', $rows[0]['slug']);
	}

	/** Nothing survives normalisation, so there is nothing to register. */
	public function test_a_slug_of_only_punctuation_drops_the_row(): void
	{
		$this->assertSame(
			array(),
			$this->sanitize(array(array('singular' => '!!!', 'slug' => '!!!')))
		);
	}

	// ---- Rejection -------------------------------------------------------

	/**
	 * Every reserved slug is refused.
	 *
	 * The list is walked rather than sampled: it holds core's own post types,
	 * where a collision replaces a real one, and WordPress's reserved query
	 * variables, where a collision resolves the archive to something else
	 * entirely. Both are silent failures, and neither is a thing a client
	 * would connect to the name they typed.
	 */
	public function test_no_reserved_slug_is_ever_accepted(): void
	{
		foreach (bridge_reserved_post_type_slugs() as $slug) {
			$this->assertSame(
				array(),
				$this->sanitize(array(array('singular' => 'Thing', 'slug' => $slug))),
				"Reserved slug accepted: {$slug}"
			);
		}
	}

	/**
	 * The second row to claim a slug loses it.
	 *
	 * Dropped rather than suffixed into uniqueness: a `project_2` nobody typed
	 * is a URL nobody recognises six months later.
	 */
	public function test_a_duplicate_slug_drops_the_later_row(): void
	{
		$rows = $this->sanitize(
			array(
				array('singular' => 'Project', 'slug' => 'project'),
				array('singular' => 'Projekt', 'slug' => 'project'),
			)
		);

		$this->assertCount(1, $rows);
		$this->assertSame('Project', $rows[0]['singular']);
	}

	/** The cap is a cap, not a suggestion. */
	public function test_the_list_stops_at_the_limit(): void
	{
		$rows = array();

		for ($i = 0; $i < bridge_post_type_limit() + 5; $i++) {
			$rows[] = array('singular' => 'Type', 'slug' => 'type_' . $i);
		}

		$this->assertCount(bridge_post_type_limit(), $this->sanitize($rows));
	}

	// ---- Shape -----------------------------------------------------------

	/**
	 * Garbage of the wrong shape survives without a notice.
	 *
	 * The same contract the rest of the record keeps: a hand-edited option row
	 * or a restore from an older version of the theme can carry a string where
	 * a list belongs, and a fatal on every page load is not an acceptable
	 * reading of that.
	 *
	 * @dataProvider provide_malformed
	 */
	public function test_malformed_input_produces_an_empty_list($given): void
	{
		$this->assertSame(
			array(),
			bridge_sanitize_tokens(array('postTypes' => $given))['postTypes']
		);
	}

	/**
	 * @return array<string, array{0:mixed}>
	 */
	public static function provide_malformed(): array
	{
		return array(
			'a string'          => array('projects'),
			'a number'          => array(7),
			'null'              => array(null),
			'a list of strings' => array(array('projects', 'venues')),
			'a list of nulls'   => array(array(null, null)),
		);
	}

	/**
	 * The stored list is a list, not a map.
	 *
	 * Load-bearing: bridge_merge_deep() replaces a list outright and merges a
	 * map key by key, so a record that came back as a map would make removing
	 * the last row impossible — the old row would merge straight back in.
	 */
	public function test_the_result_is_a_list(): void
	{
		$rows = $this->sanitize(
			array(
				2 => array('singular' => 'A', 'slug' => 'a_type'),
				9 => array('singular' => 'B', 'slug' => 'b_type'),
			)
		);

		$this->assertTrue(array_is_list($rows));
		$this->assertCount(2, $rows);
	}

	/** Names are text, not markup. */
	public function test_names_are_stripped_of_markup(): void
	{
		$rows = $this->sanitize(
			array(
				array(
					'singular' => '<script>alert(1)</script>Project',
					'plural'   => '<b>Projects</b>',
					'slug'     => 'project',
				),
			)
		);

		$this->assertStringNotContainsString('<', $rows[0]['singular']);
		$this->assertStringNotContainsString('<', $rows[0]['plural']);
	}
}
