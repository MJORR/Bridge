/**
 * Bridge — the Button panel on core/button.
 *
 * Two settings, both of which name something the design system has already
 * drawn rather than inventing anything of their own: which of the two button
 * designs this is, and which of the four grounds it is standing on.
 *
 * A button's colours are not its own. abstracts/_button.scss paints every
 * button from nine `--bridge-button-*` variables, and `button.ground($name)`
 * points those variables at one of the four compiled schemes the token
 * compiler publishes — on-Default, on-Surface, on-Accent, on-Inverted. The
 * bands already do this for the buttons inside them: a Surface band emits the
 * Surface ground, the Accent band the Accent one, anything drawn on dark the
 * Inverted one. Custom properties inherit, so a button that lands in one of
 * those needs to know nothing about where it is.
 *
 * What it cannot know is where it is *standing*, as opposed to what block it
 * is nested in — a button over a photograph, in a hero, or in a plain band
 * that happens to sit on a dark image. That is the one case the cascade cannot
 * answer, and this is the control for it.
 *
 * Neither control chooses a colour and neither can: the four grounds are drawn
 * against the palette in PHP, contrast-checked there, and change with the
 * tokens. These say which of the finished answers applies, and nothing else —
 * which is what keeps them settings rather than another paint box on a block
 * the theme deliberately took the paint box off (see button-lock.js).
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
 * Outline one on the Inverted ground arrives as the same thing rather than as
 * a default that has to be set again.
 *
 * The ground was an attribute of its own to begin with, and that list is the
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
	 * otherwise: the ground comes from the band it is in. Choosing one of the
	 * four is an override, so nothing about the site changes on its own.
	 *
	 * The four labels are bridge_button_grounds()' own, in the order that
	 * function lists them.
	 */
	const GROUNDS = [
		{ label: __('From the band', 'bridge'), value: '' },
		{ label: __('Default', 'bridge'), value: 'default' },
		{ label: __('Surface', 'bridge'), value: 'surface' },
		{ label: __('Accent', 'bridge'), value: 'accent' },
		{ label: __('Inverted', 'bridge'), value: 'inverted' },
	];

	const groundClass = (ground) => `bridge-button--on-${ground}`;

	// The four the Ground control chooses between. Its own list rather than
	// "everything this panel owns", because the two controls have to be able
	// to read past each other: an Outline button on the Inverted ground
	// carries both classes, and a search that stopped at the first one it
	// recognised reported that button as having no ground at all.
	const GROUND_CLASSES = GROUNDS.filter((entry) => entry.value).map((entry) =>
		groundClass(entry.value)
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

	const groundOf = (className) => {
		const found = classList(className).find((name) =>
			GROUND_CLASSES.includes(name)
		);

		return found ? found.replace('bridge-button--on-', '') : '';
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
							label: __('Ground', 'bridge'),
							help: __(
								'Which background this button is standing on. Leave it on “From the band” unless the button sits on an image or a colour the band does not know about.',
								'bridge'
							),
							value: groundOf(className),
							options: GROUNDS,
							onChange: (value) =>
								set(
									rewrite(
										className,
										GROUND_CLASSES,
										value ? groundClass(value) : ''
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
