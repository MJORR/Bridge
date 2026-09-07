/**
 * Bridge — "Add slide" on a slide's own toolbar.
 *
 * A slide is a `core/cover`, so the toolbar an editor is looking at while they
 * work on one is core's, not this block's. The slider's own toolbar is a level
 * up and only appears once the slider itself is selected — and selecting a
 * parent is a thing editors know how to do and almost never think to.
 *
 * So the control goes where the work is: while a Cover is a direct child of a
 * hero slider, its toolbar carries an "Add slide" button that opens the media
 * library and drops the new slide in immediately after this one. Anywhere else,
 * a Cover is untouched — the filter returns the original edit view before any
 * of this runs.
 *
 * It lives in the slider's own bundle rather than in a script of its own so
 * that `slide()` — what a slide is made of — has one definition. A second copy
 * in another file is a second answer to "does a new slide get a subtitle", and
 * they would drift.
 */

import { slide } from './edit.js';

const { addFilter } = window.wp.hooks;
const { createElement: el, Fragment } = window.wp.element;
const { createHigherOrderComponent } = window.wp.compose;
const { BlockControls, MediaUpload, MediaUploadCheck } = window.wp.blockEditor;
const { ToolbarGroup, ToolbarButton } = window.wp.components;
const { useSelect, useDispatch } = window.wp.data;
const { __ } = window.wp.i18n;

const SLIDER = 'bridge/hero-slider';

/**
 * The toolbar button, for a Cover that is a slide.
 *
 * A component of its own rather than a branch inside the filter, because it
 * calls hooks: a Cover that is not in a slider must not call them at all, and
 * the only way to be sure of that is for the component holding them not to be
 * rendered.
 *
 * @param {Object} props        Block edit props.
 * @param {Object} props.origin The wrapped edit view.
 * @return {Object} Element.
 */
const SlideTools = ({ origin: BlockEdit, ...props }) => {
	const { clientId } = props;

	/*
	 * The slider this Cover belongs to, found by walking up.
	 *
	 * Not `getBlockRootClientId()`. That answers "what is my parent", which is
	 * only the slider while the Cover is a direct child of it — and a Cover can
	 * hold another Cover. On a nested one the parent is the outer *slide*, and
	 * a new slide inserted there lands inside slide one instead of beside it:
	 * it draws over the top of it and takes the inner container's layout, which
	 * is exactly the "slide two covers slide one" this kept producing.
	 *
	 * `getBlockParentsByBlockName(…, true)` returns the ancestors nearest-first,
	 * so `[0]` is the closest hero slider however deep the Cover sits. No
	 * slider above it at all — an ordinary Cover on a page — and the list is
	 * empty, which is what takes the button off that block's toolbar.
	 */
	const sliderId = useSelect(
		(select) =>
			select('core/block-editor').getBlockParentsByBlockName(
				clientId,
				SLIDER,
				true
			)[0],
		[clientId]
	);

	const { insertBlock } = useDispatch('core/block-editor');

	if (!sliderId) {
		return el(BlockEdit, props);
	}

	/*
	 * Appended, as the last slide of the slider.
	 *
	 * No index. Core reads `index = subState.length` when it is left out, so
	 * the slide goes on the end — which is the one position that is right
	 * whatever was selected when the button was pressed, and it is what "add
	 * another slide" means when the slides are stacked one under the next.
	 *
	 * An index computed from the current block was the other half of the bug
	 * above: `getBlockIndex()` counts within the *immediate* parent, so from a
	 * nested Cover it is a position in the wrong list entirely.
	 */
	const addSlide = (media) => {
		const image = Array.isArray(media) ? media[0] : media;

		if (image) {
			insertBlock(slide(image, false), undefined, sliderId);
		}
	};

	return el(
		Fragment,
		null,
		el(BlockEdit, props),
		el(
			BlockControls,
			{ group: 'other' },
			el(
				ToolbarGroup,
				null,
				el(
					MediaUploadCheck,
					null,
					el(MediaUpload, {
						multiple: false,
						allowedTypes: ['image'],
						onSelect: addSlide,
						render: ({ open }) =>
							el(
								ToolbarButton,
								{ icon: 'plus-alt2', onClick: open },
								__('Add slide', 'bridge')
							),
					})
				)
			)
		)
	);
};

const withSlideTools = createHigherOrderComponent(
	(BlockEdit) => (props) =>
		props.name === 'core/cover'
			? el(SlideTools, { origin: BlockEdit, ...props })
			: el(BlockEdit, props),
	'withSlideTools'
);

addFilter('editor.BlockEdit', 'bridge/hero-slide-tools', withSlideTools);
