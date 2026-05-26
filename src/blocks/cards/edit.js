/**
 * Editor view for `bridge/cards`.
 *
 * Mirrors the hero-slider pattern: plain createElement (no JSX), window.wp.*
 * globals (no @wordpress/* imports). The preview is rendered by
 * <ServerSideRender>, which calls render.php via REST — single source of
 * truth for both editor and frontend.
 */

const { useBlockProps, InspectorControls } = window.wp.blockEditor;
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

const ORDERBY_OPTIONS = [
	{ label: __( 'Date', 'bridge' ),          value: 'date' },
	{ label: __( 'Title', 'bridge' ),         value: 'title' },
	{ label: __( 'Modified date', 'bridge' ), value: 'modified_date' },
	{ label: __( 'Random', 'bridge' ),        value: 'rand' },
];

const ORDER_OPTIONS = [
	{ label: __( 'Descending', 'bridge' ), value: 'desc' },
	{ label: __( 'Ascending', 'bridge' ),  value: 'asc' },
];

const POST_TYPE_FALLBACK = [
	{ label: 'Post', value: 'post' },
	{ label: 'Page', value: 'page' },
];

const Edit = ( { attributes, setAttributes } ) => {
	const {
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
	const postTypeOptions = useSelect( ( select ) => {
		const types = select( 'core' ).getPostTypes( { per_page: -1 } );
		if ( ! types ) {
			return POST_TYPE_FALLBACK;
		}
		return types
			.filter( ( t ) => t.viewable && t.slug !== 'attachment' )
			.map( ( t ) => ( { label: t.name, value: t.slug } ) );
	}, [] );

	// Category list — only fetched when we need it (postType === 'post').
	const availableCategories = useSelect( ( select ) =>
		postType === 'post'
			? select( 'core' ).getEntityRecords( 'taxonomy', 'category', {
					per_page: 100,
					_fields: 'id,name',
			  } )
			: null,
	[ postType ] );

	const categoryNameById = {};
	( availableCategories || [] ).forEach( ( c ) => {
		categoryNameById[ c.id ] = c.name;
	} );

	const selectedCategoryNames = categories
		.map( ( id ) => categoryNameById[ id ] )
		.filter( Boolean );

	const availableCategoryNames = ( availableCategories || [] ).map( ( c ) => c.name );

	const onCategoriesChange = ( tokens ) => {
		if ( ! availableCategories ) {
			return;
		}
		const ids = tokens
			.map( ( name ) => availableCategories.find( ( c ) => c.name === name )?.id ?? null )
			.filter( ( id ) => id !== null );
		setAttributes( { categories: ids } );
	};

	// Current post ID — passed to SSR so the block-renderer endpoint
	// validates against a real post and doesn't return rest_post_invalid_id.
	const postId = useSelect(
		( select ) => select( 'core/editor' )?.getCurrentPostId() ?? null,
		[]
	);

	const blockProps = useBlockProps();

	const inspector = el(
		InspectorControls,
		null,
		el(
			PanelBody,
			{ title: __( 'Query', 'bridge' ), initialOpen: true },
			el( SelectControl, {
				label:    __( 'Post type', 'bridge' ),
				value:    postType,
				options:  postTypeOptions,
				onChange: ( value ) => setAttributes( { postType: value, categories: [] } ),
			} ),
			el( RangeControl, {
				label:    __( 'Number of posts', 'bridge' ),
				value:    numberOfPosts,
				min:      1,
				max:      24,
				onChange: ( value ) => setAttributes( { numberOfPosts: value } ),
			} ),
			el( SelectControl, {
				label:    __( 'Order by', 'bridge' ),
				value:    orderBy,
				options:  ORDERBY_OPTIONS,
				onChange: ( value ) => setAttributes( { orderBy: value } ),
			} ),
			el( SelectControl, {
				label:    __( 'Order', 'bridge' ),
				value:    order,
				options:  ORDER_OPTIONS,
				onChange: ( value ) => setAttributes( { order: value } ),
			} ),
			postType === 'post' && el( FormTokenField, {
				label:       __( 'Filter by categories', 'bridge' ),
				value:       selectedCategoryNames,
				suggestions: availableCategoryNames,
				onChange:    onCategoriesChange,
			} )
		),
		el(
			PanelBody,
			{ title: __( 'Layout', 'bridge' ) },
			el( RangeControl, {
				label:    __( 'Columns per row', 'bridge' ),
				value:    columns,
				min:      1,
				max:      6,
				onChange: ( value ) => setAttributes( { columns: value } ),
			} )
		),
		el(
			PanelBody,
			{ title: __( 'Card content', 'bridge' ) },
			el( RangeControl, {
				label:    __( 'Excerpt length (words)', 'bridge' ),
				value:    excerptLength,
				min:      10,
				max:      100,
				onChange: ( value ) => setAttributes( { excerptLength: value } ),
			} ),
			el( ToggleControl, {
				label:    __( 'Show read more link', 'bridge' ),
				checked:  !! showReadMore,
				onChange: ( value ) => setAttributes( { showReadMore: value } ),
			} ),
			showReadMore && el( TextControl, {
				label:    __( 'Read more text', 'bridge' ),
				value:    readMoreText,
				onChange: ( value ) => setAttributes( { readMoreText: value } ),
			} )
		)
	);

	const ssrProps = {
		block:      BLOCK_NAME,
		attributes,
		EmptyResponsePlaceholder: () => el(
			Placeholder,
			{ label: __( 'Cards', 'bridge' ) },
			__( 'No posts match the current settings.', 'bridge' )
		),
		LoadingResponsePlaceholder: () => el(
			Placeholder,
			{ label: __( 'Cards', 'bridge' ) },
			el( Spinner )
		),
		ErrorResponsePlaceholder: ( { response } ) => el(
			Placeholder,
			{ label: __( 'Cards', 'bridge' ) },
			response?.errorMsg || __( 'Could not render preview.', 'bridge' )
		),
	};

	if ( postId ) {
		ssrProps.urlQueryArgs = { post_id: postId };
	}

	return el(
		Fragment,
		null,
		inspector,
		el( 'div', blockProps, el( ServerSideRender, ssrProps ) )
	);
};

export default Edit;
