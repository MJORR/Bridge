/**
 * Editor view for `bridge/hero-slider`.
 *
 * Renders the slides (core/cover) plus an InspectorControls panel exposing
 * slider-level settings (width, height, effect, autoplay, loop, UI toggles).
 *
 * The slides are placed with `useInnerBlocksProps` rather than by rendering an
 * `<InnerBlocks />` element, because the two produce different trees and the
 * stylesheet has to match one of them. The element form wraps the children in
 * `.block-editor-inner-blocks > .block-editor-block-list__layout`, which put
 * two divs between this block and its Covers: the canvas then matched none of
 * the rules written as `.bridge-hero-slider > .wp-block-cover` — not the
 * narrow column the slide content sits in, and not the stacked preview either,
 * since the Covers were not the flex children they were being laid out as.
 * The hook applies the same behaviour to this element, so a Cover is a direct
 * child here exactly as it is inside the track render.php prints.
 */

const {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	BlockControls,
	MediaPlaceholder,
	MediaUpload,
	MediaUploadCheck,
} = window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const {
	PanelBody,
	ToggleControl,
	RangeControl,
	SelectControl,
	ToolbarGroup,
	ToolbarButton,
} = window.wp.components;
const { useSelect, useDispatch } = window.wp.data;
const { createBlock } = window.wp.blocks;
const { __ } = window.wp.i18n;

/**
 * The chosen height, as a CSS length, for the editor canvas.
 *
 * bridge_hero_metrics() in inc/hero-blocks.php is authoritative — it is what
 * the front end renders — and this is the preview's copy of the same four
 * presets. The two lists have to agree. It lives here rather than in a shared
 * module because every editor bundle is wrapped as a self-contained IIFE, so a
 * module imported by two entries becomes a chunk the wrapper cannot import.
 *
 * The header allowance is deliberately not repeated: the canvas is not the
 * window, and the stylesheet zeroes the inset for the preview for that reason.
 *
 * @param {string} preset One of full, tall, medium, custom.
 * @param {number} size   Custom height, when the preset is custom.
 * @param {string} unit   Custom unit, when the preset is custom.
 * @return {string} A CSS length.
 */
const heroHeight = (preset, size, unit) => {
	if (preset === 'tall') {
		return '80dvh';
	}

	if (preset === 'medium') {
		return '60dvh';
	}

	if (preset === 'custom') {
		const safeUnit = ['vh', 'dvh', 'px'].includes(unit) ? unit : 'vh';
		const safeSize = Math.max(20, Math.min(4000, Number(size) || 80));

		return `${safeSize}${safeUnit}`;
	}

	return '100dvh';
};

const ALLOWED_BLOCKS = ['core/cover'];

/**
 * Centred, as the block editor now records it.
 *
 * Not `textAlign` on the heading and `align` on the paragraph, which is where
 * both used to live: WordPress moved text alignment into the typography
 * *support*, and neither block declares an attribute of either name any more —
 * so both were being handed a key nothing reads, silently, and a slide came out
 * with a centred headline and a subtitle sitting wherever the cascade left it.
 *
 * `style.typography.textAlign` is the support's own shape, so the value is the
 * one an editor sees in the alignment control and can change from there.
 */
const CENTRED = { style: { typography: { textAlign: 'center' } } };

/**
 * A slide, built around a picture.
 *
 * ---- Why a slider does not seed its slides any more ------------------------
 *
 * It used to start as two Covers, each holding a heading. Both halves of that
 * were wrong.
 *
 * Two, because a slider "ought to" have more than one — so every new slider
 * opened as two identical grey panels reading "Slide title…", and an editor who
 * wanted one hero had to work out that the second was furniture and delete it.
 * The front end has always drawn a single slide as an ordinary cover, with no
 * dots, no arrows and no runtime; the editor was the only place a lone slide
 * looked like a mistake.
 *
 * And seeded with a heading, which is the subtler one. `core/cover` offers its
 * media placeholder — the drag-and-drop panel with Upload and Media Library on
 * it — only when the block has no background *and no inner blocks*:
 *
 *     if ( ! useFeaturedImage && ! hasInnerBlocks && ! hasBackground )
 *
 * A seeded heading is an inner block, so every slide this theme created was
 * born past that test. The placeholder never appeared, and the only way left to
 * put a picture in a slide was the unlabelled media button in the block
 * toolbar. "Where do I add the image?" was the correct response to it.
 *
 * So the slider starts empty and asks for a picture, which is the one thing
 * every hero needs. Choosing one builds the slide around it: the image, the
 * dim, and a heading ready to type into.
 *
 * ---- The heading level -----------------------------------------------------
 *
 * The first heading is an h1 and every one after it an h2, and that is not a
 * detail of the design. A slider is one band of one page, so it has one
 * headline; seeding every slide at level 1 shipped a three-slide hero with
 * three h1s — plus a fourth from `bridge/page-title` on any page showing its
 * title. A screen-reader user stepping through headings met four page titles in
 * a row, and a page with four top-level headings has, to anything reading it,
 * no headline at all.
 *
 * A seed rather than a rule: an editor can set any level they want afterwards.
 *
 * @param {Object}  image   A media object from the picker.
 * @param {boolean} isFirst Whether this is the slider's first slide.
 * @return {Object} A `core/cover` block, ready to insert.
 */
export const slide = (image, isFirst) =>
	createBlock(
		'core/cover',
		{
			// `sizes.large` rather than the original: a hero built from 4000px
			// files makes the editor crawl, and the front end asks for its own
			// size anyway. The same choice the gallery block makes.
			url: image?.sizes?.large?.url || image?.url,
			id: image?.id,
			alt: image?.alt || '',
			backgroundType: 'image',
			// A scrim, so white type on an unknown photograph is legible from
			// the first paint rather than after someone notices it is not.
			dimRatio: 50,
			minHeight: 80,
			minHeightUnit: 'vh',
			contentPosition: 'center center',
		},
		/*
		 * A headline and a line under it, which is what a hero is.
		 *
		 * The subtitle was in the old seeded template and went missing when
		 * this function replaced it — leaving a slide with one field and no
		 * obvious way to add a second. Seeding inner blocks is safe here in a
		 * way it was not before: the picture is set at the same moment, so the
		 * Cover already has a background and its media placeholder — the thing
		 * inner blocks used to suppress — is not wanted anyway.
		 *
		 * Both are placeholders, not content: an unfilled paragraph serialises
		 * to nothing and the published slide is just the headline. Anything
		 * further — buttons, a second line — is added under them with the
		 * appender inside the slide, which is core's own and needs nothing from
		 * this theme.
		 */
		[
			createBlock('core/heading', {
				level: isFirst ? 1 : 2,
				placeholder: __('Slide title…', 'bridge'),
				...CENTRED,
			}),
			createBlock('core/paragraph', {
				placeholder: __('Optional subtitle', 'bridge'),
				...CENTRED,
			}),
		]
	);

const Edit = ({ attributes, setAttributes, clientId }) => {
	const {
		align,
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

	const { insertBlock } = useDispatch('core/block-editor');

	/*
	 * How many slides there are, which decides what the block shows.
	 *
	 * None and it is a request for a picture; one or more and it is the slides
	 * themselves. The count is read rather than kept, so a slide deleted
	 * through the list view puts the placeholder back without this block being
	 * told.
	 */
	const slideCount = useSelect(
		(select) =>
			select('core/block-editor').getBlock(clientId)?.innerBlocks
				.length ?? 0,
		[clientId]
	);

	/**
	 * Turn the chosen image into a slide, after the ones already there.
	 *
	 * Only the first slide of an empty slider takes the h1 — see `slide()`.
	 *
	 * The argument is still unwrapped defensively: `MediaUpload` hands back an
	 * array whenever it was opened in multi-select, and a stray `multiple` on
	 * either picker should cost the extra images rather than crash on a slide
	 * built from an array.
	 *
	 * @param {Object|Object[]} media The chosen image.
	 */
	const addSlide = (media) => {
		const image = Array.isArray(media) ? media[0] : media;

		if (!image) {
			return;
		}

		insertBlock(slide(image, slideCount === 0), undefined, clientId);
	};

	// The chosen height, published for the preview. Without it the canvas
	// always drew the 100dvh fallback and the height control looked broken.
	const blockProps = useBlockProps({
		className: 'bridge-hero-slider is-editor-preview',
		style: {
			'--bridge-slider-height': heroHeight(
				heightPreset,
				customHeight,
				customHeightUnit
			),
		},
	});

	/*
	 * No `template`. A slider starts empty and is filled from the placeholder
	 * below — see the note on `slide()` for why seeding Covers was what hid
	 * core's own media picker.
	 *
	 * No appender at all. An empty slider is already asking for its first slide
	 * through the placeholder, and every slide after that is added from the
	 * "Add slide" button on a slide's own toolbar — see slide-tools.js. A
	 * second control in the canvas was a second way to do one thing, and the
	 * one in the canvas was the one nobody had asked for.
	 */
	const innerBlocksProps = useInnerBlocksProps(blockProps, {
		allowedBlocks: ALLOWED_BLOCKS,
		templateLock: false,
		orientation: 'vertical',
		renderAppender: false,
	});

	/*
	 * The block list is pulled out of the props so the placeholder can be
	 * rendered beside it — and it must be rendered *as well*, never instead.
	 *
	 * A block's "block list settings" — the record of what it allows inside it
	 * — are registered by `useNestedSettingsUpdate`, which core calls from
	 * inside the inner-blocks *component*, not from the hook above. Render the
	 * placeholder in place of that component and the settings are never
	 * written, and then this, in canInsertBlockType:
	 *
	 *     if ( rootClientId && parentBlockListSettings === undefined )
	 *         return false;
	 *
	 * quietly answers no. `insertBlock` filters the new slide out and choosing
	 * an image appears to do nothing at all — no error, no slide.
	 */
	const { children: slides, ...sliderProps } = innerBlocksProps;

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Slider settings', 'bridge'), initialOpen: true },
				// Writes the block's own `align` attribute rather than a
				// second one of ours, so this and the alignment control in
				// the block toolbar are the same switch under two labels
				// instead of two switches that can disagree. Cleared to '',
				// not undefined — undefined would fall back to the "full"
				// default declared in block.json and the toggle would spring
				// back on.
				el(ToggleControl, {
					label: __('Full window width', 'bridge'),
					help: __(
						'On, the slider spans the whole window. Off, it stays inside the page content width.',
						'bridge'
					),
					checked: align === 'full',
					onChange: (value) =>
						setAttributes({ align: value ? 'full' : '' }),
					__nextHasNoMarginBottom: true,
				}),
				el(SelectControl, {
					label: __('Slider height', 'bridge'),
					help: __(
						'Sets the height of the whole slider — individual Cover height settings are ignored. Screen heights allow for the header, so Full screen fills the window below it and slide content stays centred in what is visible.',
						'bridge'
					),
					value: heightPreset,
					options: [
						{ label: __('Full screen', 'bridge'), value: 'full' },
						{ label: __('Tall (80%)', 'bridge'), value: 'tall' },
						{
							label: __('Medium (60%)', 'bridge'),
							value: 'medium',
						},
						{ label: __('Custom', 'bridge'), value: 'custom' },
					],
					onChange: (value) => setAttributes({ heightPreset: value }),
				}),
				heightPreset === 'custom' &&
					el(RangeControl, {
						label: __('Custom height', 'bridge'),
						value: customHeight,
						onChange: (value) =>
							setAttributes({ customHeight: value }),
						min: 20,
						max: customHeightUnit === 'px' ? 1200 : 200,
						step: 1,
					}),
				heightPreset === 'custom' &&
					el(SelectControl, {
						label: __('Unit', 'bridge'),
						value: customHeightUnit,
						options: [
							{ label: 'vh', value: 'vh' },
							{ label: 'dvh', value: 'dvh' },
							{ label: 'px', value: 'px' },
						],
						onChange: (value) =>
							setAttributes({ customHeightUnit: value }),
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
				autoplay &&
					el(RangeControl, {
						label: __('Autoplay delay (seconds)', 'bridge'),
						value: autoplayDelay,
						onChange: (value) =>
							setAttributes({ autoplayDelay: value }),
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
					onChange: (value) =>
						setAttributes({ showPagination: value }),
				}),
				el(ToggleControl, {
					label: __('Show prev/next arrows', 'bridge'),
					checked: !!showNavigation,
					onChange: (value) =>
						setAttributes({ showNavigation: value }),
				})
			)
		),
		/*
		 * Adding the second slide has to be as obvious as adding the first.
		 *
		 * The inner-block appender would insert a bare Cover, which lands the
		 * editor back in core's own placeholder one level down — workable, but
		 * two different ways to do one thing. This is the same picker the empty
		 * state offers, in the place an editor looks for actions on a block
		 * they have already selected.
		 */
		slideCount > 0 &&
			el(
				BlockControls,
				null,
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
								el(ToolbarButton, {
									icon: 'plus-alt2',
									label: __('Add slide', 'bridge'),
									onClick: open,
								}),
						})
					)
				)
			),
		/*
		 * An empty slider asks for the one thing every hero needs.
		 *
		 * `MediaPlaceholder` is core's own — the same drag-and-drop panel a
		 * Cover or an Image shows — so it is a control an editor has already
		 * met, and it accepts a drop as readily as a click. It replaces the
		 * inner blocks rather than sitting above them: with nothing to show,
		 * the appender is an empty grey band and the placeholder is the whole
		 * of what the block has to say.
		 */
		el(
			'div',
			sliderProps,
			slides,
			slideCount === 0 &&
				el(MediaPlaceholder, {
					icon: 'format-gallery',
					labels: {
						title: __('Hero Slider', 'bridge'),
						instructions: __(
							'Choose an image for the slide.',
							'bridge'
						),
					},
					/*
					 * One image. `multiple` opens the media library in
					 * multi-select — the gallery mode, with tick boxes and
					 * an "Add to gallery" button — which is a different
					 * task from the one being asked for here. Adding a
					 * second slide is a second, deliberate act; it should
					 * not be the shape of the first.
					 */
					multiple: false,
					allowedTypes: ['image'],
					onSelect: addSlide,
				})
		)
	);
};

export default Edit;
