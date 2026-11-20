/**
 * Editor view for `bridge/alternating-footer`.
 *
 * Never inserted by hand: the Row's "Show footer" switch adds and removes it,
 * and it is locked in place so it cannot be dragged into the middle of the
 * copy. What goes in it is a list — each item one of the short facts set in a
 * line along the bottom of the row — and an optional star rating in front.
 */

const { useBlockProps, useInnerBlocksProps, InspectorControls } =
	window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, RangeControl } = window.wp.components;
const { __, sprintf } = window.wp.i18n;

const ALLOWED_BLOCKS = ['core/list', 'core/paragraph'];

const TEMPLATE = [
	[
		'core/list',
		{},
		[
			['core/list-item', { placeholder: __('A short fact', 'bridge') }],
			['core/list-item', { placeholder: __('Another', 'bridge') }],
			['core/list-item', { placeholder: __('And another', 'bridge') }],
		],
	],
];

const Edit = ({ attributes, setAttributes }) => {
	const { rating } = attributes;

	const blockProps = useBlockProps({
		className: 'bridge-alternating__footer',
	});

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'bridge-alternating__footer-body' },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			templateLock: false,
		}
	);

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Rating', 'bridge'), initialOpen: true },
				el(RangeControl, {
					label: __('Stars', 'bridge'),
					help: __(
						'Drawn in front of the first item. Zero hides them.',
						'bridge'
					),
					value: rating,
					min: 0,
					max: 5,
					onChange: (value) => setAttributes({ rating: value || 0 }),
					__nextHasNoMarginBottom: true,
				})
			)
		),
		el(
			'div',
			blockProps,
			el(
				'div',
				{ className: 'bridge-alternating__footer-inner' },
				rating > 0 &&
					el(
						'span',
						{
							className: 'bridge-alternating__rating',
							role: 'img',
							'aria-label': sprintf(
								/* translators: %d: rating out of five. */
								__('Rated %d out of 5', 'bridge'),
								rating
							),
						},
						[1, 2, 3, 4, 5].map((i) =>
							el(
								'span',
								{
									key: i,
									className:
										'bridge-alternating__star' +
										(i <= rating ? ' is-filled' : ''),
									'aria-hidden': true,
								},
								'★'
							)
						)
					),
				el('div', innerBlocksProps)
			)
		)
	);
};

export default Edit;
