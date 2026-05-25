/**
 * Editor view for `bridge/hero-slider`.
 *
 * Renders InnerBlocks (core/cover slides) plus an InspectorControls panel
 * exposing slider-level settings (effect, autoplay, loop, UI toggles).
 */

const { useBlockProps, InnerBlocks, InspectorControls } = window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, ToggleControl, RangeControl, SelectControl } = window.wp.components;
const { __ } = window.wp.i18n;

const ALLOWED_BLOCKS = ['core/cover'];

const TEMPLATE = [
	[
		'core/cover',
		{
			dimRatio: 50,
			minHeight: 80,
			minHeightUnit: 'vh',
			isUserOverlayColor: true,
			contentPosition: 'center center',
		},
		[
			['core/heading', {
				level: 1,
				textAlign: 'center',
				placeholder: __('Slide title…', 'bridge'),
			}],
			['core/paragraph', {
				align: 'center',
				placeholder: __('Optional subtitle', 'bridge'),
			}],
		],
	],
	[
		'core/cover',
		{
			dimRatio: 50,
			minHeight: 80,
			minHeightUnit: 'vh',
			isUserOverlayColor: true,
			contentPosition: 'center center',
		},
		[
			['core/heading', {
				level: 1,
				textAlign: 'center',
				placeholder: __('Slide title…', 'bridge'),
			}],
		],
	],
];

const Edit = ({ attributes, setAttributes }) => {
	const {
		heightPreset,
		customHeight,
		customHeightUnit,
		effect,
		autoplay,
		autoplayDelay,
		loop,
		showPagination,
		showNavigation,
	} = attributes;

	const blockProps = useBlockProps({
		className: 'bridge-hero-slider is-editor-preview',
	});

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Slider settings', 'bridge'), initialOpen: true },
				el(SelectControl, {
					label: __('Slider height', 'bridge'),
					help: __('Sets the height of the whole slider — individual Cover height settings are ignored.', 'bridge'),
					value: heightPreset,
					options: [
						{ label: __('Full screen', 'bridge'), value: 'full' },
						{ label: __('Tall (80%)', 'bridge'), value: 'tall' },
						{ label: __('Medium (60%)', 'bridge'), value: 'medium' },
						{ label: __('Custom', 'bridge'), value: 'custom' },
					],
					onChange: (value) => setAttributes({ heightPreset: value }),
				}),
				heightPreset === 'custom' && el(RangeControl, {
					label: __('Custom height', 'bridge'),
					value: customHeight,
					onChange: (value) => setAttributes({ customHeight: value }),
					min: 20,
					max: customHeightUnit === 'px' ? 1200 : 200,
					step: 1,
				}),
				heightPreset === 'custom' && el(SelectControl, {
					label: __('Unit', 'bridge'),
					value: customHeightUnit,
					options: [
						{ label: 'vh', value: 'vh' },
						{ label: 'dvh', value: 'dvh' },
						{ label: 'px', value: 'px' },
					],
					onChange: (value) => setAttributes({ customHeightUnit: value }),
				}),
				el(SelectControl, {
					label: __('Transition effect', 'bridge'),
					value: effect,
					options: [
						{ label: __('Fade', 'bridge'), value: 'fade' },
						{ label: __('Slide', 'bridge'), value: 'slide' },
					],
					onChange: (value) => setAttributes({ effect: value }),
				}),
				el(ToggleControl, {
					label: __('Autoplay', 'bridge'),
					checked: !!autoplay,
					onChange: (value) => setAttributes({ autoplay: value }),
				}),
				autoplay && el(RangeControl, {
					label: __('Autoplay delay (seconds)', 'bridge'),
					value: autoplayDelay,
					onChange: (value) => setAttributes({ autoplayDelay: value }),
					min: 3,
					max: 15,
					step: 1,
				}),
				el(ToggleControl, {
					label: __('Loop slides', 'bridge'),
					checked: !!loop,
					onChange: (value) => setAttributes({ loop: value }),
				}),
				el(ToggleControl, {
					label: __('Show pagination dots', 'bridge'),
					checked: !!showPagination,
					onChange: (value) => setAttributes({ showPagination: value }),
				}),
				el(ToggleControl, {
					label: __('Show prev/next arrows', 'bridge'),
					checked: !!showNavigation,
					onChange: (value) => setAttributes({ showNavigation: value }),
				})
			)
		),
		el(
			'div',
			blockProps,
			el(InnerBlocks, {
				allowedBlocks: ALLOWED_BLOCKS,
				template: TEMPLATE,
				templateLock: false,
				orientation: 'vertical',
			})
		)
	);
};

export default Edit;
