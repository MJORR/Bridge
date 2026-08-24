/**
 * Bridge — code formatting.
 *
 * WordPress's own prettier configuration, unmodified: tabs, single quotes,
 * spaces inside parens. `@wordpress/eslint-plugin`'s recommended ruleset
 * delegates every formatting decision to this file, so the two agree by
 * construction rather than by us keeping them in step.
 *
 * `.cjs` because package.json sets `"type": "module"`.
 */

module.exports = require('@wordpress/prettier-config');
