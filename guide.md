# Bridge — Developer Guide

This guide is for working on the theme by hand: where each piece lives, the
commands to run, and what to check afterwards. The README covers installing the
theme and the reasons behind the build. This guide covers the day-to-day work.

All commands run from the theme folder:

```bash
cd "~/Local Sites/dawa/app/public/wp-content/themes/bridge"
```

---

## 1. One-time setup

```bash
nvm use            # Node 23, from .nvmrc
npm install        # JS/SCSS tooling (Vite, Sass, ESLint, Stylelint, Prettier)
composer install   # optional: PHP linting (PHPCS) and tests (PHPUnit)
```

Start the site in **Local** before you check anything in the browser. The site
runs at http://dawa.local.

---

## 2. The golden rule: `src/` → build → `dist/`

WordPress only loads files from `dist/`. Everything in `src/` is compiled into
`dist/` by Vite.

| You edited…                                   | Build needed? |
| --------------------------------------------- | ------------- |
| Any `.scss` file                              | **Yes**       |
| Any `.js` / `.jsx` file (blocks, editor, admin) | **Yes**     |
| Any `block.json`                              | **Yes**, because it is compiled into `dist/blocks-manifest.php` |
| Any SVG in `src/icons/`                       | **Yes**, because it is compiled into `dist/icons.json` |
| `render.php`, other PHP, `functions.php`, `inc/` | No: refresh the page |
| `theme.json`, `templates/*.html`, `parts/*.html` | No: refresh the page (but see §8) |

```bash
npm run dev     # rebuilds automatically every time you save (leave it running)
npm run build   # one-off production build, which is the one to run before committing or deploying
```

Every build wipes `dist/` and writes it again, so never edit anything in `dist/`.
The build output is committed, so the live site never needs Node.

Assets are versioned by file modification time, so a fresh build loads
automatically. If the browser still shows old styles, hard-refresh
(⌘⇧R). If a page cache plugin is active, clear it.

---

## 3. Where things live

```
functions.php        Loads assets and registers every block (bridge_register_section_block)
inc/                 PHP by concern
  tokens.php           Theme Options settings: defaults, limits, sanitising
  section-blocks.php   Shared band/section helpers (wrapper, mask, register helper)
  footer.php header.php   Header and footer data
theme.json           Palette, font sizes, spacing scale, content/wide widths
src/
  main.js            Global front-end entry, which bundles scss/main.scss and js/header.js
  scss/
    main.scss        Global stylesheet (loads on every page)
    <block>.scss     One entry file per block stylesheet (e.g. testimonials.scss)
    abstracts/       Mixins: _section (containers), _band-mask, _button, _card, _rhythm…
    base/            Reset, accessibility, vertical rhythm (_rhythm.scss)
    components/      Buttons, paragraphs, nav, section skins (_sections.scss)
    blocks/          The real styles for each block (_testimonials.scss, _footer.scss…)
    editor/ admin/   Editor-only and Theme Options screen styles
  blocks/<slug>/     One folder per block (see §5)
  editor/            Editor add-ons shared across blocks (mask panel, locks, panels)
  js/                Front-end scripts (carousel, slider, map, gallery, contact form…)
  admin/             The Theme Options app (React/JSX); tabs/ holds one file per tab
  icons/             SVG icons
templates/ parts/    Page templates and header/footer template parts
tests/               PHPUnit tests (run without booting WordPress)
```

---

## 4. Editing CSS

### Find the right file

1. In the browser, right-click the element and choose **Inspect**. Note the class
   name, for example `.bridge-testimonials__rating`.
2. Search for that class in the source:

   ```bash
   grep -rn "__rating" src/scss
   ```

   Classes use BEM with nested SCSS, so `&__rating` inside `.bridge-testimonials`
   is `.bridge-testimonials__rating`. If searching for the full name finds
   nothing, search for the part after `__`.

3. Global styles (header, footer, cards, buttons, rhythm, sections) are pulled
   in by `src/scss/main.scss`. Each block stylesheet has a small entry file, for
   example `src/scss/testimonials.scss`, which just runs
   `@use "blocks/testimonials"`. Edit the partial in `src/scss/blocks/`, not the
   entry file.

### Edit, build, check

```bash
npm run dev                 # leave running, then save the SCSS file
# or
npm run build
```

Refresh the page and check it.

### Lint and auto-fix

```bash
npm run lint:css                                     # check every SCSS file
npx stylelint --fix "src/scss/blocks/_footer.scss"   # fix one file
```

### Useful conventions

- **Use the design tokens, not raw values.** Colours come from
  `var(--wp--preset--color--primary | secondary | accent | surface | text)`.
  Spacing comes from `var(--wp--preset--spacing--40 | 50 | 60 …)`. The gap
  between blocks is `var(--wp--style--block-gap)`. These all come from
  `theme.json` and Theme Options, so the site stays consistent when the brand
  changes.
- **Breakpoints:** use the mixins rather than writing new media queries,
  e.g. `@include section.two-column { … }` (48rem). The footer has its own
  queries at 38rem and 68rem in `blocks/_footer.scss`.
- **Content width:** `@include section.container(wide)` or `(narrow)` caps
  the content and adds the side gutter. Don't add extra `padding-inline` inside
  a band: that is what caused the "indented by 40px" problem.
- **Vertical spacing** between headings, buttons, images and text is set in
  `src/scss/base/_rhythm.scss`.
- **Mask shapes:** a block opts in with `@include mask.band-mask;` on the band
  and `@include mask.band-mask-content;` on its inner wrapper (see
  `_testimonials.scss` or `_footer.scss`).

---

## 5. Editing a block

Every block lives in `src/blocks/<slug>/`:

| File          | What it does                                               | Build? |
| ------------- | ---------------------------------------------------------- | ------ |
| `block.json`  | Name, settings (attributes) and their defaults, supports, asset handles | Yes |
| `index.js`    | Registers the block in the editor (rarely needs editing)   | Yes    |
| `edit.js`     | What the editor shows: the preview and the sidebar controls | Yes   |
| `save.js`     | Usually just `InnerBlocks.Content`. The HTML comes from PHP | Yes   |
| `render.php`  | **The real front-end HTML**, rendered on the server         | No    |

### Typical change: add a setting to a block

1. **`block.json`**: add the attribute and bump `"version"`:

   ```json
   "attributes": {
     "showBadge": { "type": "boolean", "default": false }
   }
   ```

2. **`edit.js`**: add a control in the sidebar (`InspectorControls`):

   ```js
   el(ToggleControl, {
   	label: __('Show badge', 'bridge'),
   	checked: !!attributes.showBadge,
   	onChange: (value) => setAttributes({ showBadge: value }),
   })
   ```

   Editor code uses the globals WordPress already provides (`window.wp.blockEditor`,
   `window.wp.components`, …) rather than `import` statements. Copy the pattern
   at the top of an existing `edit.js`.

3. **`render.php`**: read it with a fallback, and escape everything you print:

   ```php
   $show_badge = ! empty( $attributes['showBadge'] );
   if ( $show_badge ) {
   	echo '<span class="bridge-x__badge">' . esc_html__( 'New', 'bridge' ) . '</span>';
   }
   ```

4. **SCSS**: style `.bridge-x__badge` in `src/scss/blocks/_x.scss`.

5. Build, then check both the editor and the front end:

   ```bash
   npm run build
   ```

**Passing a setting from a parent block to its child** (e.g. Feature Blocks →
Feature Block): in the parent's `block.json` add
`"providesContext": { "bridge/myKey": "myAttribute" }`. In the child add
`"usesContext": ["bridge/myKey"]`. Read it as `context['bridge/myKey']` in
`edit.js` and as `$block->context['bridge/myKey']` in `render.php`.

**Changing a default value** only affects blocks where the editor never touched
that setting. Pages that already saved a value keep it.

### Adding a new block

1. Copy a similar block folder, e.g. `cp -R src/blocks/section src/blocks/my-block`.
   Then change `"name"` (`bridge/my-block`), `"title"`, `"editorScript"`
   (`bridge-my-block-editor`) and, if it has CSS, `"style"`
   (`bridge-my-block-style`) in `block.json`, plus the name in `index.js`.
2. If it has styles, create `src/scss/my-block.scss` (`@use "blocks/my-block";`)
   and `src/scss/blocks/_my-block.scss`.
3. Add the entries to `rollupOptions.input` in `vite.config.js`:

   ```js
   'my-block-editor': resolve(__dirname, 'src/blocks/my-block/index.js'),
   'my-block': resolve(__dirname, 'src/scss/my-block.scss'),
   ```

4. Register it in `functions.php` next to the others:

   ```php
   bridge_register_section_block( 'my-block' );                  // editor JS + CSS
   bridge_register_section_block( 'my-block', true, true );      // + front-end view JS (dist/my-block-view.js)
   bridge_register_section_block( 'my-block', true, false, array( 'bridge-band-mask' ) ); // uses the mask panel
   ```

5. `npm run build`, then add the block in the editor.

The block may also need enabling under **Theme Options → Blocks**.

---

## 6. Editing JavaScript

| Kind                    | Location                         | Loaded where                    |
| ----------------------- | -------------------------------- | ------------------------------- |
| Block editor UI         | `src/blocks/<slug>/edit.js`      | Editor only                     |
| Shared editor add-ons   | `src/editor/*.js`                | Editor only                     |
| Front-end behaviour     | `src/js/*.js` (e.g. `carousel.js`, `map-view.js`) | Pages that use the block |
| Global front-end        | `src/js/header.js` via `src/main.js` | Every page                  |
| Theme Options screen    | `src/admin/**/*.jsx`             | WP admin → Theme Options        |

Each file is a separate entry in `vite.config.js` and becomes `dist/<entry>.js`.
To add a new front-end script, add an entry there and register or enqueue it in
PHP, using `bridge_register_script()` / `bridge_enqueue_script()` in
`functions.php` or the `$view` flag of `bridge_register_section_block()`.

```bash
npm run lint:js                               # check all JS/JSX
npx eslint --fix src/blocks/cards/edit.js     # fix one file
npm run format                                # Prettier over js/jsx/scss/json
```

Debugging: open the browser console. In the editor, a JS error usually shows as
"This block has encountered an error". The console names the file and line in
`dist/`. Development builds (`npm run dev`) include source maps that point back
to `src/`.

---

## 7. Theme Options (global settings)

The Theme Options screen is a React app in `src/admin/`, with one tab per file
in `src/admin/tabs/`. Its values are saved as tokens in PHP:

1. **`inc/tokens.php`**:
   - add the default value;
   - add limits (min/max/step, or the allowed choices) in `bridge_token_constraints()`;
   - make sure `bridge_sanitize_tokens()` keeps the new key.

   Anything not sanitised there is silently dropped on save.
2. **`src/admin/tabs/<tab>.jsx`**: add the control. Copy a neighbouring control,
   e.g. the footer mask controls in `templates.jsx`.
3. **Use it**: read it in PHP with `bridge_get_tokens()`, or output it as a CSS
   variable and use it in SCSS.
4. `npm run build`, open **Theme Options**, change the setting, press
   **Save Theme Options**, and check the front end.

Theme Options are only visible to administrators listed in `BRIDGE_OPERATORS` in
`wp-config.php` (if the list is empty, all admins can see them).

---

## 8. Templates, header and footer

- Page templates: `templates/*.html`. Template parts: `parts/header.html`,
  `parts/footer.html`.
- The header and footer are blocks (`src/blocks/header`, `src/blocks/footer`),
  rendered by their own `render.php`. Their settings come from
  **Theme Options → Templates**.
- **Gotcha:** if a template or part was ever edited in **Appearance → Editor**,
  WordPress stores that copy in the database and ignores the `.html` file. To go
  back to the file, open it in the Site Editor and choose **Reset**
  (or **Clear customisations**).

---

## 9. Icons

Drop a 24×24 SVG into `src/icons/` (for example `rocket.svg`). Use geometry
only and leave out stroke/fill colours. Then run `npm run build`. It is compiled
into `dist/icons.json` and rendered inline by PHP by its file name (`rocket`).

---

## 10. Checks before committing / deploying

```bash
npm run lint       # ESLint + Stylelint
npm run build      # production build: dist/ must be rebuilt and committed
composer lint      # PHPCS (WordPress standards), optional
composer test      # PHPUnit, optional
php -l src/blocks/cards/render.php   # quick syntax check of one PHP file
```

Then click through a few pages at phone, tablet (~820px) and desktop widths,
and check the editor for any block you touched.

Deploying is copying the theme folder, including `dist/`, to the server.
`node_modules/`, `vendor/` and `src/` aren't needed at runtime, but they're
harmless if present.

---

## 11. WP-CLI on the Local site

The simplest route: in the Local app, right-click the site and choose
**Open site shell**. `wp` is ready to use in that terminal:

```bash
wp cache flush
wp post list --post_type=page --fields=ID,post_title
wp option get bridge_theme_options --format=json   # inspect saved Theme Options
wp search-replace 'old' 'new' --dry-run
```

Back up first with `wp db export backup.sql`, or with Local's own export.

---

## 12. Troubleshooting

| Symptom | Likely cause / fix |
| ------- | ------------------ |
| CSS/JS change not showing | You didn't build. Run `npm run build` or keep `npm run dev` running, then hard-refresh |
| `block.json` change does nothing | Same: the manifest in `dist/` is only rebuilt by a build |
| "This block contains unexpected or invalid content" | `save.js` output changed. Usually choose **Attempt recovery** in the editor. Keep `save.js` as just `InnerBlocks.Content` |
| Block shows an error in the editor | JS error. Check the browser console |
| Front end blank or "critical error" | PHP error in `render.php`. Turn on `WP_DEBUG` and `WP_DEBUG_LOG` in `wp-config.php` and read `wp-content/debug.log` |
| Header/footer `.html` edits ignored | The part is customised in the database. Reset it in the Site Editor (§8) |
| Theme Option doesn't save | The key is missing from `bridge_sanitize_tokens()` in `inc/tokens.php` |
| Build fails | Read the first error. It is usually an SCSS syntax error, or an entry in `vite.config.js` pointing at a missing file |
