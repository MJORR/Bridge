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
	ToggleControl,
	TextControl,
	FormTokenField,
	Placeholder,
	Spinner,
} = window.wp.components;
const { useSelect } = window.wp.data;
const { __ } = window.wp.i18n;
const ServerSideRender = window.wp.serverSideRender;

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

const Edit = ({ attributes, setAttributes }) => {
	const {
		width,
		overflowStyle,
		postType,
		numberOfPosts,
		columns,
		orderBy,
		order,
		categories,
		excerptLength,
		showReadMore,
		readMoreText,
	} = attributes;

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
		className: [
			'bridge-cards',
			`bridge-cards--${width}`,
			`bridge-cards--${overflowStyle}`,
			'bridge-section',
			'bridge-band',
			'alignfull',
			// The carousel previews as the wrapped layout. A horizontally
			// scrolling row is awkward to author in — clicking a card scrolls
			// it under the toolbar — and the stylesheet keys the swipe
			// behaviour off the absence of this class.
			'is-editor-preview',
		].join(' '),
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
				label: __('Post type', 'bridge'),
				value: postType,
				options: postTypeOptions,
				onChange: (value) =>
					setAttributes({ postType: value, categories: [] }),
			}),
			el(RangeControl, {
				label: __('Number of posts', 'bridge'),
				value: numberOfPosts,
				min: 1,
				max: 24,
				onChange: (value) => setAttributes({ numberOfPosts: value }),
			}),
			el(SelectControl, {
				label: __('Order by', 'bridge'),
				value: orderBy,
				options: ORDERBY_OPTIONS,
				onChange: (value) => setAttributes({ orderBy: value }),
			}),
			el(SelectControl, {
				label: __('Order', 'bridge'),
				value: order,
				options: ORDER_OPTIONS,
				onChange: (value) => setAttributes({ order: value }),
			}),
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
			el(RangeControl, {
				label: __('Columns per row', 'bridge'),
				value: columns,
				min: 1,
				max: 6,
				onChange: (value) => setAttributes({ columns: value }),
			}),
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
				onChange: (value) => setAttributes({ overflowStyle: value }),
			})
		),
		el(
			PanelBody,
			{ title: __('Card content', 'bridge') },
			el(RangeControl, {
				label: __('Excerpt length (words)', 'bridge'),
				value: excerptLength,
				min: 10,
				max: 100,
				onChange: (value) => setAttributes({ excerptLength: value }),
			}),
			el(ToggleControl, {
				label: __('Show read more link', 'bridge'),
				checked: !!showReadMore,
				onChange: (value) => setAttributes({ showReadMore: value }),
			}),
			showReadMore &&
				el(TextControl, {
					label: __('Read more text', 'bridge'),
					// Empty uses the theme's own wording, which is the only
					// wording that gets translated.
					placeholder: __('Read more', 'bridge'),
					value: readMoreText,
					onChange: (value) => setAttributes({ readMoreText: value }),
				})
		)
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
