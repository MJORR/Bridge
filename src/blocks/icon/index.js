/**
 * Bridge — Icon block registration.
 *
 * The picker is populated from window.bridgeIcons, which PHP prints from the
 * compiled library. There is no free-text field and no upload: an editor can
 * place any icon the theme ships and cannot introduce one that it doesn't.
 *
 * Plain createElement (no JSX) + window.wp.* globals, matching the other
 * block scripts in this theme.
 */

import '../../scss/editor/_icon-picker.scss';

(function (wp) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment, useState } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, TextControl, SearchControl, Button } =
		wp.components;
	const { __ } = wp.i18n;

	const LIBRARY = window.bridgeIcons || {};
	const NAMES = Object.keys(LIBRARY);

	const SIZES = {
		small: '1em',
		medium: '1.5em',
		large: '2.25em',
	};

	/**
	 * Render an icon from the library.
	 *
	 * dangerouslySetInnerHTML is the only way to inject raw SVG path data into
	 * React, and it is safe here: the markup is compiled from files in the
	 * theme by the build, never from anything a user typed.
	 *
	 * @param {string} name Icon name, as compiled into dist/icons.json.
	 * @param {string} size Any CSS length; the SVG is square.
	 */
	const Icon = (name, size) =>
		el('svg', {
			viewBox: '0 0 24 24',
			width: size,
			height: size,
			fill: 'none',
			stroke: 'currentColor',
			strokeWidth: 1.6,
			strokeLinecap: 'round',
			strokeLinejoin: 'round',
			'aria-hidden': true,
			focusable: false,
			dangerouslySetInnerHTML: { __html: LIBRARY[name] || '' },
		});

	/**
	 * Grid of every available icon, filtered by a search box.
	 *
	 * @param {Object} props Component props.
	 */
	const IconGrid = ({ value, onChange }) => {
		const [query, setQuery] = useState('');
		const term = query.trim().toLowerCase();
		const matches = term ? NAMES.filter((n) => n.includes(term)) : NAMES;

		return el(
			Fragment,
			null,
			el(SearchControl, {
				label: __('Search icons', 'bridge'),
				value: query,
				onChange: setQuery,
				__nextHasNoMarginBottom: true,
			}),
			matches.length
				? el(
						'div',
						{ className: 'bridge-icon-grid' },
						matches.map((name) =>
							el(
								Button,
								{
									key: name,
									className:
										'bridge-icon-grid__item' +
										(name === value ? ' is-selected' : ''),
									onClick: () => onChange(name),
									label: name,
									showTooltip: true,
								},
								Icon(name, '1.5em')
							)
						)
					)
				: el(
						'p',
						{ className: 'bridge-icon-grid__empty' },
						__('No icons match.', 'bridge')
					)
		);
	};

	/**
	 * A capitalised component rather than an inline `edit` arrow: hooks are
	 * only legal inside something React recognises as a component, and the
	 * linter enforces that by name.
	 *
	 * @param {Object} props Component props.
	 */
	const Edit = ({ attributes, setAttributes }) => {
		const { name, size, label } = attributes;
		const blockProps = useBlockProps({
			className: 'bridge-icon-block',
		});

		return el(
			Fragment,
			null,
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __('Icon', 'bridge') },
					el(IconGrid, {
						value: name,
						onChange: (next) => setAttributes({ name: next }),
					})
				),
				el(
					PanelBody,
					{ title: __('Settings', 'bridge'), initialOpen: false },
					el(SelectControl, {
						label: __('Size', 'bridge'),
						value: size,
						options: [
							{
								label: __('Small', 'bridge'),
								value: 'small',
							},
							{
								label: __('Medium', 'bridge'),
								value: 'medium',
							},
							{
								label: __('Large', 'bridge'),
								value: 'large',
							},
						],
						onChange: (next) => setAttributes({ size: next }),
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					}),
					el(TextControl, {
						label: __('Accessible label', 'bridge'),
						value: label,
						onChange: (next) => setAttributes({ label: next }),
						help: __(
							'Leave empty when the icon sits next to text that already says the same thing — a screen reader should not hear it twice.',
							'bridge'
						),
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					})
				)
			),
			el('span', blockProps, Icon(name, SIZES[size] || SIZES.medium))
		);
	};

	registerBlockType('bridge/icon', {
		edit: Edit,

		// Dynamic block: render.php produces the markup, so the stroke weight
		// tracks the design system instead of being frozen into post content.
		save: () => null,
	});
})(window.wp);
