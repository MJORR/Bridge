/**
 * Deprecations for `bridge/logo-slider`.
 *
 * The intro was a heading and an optional paragraph nested inside the block.
 * The block carries one label now, which means every block already saved in a
 * page holds markup this block no longer knows how to produce — and a block
 * whose saved markup does not match what it would save today is shown to the
 * editor as broken content with a button to delete it.
 *
 * So the old save lives on here. WordPress tries it when the current one does
 * not match, and `migrate` moves the heading's words into the label.
 *
 * They are set differently there — the label is small, spaced and upper case,
 * where the heading was a heading — so a migrated block reads as a label
 * rather than as the title it used to be. That is the block changing shape,
 * and the alternative is dropping words an editor wrote. Any paragraph that
 * was nested alongside the heading has nowhere to go and is not carried over.
 */

import metadata from './block.json';

const { InnerBlocks } = window.wp.blockEditor;
const { createElement: el } = window.wp.element;

/**
 * The text of one inner block.
 *
 * `content` comes back as a rich-text value rather than a string in current
 * WordPress, and it stringifies to the HTML the editor wrote — which is what
 * these fields hold too.
 *
 * @param {Object} block A parsed inner block, or undefined.
 * @return {string} Its content, trimmed.
 */
const text = (block) => String(block?.attributes?.content ?? '').trim();

const deprecated = [
	{
		// The attribute set as it is today. A deprecation only has to be able
		// to *parse* the old markup, and the old markup carries no attribute
		// this list is missing — so pointing at the block's own definition
		// keeps the two from drifting apart.
		attributes: metadata.attributes,
		supports: metadata.supports,

		save: () => el(InnerBlocks.Content),

		migrate: (attributes, innerBlocks) => [
			{
				...attributes,
				label:
					text(
						innerBlocks.find(({ name }) => 'core/heading' === name)
					) || attributes.label,
			},
			[],
		],
	},
];

export default deprecated;
