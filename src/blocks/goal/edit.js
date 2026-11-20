/**
 * Editor view for `bridge/goal`.
 *
 * Three fields, edited in place: the number, its unit and the label under
 * them. Nothing here is an inspector control — a figure is three short strings
 * and they are all visible, so there is nothing worth putting in a sidebar.
 */

const { useBlockProps, RichText, InspectorControls } = window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, SelectControl } = window.wp.components;
const { __ } = window.wp.i18n;

/**
 * Whether the unit is written in front of the number rather than after it.
 *
 * `£1,200`, but `120 miles`. Which side a unit belongs on is a property of the
 * unit and not a decision an editor should have to make again for every
 * figure, so it is read from the character rather than asked for as a fourth
 * field: `\p{Sc}` is Unicode's currency-symbol category, which is exactly the
 * set of units that lead. Everything else — miles, kg, %, hours — follows.
 *
 * The same test runs in render.php. Kept in step by being one line in each
 * rather than a shared value the two would have to agree about.
 *
 * @param {string} unit The unit as typed.
 * @return {boolean} True when the unit leads.
 */
const leads = (unit) => /^\p{Sc}/u.test(unit || '');

const Edit = ({ attributes, setAttributes }) => {
	const { number, unit, label, unitPosition = 'auto' } = attributes;

	// "auto" is the old rule: currency leads, everything else follows. The
	// other two are for a unit the rule gets wrong, like a ★ before a rating.
	const before =
		'auto' === unitPosition ? leads(unit) : 'before' === unitPosition;
	const spaced = before && 'before' === unitPosition;

	const blockProps = useBlockProps({ className: 'bridge-goal' });

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Unit', 'bridge'), initialOpen: true },
				el(SelectControl, {
					label: __('Unit position', 'bridge'),
					help: __(
						'Automatic puts currency symbols before the number and everything else after it.',
						'bridge'
					),
					value: unitPosition,
					options: [
						{ label: __('Automatic', 'bridge'), value: 'auto' },
						{
							label: __('Before the number', 'bridge'),
							value: 'before',
						},
						{
							label: __('After the number', 'bridge'),
							value: 'after',
						},
					],
					onChange: (value) => setAttributes({ unitPosition: value }),
					__nextHasNoMarginBottom: true,
				})
			)
		),
		el(
			'li',
			blockProps,
			el(
				'p',
				{ className: 'bridge-goal__value' },
				// Always number-then-unit in the markup, whichever side the unit
				// is drawn on: the stylesheet moves a leading unit with `order`.
				// Reordering these two in the DOM instead would tear the caret out
				// of the field the moment a `£` was typed into it, which is the
				// keystroke that flips the order.
				el(RichText, {
					tagName: 'span',
					className: 'bridge-goal__number',
					value: number,
					allowedFormats: [],
					disableLineBreaks: true,
					onChange: (value) => setAttributes({ number: value }),
					placeholder: __('120', 'bridge'),
				}),
				el(RichText, {
					tagName: 'span',
					className:
						'bridge-goal__unit' +
						(before ? ' is-prefix' : '') +
						(spaced ? ' is-spaced' : ''),
					value: unit,
					allowedFormats: [],
					disableLineBreaks: true,
					onChange: (value) => setAttributes({ unit: value }),
					placeholder: __('miles', 'bridge'),
				})
			),
			el(RichText, {
				tagName: 'p',
				className: 'bridge-goal__label',
				value: label,
				allowedFormats: [],
				disableLineBreaks: true,
				onChange: (value) => setAttributes({ label: value }),
				placeholder: __('Distance Run', 'bridge'),
			})
		)
	);
};

export default Edit;
