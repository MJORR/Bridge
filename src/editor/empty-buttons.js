/**
 * Bridge — deleting the last button deletes its row.
 *
 * A button lives inside a Buttons block, the row that lays buttons out. Delete
 * the only button and core leaves the row behind, empty, showing nothing but
 * an inserter. In the hero banner that reads as "the button won't go away".
 * The row has to be selected through the List View or the parent selector and
 * deleted a second time.
 *
 * So when a Buttons block that had buttons is left with none, it goes too.
 * Only on that transition: a Buttons block that has just been inserted is
 * empty for a moment before core fills it from its template, and it never
 * had a button to lose.
 *
 * The removal is marked as not persistent, so it joins the delete that caused
 * it. One Undo brings back the button and its row together.
 */

const { subscribe, select, dispatch } = window.wp.data;

const STORE = 'core/block-editor';

// Buttons block clientId → how many buttons it held last time we looked.
const counts = new Map();

subscribe(() => {
	const editor = select(STORE);

	if (!editor || typeof editor.getBlocksByName !== 'function') {
		return;
	}

	const ids = editor.getBlocksByName('core/buttons');
	const seen = new Set();

	ids.forEach((id) => {
		seen.add(id);

		const count = editor.getBlockCount(id);
		const before = counts.get(id) || 0;

		counts.set(id, count);

		if (before > 0 && count === 0) {
			counts.delete(id);

			const actions = dispatch(STORE);
			actions.__unstableMarkNextChangeAsNotPersistent();
			actions.removeBlock(id, false);
		}
	});

	// Forget rows that are no longer on the page.
	counts.forEach((_, id) => {
		if (!seen.has(id)) {
			counts.delete(id);
		}
	});
}, STORE);
