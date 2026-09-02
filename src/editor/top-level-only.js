/**
 * Bridge — bands may only be inserted at the top level of a page.
 *
 * A band is a full-width stripe. `render.php` puts `alignfull` on it and the
 * stylesheet gives it the full-bleed treatment:
 *
 *   .bridge-band.bridge-band.alignfull {
 *     width: 100vw;
 *     margin-inline: calc(50% - 50vw);
 *   }
 *
 * That `50%` resolves against the containing block. At the top level of a page
 * the containing block is the content column, and the arithmetic lands the band
 * flush against both edges of the window — which is the whole point of it.
 *
 * Nested, the same declaration resolves against whatever it was dropped into.
 * In one of two columns on a 720px content column, at a 1440px window, the band
 * still grows to 1440px but centres on the *column*: it hangs 186px off the
 * left of the window, leaves 186px bare on the right, and — nothing on the page
 * clips horizontally — adds 186px of sideways scroll to the whole site.
 *
 * There is no width at which that comes right, because a full-bleed element
 * inside a narrower parent is a contradiction rather than a bug to be tuned. So
 * the inserter stops offering it anywhere but the root.
 *
 * ---- Why a filter rather than `parent` or `ancestor` ----------------------
 *
 * Neither can say "top level". Both name blocks this one must sit inside, and
 * the root of a page is not a block — it has no name to give them. `ancestor:
 * ['core/post-content']` would be satisfied by a column, which is the case this
 * exists to stop.
 *
 * `blockEditor.__unstableCanInsertBlockType` is the hook that receives the
 * insertion point itself. It is marked unstable, and core uses it for exactly
 * this shape of rule; if it is ever renamed the failure is soft — the inserter
 * goes back to offering bands inside columns, which is where this started.
 *
 * ---- What this does not do -----------------------------------------------
 *
 * It does not touch blocks that are already nested. A rule about what may be
 * inserted cannot unpick a page somebody has already published, and silently
 * lifting a band out of a column would rearrange a layout nobody asked it to
 * touch. The block says so itself instead — see the notice in
 * src/blocks/faqs/edit.js.
 */

const { addFilter } = window.wp.hooks;

/**
 * The blocks this rule covers.
 *
 * One for now. Every other `bridge_register_section_block()` block wears the
 * same `bridge-band alignfull` and has the same arithmetic behind it, so adding
 * a name here is the whole of extending this — but each one is a decision about
 * a block somebody may already have nested, so they are added deliberately
 * rather than by matching a prefix.
 */
const TOP_LEVEL_ONLY = ['bridge/faqs'];

/**
 * @param {boolean} canInsert    Whether the editor would allow this.
 * @param {Object}  blockType    The block being offered.
 * @param {?string} rootClientId The block it would go inside, or null at the root.
 * @return {boolean} False for a band anywhere but the root.
 */
const rootOnly = (canInsert, blockType, rootClientId) => {
	if (!TOP_LEVEL_ONLY.includes(blockType.name)) {
		return canInsert;
	}

	// `undefined` at the root of the editor, a client id anywhere else. Never
	// widened to true: another filter may have had a reason to say no.
	return rootClientId ? false : canInsert;
};

addFilter(
	'blockEditor.__unstableCanInsertBlockType',
	'bridge/top-level-only',
	rootOnly
);
