<?php

/**
 * inc/activation.php — the content types a fresh site starts with.
 *
 * Only the token arithmetic. The menu seeding beside it inserts posts, which
 * is WordPress's behaviour rather than the theme's, and a test against a double
 * of wp_insert_post() would be a test of the double.
 *
 * What is worth pinning here is that activation is *additive*. A theme
 * re-activated on a live site must not undo the operator's naming, their
 * ordering, or their choice to turn a type's pages off — and every one of
 * those is a plausible way for a seeder to go wrong.
 *
 * @package Bridge
 */

declare(strict_types=1);

final class ActivationTest extends BridgeTestCase
{
	/**
	 * One activation.
	 *
	 * The memo is cleared first because a real activation is its own request,
	 * where nothing has resolved the tokens yet. Without this, a second call in
	 * one test would read the set the first call replaced.
	 *
	 * @return array<int, array<string, mixed>> The declared types afterwards.
	 */
	private function activate(): array
	{
		bridge_tokens_memo(null, true);
		bridge_seed_post_types();
		bridge_tokens_memo(null, true);

		return bridge_declared_post_types();
	}

	// ---- What a fresh site gets ------------------------------------------

	public function test_activation_seeds_team_and_faqs(): void
	{
		$this->assertSame(
			array('team', 'faq'),
			array_column($this->activate(), 'slug')
		);
	}

	/**
	 * The two differ on the one switch that matters.
	 *
	 * Team cards are links stretched over a permalink, so a pageless team type
	 * is a grid of cards that all 404. An FAQ has nothing to link to.
	 */
	public function test_team_has_pages_and_faqs_do_not(): void
	{
		$seeded = array_column($this->activate(), 'hasPages', 'slug');

		$this->assertTrue($seeded['team']);
		$this->assertFalse($seeded['faq']);
	}

	/** Both arrive switched on. A site that wants neither turns them off. */
	public function test_both_seeds_arrive_switched_on(): void
	{
		$seeded = array_column($this->activate(), 'enabled', 'slug');

		$this->assertTrue($seeded['team']);
		$this->assertTrue($seeded['faq']);
	}

	/**
	 * FAQs divide into categories, a team does not.
	 *
	 * The FAQs block can be pointed at one category per page, which is the
	 * whole use for the taxonomy. A team is a list of people, and a site that
	 * wants it split by department says so in Theme Options.
	 */
	public function test_faqs_get_categories_and_team_does_not(): void
	{
		$seeded = array_column($this->activate(), 'hasCategories', 'slug');

		$this->assertTrue($seeded['faq']);
		$this->assertFalse($seeded['team']);
	}

	// ---- Re-activation ---------------------------------------------------

	/** Activating twice does not produce two of everything. */
	public function test_activation_is_idempotent(): void
	{
		$this->activate();

		$this->assertSame(
			array('team', 'faq'),
			array_column($this->activate(), 'slug')
		);
	}

	/**
	 * A renamed type keeps its name.
	 *
	 * The slug identifies a type; the labels belong to whoever wrote them. A
	 * seeder that matched on label — or that assigned over the record — would
	 * rename "Our People" back to "Team" on the next theme update.
	 */
	public function test_activation_leaves_a_renamed_type_alone(): void
	{
		bridge_update_tokens(
			array(
				'postTypes' => array(
					array('singular' => 'Person', 'plural' => 'Our People', 'slug' => 'team'),
				),
			)
		);

		$types = array_column($this->activate(), 'plural', 'slug');

		$this->assertSame('Our People', $types['team']);
	}

	/**
	 * An operator who turned a seeded type's pages off keeps them off.
	 *
	 * The switch is the one setting here somebody is most likely to have
	 * changed deliberately, and re-activating a theme is not a request to put
	 * a set of URLs back.
	 */
	public function test_activation_does_not_restore_pages_an_operator_turned_off(): void
	{
		bridge_update_tokens(
			array(
				'postTypes' => array(
					array(
						'singular' => 'Team Member',
						'plural'   => 'Team',
						'slug'     => 'team',
						'hasPages' => false,
					),
				),
			)
		);

		$types = array_column($this->activate(), 'hasPages', 'slug');

		$this->assertFalse($types['team']);
	}

	/** Types the operator declared keep their place at the front. */
	public function test_seeds_are_appended_after_what_is_already_declared(): void
	{
		bridge_update_tokens(
			array(
				'postTypes' => array(
					array('singular' => 'Project', 'plural' => 'Projects', 'slug' => 'project'),
				),
			)
		);

		$this->assertSame(
			array('project', 'team', 'faq'),
			array_column($this->activate(), 'slug')
		);
	}

	/**
	 * At the cap, the seeds are what gets dropped.
	 *
	 * The right way round: a seed is a suggestion, and the types somebody
	 * chose are not. The sanitiser keeps the first N and these are last, so
	 * this is asserting that the append order is load-bearing rather than
	 * incidental.
	 */
	public function test_a_full_record_keeps_its_own_types_over_the_seeds(): void
	{
		$declared = array();

		for ($i = 0; $i < bridge_post_type_limit(); $i++) {
			$declared[] = array(
				'singular' => 'Type',
				'plural'   => 'Types',
				'slug'     => 'type_' . $i,
			);
		}

		bridge_update_tokens(array('postTypes' => $declared));

		$slugs = array_column($this->activate(), 'slug');

		$this->assertCount(bridge_post_type_limit(), $slugs);
		$this->assertNotContains('team', $slugs);
		$this->assertNotContains('faq', $slugs);
	}

	// ---- Filling in a switch the theme has since added --------------------

	/**
	 * Store a record the way an older version of the theme wrote it.
	 *
	 * Straight into the option rather than through `bridge_update_tokens()`,
	 * because the sanitiser fills every field in — which is the exact thing
	 * these tests need to be missing. This is what a site upgraded from a
	 * build before the field existed actually holds.
	 *
	 * @param array<int, array<string, mixed>> $rows Post type rows, as stored.
	 */
	private function storeRows(array $rows): void
	{
		$GLOBALS['bridge_test_options'][BRIDGE_TOKENS_OPTION] = array(
			'postTypes' => $rows,
		);
	}

	/**
	 * A site upgraded from before the switch existed gets what a fresh one gets.
	 *
	 * Nobody on that site has decided against FAQ categories, because there was
	 * nothing to decide. Leaving the field absent would ship the taxonomy to
	 * new sites only, with nothing on the screen to explain the difference.
	 */
	public function test_activation_fills_in_a_field_the_record_never_held(): void
	{
		$this->storeRows(
			array(
				array(
					'singular' => 'FAQ',
					'plural'   => 'FAQs',
					'slug'     => 'faq',
					'hasPages' => false,
				),
			)
		);

		$types = array_column($this->activate(), 'hasCategories', 'slug');

		$this->assertTrue($types['faq']);
	}

	/** An operator who switched categories off keeps them off. */
	public function test_activation_does_not_undo_a_field_the_record_answers(): void
	{
		$this->storeRows(
			array(
				array(
					'singular'      => 'FAQ',
					'plural'        => 'FAQs',
					'slug'          => 'faq',
					'hasPages'      => false,
					'hasCategories' => false,
				),
			)
		);

		$types = array_column($this->activate(), 'hasCategories', 'slug');

		$this->assertFalse($types['faq']);
	}

	/**
	 * The backfill never touches a field whose absence already means something.
	 *
	 * `hasPages` is the one. A record written before that switch existed
	 * described a public type, and the sanitiser says so — filling it in from
	 * the seed would take the archives off a live site's FAQ type on the next
	 * theme update. So the backfill works from a named list rather than from
	 * "every key the row is missing".
	 */
	public function test_activation_does_not_backfill_pages_from_the_seed(): void
	{
		$this->storeRows(
			array(
				array('singular' => 'FAQ', 'plural' => 'FAQs', 'slug' => 'faq'),
			)
		);

		$types = array_column($this->activate(), 'hasPages', 'slug');

		$this->assertTrue($types['faq']);
	}

	/** Nor a name. The labels belong to whoever wrote them. */
	public function test_the_backfill_list_holds_no_label_fields(): void
	{
		$this->assertSame(
			array(),
			array_intersect(
				bridge_seed_backfill_fields(),
				array('singular', 'plural', 'slug')
			)
		);
	}

	/**
	 * Every field on the backfill list is a field the seeds actually carry.
	 *
	 * Cheap, and the failure it catches is silent: a name left on the list
	 * after the seed stopped setting it would simply do nothing, for as long as
	 * nobody looked.
	 */
	public function test_every_backfill_field_is_on_every_seed(): void
	{
		foreach (bridge_seeded_post_types() as $seed) {
			foreach (bridge_seed_backfill_fields() as $field) {
				$this->assertArrayHasKey($field, $seed, $seed['slug']);
			}
		}
	}

	// ---- The seed list itself --------------------------------------------

	/**
	 * Every seed survives the sanitiser unchanged.
	 *
	 * A seed with a slug the sanitiser rejects — reserved, too long, badly
	 * formed — would vanish on activation with nothing to say why. Cheap to
	 * assert, and the failure it catches is silent.
	 */
	public function test_every_seed_is_a_valid_declaration(): void
	{
		$sanitised = bridge_sanitize_tokens(
			array('postTypes' => bridge_seeded_post_types())
		)['postTypes'];

		$this->assertSame(
			array_column(bridge_seeded_post_types(), 'slug'),
			array_column($sanitised, 'slug')
		);
	}

	/** Three menus, each named once. */
	public function test_the_seeded_menu_names_are_distinct(): void
	{
		$menus = bridge_seeded_menus();

		$this->assertCount(3, $menus);
		$this->assertSame($menus, array_values(array_unique($menus)));

		foreach ($menus as $name) {
			$this->assertNotSame('', trim($name));
		}
	}
}
