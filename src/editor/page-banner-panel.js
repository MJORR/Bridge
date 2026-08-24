/**
 * Bridge — "Title Banner" document panel.
 *
 * Adds a panel to the Page editor sidebar that controls, per page, how the
 * title is displayed by the default Page template's `bridge/page-title`
 * block. The choices are saved to post meta:
 *
 *   bridge_banner_style  hidden | none | contained | fullwidth
 *   bridge_title_align   left | center
 *   bridge_banner_color  theme-palette colour slug
 *
 * render.php reads the same meta on the frontend — this panel is the only
 * place the values are set. Plain createElement (no JSX) + window.wp.*
 * globals, matching the rest of the theme's editor scripts.
 *
 * @param {Object} wp The WordPress globals this file is wrapped around.
 */

(function (wp) {
	const { registerPlugin } = wp.plugins;
	const { createElement: el, Fragment } = wp.element;
	const { useSelect } = wp.data;
	const { useEntityProp } = wp.coreData;
	const { SelectControl, BaseControl, ToggleControl } = wp.components;
	const VStack = wp.components.__experimentalVStack || Fragment;
	const { ColorPalette } = wp.blockEditor;
	const { __ } = wp.i18n;

	// PluginDocumentSettingPanel lives in @wordpress/editor on current WP and
	// in @wordpress/edit-post on older releases — support whichever is present.
	const PluginDocumentSettingPanel =
		(wp.editor && wp.editor.PluginDocumentSettingPanel) ||
		(wp.editPost && wp.editPost.PluginDocumentSettingPanel);

	if (!registerPlugin || !PluginDocumentSettingPanel) {
		return;
	}

	// Ordered by how much of the page the title claims, least first. "No
	// title" hides the heading only — the page keeps its name everywhere it
	// is referred to rather than displayed.
	const STYLE_OPTIONS = [
		{ label: __('No title', 'bridge'), value: 'hidden' },
		{ label: __('Standard — no banner', 'bridge'), value: 'none' },
		{
			label: __('Banner — inside container', 'bridge'),
			value: 'contained',
		},
		{ label: __('Banner — full width', 'bridge'), value: 'fullwidth' },
	];

	const Panel = () => {
		const postType = useSelect(
			(select) => select('core/editor')?.getCurrentPostType(),
			[]
		);

		// Theme palette (custom colours are disabled in theme.json, so this is
		// the "main theme color set" the banner background is chosen from).
		const themeColors = useSelect((select) => {
			const settings = select('core/block-editor').getSettings();
			return settings.colors || settings.colorPalette || [];
		}, []);

		const [meta, setMeta] = useEntityProp('postType', postType, 'meta');

		// Only pages use the default Page template's banner.
		if (postType !== 'page') {
			return null;
		}

		const style = meta?.bridge_banner_style || 'none';
		const align = meta?.bridge_title_align || 'left';
		const colorSlug = meta?.bridge_banner_color || '';

		const update = (next) => setMeta({ ...meta, ...next });

		const slugToHex = (slug) =>
			themeColors.find((c) => c.slug === slug)?.color || undefined;

		const onColorChange = (hex) => {
			const match = themeColors.find((c) => c.color === hex);
			update({ bridge_banner_color: match ? match.slug : '' });
		};

		// Alignment and background only mean something once there is a banner
		// to align and colour. Named positively rather than as "not none", so
		// a fourth style that draws no banner — as `hidden` does — does not
		// have to remember to opt out of controls that cannot apply to it.
		const showBannerControls =
			style === 'contained' || style === 'fullwidth';

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'bridge-title-banner',
				title: __('Title Banner', 'bridge'),
				className: 'bridge-title-banner-panel',
			},
			el(
				VStack,
				{ spacing: 4 },
				el(SelectControl, {
					label: __('Banner style', 'bridge'),
					value: style,
					options: STYLE_OPTIONS,
					onChange: (value) => update({ bridge_banner_style: value }),
					__nextHasNoMarginBottom: true,
					help: __(
						'Full width spans the whole window; contained keeps the banner inside the page width. No title removes the heading and the space above it, so the page opens straight into its first block — it keeps its name in menus, the browser tab and search results, which you can edit from the Pages list.',
						'bridge'
					),
				}),
				showBannerControls &&
					el(ToggleControl, {
						label: __('Centre the title', 'bridge'),
						checked: align === 'center',
						onChange: (checked) =>
							update({
								bridge_title_align: checked ? 'center' : 'left',
							}),
						__nextHasNoMarginBottom: true,
					}),
				showBannerControls &&
					el(
						BaseControl,
						{
							label: __('Banner background', 'bridge'),
							__nextHasNoMarginBottom: true,
						},
						el(ColorPalette, {
							colors: themeColors,
							value: slugToHex(colorSlug),
							onChange: onColorChange,
							disableCustomColors: true,
							clearable: true,
						})
					)
			)
		);
	};

	registerPlugin('bridge-title-banner-panel', { render: Panel });
})(window.wp);
