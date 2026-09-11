/**
 * Editor view for `bridge/cards`.
 *
 * Mirrors the hero-slider pattern: plain createElement (no JSX), window.wp.*
 * globals (no @wordpress/* imports).
 *
 * Two halves, because the block is two things. The band and its intro are the
 * editor's own — a heading and an optional summary line, edited in place as
 * inner blocks, the way every other section block on this theme works. The
 * grid is a query, so only the server can know what is in it, and it comes
 * from <ServerSideRender> calling render.php.
 *
 * The `isPreview` attribute is what keeps the two from colliding: it is set
 * only on the preview request, never on the block, and it tells render.php to
 * return the grid without the band and intro that are already on screen here.
 */

const { useBlockProps, useInnerBlocksProps, InspectorControls } =
	window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const {
	PanelBody,
	SelectControl,
	RangeControl,
	__experimentalToggleGroupControl: ToggleGroupControl,
	__experimentalToggleGroupControlOption: ToggleGroupControlOption,
	ToggleControl,
	TextControl,
	FormTokenField,
	Placeholder,
	Spinner,
} = window.wp.components;
const { useSelect } = window.wp.data;
const { __, sprintf } = window.wp.i18n;
const ServerSideRender = window.wp.serverSideRender;

// The mask shape's controls and preview styling, shared with every other band
// that offers them. Its own script handle, named in this block's dependencies
// — see src/editor/band-mask.js.
const { MaskPanel, maskStyle, hasMask } = window.bridgeBandMaskUI || {};

/**
 * The site's excerpt length, printed by functions.php.
 *
 * Only used to tell an editor what "site default" means. The fallback matches
 * the token's own default, so a stale cached script shows a plausible number
 * rather than "undefined words".
 */
const SITE_EXCERPT_LENGTH = window.bridgeCards?.excerptLength ?? 20;

// The site's mask shape, set once in Theme Options. Empty when none is set,
// which is what the inspector reports rather than offering controls that would
// paint nothing.
const BLOCK_NAME = 'bridge/cards';

// The intro only. The cards themselves are queried, not authored, so there is
// nothing else an editor could usefully put inside this block.
const ALLOWED_BLOCKS = ['core/heading', 'core/paragraph'];

const TEMPLATE = [
	['core/heading', { level: 2, placeholder: __('Latest news', 'bridge') }],
	[
		'core/paragraph',
		{ placeholder: __('A line of summary (optional)', 'bridge') },
	],
];

const ORDERBY_OPTIONS = [
	{ label: __('Date', 'bridge'), value: 'date' },
	{ label: __('Title', 'bridge'), value: 'title' },
	{ label: __('Modified date', 'bridge'), value: 'modified_date' },
	{ label: __('Random', 'bridge'), value: 'rand' },
];

const ORDER_OPTIONS = [
	{ label: __('Descending', 'bridge'), value: 'desc' },
	{ label: __('Ascending', 'bridge'), value: 'asc' },
];

const POST_TYPE_FALLBACK = [
	{ label: 'Post', value: 'post' },
	{ label: 'Page', value: 'page' },
];

/**
 * The three card styles, and which of the card-content controls each one has.
 *
 * The table is here rather than spelled out in conditions further down because
 * it is the thing that goes stale: a fourth style is a row, and the panel that
 * draws the controls does not have to be reread to add one. `fields` names the
 * attributes the style actually draws — everything else is hidden rather than
 * disabled, for the reason the source control gives: a control that cannot
 * change anything is still a control someone will try.
 */
const CARD_STYLES = [
	{
		value: 'summary',
		label: __('Summary', 'bridge'),
		help: __(
			'A photograph, a heading and a few lines of the post itself. The right choice for a list of things to read.',
			'bridge'
		),
		fields: ['excerpt', 'readMore'],
	},
	{
		value: 'tile',
		label: __('Tile', 'bridge'),
		help: __(
			'A large heading over a photograph, with a chip in the corner carrying the post’s “distance” field. For places rather than articles.',
			'bridge'
		),
		fields: ['excerpt'],
	},
	{
		value: 'cover',
		label: __('Cover', 'bridge'),
		help: __(
			'The photograph is the whole card, with the title and a link marker over it. A poster rather than a summary — it wants a strong image and a short title.',
			'bridge'
		),
		// No excerpt and no read-more: the only words on a Cover card are the
		// headline, which is what makes it work at small sizes.
		fields: [],
	},
	{
		value: 'team',
		label: __('Team', 'bridge'),
		help: __(
			'A circular photograph, a name, the post’s “subtitle” field as a role line, and a button. For people.',
			'bridge'
		),
		fields: ['button'],
		// Built and styled, but not offered — see `bridge_card_styles()` in
		// inc/tokens.php for the argument. It is drawn for a Team post type
		// that does not exist yet, and offering it now would let an editor make
		// an ordinary post look like a staff profile.
		//
		// Listed rather than deleted so a block already set to it still finds
		// its `fields` and still draws the right inspector; it is only kept out
		// of the picker below.
		hidden: true,
	},
];

// What the Card style control offers. Hidden styles stay in the table above so
// a block already using one keeps working — they are simply not choosable.
const CARD_STYLE_CHOICES = CARD_STYLES.filter((s) => !s.hidden);

const cardStyleHas = (style, field) =>
	(
		CARD_STYLES.find((s) => s.value === style) ?? CARD_STYLES[0]
	).fields.includes(field);

const Edit = ({ attributes, setAttributes }) => {
	const {
		source,
		width,
		overflowStyle,
		cardStyle,
		layout,
		listMedia,
		listMediaRounded,
		listMediaShadow,
		listAlternate,
		listContrast,
		listContrastStrength,
		postType,
		numberOfPosts,
		columns,
		orderBy,
		order,
		categories,
		excerptLength,
		showReadMore,
		readMoreText,
		buttonText,
	} = attributes;

	// Named once. Six places read it — the panel decides what to show, and the
	// band decides what classes to wear.
	const isList = layout === 'list';

	const style = CARD_STYLES.find((s) => s.value === cardStyle)
		? cardStyle
		: 'summary';

	// Public, viewable post types. Falls back to post/page while loading.
	const postTypeOptions = useSelect((select) => {
		const types = select('core').getPostTypes({ per_page: -1 });
		if (!types) {
			return POST_TYPE_FALLBACK;
		}
		return types
			.filter((t) => t.viewable && t.slug !== 'attachment')
			.map((t) => ({ label: t.name, value: t.slug }));
	}, []);

	// Category list — only fetched when we need it (postType === 'post').
	const availableCategories = useSelect(
		(select) =>
			postType === 'post'
				? select('core').getEntityRecords('taxonomy', 'category', {
						per_page: 100,
						_fields: 'id,name',
					})
				: null,
		[postType]
	);

	const categoryNameById = {};
	(availableCategories || []).forEach((c) => {
		categoryNameById[c.id] = c.name;
	});

	const selectedCategoryNames = categories
		.map((id) => categoryNameById[id])
		.filter(Boolean);

	const availableCategoryNames = (availableCategories || []).map(
		(c) => c.name
	);

	const onCategoriesChange = (tokens) => {
		if (!availableCategories) {
			return;
		}
		const ids = tokens
			.map(
				(name) =>
					availableCategories.find((c) => c.name === name)?.id ?? null
			)
			.filter((id) => id !== null);
		setAttributes({ categories: ids });
	};

	// Current post ID — passed to SSR so the block-renderer endpoint
	// validates against a real post and doesn't return rest_post_invalid_id.
	const postId = useSelect(
		(select) => select('core/editor')?.getCurrentPostId() ?? null,
		[]
	);

	// The band. The same classes render.php puts on the front end, so the
	// editor shows the section skin, the background colour and the width the
	// page will actually have.

	const blockProps = useBlockProps({
		style: maskStyle(attributes),
		className: [
			'bridge-cards',
			`bridge-cards--${width}`,
			// `wrap`, whatever the Overflow control was left at, for the reason
			// render.php gives: a list band routinely still carries a grid's
			// overflow style, and the carousel class lays its rows out
			// sideways. The editor happened to be spared that — the carousel
			// rule excludes `.is-editor-preview` — which is exactly why the
			// fault was invisible here and plain on the front end.
			`bridge-cards--${isList ? 'wrap' : overflowStyle}`,
			/*
			 * The list's own settings are deliberately not here.
			 *
			 * They ride on the grid, which render.php serves to the canvas
			 * through <ServerSideRender> — so the preview draws them from the
			 * same lines the front end does and there is nothing to keep in
			 * step. Copying them onto the band, which is what this block used
			 * to do, meant two lists of classes that had to stay identical by
			 * hand; the overflow class below is the one that did not, and a
			 * list rendered sideways on the page while looking right here.
			 */
			'bridge-section',
			'bridge-band',
			'alignfull',
			// The carousel previews as the wrapped layout. A horizontally
			// scrolling row is awkward to author in — clicking a card scrolls
			// it under the toolbar — and the stylesheet keys the swipe
			// behaviour off the absence of this class.
			'is-editor-preview',
			hasMask(attributes) ? 'has-mask' : '',
		]
			.filter(Boolean)
			.join(' '),
	});

	const introProps = useInnerBlocksProps(
		{ className: 'bridge-cards__intro bridge-section__intro' },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			templateLock: false,
		}
	);

	const inspector = el(
		InspectorControls,
		null,
		el(
			PanelBody,
			{ title: __('Query', 'bridge'), initialOpen: true },
			el(SelectControl, {
				label: __('Posts to show', 'bridge'),
				help:
					source === 'main'
						? __(
								'The posts this page is already showing — the archive, the category or the search results. Everything below is decided by the page and by Settings → Reading, so it is not offered here. This is the setting the archive templates use; a band on a page should not.',
								'bridge'
							)
						: __(
								'A query of this block’s own, set below. The right choice anywhere except an archive template.',
								'bridge'
							),
				value: source || 'self',
				options: [
					{
						label: __('Chosen here', 'bridge'),
						value: 'self',
					},
					{
						label: __('This page’s own posts', 'bridge'),
						value: 'main',
					},
				],
				onChange: (value) => setAttributes({ source: value }),
			}),
			// Hidden rather than disabled when the page owns the query: a
			// control that cannot change anything is still a control someone
			// will try, and four of them read as a form that is broken.
			source !== 'main' &&
				el(SelectControl, {
					label: __('Post type', 'bridge'),
					value: postType,
					options: postTypeOptions,
					onChange: (value) =>
						setAttributes({ postType: value, categories: [] }),
				}),
			source !== 'main' &&
				el(RangeControl, {
					label: __('Number of posts', 'bridge'),
					value: numberOfPosts,
					min: 1,
					max: 24,
					onChange: (value) =>
						setAttributes({ numberOfPosts: value }),
				}),
			source !== 'main' &&
				el(SelectControl, {
					label: __('Order by', 'bridge'),
					value: orderBy,
					options: ORDERBY_OPTIONS,
					onChange: (value) => setAttributes({ orderBy: value }),
				}),
			source !== 'main' &&
				el(SelectControl, {
					label: __('Order', 'bridge'),
					value: order,
					options: ORDER_OPTIONS,
					onChange: (value) => setAttributes({ order: value }),
				}),
			source !== 'main' &&
				postType === 'post' &&
				el(FormTokenField, {
					label: __('Filter by categories', 'bridge'),
					value: selectedCategoryNames,
					suggestions: availableCategoryNames,
					onChange: onCategoriesChange,
				})
		),
		el(
			PanelBody,
			{ title: __('Layout', 'bridge') },
			/*
			 * First, and it changes what the rest of the panel is.
			 *
			 * Grid and list are not two card styles — they are two answers to
			 * "how are these arranged", which is a different question from
			 * "what shape is one of them". Keeping them apart is what lets
			 * each mode show only the settings that do something in it: a list
			 * has no columns to set and nothing to the side to swipe to, and a
			 * grid has no picture-beside-words to size. A panel offering
			 * controls that change nothing is worse than a shorter panel —
			 * which is the rule the Card content panel below already follows.
			 */
			el(
				ToggleGroupControl,
				{
					label: __('Arrangement', 'bridge'),
					value: isList ? 'list' : 'grid',
					isBlock: true,
					onChange: (value) =>
						setAttributes({ layout: value || 'grid' }),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				},
				el(ToggleGroupControlOption, {
					value: 'grid',
					label: __('Grid', 'bridge'),
				}),
				el(ToggleGroupControlOption, {
					value: 'list',
					label: __('List', 'bridge'),
				})
			),
			// Shared by both: the band is always the full width of the window,
			// and this is how wide the content runs inside it.
			el(SelectControl, {
				label: __('Width', 'bridge'),
				help: __(
					'How wide the content runs inside the band. The band itself is always the full width of the window.',
					'bridge'
				),
				value: width,
				options: [
					{ label: __('Wide', 'bridge'), value: 'wide' },
					{ label: __('Narrow', 'bridge'), value: 'narrow' },
				],
				onChange: (value) => setAttributes({ width: value }),
			}),

			// ---- Grid ----------------------------------------------------
			!isList &&
				el(SelectControl, {
					label: __('Card style', 'bridge'),
					help: CARD_STYLES.find((s) => s.value === style)?.help,
					value: style,
					options: CARD_STYLE_CHOICES.map(({ value, label }) => ({
						value,
						label,
					})),
					onChange: (value) => setAttributes({ cardStyle: value }),
				}),
			!isList &&
				el(RangeControl, {
					label: __('Columns per row', 'bridge'),
					value: columns,
					min: 1,
					max: 6,
					onChange: (value) => setAttributes({ columns: value }),
				}),
			!isList &&
				el(SelectControl, {
					label: __('Overflow style', 'bridge'),
					help: __(
						'Rows wrap the cards onto as many lines as they need. Carousel keeps them on one line that swipes, with a chevron at each end and a dot per page. The editor previews both as rows.',
						'bridge'
					),
					value: overflowStyle,
					options: [
						{ label: __('Rows', 'bridge'), value: 'wrap' },
						{ label: __('Carousel', 'bridge'), value: 'carousel' },
					],
					onChange: (value) =>
						setAttributes({ overflowStyle: value }),
				}),

			// ---- List ----------------------------------------------------
			// No picker for the arrangement of the row itself while there is
			// one of them. The attribute is there so a second is a new value
			// rather than a migration; the control arrives with it.
			isList &&
				el(SelectControl, {
					label: __('Image size', 'bridge'),
					help: __(
						'How much of the row the photograph takes. Small stays a thumbnail at any screen width; medium and large are shares of the row, so they keep their proportion as the band changes width. Below the two-column breakpoint every row stacks and the picture is full width.',
						'bridge'
					),
					value: listMedia,
					options: [
						{ label: __('Small', 'bridge'), value: 'small' },
						{ label: __('Medium', 'bridge'), value: 'medium' },
						{ label: __('Large (half)', 'bridge'), value: 'large' },
					],
					onChange: (value) =>
						setAttributes({ listMedia: value || 'medium' }),
				}),
			isList &&
				el(ToggleControl, {
					label: __('Round the image corners', 'bridge'),
					help: __(
						'Uses the corner set for cards in Theme Options, so a site with sharp-cornered cards gets sharp-cornered list images too.',
						'bridge'
					),
					checked: !!listMediaRounded,
					onChange: (value) =>
						setAttributes({ listMediaRounded: value }),
					__nextHasNoMarginBottom: true,
				}),
			isList &&
				el(ToggleControl, {
					label: __('Shadow under the image', 'bridge'),
					help: __(
						'The card shadow from Theme Options, on the picture rather than the row. Nothing on a site whose cards are flat.',
						'bridge'
					),
					checked: !!listMediaShadow,
					onChange: (value) =>
						setAttributes({ listMediaShadow: value }),
					__nextHasNoMarginBottom: true,
				}),
			isList &&
				el(ToggleControl, {
					label: __('Alternate the sides', 'bridge'),
					help: __(
						'Every second row draws its picture on the other side. Only where there is room for two columns — stacked, there is nothing to alternate.',
						'bridge'
					),
					checked: !!listAlternate,
					onChange: (value) =>
						setAttributes({ listAlternate: value }),
					__nextHasNoMarginBottom: true,
				}),
			isList &&
				el(ToggleControl, {
					label: __('Shade alternate rows', 'bridge'),
					help: __(
						'Washes every other row in the band’s own foreground, starting with the first — so it darkens a light band and lightens a dark one. The shading runs to both edges of the window; the words stay on the content column, and every row is spaced the same whether it is shaded or not.',
						'bridge'
					),
					checked: !!listContrast,
					onChange: (value) => setAttributes({ listContrast: value }),
					__nextHasNoMarginBottom: true,
				}),
			isList &&
				!!listContrast &&
				el(RangeControl, {
					label: __('Shading strength (percent)', 'bridge'),
					help: __(
						'How much of the band’s own foreground the wash carries. It is a wash rather than a flat colour, so the band shows through it — including its gradient, if it has one.',
						'bridge'
					),
					value: listContrastStrength,
					min: 0,
					max: 50,
					step: 1,
					onChange: (value) =>
						setAttributes({ listContrastStrength: value ?? 12 }),
					__nextHasNoMarginBottom: true,
				})
		),
		// Which controls belong here is the style's decision — see CARD_STYLES.
		// Portrait draws no excerpt and Tile no read-more, and a panel offering
		// settings that change nothing on screen is worse than a shorter panel.
		el(
			PanelBody,
			{ title: __('Card content', 'bridge') },
			cardStyleHas(style, 'excerpt') &&
				el(RangeControl, {
					label: __('Excerpt length (words)', 'bridge'),
					help: excerptLength
						? __(
								'This band only. Reset it to follow the site default set in Theme Options → Cards.',
								'bridge'
							)
						: sprintf(
								/* translators: %d: number of words. */
								__(
									'Following the site default of %d words, set in Theme Options → Cards.',
									'bridge'
								),
								SITE_EXCERPT_LENGTH
							),
					// `undefined` rather than 0 is what draws the slider as
					// unset: 0 would put the handle at the far left and read as
					// "no excerpt" rather than "not decided here".
					value: excerptLength || undefined,
					min: 10,
					max: 100,
					allowReset: true,
					// What Reset writes. 0 is the block saying nothing, which
					// is what render.php reads as "ask the site".
					resetFallbackValue: 0,
					onChange: (value) =>
						setAttributes({ excerptLength: value ?? 0 }),
				}),
			cardStyleHas(style, 'readMore') &&
				el(ToggleControl, {
					label: __('Show read more link', 'bridge'),
					checked: !!showReadMore,
					onChange: (value) => setAttributes({ showReadMore: value }),
				}),
			cardStyleHas(style, 'readMore') &&
				showReadMore &&
				el(TextControl, {
					label: __('Read more text', 'bridge'),
					// Empty uses the theme's own wording, which is the only
					// wording that gets translated.
					placeholder: __('Read more', 'bridge'),
					value: readMoreText,
					onChange: (value) => setAttributes({ readMoreText: value }),
				}),
			cardStyleHas(style, 'button') &&
				el(TextControl, {
					label: __('Button text', 'bridge'),
					help: __(
						'The same on every card. It is the visual cue, not a second link — the card’s name already carries the link.',
						'bridge'
					),
					placeholder: __('View profile', 'bridge'),
					value: buttonText,
					onChange: (value) => setAttributes({ buttonText: value }),
				})
		),
		MaskPanel(attributes, setAttributes)
	);

	const ssrProps = {
		block: BLOCK_NAME,
		// Never saved on the block — this object is only what the preview
		// request carries, and it asks render.php for the grid on its own.
		attributes: { ...attributes, isPreview: true },
		EmptyResponsePlaceholder: () =>
			el(
				Placeholder,
				{ label: __('Cards', 'bridge') },
				__('No posts match the current settings.', 'bridge')
			),
		LoadingResponsePlaceholder: () =>
			el(Placeholder, { label: __('Cards', 'bridge') }, el(Spinner)),
		ErrorResponsePlaceholder: ({ response }) =>
			el(
				Placeholder,
				{ label: __('Cards', 'bridge') },
				response?.errorMsg || __('Could not render preview.', 'bridge')
			),
	};

	if (postId) {
		ssrProps.urlQueryArgs = { post_id: postId };
	}

	return el(
		Fragment,
		null,
		inspector,
		el(
			'section',
			blockProps,
			el(
				'div',
				{ className: 'bridge-cards__inner' },
				el('div', introProps),
				el(ServerSideRender, ssrProps)
			)
		)
	);
};

export default Edit;
