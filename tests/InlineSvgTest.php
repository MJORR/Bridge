<?php

/**
 * Bridge — writing an SVG into the page instead of linking to it.
 *
 * The three brand marks — both logos and the strapline's swoosh — are inlined
 * so a stylesheet can reach inside them. That buys restyling and costs the
 * isolation an <img> gave, and everything pinned here is about the second half
 * of that trade: the same file drawn twice must not collide with itself, a
 * reference must survive being renamed, and nothing that is not an id must be
 * renamed at all.
 *
 * @package Bridge
 */

declare(strict_types=1);

final class InlineSvgTest extends BridgeTestCase
{
	/** Fixtures written per test, cleaned up after. */
	private array $files = array();

	protected function tearDown(): void
	{
		foreach ($this->files as $path) {
			if (file_exists($path)) {
				unlink($path);
			}
		}

		$this->files                       = array();
		$GLOBALS['bridge_test_svg']        = null;
		$GLOBALS['bridge_test_transients'] = array();

		parent::tearDown();
	}

	/** Point the attachment doubles at a file holding this markup. */
	private function fixture(string $markup): void
	{
		$path = tempnam(sys_get_temp_dir(), 'bridge-svg') . '.svg';

		file_put_contents($path, $markup);

		$this->files[]              = $path;
		$GLOBALS['bridge_test_svg'] = $path;
	}

	/** A file exercising every shape of internal reference. */
	private function referencingLogo(): string
	{
		return '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="40" viewBox="0 0 120 40">'
			. '<defs>'
			. '<linearGradient id="a"><stop offset="0" stop-color="#f60"/></linearGradient>'
			. '<clipPath id="abc"><rect width="60" height="40"/></clipPath>'
			. '</defs>'
			. '<style>.st0{fill:#000}#a{stop-opacity:1}</style>'
			. '<rect class="st0" fill="url(#a)" clip-path="url(#abc)" width="120" height="40"/>'
			. '<use href="#abc"/>'
			. '</svg>';
	}

	/**
	 * Two renders of one file do not share an id.
	 *
	 * The case this exists for: the header and the footer usually draw the
	 * same logo. Duplicate ids are invalid HTML, and every `url(#gradient)` in
	 * the document resolves to the first one — so the second copy silently
	 * loses its gradient, clip path or mask.
	 */
	public function test_two_instances_of_one_file_get_different_ids(): void
	{
		$this->fixture($this->referencingLogo());

		$first  = bridge_inline_svg(7);
		$second = bridge_inline_svg(7);

		preg_match_all('/id="([^"]+)"/', $first, $a);
		preg_match_all('/id="([^"]+)"/', $second, $b);

		$this->assertNotEmpty($a[1]);
		$this->assertSame(array(), array_intersect($a[1], $b[1]));
	}

	/**
	 * A renamed id takes its references with it, in all three shapes the
	 * sanitiser permits.
	 */
	public function test_every_reference_follows_the_id_it_points_at(): void
	{
		$this->fixture($this->referencingLogo());

		$svg = bridge_inline_svg(7);

		preg_match('/<linearGradient id="([^"]+)"/', $svg, $gradient);
		preg_match('/<clipPath id="([^"]+)"/', $svg, $clip);

		$this->assertNotEmpty($gradient[1]);
		$this->assertNotEmpty($clip[1]);

		// url(#…) in a paint attribute, and in a clip-path.
		$this->assertStringContainsString('fill="url(#' . $gradient[1] . ')"', $svg);
		$this->assertStringContainsString('clip-path="url(#' . $clip[1] . ')"', $svg);
		// A bare fragment in href.
		$this->assertStringContainsString('href="#' . $clip[1] . '"', $svg);
		// And the same id named inside a <style> block.
		$this->assertStringContainsString('#' . $gradient[1] . '{stop-opacity:1}', $svg);

		// Nothing may still point at the original names.
		$this->assertStringNotContainsString('url(#a)', $svg);
		$this->assertStringNotContainsString('href="#abc"', $svg);
	}

	/**
	 * An id that is a prefix of another id is not confused with it.
	 *
	 * `#a` is a substring of `#abc`, so a plain search-and-replace rewrites
	 * half of the longer reference and leaves it pointing at nothing. Short
	 * ids like these are exactly what a minified export produces.
	 */
	public function test_an_id_that_prefixes_another_is_not_mangled(): void
	{
		$this->fixture($this->referencingLogo());

		$svg = bridge_inline_svg(7);

		preg_match('/<clipPath id="([^"]+)"/', $svg, $clip);

		// The clip path's name must still end in the whole original id.
		$this->assertStringEndsWith('-abc', $clip[1]);
	}

	/** A colour in a <style> block is not an id reference. */
	public function test_a_hex_colour_is_left_alone(): void
	{
		$this->fixture($this->referencingLogo());

		$svg = bridge_inline_svg(7);

		$this->assertStringContainsString('fill:#000', $svg);
		$this->assertStringContainsString('stop-color="#f60"', $svg);
	}

	/**
	 * The artboard's dimensions are dropped so CSS can size the mark, and the
	 * viewBox that makes that possible is kept.
	 */
	public function test_the_artboard_size_gives_way_to_css(): void
	{
		$this->fixture($this->referencingLogo());

		$svg = bridge_inline_svg(7);

		$this->assertStringContainsString('viewBox="0 0 120 40"', $svg);
		$this->assertDoesNotMatchRegularExpression('/<svg[^>]*\swidth=/', $svg);
		$this->assertDoesNotMatchRegularExpression('/<svg[^>]*\sheight=/', $svg);
	}

	/**
	 * A file with no viewBox keeps a scalable box rather than losing its only
	 * dimensions — stripped of both, it would draw at the browser's 300×150
	 * default.
	 */
	public function test_a_file_without_a_viewbox_is_given_one(): void
	{
		$this->fixture(
			'<svg xmlns="http://www.w3.org/2000/svg" width="80" height="20"><path d="M0 0h10v10H0z"/></svg>'
		);

		$svg = bridge_inline_svg(7);

		$this->assertStringContainsString('viewBox="0 0 80 20"', $svg);
	}

	/** Decorative by default: named only when a caller asks for a name. */
	public function test_a_mark_is_decorative_unless_it_is_labelled(): void
	{
		$this->fixture($this->referencingLogo());

		$plain = bridge_inline_svg(7);

		$this->assertStringContainsString('aria-hidden="true"', $plain);
		$this->assertStringContainsString('focusable="false"', $plain);
		$this->assertStringNotContainsString('role="img"', $plain);

		$named = bridge_inline_svg(7, array('label' => 'magnusorr.com'));

		$this->assertStringContainsString('role="img"', $named);
		$this->assertStringContainsString('aria-label="magnusorr.com"', $named);
		$this->assertStringNotContainsString('aria-hidden', $named);
	}

	/**
	 * A caller's class joins whatever the file already carried rather than
	 * replacing it — a class on the root may be what the file's own <style>
	 * block selects on.
	 */
	public function test_a_class_is_added_without_discarding_the_file_s_own(): void
	{
		$this->fixture(
			'<svg xmlns="http://www.w3.org/2000/svg" class="brand" viewBox="0 0 10 10"><path d="M0 0h10v10H0z"/></svg>'
		);

		$svg = bridge_inline_svg(7, array('class' => 'bridge-footer__logo-img'));

		$this->assertMatchesRegularExpression('/class="brand bridge-footer__logo-img"/', $svg);
	}

	/**
	 * Anything that is not a usable SVG comes back empty, which is every
	 * caller's signal to fall back to an <img>.
	 */
	public function test_an_unusable_file_returns_nothing(): void
	{
		$this->fixture('<html><body>not a drawing</body></html>');

		$this->assertSame('', bridge_inline_svg(7));
		$this->assertSame('', bridge_inline_svg(0));
	}

	/**
	 * The sanitiser runs at output, not only at upload.
	 *
	 * A file can reach uploads/ without passing through the upload filter — a
	 * migration, a restore, an FTP client — and the markup here is about to be
	 * written into the document unescaped.
	 */
	public function test_a_hostile_file_on_disk_is_still_stripped(): void
	{
		$this->fixture(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'
				. '<script>alert(1)</script>'
				. '<path d="M0 0h10v10H0z" onload="alert(2)"/>'
				. '</svg>'
		);

		$svg = bridge_inline_svg(7);

		$this->assertStringNotContainsString('<script', $svg);
		$this->assertStringNotContainsString('onload', $svg);
		$this->assertStringContainsString('<path', $svg);
	}
}
