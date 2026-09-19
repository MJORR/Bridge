/**
 * Bridge — the Button panel on core/button.
 *
 * Two settings, both of which name something the design system has already
 * drawn rather than inventing anything of their own: which of the two button
 * designs this is, and what colour it is.
 *
 * A button's colours are not loose. abstracts/_button.scss paints every button
 * from nine `--bridge-button-*` variables, and the bands set those for the
 * buttons inside them — a Surface band emits the Surface ground, the Accent
 * band the Accent one, anything drawn on dark the Inverted one. Custom
 * properties inherit, so a button that lands in one of those needs to know
 * nothing about where it is, and Background left on "From the band" is that.
 *
 * Choosing a colour points the same nine variables at a palette scheme
 * instead. That is still not a paint box: the editor picks one palette colour
 * and PHP settles everything that follows from it — the label that can be read
 * on it, the hover shade, the border it needs only if its fill is too close to
 * the page to have an edge of its own, the focus ring. See
 * bridge_button_palette_schemes(), which is the four grounds' own arithmetic
 * pointed at the palette, and button-lock.js for why core's paint box is off.
 *
 * There used to be a third answer here — which of the four grounds the button
 * was *standing* on, for a button over a photograph that the cascade could not
 * work out. One colour question per control is enough, so it is not offered
 * any more; components/_button.scss still paints those four classes, so a page
 * carrying one keeps the button it had until somebody changes it.
 *
 * ---- Solid and Outline -----------------------------------------------------
 *
 * Theme Options draws both, previews both on each of the four grounds and
 * contrast-audits both. Until this control existed there was no way to make an
 * Outline one: core's own Fill/Outline picker is removed by button-lock.js, so
 * it could only be had from the Call to Action block, whose template seeds its
 * second button with the class, or by typing `is-style-outline` into Advanced
 * → Additional CSS class(es). A design the options screen guarantees and the
 * editor cannot produce is a gap, not a decision.
 *
 * ---- Why both live in `className` -----------------------------------------
 *
 * Because core/buttons copies it. Adding a button to a row does not insert a
 * blank one: `DEFAULT_BLOCK.attributesToCopy` in the buttons block carries
 * `className` over from the button before it, so a second button beside an
 * Outline one in Accent arrives as the same thing rather than as a default
 * that has to be set again.
 *
 * The colour was an attribute of its own to begin with, and that list is the
 * reason it is not: `className` was copied and `bridgeGround` was not, so of
 * the two settings in this one panel the first carried to the next button and
 * the second silently did not. Two controls that look alike and behave
 * differently is worse than either behaviour.
 *
 * It is also simply where this belongs. The class is what the stylesheet
 * paints, `is-style-outline` is where core's own style picker stores the same
 * fact, and a button already carrying either class — from the CTA template,
 * from the Additional CSS class field, from before any of this — reads back
 * into these controls correctly, with no second source of truth and nothing to
 * migrate.
 *
 * Plain createElement (no JSX) + window.wp.* globals, matching the rest of the
 * theme's editor scripts.
 *
 * @param {Object} wp The WordPress globals this file is wrapped around.
 */

(function (wp) {
	const { addFilter } = wp.hooks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl } = wp.components;
	const { useSelect } = wp.data;
	const { createHigherOrderComponent } = wp.compose;
	const { __ } = wp.i18n;

	const BLOCK = 'core/button';

	// The class core's own Outline style variation writes, and the one
	// components/_button.scss and theme.json both paint.
	const OUTLINE = 'is-style-outline';

	// Named as Theme Options names them, which is what they are rather than
	// what rank they hold: one is filled and one is not. A page can perfectly
	// well carry a single Outline button and no filled one at all, which
	// "Secondary" quietly denied.
	const VARIANTS = [
		{ label: __('Solid', 'bridge'), value: 'solid' },
		{ label: __('Outline', 'bridge'), value: 'outline' },
	];

	/*
	 * The empty value first, and it is what a button has until somebody says
	 * otherwise: the colour comes from the band the button is in. Choosing one
	 * is an override, so nothing about the site changes on its own.
	 *
	 * What follows it is the palette, read from the editor's own settings
	 * rather than listed here — the slugs are fixed by the theme but the names
	 * and the colours are the client's, and theme.json is where both of those
	 * arrive. See bridge_palette_slugs() and bridge_button_palette_schemes().
	 */
	const FROM_THE_BAND = { label: __('From the band', 'bridge'), value: '' };

	const fillClass = (slug) => `bridge-button--fill-${slug}`;

	/*
	 * The classes the Background control chooses between, plus the four a
	 * button could once be told it was standing on.
	 *
	 * The old ones are still swept up when the control is changed, so a button
	 * that carries one is not left wearing two answers at once. They are not
	 * offered any more — one colour question is enough for one control — but
	 * the stylesheet still paints them, so a page built before this keeps the
	 * button it had until somebody touches it.
	 */
	const LEGACY_GROUNDS = ['default', 'surface', 'accent', 'inverted'].map(
		(ground) => `bridge-button--on-${ground}`
	);

	const classList = (className) =>
		String(className || '')
			.split(/\s+/)
			.filter(Boolean);

	/**
	 * Rewrite one of this panel's classes, leaving every other alone.
	 *
	 * The classes an editor put there keep their place — this owns two words
	 * of that attribute, not all of it.
	 *
	 * @param {string}   className The block's class attribute.
	 * @param {string[]} family    The classes this control chooses between.
	 * @param {string}   next      The one to end up with, or '' for none.
	 * @return {string|undefined} The new value, or undefined when empty.
	 */
	const rewrite = (className, family, next) => {
		const classes = classList(className).filter(
			(name) => !family.includes(name)
		);

		if (next) {
			classes.push(next);
		}

		return classes.length ? classes.join(' ') : undefined;
	};

	const variantOf = (className) =>
		classList(className).includes(OUTLINE) ? 'outline' : 'solid';

	const fillOf = (className) => {
		const found = classList(className).find((name) =>
			name.startsWith('bridge-button--fill-')
		);

		return found ? found.replace('bridge-button--fill-', '') : '';
	};

	/**
	 * The panel.
	 */
	const withPanel = createHigherOrderComponent(
		(BlockEdit) => (props) => {
			if (props.name !== BLOCK) {
				return el(BlockEdit, props);
			}

			const { className } = props.attributes;
			const set = (value) => props.setAttributes({ className: value });

			// The palette as the editor has it, which is theme.json's, which is
			// the client's tokens compiled. Names travel with it, so a renamed
			// Accent reads as its new name here without this file knowing.
			const palette = useSelect(
				(select) =>
					select('core/block-editor').getSettings().colors || [],
				[]
			);

			const backgrounds = [
				FROM_THE_BAND,
				...palette.map((colour) => ({
					label: colour.name || colour.slug,
					value: colour.slug,
				})),
			];

			// Everything this control owns: the fill classes it writes, and the
			// four ground classes it used to, so changing it clears either.
			const family = [
				...palette.map((colour) => fillClass(colour.slug)),
				...LEGACY_GROUNDS,
			];

			return el(
				Fragment,
				null,
				el(BlockEdit, props),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Button', 'bridge'), initialOpen: true },
						el(SelectControl, {
							__nextHasNoMarginBottom: true,
							label: __('Style', 'bridge'),
							help: __(
								'The two button designs Theme Options draws and contrast-audits. Outline is the same button with the fill taken out of it, drawn in the foreground of whichever ground it is standing on.',
								'bridge'
							),
							value: variantOf(className),
							options: VARIANTS,
							onChange: (value) =>
								set(
									rewrite(
										className,
										[OUTLINE],
										value === 'outline' ? OUTLINE : ''
									)
								),
						}),
						el(SelectControl, {
							__nextHasNoMarginBottom: true,
							label: __('Background', 'bridge'),
							value: fillOf(className),
							options: backgrounds,
							onChange: (value) =>
								set(
									rewrite(
										className,
										family,
										value ? fillClass(value) : ''
									)
								),
						})
					)
				)
			);
		},
		'bridgeButtonPanel'
	);

	addFilter('editor.BlockEdit', 'bridge/button-panel', withPanel);
})(window.wp);
