/**
 * Bridge — JavaScript linting.
 *
 * WordPress's own ruleset, which is also what decides formatting: the
 * recommended config defers every stylistic question to prettier through
 * `@wordpress/prettier-config`. Nothing here restyles the code; running
 * `npm run format` does.
 *
 * `.cjs` because package.json sets `"type": "module"`.
 */

module.exports = {
	root: true,

	extends: ['plugin:@wordpress/eslint-plugin/recommended'],

	env: {
		browser: true,
	},

	globals: {
		// WordPress prints these. The build externalises @wordpress/* rather
		// than bundling it, so the packages arrive as globals on `wp` rather
		// than as imports — which is why the i18n and dependency-group rules
		// below have nothing to work with.
		wp: 'readonly',
		// Printed by inc/icons.php ahead of the icon block's editor script.
		bridgeIcons: 'readonly',
	},

	settings: {
		// Every translator call in this theme belongs to one domain, and the
		// rule cannot know which without being told.
		'@wordpress/i18n-text-domain': {
			allowedTextDomain: 'bridge',
		},
	},

	rules: {
		// The rule checks that translator functions were imported from
		// @wordpress/i18n. Here they are destructured from the `wp` global,
		// which is the documented approach for scripts that declare
		// wp-i18n as a script dependency rather than bundling it.
		'@wordpress/i18n-no-variables': 'off',

		// Document the props bag, not every key in it. jsdoc has no name for a
		// destructured parameter, so it invents one: asking for these produces
		// `@param root0.title` — a parameter that does not exist in the source,
		// with no type and no description. Left on, the rule's own autofix
		// writes sixty of those. Functions with named parameters are still
		// required to document them.
		'jsdoc/require-param': ['error', { checkDestructured: false }],
		'jsdoc/check-param-names': ['error', { checkDestructured: false }],

		/*
		 * Reading a `const` above its declaration.
		 *
		 * Not a build error and not a parse error — the bundle is valid
		 * JavaScript and `node --check` passes. It throws a ReferenceError on
		 * the first render instead, which in a block editor takes the whole
		 * block down and shows nothing but an error boundary. This has cost a
		 * live block once already.
		 *
		 * Functions are exempt because hoisted declarations are how helpers
		 * are written above the component that uses them throughout this
		 * codebase; classes and variables are not hoisted the same way, so
		 * they stay checked.
		 */
		'no-use-before-define': [
			'error',
			{ functions: false, classes: true, variables: true },
		],
	},

	// Build output is generated, minified and not ours to lint.
	ignorePatterns: ['dist/**', 'node_modules/**'],
};
