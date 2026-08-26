/**
 * Editor view for `bridge/section`.
 */

const { useBlockProps, useInnerBlocksProps, InspectorControls } =
	window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, SelectControl } = window.wp.components;
const { __ } = window.wp.i18n;

// Anything. The band is a container, and the whole point of it is that an
// editor can build inside it without reaching for a group and its spacing
// controls.
const TEMPLATE = [
	['core/paragraph', { placeholder: __('Write something…', 'bridge') }],
];

const Edit = ({ attributes, setAttributes }) => {
	const { width } = attributes;

	const blockProps = useBlockProps({
		className: [
			`bridge-section--${width}`,
			'bridge-section',
			'bridge-band',
			'alignfull',
			// The canvas is not the window, so the band keeps its colour and
			// its column and drops the full-bleed breakout.
			'is-editor-preview',
		].join(' '),
	});

	const innerProps = useInnerBlocksProps(
		{ className: 'bridge-section__inner' },
		{ templateLock: false, template: TEMPLATE }
	);

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Layout', 'bridge'), initialOpen: true },
				el(SelectControl, {
					label: __('Width', 'bridge'),
					help: __(
						'How wide the content runs inside the band. The band itself is always the full width of the window, so its background colour reaches both edges of the page.',
						'bridge'
					),
					value: width,
					options: [
						{ label: __('Narrow', 'bridge'), value: 'narrow' },
						{ label: __('Wide', 'bridge'), value: 'wide' },
					],
					onChange: (value) => setAttributes({ width: value }),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				})
			)
		),
		el('section', blockProps, el('div', innerProps))
	);
};

export default Edit;
