/**
 * Bridge — stylesheet linting.
 *
 * WordPress's own SCSS ruleset. Formatting is prettier's job here too, so this
 * extends the non-stylistic variant: `@wordpress/stylelint-config/scss` rather
 * than `/scss-stylistic`, which would fight `npm run format` over whitespace.
 *
 * `.cjs` because package.json sets `"type": "module"`.
 */

module.exports = {
	extends: ['@wordpress/stylelint-config/scss'],

	rules: {
		// WordPress's pattern is lowercase-with-hyphens, no underscores. That
		// standard was written for wp-admin's stylesheets; block markup is BEM,
		// core's own included — `wp-block-cover__inner-container`,
		// `wp-block-navigation__responsive-container` — and this theme's classes
		// render alongside those in the same markup. Enforcing hyphens here
		// would make the CSS less consistent with the HTML it styles, not more.
		//
		// It would also destroy information: `__` marks an element and `--` a
		// modifier, and hyphens-only collapses `bridge-header__top` and
		// `bridge-header--sticky` into the same shape.
		'selector-class-pattern': null,

		// Three findings, all of them a submenu's link styled after a top-level
		// link's hover state — different contexts rather than accidents. The
		// rule reports the shape of nesting, not a bug.
		'no-descending-specificity': null,

		// A block that opens with an @include — `@include section.container()`
		// on the first line of `&__inner` — is the shape used by every
		// component in the theme. The default demands a blank line *after* the
		// opening brace to get there, which reads as a typo and which prettier
		// then leaves alone, so the two tools disagreed on twenty-odd files
		// forever. `first-nested` excepts exactly that position and nothing
		// else: a blank line is still expected between an @include and the
		// declarations after it.
		// `ignore` rather than `except`: `except` would invert the rule there
		// and start demanding the blank line be absent, which is just the same
		// argument from the other side. Ignored means the linter holds no
		// opinion about that one position and prettier is left to it.
		'at-rule-empty-line-before': [
			'always',
			{
				except: ['blockless-after-same-name-blockless'],
				ignore: ['after-comment', 'first-nested'],
			},
		],
		'rule-empty-line-before': [
			'always-multi-line',
			{
				ignore: ['after-comment', 'first-nested'],
			},
		],

		// Prettier strips blank lines from the top of a block, so a rule that
		// opens with a prose comment — the shape most of the blocks in this
		// theme use — can never satisfy the default here: the blank line the
		// linter asks for is removed again by the next `npm run format`.
		// `first-nested` excepts exactly that position, which is prettier's
		// own behaviour written down. Everywhere else a comment still wants
		// air above it.
		'comment-empty-line-before': [
			'always',
			{
				except: ['first-nested'],
				ignore: ['stylelint-commands', 'after-comment'],
			},
		],

		// A bare `//` line is a paragraph break inside a prose comment, which
		// this theme's comments have plenty of. It is not an empty comment in
		// the sense the rule means — a stray `/* */` left behind by a deletion.
		'scss/comment-no-empty': null,
	},

	ignoreFiles: ['dist/**', 'node_modules/**'],
};
