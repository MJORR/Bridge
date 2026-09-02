/**
 * Editor view for `bridge/faqs`.
 *
 * Two halves that behave differently, on purpose.
 *
 * The intro — a heading and an optional summary — is inner blocks, edited in
 * place, because it is this block's own content and belongs to this page.
 *
 * The questions are not. They are FAQs, written on their own admin screen, and
 * what is drawn here is a read-only list of the ones the block will pull in.
 * Making them editable here would be offering to edit the source record from
 * inside a page that merely quotes it — which is the thing this block exists
 * to stop.
 *
 * ---- Where they come from -------------------------------------------------
 *
 * The FAQ content type, and there is no control for it. The block is called
 * FAQs, it draws an accordion of questions and answers, and the theme declares
 * an FAQ type for exactly that — so "which content type" was a question with
 * one right answer, which makes it a question that only exists to be answered
 * wrong. A block pointed at Team by accident renders a grid of biographies
 * inside a disclosure widget, and looks in the editor like it is working.
 *
 * So the source is stated rather than chosen, and the only thing that changes
 * it is switching the FAQ type off in Theme Options — which the block reports
 * instead of quietly reading something else.
 *
 * The list is titles only, not titles and answers. An accordion on the front
 * end is a list of questions until a reader opens one, so a preview of it is
 * the same list; drawing every answer expanded would show a shape the page
 * never has.
 *
 * ---- Categories -----------------------------------------------------------
 *
 * Chosen here, once, rather than offered to the reader as a row of filters. A
 * page that asks a question — a pricing page, a delivery page — wants the
 * answers about that thing and nothing else, and a filter bar on it is a
 * control whose only useful setting is the one the page was already about. So
 * the block narrows to a category and renders that set; a page that wants
 * every question chooses All, which is the default.
 *
 * ---- Where it may go ------------------------------------------------------
 *
 * The top level of a page, and nowhere else. This is a full-width band, and its
 * full-bleed arithmetic resolves against whatever box contains it — so nested
 * in a column it grows to the window's width but centres on the column, hanging
 * off one side of the page. src/editor/top-level-only.js stops it being
 * inserted anywhere else; the notice below is for the blocks that were nested
 * before that rule existed, which no filter can reach.
 *
 * The control appears whenever the FAQ type has categories switched on in Theme
 * Options — including before anybody has written one. An empty select is a poor
 * control, but a control that is absent until some invisible precondition is met
 * is worse: it reads as a missing feature, and there is nothing on the screen to
 * say what would bring it back. So it shows, disabled, and says where categories
 * are written.
 */

const { useBlockProps, useInnerBlocksProps, InspectorControls } =
	window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const {
	PanelBody,
	Notice,
	RangeControl,
	SelectControl,
	Spinner,
	ToggleControl,
	ExternalLink,
} = window.wp.components;
const { useSelect } = window.wp.data;
const { __, sprintf } = window.wp.i18n;

// Printed by functions.php: the FAQ type's slug, what it is called, the name of
// its category taxonomy, and the address of the screen that declares it. Read
// from a global rather than fetched, for the reason the cards block does the
// same — it is three strings already in memory on the server, and a REST round
// trip on every editor load is a request to learn something we already knew.
//
// `source` is empty when the FAQ type has been switched off or removed.
const SETTINGS = window.bridgeFaqs || {};
const SOURCE = SETTINGS.source || '';
const TAXONOMY = SETTINGS.taxonomy || '';
const TYPE_NAME = SETTINGS.plural || '';

const ALLOWED_BLOCKS = ['core/heading', 'core/paragraph'];

const TEMPLATE = [
	[
		'core/heading',
		{ level: 2, placeholder: __('Frequently asked questions', 'bridge') },
	],
	[
		'core/paragraph',
		{ placeholder: __('A line of summary (optional)', 'bridge') },
	],
];

// The same geometry as src/icons/chevron-down.svg. Drawn here rather than
// fetched, because the compiled library is a PHP-side contract and the editor
// needs one path.
const Chevron = () =>
	el(
		'svg',
		{
			className: 'bridge-icon bridge-icon--small bridge-faq__marker',
			viewBox: '0 0 24 24',
			width: '1em',
			height: '1em',
			fill: 'none',
			stroke: 'currentColor',
			strokeWidth: '1.6',
			strokeLinecap: 'round',
			strokeLinejoin: 'round',
			'aria-hidden': 'true',
			focusable: 'false',
		},
		el('path', { d: 'm5 9 7 7 7-7' })
	);

const ORDER_QUERY = {
	menu_order: { orderby: 'menu_order', order: 'asc' },
	title: { orderby: 'title', order: 'asc' },
	date: { orderby: 'date', order: 'desc' },
};

const Edit = ({ attributes, setAttributes, clientId }) => {
	const { width, exclusive, category, order, count } = attributes;

	// Anything but the root of the page. Read rather than corrected: lifting a
	// band out of a column on load would rearrange a published layout nobody
	// asked this block to touch, so it reports and leaves the move to whoever
	// built the page.
	const nested = useSelect(
		(select) =>
			!!select('core/block-editor').getBlockRootClientId(clientId),
		[clientId]
	);

	// Not an attribute and not a choice — see the file header.
	const source = SOURCE;
	const valid = !!source;

	// Empty unless the FAQ type has categories switched on in Theme Options.
	const taxonomy = TAXONOMY;

	// A stored category with no taxonomy behind it any more means categories
	// were switched off for this type in Theme Options. The attribute is left
	// alone rather than cleared here, for two reasons: an effect that writes to
	// a block on load marks a post dirty that nobody has edited, and keeping it
	// means switching categories back on restores every block's filter with
	// them. The front end reads it the same way — see render.php.
	const terms = useSelect(
		(select) =>
			taxonomy
				? select('core').getEntityRecords('taxonomy', taxonomy, {
						per_page: 100,
						orderby: 'name',
						order: 'asc',
						_fields: 'id,name,slug',
					})
				: null,
		[taxonomy]
	);

	// Everything below reads `terms`, so all of it lives under that call. It
	// was above it once, and a `const` read before its declaration is not a
	// build error — it is a ReferenceError thrown on the first render, which
	// takes the whole block down with it.
	//
	// `getEntityRecords` answers null while it is still resolving and an empty
	// array once it knows there is nothing. Worth telling apart: one of them is
	// a spinner's worth of waiting and the other is a message about how to fix
	// the site.
	const noTerms = !!taxonomy && Array.isArray(terms) && terms.length === 0;

	// Slugs travel between environments and term ids do not, so the attribute
	// holds a slug — see render.php. REST filters by id, so the preview needs
	// the one the other names.
	const chosenId = (terms || []).find((term) => term.slug === category)?.id;

	// A category that was renamed or deleted since this block was saved. Worth
	// naming rather than showing as an empty list: the front end renders
	// nothing in this state, and "no questions" and "no such category" are
	// fixed in different places.
	const missingCategory = !!category && !!terms && !chosenId;

	const { questions, loading } = useSelect(
		(select) => {
			if (!valid) {
				return { questions: [], loading: false };
			}

			const store = select('core');
			const query = {
				per_page: count > 0 ? count : 100,
				status: 'publish',
				_fields: 'id,title',
				...(ORDER_QUERY[order] || ORDER_QUERY.menu_order),
				// The taxonomy's REST parameter is its name, and it takes term
				// ids. Left out entirely when there is no category chosen —
				// sending an empty value asks for posts in the term whose id
				// is nothing, which is no posts at all.
				...(taxonomy && chosenId ? { [taxonomy]: [chosenId] } : {}),
			};

			return {
				questions:
					store.getEntityRecords('postType', source, query) || [],
				loading: !store.hasFinishedResolution('getEntityRecords', [
					'postType',
					source,
					query,
				]),
			};
		},
		[valid, source, order, count, taxonomy, chosenId]
	);

	const blockProps = useBlockProps({
		className: `bridge-faqs bridge-section bridge-band alignfull bridge-faqs--${width}`,
	});

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'bridge-section__intro bridge-faqs__intro' },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			templateLock: false,
		}
	);

	const typeName = TYPE_NAME;
	const categoryName =
		(terms || []).find((term) => term.slug === category)?.name || category;

	// ---- What the list area says, in the order the cases actually happen ---
	let list;

	if (nested) {
		// First, because it is about the block rather than about its contents:
		// a correct list of questions in the wrong place is still the wrong
		// place, and the questions are not what needs fixing.
		list = el(
			Notice,
			{ status: 'warning', isDismissible: false },
			__(
				'This is a full-width band and it is inside another block, so on the page it will hang off one side of the window. Move it to the top level of the page.',
				'bridge'
			)
		);
	} else if (!valid) {
		// One case now, not two: with nothing to choose, the only way to have
		// no source is for the FAQ type to be off or gone. Says where to fix
		// it rather than describing where to look.
		list = el(
			Notice,
			{ status: 'warning', isDismissible: false },
			__(
				'The FAQs content type is switched off, so there is nothing for this block to show. Switch it back on in Theme Options.',
				'bridge'
			),
			' ',
			SETTINGS.optionsUrl &&
				el(
					ExternalLink,
					{ href: SETTINGS.optionsUrl },
					__('Theme Options', 'bridge')
				)
		);
	} else if (missingCategory) {
		list = el(
			Notice,
			{ status: 'warning', isDismissible: false },
			sprintf(
				/* translators: %s: the stored category slug. */
				__(
					'The category “%s” no longer exists. Nothing will render here until another one is chosen.',
					'bridge'
				),
				category
			)
		);
	} else if (loading) {
		list = el('div', { className: 'bridge-faqs__loading' }, el(Spinner));
	} else if (!questions.length) {
		// Two different reports, because they are fixed in two different
		// places: writing some, or widening what this block asks for.
		list = el(
			Notice,
			{ status: 'warning', isDismissible: false },
			category
				? sprintf(
						/* translators: 1: plural name of the content type, 2: category name. */
						__(
							'No published %1$s in “%2$s”. Nothing will render here until there are some, or until this block asks for all of them.',
							'bridge'
						),
						typeName.toLowerCase(),
						categoryName
					)
				: sprintf(
						/* translators: %s: plural name of the content type. */
						__(
							'No published %s yet. Nothing will render here until there are some.',
							'bridge'
						),
						typeName.toLowerCase()
					)
		);
	} else {
		list = el(
			'div',
			{ className: 'bridge-faqs__list' },
			questions.map((question) =>
				el(
					'div',
					{ className: 'bridge-faq is-editing', key: question.id },
					el(
						'div',
						{ className: 'bridge-faq__question' },
						el('span', {
							className: 'bridge-faq__text',
							// The title arrives as rendered HTML — an
							// apostrophe in a question is an entity by the
							// time REST has it, and printing it as text shows
							// the entity rather than the punctuation.
							dangerouslySetInnerHTML: {
								__html: question.title?.rendered || '',
							},
						}),
						el(Chevron)
					)
				)
			)
		);
	}

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Questions', 'bridge'), initialOpen: true },
				// Where the questions come from, said rather than asked. The
				// panel would otherwise open on a category filter with
				// nothing above it explaining what is being filtered.
				valid &&
					el(
						'p',
						{ className: 'bridge-faqs__source' },
						sprintf(
							/* translators: %s: plural name of the FAQ content type. */
							__(
								'Drawn from %s. Editing the questions happens on that screen, not here.',
								'bridge'
							),
							typeName
						)
					),
				// Whenever the FAQ type has categories switched on, written or
				// not — see the file header for why it does not wait for a
				// term to exist. Disabled when there is nothing to choose,
				// unless the block is holding a category that has since been
				// deleted, which is the one case where an empty list still
				// has something to let go of.
				valid &&
					!!taxonomy &&
					el(SelectControl, {
						label: __('Category', 'bridge'),
						help: noTerms
							? el(
									Fragment,
									null,
									__(
										'No FAQ Categories yet. Add them on the FAQs screen and they will appear here.',
										'bridge'
									),
									SETTINGS.categoriesUrl && ' ',
									SETTINGS.categoriesUrl &&
										el(
											ExternalLink,
											{ href: SETTINGS.categoriesUrl },
											__('FAQ Categories', 'bridge')
										)
								)
							: __(
									'Narrows this block to one category. The categories themselves are set on each question.',
									'bridge'
								),
						disabled: noTerms && !missingCategory,
						value: category,
						options: [
							{
								label: __('All categories', 'bridge'),
								value: '',
							},
							...(terms || []).map((term) => ({
								label: term.name,
								value: term.slug,
							})),
							// A saved slug with no term behind it would
							// otherwise make the select show "All" while the
							// front end renders nothing — a control
							// disagreeing with the page it controls.
							...(missingCategory
								? [
										{
											label: sprintf(
												/* translators: %s: the stored category slug. */
												__('%s (removed)', 'bridge'),
												category
											),
											value: category,
										},
									]
								: []),
						],
						onChange: (value) => setAttributes({ category: value }),
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					}),
				el(SelectControl, {
					label: __('Order', 'bridge'),
					value: order,
					options: [
						{
							label: __('The order set on each one', 'bridge'),
							value: 'menu_order',
						},
						{ label: __('A to Z', 'bridge'), value: 'title' },
						{ label: __('Newest first', 'bridge'), value: 'date' },
					],
					onChange: (value) => setAttributes({ order: value }),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				}),
				el(RangeControl, {
					label: __('How many', 'bridge'),
					help: __('0 shows all of them.', 'bridge'),
					value: count,
					onChange: (value) => setAttributes({ count: value || 0 }),
					min: 0,
					max: 50,
					step: 1,
					__nextHasNoMarginBottom: true,
				})
			),
			el(
				PanelBody,
				{ title: __('Layout', 'bridge'), initialOpen: false },
				el(SelectControl, {
					label: __('Width', 'bridge'),
					value: width,
					options: [
						{ label: __('Narrow', 'bridge'), value: 'narrow' },
						{ label: __('Wide', 'bridge'), value: 'wide' },
					],
					onChange: (value) => setAttributes({ width: value }),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				}),
				el(ToggleControl, {
					label: __('One answer at a time', 'bridge'),
					help: __(
						'Opening a question closes the one already open.',
						'bridge'
					),
					checked: !!exclusive,
					onChange: (value) => setAttributes({ exclusive: value }),
					__nextHasNoMarginBottom: true,
				})
			)
		),
		el(
			'section',
			blockProps,
			el(
				'div',
				{ className: 'bridge-faqs__inner' },
				el('div', innerBlocksProps),
				list
			)
		)
	);
};

export default Edit;
