import { defineConfig } from 'vite';
import { resolve } from 'node:path';

/**
 * Vite configuration for the Bridge WordPress theme.
 *
 * Entries:
 *   - main                  Global frontend JS + bundled global SCSS.
 *   - slider                Hero-slider viewScript + viewStyle (Swiper + component SCSS).
 *   - hero-slider-editor    Block registration script loaded in the editor.
 *
 * Output:
 *   - Stable, hash-free filenames so PHP can enqueue them statically.
 *   - Per-entry CSS bundles via cssCodeSplit so per-block styles ship only
 *     where the block is rendered.
 *   - @wordpress/* packages are externalized; consuming files use the
 *     window.wp.* globals WordPress already provides.
 */
export default defineConfig({
  root: resolve(__dirname, 'src'),
  base: '/wp-content/themes/bridge/dist/',

  css: {
    devSourcemap: true,
    preprocessorOptions: {
      scss: {
        api: 'modern-compiler',
      },
    },
  },

  build: {
    outDir: resolve(__dirname, 'dist'),
    emptyOutDir: true,
    manifest: false,
    cssCodeSplit: true,
    sourcemap: true,
    target: 'es2020',

    rollupOptions: {
      input: {
        main: resolve(__dirname, 'src/main.js'),
        slider: resolve(__dirname, 'src/js/slider.js'),
        'hero-slider-editor': resolve(__dirname, 'src/blocks/hero-slider/index.js'),
        'cards-editor': resolve(__dirname, 'src/blocks/cards/index.js'),
        'template-watcher': resolve(__dirname, 'src/editor/template-watcher.js'),
      },
      external: [
        /^@wordpress\//,
      ],
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
});
