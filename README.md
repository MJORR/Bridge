# Bridge

**One global design system, set once, applied everywhere.** Brand colours,
fonts, cards and buttons are set globally within theme options.

Requires WordPress 6.6 or later and PHP 8.1 or later. The site can be installed and run without a developer as the CSS files are pre-compiled within the theme folder.

- [Getting started](#getting-started)
- [Developer notes](#developer-notes)

## Getting started

### 1. Install it

Copy the theme folder into `wp-content/themes/`, or upload it as a zip through
**Appearance → Themes → Add New → Upload Theme**.

### 2. Activate it

**Appearance → Themes → Activate.**

The theme sets a few things up for you on activation: the Team and FAQs content
types, and the menus for the header and footer.

### 3. Access Global Theme Options

The design controls are deliberately locked down, to improve the editor
experience and to prevent design drift. Add yourself in `wp-config.php`:

```php
define( 'BRIDGE_OPERATORS', 'you@example.com,someone@agency.com' );
```

You'll need to be an administrator _and_ on that list. If the list is left empty
the lock opens for all administrators rather than locking everyone out.

### 4. Set the design up

**Theme Options** — the top-level menu just above Appearance, named after the
brand:

| Tab             | What it sets                                                     |
| --------------- | ---------------------------------------------------------------- |
| Colours & Fonts | The palette and the type scale, for the whole site               |
| Buttons         | One of three button styles, and how it behaves under the pointer |
| Cards           | How cards are drawn, previewed across all four backgrounds       |
| Posts           | How blog posts and archives are laid out                         |
| Blocks          | Which blocks editors are offered at all                          |
| Content types   | Custom content types — Team, FAQs, whatever else you need        |
| Templates       | Page layouts                                                     |

Changes preview live on the right. Nothing reaches the site until you press
**Save Theme Options** — the preview is live, the site is not.

**Site Options** is a separate screen underneath: contact details, social links
and the map API keys.

### 5. Build pages

Add a page and start with the custom blocks — Section, Cards, FAQs, Hero Banner
and the rest. Most of them have a small settings panel on the right for the
choices that are genuinely per-page (how many items, which category, wide or
full width).

---

## Developer notes

### Requirements

| Dependency | Requirement                         |
| ---------- | ----------------------------------- |
| WordPress  | 6.6 or later                        |
| PHP        | 8.1 or later                        |
| Node       | 23 — see `.nvmrc`                   |
| Composer   | Development only: PHPCS and PHPUnit |

`composer.json` is the accurate source for the PHP version; the `style.css`
header still says 8.0.

The front end is served entirely from `dist/`, which Vite builds from `src/`.
PHP never reads `src/` at runtime.

### Building

```bash
nvm use          # Node 23, per .nvmrc
npm install      # first time only

npm run dev      # vite build --watch, development mode (source maps)
npm run build    # production build — run this before committing
```

Both write to `dist/`. `emptyOutDir` is on, so **`dist/` is wiped on every
build** — never hand-edit anything in it.

Rebuild after any change under `src/`: SCSS, editor JS, the options app, the
icon library. PHP, `theme.json` and the `.html` templates are read directly and
need no build.

#### Checks

```bash
npm run lint     # eslint (src/**/*.js,jsx) + stylelint (src/**/*.scss)
npm run format   # prettier over js, jsx, scss, json

composer install
composer lint    # phpcs, WordPress coding standards
composer test    # phpunit
```

The PHP tests don't boot WordPress. `tests/bootstrap.php` loads doubles from
`tests/stubs.php` and then the theme's files in the order `functions.php` uses,
so they run anywhere PHP does.

#### What the build produces

- **Stable, hash-free filenames**, because PHP enqueues them statically by name.
- **Per-entry CSS bundles** (`cssCodeSplit`), so a block's stylesheet ships only
  on pages that render that block.
- **`dist/icons.json`** — every `src/icons/*.svg` compiled into one library at
  `writeBundle` (after `emptyOutDir` has run, so the file survives the build
  that made it). PHP renders each icon inline rather than through an external
  sprite.
- **`dist/blocks-manifest.php`** — every block's `block.json` in one PHP file,
  registered through `wp_register_block_metadata_collection()`. Without it
  WordPress reads and decodes one JSON file per block on every request, admin
  and REST included. It is generated, and it wins over the JSON at runtime, so
  **a `block.json` edit does nothing until the next build**.
- **Source maps in development only.** They're deploy bloat in production, and
  browsers fetch them only with devtools open.

`@wordpress/*` packages are **not** bundled. Editor code uses the `window.wp.*`
globals WordPress already prints, and `.jsx` compiles straight to
`wp.element.createElement` via the esbuild settings in `vite.config.js` — so no
React import, and no runtime added.

### `dist/` is committed

The build output is tracked in version control, so installing the theme is clone
and activate — running the site needs no Node toolchain.

### Adding a block

1. Create `src/blocks/<slug>/` with `block.json`, `index.js`, `edit.js`,
   `save.js` and `render.php`.
2. Add its entries to `rollupOptions.input` in `vite.config.js` — the editor
   script (`<slug>-editor`) and, if it has styles, its SCSS entry (`<slug>`).
3. Register it in `functions.php` through `bridge_register_section_block()`,
   which registers the script, the stylesheet and the type in one call.
4. Run `npm run build` — which is also what puts the block into
   `dist/blocks-manifest.php`, the file WordPress reads its metadata from.

If the item block holds a media library id, name that attribute in
`bridge_item_image_attributes()`. The band then fetches every item's image in
one round trip rather than one per item.

### Rebranding the theme

A rebrand, not a refactor. Three files carry the name a client sees; the
`bridge` identifiers underneath stay exactly as they are.

1. **`style.css` header** — `Theme Name`, `Theme URI`, `Author`, `Author URI`,
   `Description`.
2. **`functions.php`** — `BRIDGE_BRAND`. This is the admin menu label and the
   Theme Options page title, nothing else.
3. **`screenshot.png`** — the image in the theme picker. The current one is
   1350×900; WordPress asks for a 4:3 image at least 1200×900.

That's the whole rebrand. None of it changes an identifier, so there's nothing
to break and nothing to migrate.

#### Leave `bridge` alone

The `bridge` prefix is internal and invisible to everyone but a developer —
function names, CSS classes, script handles, the text domain. None of it is
visible to a client. The brand lives in the three files above.

### Project layout

```
functions.php      Bootstrap: constants, asset registration, block registration
inc/               PHP by concern — tokens, colour, options screens, post types,
                   capabilities, header/footer, schema, REST, lockdown
src/blocks/        One directory per block: block.json, edit/save/index, render.php
src/scss/          abstracts (mixins), base, components, blocks, editor, admin
src/admin/         The Theme Options app (JSX, wp.element)
src/icons/         SVGs compiled into dist/icons.json
dist/              Build output — generated, wiped on every build
templates/ parts/  Block templates and template parts
tests/             PHPUnit, with WordPress doubled rather than booted
```

### Escape hatches

Both are `wp-config.php` constants, both deliberate:

- `define( 'BRIDGE_OPERATORS', 'you@example.com' );` — who may open Theme
  Options. An empty list fails _open_, so a misconfigured allowlist can't brick
  the design tools for everyone including whoever is fixing it;
  `bridge_operator_warning()` makes that state visible rather than silent.
- `define( 'BRIDGE_LOCKDOWN', false );` — turns the lockdown off entirely, for a
  build or a migration, without editing theme code.
