import { defineConfig } from 'vite';
import { resolve, basename } from 'node:path';
import { readdirSync, readFileSync, writeFileSync } from 'node:fs';

/**
 * Compile src/icons/*.svg into a single dist/icons.json library.
 *
 * PHP renders each icon inline from this file rather than referencing an
 * external sprite through <use>: external references have long-standing
 * cross-browser caching quirks, and inlining sidesteps the question of when
 * the sprite has to be printed relative to the blocks that need it. The
 * per-icon cost is a hundred bytes of path data that gzip collapses to almost
 * nothing when an icon repeats.
 *
 * Geometry only — no stroke or fill attributes are kept, so the rendering
 * <svg> controls colour and weight from the design system.
 */
function iconLibrary() {
	return {
		name: 'bridge-icon-library',
		// writeBundle rather than generateBundle: emptyOutDir has already run by
		// this point, so the file survives the build that produced it.
		writeBundle() {
			const dir = resolve(__dirname, 'src/icons');
			const icons = {};

			for (const file of readdirSync(dir)
				.filter((f) => f.endsWith('.svg'))
				.sort()) {
				const source = readFileSync(resolve(dir, file), 'utf8');
				const inner = source
					.replace(/^[\s\S]*?<svg[^>]*>/, '')
					.replace(/<\/svg>[\s\S]*$/, '');

				icons[basename(file, '.svg')] = inner.trim();
			}

			writeFileSync(
				resolve(__dirname, 'dist/icons.json'),
				JSON.stringify(icons, null, '\t')
			);
			this.info(`bridge: compiled ${Object.keys(icons).length} icons`);
		},
	};
}

/**
 * Compile every src/blocks/<slug>/block.json into dist/blocks-manifest.php.
 *
 * `register_block_type()` reads and JSON-decodes one block.json per block on
 * every request — front end, admin, REST and cron alike — unless the theme has
 * registered a metadata collection, which core checks first (see
 * `register_block_type_from_metadata()` and WP_Block_Metadata_Registry, 6.7+).
 * Twenty-eight files measured at about 0.8ms of PHP per request; one manifest
 * is a single `require` that opcache holds compiled.
 *
 * PHP rather than JSON on purpose: a JSON manifest would still be read and
 * decoded, which is the cost being removed. This is the same shape core's own
 * blocks-manifest.php has.
 *
 * The keys are the block folder names, because that is what core resolves a
 * registration path to — `basename()` of the directory. So a folder renamed
 * without a rebuild simply misses the collection and falls back to reading its
 * block.json, which is the safe direction for this to be wrong in.
 *
 * The manifest is generated, so it is authoritative until the next build: a
 * block.json edited without running `npm run build` is an edit the site does
 * not see. That is the same rule the rest of dist/ follows.
 */
function blockManifest() {
	// PHP array syntax, written out rather than JSON.parse'd at runtime.
	// Single-quoted strings, so only the backslash and the quote need escaping.
	const tabs = (depth) => '\t'.repeat(depth);

	const php = (value, depth) => {
		if (null === value) {
			return 'null';
		}

		if ('boolean' === typeof value) {
			return value ? 'true' : 'false';
		}

		if ('number' === typeof value) {
			return String(value);
		}

		if (Array.isArray(value)) {
			if (0 === value.length) {
				return 'array()';
			}

			const items = value.map(
				(v) => `${tabs(depth + 1)}${php(v, depth + 1)},`
			);

			return `array(\n${items.join('\n')}\n${tabs(depth)})`;
		}

		if ('object' === typeof value) {
			const keys = Object.keys(value);

			if (0 === keys.length) {
				return 'array()';
			}

			const items = keys.map(
				(k) =>
					`${tabs(depth + 1)}${php(k, depth + 1)} => ${php(
						value[k],
						depth + 1
					)},`
			);

			return `array(\n${items.join('\n')}\n${tabs(depth)})`;
		}

		return `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
	};

	return {
		name: 'bridge-block-manifest',
		// writeBundle for the reason the icon library gives: emptyOutDir has
		// already run, so the file survives the build that produced it.
		writeBundle() {
			const dir = resolve(__dirname, 'src/blocks');
			const blocks = {};

			for (const slug of readdirSync(dir, { withFileTypes: true })
				.filter((entry) => entry.isDirectory())
				.map((entry) => entry.name)
				.sort()) {
				const file = resolve(dir, slug, 'block.json');

				try {
					blocks[slug] = JSON.parse(readFileSync(file, 'utf8'));
				} catch {
					// A directory with no block.json is not a block — the
					// partials folder inside cards/ is one. Skipped rather
					// than reported: only a malformed block.json is worth
					// stopping a build for, and JSON.parse would have thrown
					// somewhere far louder than here.
					continue;
				}
			}

			const body = Object.keys(blocks)
				.map((slug) => `\t'${slug}' => ${php(blocks[slug], 1)},`)
				.join('\n');

			writeFileSync(
				resolve(__dirname, 'dist/blocks-manifest.php'),
				`<?php\n` +
					`/**\n` +
					` * Generated by the Vite build from the block.json files under src/blocks.\n` +
					` * Do not edit: the next build overwrites it.\n` +
					` *\n` +
					` * Registered with wp_register_block_metadata_collection() in functions.php,\n` +
					` * which is what stops WordPress reading one block.json per block per request.\n` +
					` *\n` +
					` * @package Bridge\n` +
					` */\n\n` +
					`return array(\n${body}\n);\n`
			);
			this.info(
				`bridge: wrote manifest for ${
					Object.keys(blocks).length
				} blocks`
			);
		},
	};
}

/**
 * Vite configuration for the Bridge WordPress theme.
 *
 * Entries:
 *   - main                  Global frontend JS + bundled global SCSS.
 *   - slider                Hero-slider runtime + stylesheet (component SCSS).
 *                           The stylesheet is the block's `style`, so the editor
 *                           and the front end are painted by one file; the script
 *                           is enqueued by render.php, and only where there are
 *                           two slides or more.
 *   - hero-slider-editor    Block registration script loaded in the editor.
 *   - hero-banner-editor    Hero Banner block registration script (editor only).
 *   - hero-banner           Hero Banner stylesheet (CSS-only entry).
 *
 * Output:
 *   - Stable, hash-free filenames so PHP can enqueue them statically.
 *   - Per-entry CSS bundles via cssCodeSplit so per-block styles ship only
 *     where the block is rendered.
 *   - @wordpress/* packages are externalized; consuming files use the
 *     window.wp.* globals WordPress already provides.
 */
export default defineConfig(({ mode }) => ({
	root: resolve(__dirname, 'src'),
	base: '/wp-content/themes/bridge/dist/',

	plugins: [iconLibrary(), blockManifest()],

	css: {
		devSourcemap: true,
		preprocessorOptions: {
			scss: {
				api: 'modern-compiler',
			},
		},
	},

	// JSX compiles straight to the `wp.element` globals WordPress already
	// prints, so .jsx files need no React import and add no runtime — the same
	// globals-not-imports approach the editor scripts use, just readable.
	// Only .jsx files are transformed; existing .js entries are untouched.
	esbuild: {
		jsxFactory: 'wp.element.createElement',
		jsxFragment: 'wp.element.Fragment',
	},

	build: {
		outDir: resolve(__dirname, 'dist'),
		emptyOutDir: true,
		manifest: false,
		cssCodeSplit: true,
		// Ship source maps in dev only — they're deploy bloat (and expose source)
		// in production, where browsers fetch them only with devtools open.
		sourcemap: mode !== 'production',
		target: 'es2020',

		rollupOptions: {
			input: {
				main: resolve(__dirname, 'src/main.js'),
				slider: resolve(__dirname, 'src/js/slider.js'),
				'header-cta-backdrop': resolve(
					__dirname,
					'src/js/header-cta-backdrop.js'
				),
				'hero-slider-editor': resolve(
					__dirname,
					'src/blocks/hero-slider/index.js'
				),
				'hero-banner-editor': resolve(
					__dirname,
					'src/blocks/hero-banner/index.js'
				),
				'hero-banner': resolve(__dirname, 'src/scss/hero-banner.scss'),
				// --- Section blocks (converted from the ACF flexible-content layouts)
				'map-editor': resolve(__dirname, 'src/blocks/map/index.js'),
				map: resolve(__dirname, 'src/scss/map.scss'),
				'call-to-action-editor': resolve(
					__dirname,
					'src/blocks/call-to-action/index.js'
				),
				'call-to-action': resolve(
					__dirname,
					'src/scss/call-to-action.scss'
				),
				'testimonials-editor': resolve(
					__dirname,
					'src/blocks/testimonials/index.js'
				),
				'testimonial-editor': resolve(
					__dirname,
					'src/blocks/testimonial/index.js'
				),
				testimonials: resolve(__dirname, 'src/scss/testimonials.scss'),
				'downloads-editor': resolve(
					__dirname,
					'src/blocks/downloads/index.js'
				),
				'download-item-editor': resolve(
					__dirname,
					'src/blocks/download-item/index.js'
				),
				downloads: resolve(__dirname, 'src/scss/downloads.scss'),
				'price-table-editor': resolve(
					__dirname,
					'src/blocks/price-table/index.js'
				),
				'price-card-editor': resolve(
					__dirname,
					'src/blocks/price-card/index.js'
				),
				'price-table': resolve(__dirname, 'src/scss/price-table.scss'),
				'feature-blocks-editor': resolve(
					__dirname,
					'src/blocks/feature-blocks/index.js'
				),
				'feature-block-editor': resolve(
					__dirname,
					'src/blocks/feature-block/index.js'
				),
				'feature-blocks': resolve(
					__dirname,
					'src/scss/feature-blocks.scss'
				),
				'gallery-editor': resolve(
					__dirname,
					'src/blocks/gallery/index.js'
				),
				'gallery-item-editor': resolve(
					__dirname,
					'src/blocks/gallery-item/index.js'
				),
				gallery: resolve(__dirname, 'src/scss/gallery.scss'),
				'gallery-view': resolve(__dirname, 'src/js/gallery-view.js'),
				// One carousel runtime for every block that offers a swipe
				// layout — cards, testimonials and feature panels — under one
				// handle, so a page carrying two of them loads it once.
				carousel: resolve(__dirname, 'src/js/carousel.js'),
				'logo-slider-editor': resolve(
					__dirname,
					'src/blocks/logo-slider/index.js'
				),
				'logo-slider': resolve(__dirname, 'src/scss/logo-slider.scss'),
				'logo-slider-view': resolve(
					__dirname,
					'src/js/logo-slider-view.js'
				),
				'alternating-content-editor': resolve(
					__dirname,
					'src/blocks/alternating-content/index.js'
				),
				'alternating-row-editor': resolve(
					__dirname,
					'src/blocks/alternating-row/index.js'
				),
				'alternating-content': resolve(
					__dirname,
					'src/scss/alternating-content.scss'
				),
				'faqs-editor': resolve(__dirname, 'src/blocks/faqs/index.js'),
				faqs: resolve(__dirname, 'src/scss/faqs.scss'),
				'goals-editor': resolve(__dirname, 'src/blocks/goals/index.js'),
				'goal-editor': resolve(__dirname, 'src/blocks/goal/index.js'),
				goals: resolve(__dirname, 'src/scss/goals.scss'),
				'goals-view': resolve(__dirname, 'src/js/goals-view.js'),
				'map-view': resolve(__dirname, 'src/js/map-view.js'),
				'split-content-editor': resolve(
					__dirname,
					'src/blocks/split-content/index.js'
				),
				'split-content': resolve(
					__dirname,
					'src/scss/split-content.scss'
				),
				'contact-form-editor': resolve(
					__dirname,
					'src/blocks/contact-form/index.js'
				),
				'contact-form': resolve(
					__dirname,
					'src/scss/contact-form.scss'
				),
				'contact-form-view': resolve(
					__dirname,
					'src/js/contact-form-view.js'
				),
				'cards-editor': resolve(__dirname, 'src/blocks/cards/index.js'),
				'page-title-editor': resolve(
					__dirname,
					'src/blocks/page-title/index.js'
				),
				'breadcrumb-editor': resolve(
					__dirname,
					'src/blocks/breadcrumb/index.js'
				),
				'template-watcher': resolve(
					__dirname,
					'src/editor/template-watcher.js'
				),
				'page-banner-panel': resolve(
					__dirname,
					'src/editor/page-banner-panel.js'
				),
				'banner-preview': resolve(
					__dirname,
					'src/editor/banner-preview.js'
				),
				'section-editor': resolve(
					__dirname,
					'src/blocks/section/index.js'
				),
				'paragraph-lock': resolve(
					__dirname,
					'src/editor/paragraph-lock.js'
				),
				'button-lock': resolve(__dirname, 'src/editor/button-lock.js'),
				'cover-lock': resolve(__dirname, 'src/editor/cover-lock.js'),
				'button-panel': resolve(
					__dirname,
					'src/editor/button-panel.js'
				),
				'band-gradient': resolve(
					__dirname,
					'src/editor/band-gradient.js'
				),
				'top-level-only': resolve(
					__dirname,
					'src/editor/top-level-only.js'
				),
				'band-mask': resolve(__dirname, 'src/editor/band-mask.js'),
				inspector: resolve(__dirname, 'src/scss/inspector.scss'),
				'options-app': resolve(__dirname, 'src/admin/options-app.jsx'),
				'simple-editor': resolve(
					__dirname,
					'src/scss/simple-editor.scss'
				),
				'answer-editor': resolve(
					__dirname,
					'src/scss/answer-editor.scss'
				),
				'header-editor': resolve(
					__dirname,
					'src/blocks/header/index.js'
				),
				'footer-editor': resolve(
					__dirname,
					'src/blocks/footer/index.js'
				),
			},
			external: [/^@wordpress\//],
			output: {
				entryFileNames: '[name].js',
				chunkFileNames: 'chunks/[name].js',
				// WordPress loads these via <script>, not <script type="module">.
				// Without this IIFE wrap, ES-module top-level `const`s leak to
				// global scope and collide across bundles (cards/hero-slider both
				// declare `const InspectorControls` etc.) — SyntaxError on load.
				banner: ';(function(){"use strict";',
				footer: '})();',
				assetFileNames: (assetInfo) => {
					if (assetInfo.name && assetInfo.name.endsWith('.css')) {
						return '[name][extname]';
					}
					return 'assets/[name][extname]';
				},
			},
		},
	},
}));
