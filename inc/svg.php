<?php

/**
 * Bridge — SVG uploads.
 *
 * WordPress refuses SVG for a good reason: an SVG is a document, not a picture.
 * It can carry <script>, event handlers, external references and CSS imports,
 * and uploads are served from the site's own origin — so a hostile SVG opened
 * directly is same-origin script execution, i.e. stored XSS with a `.svg`
 * extension. Most "enable SVG" snippets just whitelist the MIME type and hand
 * that vector to whoever can reach the media library.
 *
 * This file takes the other approach: the file is *rewritten* before it lands
 * in uploads. It is parsed as XML, walked, and re-serialised from an allowlist
 * of elements and attributes — anything not named is dropped, rather than
 * anything known-bad being stripped. A file that will not parse, or whose root
 * element is not <svg>, is refused outright.
 *
 * Two further limits, both deliberate:
 *
 *   - Uploading is gated on the operator capability, not `upload_files`. A logo
 *     is a brand asset and this theme's design system is agency-owned; clients
 *     get PNGs. See inc/capabilities.php.
 *   - `.svgz` is not accepted. It is a gzipped SVG, which means the bytes on
 *     disk are not what the sanitiser inspected unless it also owns the
 *     compression round-trip. Not worth the surface for a logo.
 *
 * The second half of the file is about making an SVG behave like an image once
 * uploaded. SVGs have no raster metadata, so core computes 0×0 for them and
 * `wp_get_attachment_image()` emits `width="0" height="0"` — an invisible logo.
 * Dimensions are read from the root element's width/height or viewBox and
 * stored as attachment metadata, which is where every core size lookup reads
 * from.
 *
 * @package Bridge
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * May the current user upload an SVG?
 *
 * Falls back to `manage_options` when lockdown is off, so a site running
 * without an operator allowlist is not left unable to set its own logo.
 */
function bridge_can_upload_svg(): bool
{
	$allowed = bridge_lockdown_enabled()
		? bridge_current_user_is_operator()
		: current_user_can('manage_options');

	return (bool) apply_filters('bridge_can_upload_svg', $allowed);
}

/**
 * Largest SVG the sanitiser will parse, in bytes.
 *
 * Parsing builds a DOM in memory, so an unbounded file is an unbounded
 * allocation. Logos and icons are measured in kilobytes; anything approaching
 * this ceiling is a traced photograph or a map, and is not what this exists for.
 */
function bridge_svg_max_bytes(): int
{
	return (int) apply_filters('bridge_svg_max_bytes', 2 * MB_IN_BYTES);
}

/**
 * Elements the sanitiser keeps, lowercased.
 *
 * Shapes, text, gradients, clips, masks and the filter primitives a design tool
 * emits. Notable absences, all intentional:
 *
 *   script, handler        the obvious ones.
 *   foreignObject, switch  a hole straight back to arbitrary HTML.
 *   image                  embeds or links external raster data; a logo does
 *                          not need it and it is the usual smuggling route.
 *   animate, set, a        `animate` can retarget an attribute at runtime and
 *                          `a` reintroduces navigation into an image.
 *
 * @return string[]
 */
function bridge_svg_allowed_elements(): array
{
	return (array) apply_filters(
		'bridge_svg_allowed_elements',
		array(
			'svg',
			'g',
			'defs',
			'symbol',
			'use',
			'title',
			'desc',
			'style',
			'path',
			'rect',
			'circle',
			'ellipse',
			'line',
			'polyline',
			'polygon',
			'text',
			'tspan',
			'textpath',
			'lineargradient',
			'radialgradient',
			'stop',
			'pattern',
			'clippath',
			'mask',
			'marker',
			'filter',
			'feblend',
			'fecolormatrix',
			'fecomposite',
			'fedropshadow',
			'feflood',
			'fegaussianblur',
			'femerge',
			'femergenode',
			'feoffset',
		)
	);
}

/**
 * Attributes the sanitiser keeps, lowercased.
 *
 * A single flat list rather than a per-element map: SVG presentation
 * attributes are near-universal, and a map that pretended otherwise would be
 * both longer and wrong at the edges. Every value is still checked by
 * bridge_svg_attribute_is_safe(), which is where the actual defence lives —
 * `fill` is harmless, `fill="url(//evil.example/x)"` is not.
 *
 * @return string[]
 */
function bridge_svg_allowed_attributes(): array
{
	return (array) apply_filters(
		'bridge_svg_allowed_attributes',
		array(
			// Structure.
			'id',
			'class',
			'style',
			'xmlns',
			'xmlns:xlink',
			'xml:space',
			'version',
			'viewbox',
			'preserveaspectratio',
			'width',
			'height',
			'x',
			'y',
			'transform',
			'transform-origin',
			'href',
			'xlink:href',

			// Geometry.
			'd',
			'cx',
			'cy',
			'r',
			'rx',
			'ry',
			'x1',
			'y1',
			'x2',
			'y2',
			'points',
			'pathlength',

			// Paint.
			'fill',
			'fill-opacity',
			'fill-rule',
			'stroke',
			'stroke-width',
			'stroke-opacity',
			'stroke-linecap',
			'stroke-linejoin',
			'stroke-miterlimit',
			'stroke-dasharray',
			'stroke-dashoffset',
			'opacity',
			'color',
			'paint-order',
			'vector-effect',
			'shape-rendering',
			'mix-blend-mode',
			'isolation',

			// Text.
			'font-family',
			'font-size',
			'font-style',
			'font-weight',
			'font-variant',
			'font-stretch',
			'letter-spacing',
			'word-spacing',
			'text-anchor',
			'text-decoration',
			'dominant-baseline',
			'alignment-baseline',
			'dx',
			'dy',
			'rotate',
			'textlength',
			'lengthadjust',
			'startoffset',

			// Visibility and references.
			'display',
			'visibility',
			'overflow',
			'clip-path',
			'clip-rule',
			'mask',
			'filter',
			'marker-start',
			'marker-mid',
			'marker-end',

			// Gradients, patterns, clips, masks, markers.
			'offset',
			'stop-color',
			'stop-opacity',
			'gradientunits',
			'gradienttransform',
			'spreadmethod',
			'fx',
			'fy',
			'patternunits',
			'patterncontentunits',
			'patterntransform',
			'clippathunits',
			'maskunits',
			'maskcontentunits',
			'markerwidth',
			'markerheight',
			'markerunits',
			'refx',
			'refy',
			'orient',

			// Filter primitives.
			'filterunits',
			'primitiveunits',
			'result',
			'in',
			'in2',
			'mode',
			'type',
			'values',
			'stddeviation',
			'operator',
			'k1',
			'k2',
			'k3',
			'k4',
			'flood-color',
			'flood-opacity',
		)
	);
}

/**
 * Is this attribute value safe to keep?
 *
 * The rule that does most of the work is the one about `url()`: inside an SVG
 * a functional reference is legitimate only when it points at a fragment in the
 * same document — `fill="url(#brand-gradient)"`. Every other form reaches off
 * the document, and off-document is where tracking pixels, callbacks and
 * protocol tricks live. Same for href, which exists here purely so <use> can
 * reference a <symbol>.
 *
 * @param string $name  Lowercased attribute name, with prefix.
 * @param string $value Attribute value, already entity-decoded by the parser.
 */
function bridge_svg_attribute_is_safe(string $name, string $value): bool
{
	if (str_starts_with($name, 'on')) {
		return false;
	}

	// Control characters and whitespace are how `java\nscript:` gets past a
	// naive substring test; the browser ignores them, so the test must too.
	$probe = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', $value) ?? '');

	if ('' === $probe) {
		return true;
	}

	foreach (array('javascript:', 'vbscript:', 'data:text/html', 'expression(', '<script', '@import', 'behavior:', '-moz-binding') as $needle) {
		if (str_contains($probe, $needle)) {
			return false;
		}
	}

	if ('href' === $name || 'xlink:href' === $name) {
		return (bool) preg_match('/^#[a-z0-9_.:-]+$/', $probe);
	}

	// Every url() must be a same-document fragment.
	if (str_contains($probe, 'url(')) {
		return ! (bool) preg_match('/url\((?!#)/', $probe);
	}

	return true;
}

/**
 * Sanitise SVG markup, or reject it.
 *
 * @param string $markup Raw file contents.
 * @return string|null Clean markup, or null when the file is not a usable SVG.
 */
function bridge_sanitize_svg(string $markup): ?string
{
	if ('' === trim($markup) || strlen($markup) > bridge_svg_max_bytes()) {
		return null;
	}

	// A BOM or stray leading bytes make loadXML fail on files that are
	// otherwise perfectly ordinary exports.
	$markup = preg_replace('/^\xEF\xBB\xBF/', '', $markup) ?? $markup;
	$markup = ltrim($markup);

	$dom                     = new DOMDocument();
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput       = false;

	$previous = libxml_use_internal_errors(true);

	// LIBXML_NONET blocks network fetches. LIBXML_NOENT is deliberately *not*
	// set: substituting entities is what turns a ten-line file into a gigabyte
	// of memory (the "billion laughs" expansion).
	$loaded = $dom->loadXML($markup, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

	libxml_clear_errors();
	libxml_use_internal_errors($previous);

	if (! $loaded || ! $dom->documentElement instanceof DOMElement) {
		return null;
	}

	if ('svg' !== strtolower($dom->documentElement->localName)) {
		return null;
	}

	$namespace = $dom->documentElement->namespaceURI;

	if (null !== $namespace && 'http://www.w3.org/2000/svg' !== $namespace) {
		return null;
	}

	// A doctype with an internal subset can define entities the rest of the
	// document leans on, so it cannot simply be dropped — refuse the file
	// instead. A bare doctype (Illustrator still emits the SVG 1.1 one) is
	// inert and is removed.
	if ($dom->doctype instanceof DOMDocumentType) {
		if (! empty($dom->doctype->internalSubset)) {
			return null;
		}

		$dom->removeChild($dom->doctype);
	}

	bridge_svg_clean_node($dom->documentElement);

	// libxml keeps namespace declarations out of the attribute list, so a null
	// namespace here means the file genuinely never declared one — which
	// browsers reject when the .svg is loaded as a document rather than parsed
	// inline. Declaring it as a plain attribute is safe precisely because there
	// is no existing declaration to duplicate.
	if (null === $namespace) {
		$dom->documentElement->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
	}

	$clean = $dom->saveXML($dom->documentElement);

	return is_string($clean) && '' !== $clean ? $clean : null;
}

/**
 * Strip everything not on the allowlist, depth first.
 *
 * Child lists are snapshotted before iterating: DOMNodeList is live, and
 * removing a node while walking it silently skips its sibling.
 */
function bridge_svg_clean_node(DOMElement $element): void
{
	$elements   = bridge_svg_allowed_elements();
	$attributes = bridge_svg_allowed_attributes();

	foreach (iterator_to_array($element->attributes ?? array()) as $attribute) {
		$name = strtolower($attribute->nodeName);

		if (! in_array($name, $attributes, true) || ! bridge_svg_attribute_is_safe($name, (string) $attribute->nodeValue)) {
			$element->removeAttributeNode($attribute);
		}
	}

	foreach (iterator_to_array($element->childNodes) as $child) {
		if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction || $child instanceof DOMEntityReference) {
			$element->removeChild($child);
			continue;
		}

		if (! $child instanceof DOMElement) {
			continue;
		}

		$name = strtolower($child->localName);

		// Namespace check as well as name: `<html:script>` has a localName of
		// `script`, but so would a hypothetical allowlisted element smuggled in
		// under a foreign namespace.
		$namespace = $child->namespaceURI;
		$foreign   = null !== $namespace && 'http://www.w3.org/2000/svg' !== $namespace;

		if ($foreign || ! in_array($name, $elements, true)) {
			$element->removeChild($child);
			continue;
		}

		// A <style> block is CSS, so the attribute rules do not reach it; judge
		// the whole rule set at once and drop it entirely if anything in there
		// reaches off the document.
		if ('style' === $name) {
			if (! bridge_svg_attribute_is_safe('style', $child->textContent)) {
				$element->removeChild($child);
			}

			continue;
		}

		bridge_svg_clean_node($child);
	}
}

/**
 * Rewrite an uploaded SVG before WordPress moves it into place.
 *
 * `wp_handle_upload_prefilter` runs while the file is still in PHP's temp
 * directory and before the MIME check below, so the bytes that get type-checked
 * and stored are the sanitised ones — there is no window in which the original
 * exists under the uploads directory.
 *
 * @param array<string, mixed> $file Entry from $_FILES.
 * @return array<string, mixed>
 */
function bridge_sanitize_svg_upload(array $file): array
{
	if (! empty($file['error'])) {
		return $file;
	}

	$name = isset($file['name']) ? (string) $file['name'] : '';

	if ('svg' !== strtolower((string) pathinfo($name, PATHINFO_EXTENSION))) {
		return $file;
	}

	if (! bridge_can_upload_svg()) {
		$file['error'] = __('You are not allowed to upload SVG files.', 'bridge');

		return $file;
	}

	$path   = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
	$markup = ('' !== $path && is_readable($path)) ? (string) file_get_contents($path) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading PHP's own upload temp file; WP_Filesystem is not initialised this early and adds nothing here.
	$clean  = '' !== $markup ? bridge_sanitize_svg($markup) : null;

	if (null === $clean) {
		$file['error'] = __('This SVG could not be read, or contained markup that is not allowed. Re-export it as a plain SVG — without scripts, embedded images or external references — and try again.', 'bridge');

		return $file;
	}

	file_put_contents($path, $clean); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents -- Same temp file, same reason.

	// The size check downstream reads this rather than stat()ing the file.
	$file['size'] = strlen($clean);
	$file['type'] = 'image/svg+xml';

	return $file;
}
add_filter('wp_handle_upload_prefilter', 'bridge_sanitize_svg_upload');

/**
 * Allow the SVG MIME type, for operators only.
 *
 * @param array<string, string> $mimes Extension pattern => MIME type.
 * @return array<string, string>
 */
function bridge_svg_upload_mimes(array $mimes): array
{
	if (bridge_can_upload_svg()) {
		$mimes['svg'] = 'image/svg+xml';
	}

	return $mimes;
}
add_filter('upload_mimes', 'bridge_svg_upload_mimes');

/**
 * Agree that a .svg file is an SVG.
 *
 * WordPress cross-checks the extension against what fileinfo makes of the
 * bytes, and fileinfo reads SVG as `text/html`, `text/plain` or `image/svg+xml`
 * depending on the file and the libmagic build. That mismatch blanks `ext` and
 * `type`, and the upload is refused no matter what `upload_mimes` says.
 *
 * Overriding it is only defensible because the sanitiser has already run: by
 * this point the file is known to have parsed as XML with an <svg> root.
 *
 * @param array<string, mixed> $data     ext, type, proper_filename.
 * @param string               $file     Full path to the file.
 * @param string               $filename Name of the file.
 * @param string[]|null        $mimes    Allowed MIME types.
 * @return array<string, mixed>
 */
function bridge_svg_check_filetype($data, $file, $filename, $mimes = null)
{
	$data = (array) $data;

	if ('svg' !== strtolower((string) pathinfo((string) $filename, PATHINFO_EXTENSION))) {
		return $data;
	}

	if (! bridge_can_upload_svg()) {
		return $data;
	}

	$data['ext']             = 'svg';
	$data['type']            = 'image/svg+xml';
	$data['proper_filename'] = false;

	return $data;
}
add_filter('wp_check_filetype_and_ext', 'bridge_svg_check_filetype', 10, 4);

/**
 * Read an SVG's intrinsic size.
 *
 * Prefers explicit width/height, falls back to the viewBox, and combines the
 * two when only one dimension is a real length — a file with `width="120"` and
 * `viewBox="0 0 240 60"` is 120×30, and guessing 120×120 would squash the logo.
 * Percentage widths are treated as absent, because they describe a share of a
 * container that does not exist yet.
 *
 * @param string $path Absolute path to the file.
 * @return array{0: int, 1: int} Width and height; 0 when undeterminable.
 */
function bridge_svg_dimensions(string $path): array
{
	if (! is_readable($path) || filesize($path) > bridge_svg_max_bytes()) {
		return array(0, 0);
	}

	$markup = (string) file_get_contents($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local attachment file, read at upload time.

	if ('' === trim($markup)) {
		return array(0, 0);
	}

	$dom      = new DOMDocument();
	$previous = libxml_use_internal_errors(true);
	$loaded   = $dom->loadXML(ltrim($markup, "\xEF\xBB\xBF \t\n\r"), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

	libxml_clear_errors();
	libxml_use_internal_errors($previous);

	if (! $loaded || ! $dom->documentElement instanceof DOMElement) {
		return array(0, 0);
	}

	$root = $dom->documentElement;

	$length = static function (string $value): float {
		$value = trim($value);

		if ('' === $value || str_contains($value, '%')) {
			return 0.0;
		}

		return max(0.0, (float) $value);
	};

	$width  = $length($root->getAttribute('width'));
	$height = $length($root->getAttribute('height'));

	if ($width > 0 && $height > 0) {
		return array((int) round($width), (int) round($height));
	}

	$box = preg_split('/[\s,]+/', trim($root->getAttribute('viewBox')));

	if (is_array($box) && 4 === count($box)) {
		$box_width  = (float) $box[2];
		$box_height = (float) $box[3];

		if ($box_width > 0 && $box_height > 0) {
			if ($width > 0) {
				return array((int) round($width), (int) round($width * $box_height / $box_width));
			}

			if ($height > 0) {
				return array((int) round($height * $box_width / $box_height), (int) round($height));
			}

			return array((int) round($box_width), (int) round($box_height));
		}
	}

	return array((int) round($width), (int) round($height));
}

/**
 * Give SVG attachments the width and height core would otherwise compute as 0.
 *
 * Attachment metadata is the single place every size lookup reads from —
 * image_downsize(), the media modal, the block editor — so filling it in here
 * fixes all of them at once instead of patching each output path.
 *
 * @param array<string, mixed> $metadata      Generated metadata.
 * @param int                  $attachment_id Attachment post id.
 * @return array<string, mixed>
 */
function bridge_svg_attachment_metadata($metadata, $attachment_id): array
{
	$metadata = is_array($metadata) ? $metadata : array();

	if ('image/svg+xml' !== get_post_mime_type((int) $attachment_id)) {
		return $metadata;
	}

	$path = (string) get_attached_file((int) $attachment_id);

	if ('' === $path) {
		return $metadata;
	}

	list($width, $height) = bridge_svg_dimensions($path);

	$metadata['file']  = _wp_relative_upload_path($path);
	$metadata['sizes'] = isset($metadata['sizes']) ? (array) $metadata['sizes'] : array();

	if ($width > 0 && $height > 0) {
		$metadata['width']  = $width;
		$metadata['height'] = $height;
	}

	return $metadata;
}
add_filter('wp_generate_attachment_metadata', 'bridge_svg_attachment_metadata', 10, 2);

/**
 * Show SVGs in the media library and the logo picker.
 *
 * The modal renders a thumbnail from `sizes`, which an SVG has none of, so it
 * falls back to the generic document icon — including in the options page's
 * logo picker, where seeing the actual logo is the entire point. Every size
 * maps to the one file, since that is what vector means.
 *
 * @param array<string, mixed> $response   Attachment data for JS.
 * @param WP_Post              $attachment Attachment post.
 * @return array<string, mixed>
 */
function bridge_svg_attachment_for_js($response, $attachment): array
{
	$response = (array) $response;

	if ('image/svg+xml' !== get_post_mime_type($attachment)) {
		return $response;
	}

	$url                  = (string) wp_get_attachment_url($attachment->ID);
	list($width, $height) = bridge_svg_dimensions((string) get_attached_file($attachment->ID));

	// Something must be reported or the modal lays out a zero-height tile; the
	// square is a display box for an unsized vector, not a claim about the art.
	$width  = $width > 0 ? $width : 300;
	$height = $height > 0 ? $height : 300;

	$response['icon']   = $url;
	$response['width']  = $width;
	$response['height'] = $height;
	$response['sizes']  = array();

	foreach (array('full', 'large', 'medium', 'thumbnail') as $size) {
		$response['sizes'][$size] = array(
			'url'         => $url,
			'width'       => $width,
			'height'      => $height,
			'orientation' => $height > $width ? 'portrait' : 'landscape',
		);
	}

	return $response;
}
add_filter('wp_prepare_attachment_for_js', 'bridge_svg_attachment_for_js', 10, 2);

/**
 * Drop zero dimensions from a rendered SVG <img>.
 *
 * A safety net for attachments uploaded before this file existed, or whose art
 * carries neither a size nor a viewBox: `width="0"` renders nothing at all,
 * whereas no attribute at all lets CSS size the logo. Attributes are only
 * removed when they are useless.
 *
 * @param array<string, string> $attr       Image attributes.
 * @param WP_Post               $attachment Attachment post.
 * @return array<string, string>
 */
function bridge_svg_image_attributes($attr, $attachment): array
{
	$attr = (array) $attr;

	if ('image/svg+xml' !== get_post_mime_type($attachment)) {
		return $attr;
	}

	if (empty($attr['width']) || empty($attr['height'])) {
		unset($attr['width'], $attr['height']);
	}

	return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'bridge_svg_image_attributes', 10, 2);

/**
 * Keep SVG thumbnails inside their tile in the admin.
 *
 * The list table and grid size thumbnails by the intrinsic dimensions of a
 * raster file. An SVG scales to whatever it is given, so without a constraint a
 * wide wordmark overflows its cell.
 */
function bridge_svg_admin_styles(): void
{
	echo '<style>
		.media-icon img[src$=".svg" i],
		.attachment-preview .thumbnail img[src$=".svg" i],
		.media-frame .attachment-preview img[src$=".svg" i] {
			width: 100%;
			height: 100%;
			object-fit: contain;
		}
	</style>' . "\n";
}
add_action('admin_head', 'bridge_svg_admin_styles');

/* ---------------------------------------------------------------------------
 * Inlining
 *
 * An <img src="logo.svg"> is a picture: the browser renders the file in its own
 * isolated context, and nothing outside it can reach in. That is the right
 * default for an illustration and the wrong one for a mark the page needs to
 * restyle — a logo that should take the footer's ink on a dark ground, or a
 * drawn stroke that should be the brand's accent colour rather than whatever
 * hex was in the artboard. `currentColor` inside an <img> resolves against the
 * SVG document, not the page.
 *
 * So the three brand marks — the header logo, the footer logo and the
 * strapline's swoosh — are written into the page instead. Their shapes become
 * ordinary elements, `fill="currentColor"` follows the text colour around them,
 * and a stylesheet can address them like anything else.
 *
 * What it costs, and why it is worth it here but not everywhere:
 *
 *   - The bytes are in the HTML rather than in a cacheable file, so they are
 *     re-sent on every page. Fine for a wordmark; not fine for a gallery.
 *   - The file is read from disk and parsed at render time. Cached, below.
 *   - Two copies of one file collide on any `id` inside it, which is what
 *     bridge_inline_svg() rewrites per instance.
 * ------------------------------------------------------------------------ */

/**
 * How long a sanitised file stays cached.
 *
 * The cache key carries the file's modification time, so this is not how long
 * a stale logo can be served — replacing the file changes the key and the old
 * entry is simply never read again. It is only how long an unused entry
 * lingers.
 */
define('BRIDGE_INLINE_SVG_TTL', WEEK_IN_SECONDS);

/**
 * An attachment's SVG markup, sanitised and ready to write into the page.
 *
 * Sanitised again here rather than trusted because it was sanitised on the way
 * in. Upload is not the only way a file reaches uploads/ — a migration, a
 * restore, an FTP client or a plugin with its own media handling all put files
 * on disk without passing through bridge_sanitize_svg_upload(), and a file
 * placed before this theme was installed never saw it at all. Since the markup
 * is about to be written into the document unescaped, the check belongs at the
 * point of output.
 *
 * @param int $attachment_id Attachment post id.
 * @return string Clean `<svg>…</svg>`, or an empty string.
 */
function bridge_svg_markup(int $attachment_id): string
{
	if ($attachment_id <= 0 || 'image/svg+xml' !== get_post_mime_type($attachment_id)) {
		return '';
	}

	$path = get_attached_file($attachment_id);

	if (! is_string($path) || '' === $path || ! is_readable($path)) {
		return '';
	}

	/*
	 * The key identifies the *file*, not the attachment.
	 *
	 * Path, modification time and size together, hashed to keep the key inside
	 * the 172 characters an option name allows. The attachment id alone would
	 * be wrong twice over: a logo replaced in place keeps its id, and an id
	 * plus an mtime still collides when the replacement lands in the same
	 * second the original did — which a scripted deploy or a media-replace
	 * plugin manages easily. Size is what separates those, and reading all
	 * three is three stat calls against a file that has just been opened
	 * anyway.
	 */
	$key = 'bridge_svg_' . md5($path . '|' . filemtime($path) . '|' . filesize($path));

	$cached = get_transient($key);

	// `false` is "no entry"; an empty string is a real answer — a file that has
	// already been read and found unusable — and is honoured rather than
	// re-parsed on every request.
	if (is_string($cached)) {
		return $cached;
	}

	$markup = bridge_sanitize_svg(
		(string) file_get_contents($path) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local attachment file.
	);

	$markup = is_string($markup) ? $markup : '';

	set_transient($key, $markup, BRIDGE_INLINE_SVG_TTL);

	return $markup;
}

/**
 * Write an SVG attachment into the page as markup.
 *
 * Returns an empty string for anything that is not a usable SVG — a raster
 * attachment, a deleted one, a file that will not parse. Every caller treats
 * that as "fall back to an <img>", so a PNG logo keeps working and nothing has
 * to ask what kind of file it was given first.
 *
 * ---- What is changed on the way out ---------------------------------------
 *
 * `width` and `height` are removed when the file has a `viewBox`, so the
 * element takes its size from CSS instead of from whatever the artboard was.
 * Without a viewBox they are kept and one is synthesised from them — an SVG
 * with neither cannot be scaled at all, and stripping its only dimensions
 * would render it at the browser's 300×150 default.
 *
 * Every `id` is rewritten with a per-instance prefix, and so is every
 * reference to one: `url(#id)` in an attribute, `href="#id"`, and any mention
 * inside a `<style>` block. This is not tidiness. The header and the footer
 * routinely draw the *same* logo, and two elements with one id is invalid
 * HTML where every `url(#gradient)` in the document resolves to the first —
 * so the second copy loses its gradient, its clip path or its mask, and looks
 * broken in a way that inspecting the CSS never explains.
 *
 * ---- What is not changed --------------------------------------------------
 *
 * Class names inside a `<style>` block are left alone. Illustrator's default
 * export names them `.st0`, `.st1`, … per file, so two *different* inlined
 * files can each define `.st0` and the second wins for both. Rewriting them
 * means parsing arbitrary CSS, which is a different job with worse failure
 * modes than the one it fixes. Exporting with "Style Elements: Presentation
 * Attributes" avoids it entirely and is what these marks should use anyway,
 * because presentation attributes are what a page can restyle.
 *
 * @param int   $attachment_id Attachment post id.
 * @param array $args          class: string of classes for the root element.
 *                             label: accessible name; omit or leave empty for
 *                             a decorative mark, which is hidden from
 *                             assistive technology.
 * @return string Inline SVG markup, or an empty string.
 */
function bridge_inline_svg(int $attachment_id, array $args = array()): string
{
	$markup = bridge_svg_markup($attachment_id);

	if ('' === $markup) {
		return '';
	}

	$dom      = new DOMDocument();
	$previous = libxml_use_internal_errors(true);
	$loaded   = $dom->loadXML($markup, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

	libxml_clear_errors();
	libxml_use_internal_errors($previous);

	if (! $loaded || ! $dom->documentElement instanceof DOMElement) {
		return '';
	}

	$root = $dom->documentElement;

	// One counter per request, so the prefix is short, readable in a DOM
	// inspector, and the same on every render of a given page — which a
	// uniqid() or a random token would not be, and a cached page would then
	// differ from itself for no reason.
	static $instance = 0;

	++$instance;

	bridge_svg_namespace_ids($dom, $root, 'bsvg' . $instance);

	// The `xmlns` declaration stays. It is redundant once the markup is part of
	// an HTML document — the parser already knows that anything inside <svg>
	// is SVG — but it is also inert there, and it is not an attribute libxml
	// will part with: a namespace declaration is not in the attribute list, so
	// removeAttribute() reports success and changes nothing. Not worth
	// serialising the document by hand to save thirty bytes.
	if ($root->hasAttribute('viewBox')) {
		$root->removeAttribute('width');
		$root->removeAttribute('height');
	} elseif ($root->hasAttribute('width') && $root->hasAttribute('height')) {
		// Only the numbers, because a viewBox has no units: "120px" is a width
		// and a syntax error in the same string.
		$width  = (float) $root->getAttribute('width');
		$height = (float) $root->getAttribute('height');

		if ($width > 0 && $height > 0) {
			$root->setAttribute('viewBox', sprintf('0 0 %s %s', $width, $height));
			$root->removeAttribute('width');
			$root->removeAttribute('height');
		}
	}

	$class = isset($args['class']) ? trim((string) $args['class']) : '';

	if ('' !== $class) {
		// Appended rather than assigned: a class on the root element in the
		// file may be what its own <style> block is selecting on, and throwing
		// it away would unpaint the artwork.
		$existing = trim($root->getAttribute('class'));

		$root->setAttribute('class', '' !== $existing ? $existing . ' ' . $class : $class);
	}

	$label = isset($args['label']) ? trim((string) $args['label']) : '';

	if ('' !== $label) {
		$root->setAttribute('role', 'img');
		$root->setAttribute('aria-label', $label);
	} else {
		// Decorative. `focusable="false"` as well as `aria-hidden`, because
		// without it an inline <svg> is a tab stop in some browsers — and a
		// hidden tab stop is a focus that lands on nothing.
		$root->setAttribute('aria-hidden', 'true');
		$root->setAttribute('focusable', 'false');
	}

	$out = $dom->saveXML($root);

	return is_string($out) ? $out : '';
}

/**
 * Prefix every id in one document, and every reference to one.
 *
 * Attribute values are rewritten in three shapes, which is all the sanitiser
 * lets through: a bare `#id` in `href`/`xlink:href`, a `url(#id)` anywhere,
 * and the id attribute itself. `<style>` text is rewritten for `#id` selectors
 * as well.
 *
 * Renaming is done from a map built in a first pass rather than as each id is
 * met, because a reference can appear before the element it points at — a
 * `fill="url(#a)"` above the `<defs>` that defines `a` is ordinary output.
 *
 * @param DOMDocument $dom    The document.
 * @param DOMElement  $root   Its root element.
 * @param string      $prefix Instance prefix, already safe for an id.
 */
function bridge_svg_namespace_ids(DOMDocument $dom, DOMElement $root, string $prefix): void
{
	$xpath = new DOMXPath($dom);
	$map   = array();

	foreach ($xpath->query('//*[@id]', $root) ?: array() as $node) {
		if (! $node instanceof DOMElement) {
			continue;
		}

		$id = $node->getAttribute('id');

		if ('' !== $id) {
			$map[$id] = $prefix . '-' . $id;
		}
	}

	if (! $map) {
		return;
	}

	foreach ($xpath->query('//*[@id]', $root) ?: array() as $node) {
		if ($node instanceof DOMElement) {
			$node->setAttribute('id', $map[$node->getAttribute('id')] ?? $node->getAttribute('id'));
		}
	}

	/*
	 * One pass over every `#…` in the string, rather than one pass per id.
	 *
	 * A plain str_replace of `#a` is wrong in a way that only shows up on real
	 * artwork: it also rewrites `#abc`, because `#a` is a prefix of it. A file
	 * with ids `a` and `abc` — which is exactly what a short export produces —
	 * would come out with `#prefix-abc` pointing at nothing.
	 *
	 * So the reference is matched whole, up to the first character that cannot
	 * be part of an id, and looked up. Anything not in the map is left exactly
	 * as it was, which is what keeps a CSS colour like `#fff` from being
	 * treated as a reference to an element nobody named `fff`.
	 */
	$rewrite = static function (string $value) use ($map): string {
		return (string) preg_replace_callback(
			'/#([A-Za-z_][\w.:-]*)/',
			static fn(array $m): string => isset($map[$m[1]]) ? '#' . $map[$m[1]] : $m[0],
			$value
		);
	};

	foreach ($xpath->query('//@*', $root) ?: array() as $attribute) {
		if (! $attribute instanceof DOMAttr || 'id' === $attribute->name) {
			continue;
		}

		if (! str_contains($attribute->value, '#')) {
			continue;
		}

		$owner = $attribute->ownerElement;

		if (! $owner instanceof DOMElement) {
			continue;
		}

		// Through setAttribute() rather than by assigning to `$attribute->value`.
		// The property setter does not escape what it is given — an `&` in the
		// value produces malformed XML and a warning — where setAttribute()
		// escapes properly. `nodeValue` on the attribute has the same problem.
		$owner->setAttribute($attribute->name, $rewrite($attribute->value));
	}

	foreach ($xpath->query('//*[local-name()="style"]', $root) ?: array() as $style) {
		if (! str_contains($style->textContent, '#')) {
			continue;
		}

		$style->textContent = $rewrite($style->textContent);
	}
}
