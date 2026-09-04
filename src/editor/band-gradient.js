/**
 * Bridge — the gradient a skinned band can wear, in the editor.
 *
 * The three section skins paint a band one flat colour. This is the other
 * option: the same colour graded across, an eighth darker at the left edge and
 * an eighth lighter at the right. components/_sections.scss draws it,
 * from the `--bridge-band-ground` each skin declares — so the arithmetic lives
 * with the skins and this file only says which bands are wearing it.
 *
 * It sits at the end of the Styles tab, under the skin picker it modifies.
 * That is what `group: 'styles'` below buys: an InspectorControls with no group
 * renders in Settings, which would have put the gradient a tab away from the
 * three styles it grades.
 *
 * Last in that tab, and not by luck. A slot renders its fills in the order they
 * mount, and this one is a filter on `editor.BlockEdit`: the wrapped edit view
 * — with every fill the block itself contributes — is the first child of the
 * fragment below, so it mounts before this panel does. Anything the block or
 * core puts in the Styles tab therefore comes first, whatever block this is
 * wrapping.
 *
 * ---- Why there is no attribute and no per-block wiring ---------------------
 *
 * Sixteen blocks offer these skins, and core/group offers them too. An
 * attribute would have meant sixteen block.json files declaring it, sixteen
 * edit views rendering the toggle and sixteen renderers emitting the class —
 * forty-eight places to add a skin to, and forty-eight to miss one in.
 *
 * The skin itself is not stored that way either: a block style *is* a class in
 * `className`, which is where core keeps it. So this keeps its own answer in
 * the same place, and everything downstream already works — the canvas applies
 * `className` itself, and `get_block_wrapper_attributes()` merges it into
 * every one of those renderers without being told.
 *
 * That is also what decides where the toggle appears. It is offered to any
 * block already wearing one of the three skins rather than to a list of block
 * names: a band is skinned or it is not, the class says so, and a block that
 * gains the skins later is included without this file knowing it exists.
 *
 * Plain createElement (no JSX) + window.wp.* globals, and self-contained: each
 * editor bundle here is its own IIFE, so a helper shared with button-panel.js
 * would be hoisted into a chunk nothing enqueues. See the note in
 * vite.config.js.
 *
 * @param {Object} wp The WordPress globals this file is wrapped around.
 */

(function (wp) {
	const { addFilter } = wp.hooks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, ToggleControl } = wp.components;
	const { createHigherOrderComponent } = wp.compose;
	const { __ } = wp.i18n;

	// The class the stylesheet grades on, and the three it will grade.
	const GRADIENT = 'has-band-gradient';
	const SKINS = [
		'is-style-bridge-surface',
		'is-style-bridge-inverted',
		'is-style-bridge-accent',
	];

	const classList = (className) =>
		String(className || '')
			.split(/\s+/)
			.filter(Boolean);

	const isSkinned = (className) =>
		classList(className).some((name) => SKINS.includes(name));

	const isGraded = (className) => classList(className).includes(GRADIENT);

	/**
	 * The same class attribute, saying the other thing.
	 *
	 * Every other class an editor put there keeps its place — this owns one
	 * word of that attribute, not all of it.
	 *
	 * @param {string}  className The block's class attribute.
	 * @param {boolean} on        Whether the band is graded.
	 * @return {string|undefined} The new value, or undefined when empty.
	 */
	const withGradient = (className, on) => {
		const classes = classList(className).filter(
			(name) => name !== GRADIENT
		);

		if (on) {
			classes.push(GRADIENT);
		}

		return classes.length ? classes.join(' ') : undefined;
	};

	const withPanel = createHigherOrderComponent(
		(BlockEdit) => (props) => {
			const { className } = props.attributes || {};

			// Nothing to grade until the band has a colour of its own. The
			// control does not appear greyed out on an unskinned band, because
			// a band with no skin has no question to answer here.
			if (!isSkinned(className)) {
				return el(BlockEdit, props);
			}

			return el(
				Fragment,
				null,
				el(BlockEdit, props),
				el(
					InspectorControls,
					// The Styles tab, beside the Surface/Inverted/Accent picker
					// this modifies, rather than the Settings tab an
					// InspectorControls with no group lands in. The skin and
					// whether it is graded are one decision made twice; a tab
					// between them makes the second one hard to find and easy
					// to believe is missing.
					{ group: 'styles' },
					el(
						PanelBody,
						{ title: __('Band', 'bridge'), initialOpen: true },
						el(ToggleControl, {
							label: __('Gradient on/off', 'bridge'),
							help: __('Add background gradient', 'bridge'),
							checked: isGraded(className),
							onChange: (value) =>
								props.setAttributes({
									className: withGradient(className, value),
								}),
							__nextHasNoMarginBottom: true,
						})
					)
				)
			);
		},
		'bridgeBandGradient'
	);

	addFilter('editor.BlockEdit', 'bridge/band-gradient', withPanel);
})(window.wp);
