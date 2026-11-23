/**
 * Editor view for `bridge/contact-form`.
 *
 * The heading and the copy above the form are inner blocks, edited where they
 * appear. The form under them is a preview and nothing more — the controls are
 * disabled, there is no nonce and no action, and it cannot be submitted from
 * the canvas.
 *
 * The fields it draws are not written here. They arrive as `window.bridgeContactForm`,
 * printed by PHP from bridge_enquiry_fields() — the same list render.php draws,
 * the handler validates against and both emails read. A preview that carried
 * its own copy of the list would be the version an editor trusts, and the one
 * nobody updates.
 */

const { useBlockProps, useInnerBlocksProps, InspectorControls } =
	window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const {
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
	ExternalLink,
	Notice,
} = window.wp.components;
const { __, sprintf } = window.wp.i18n;

const DATA = window.bridgeContactForm || {};
const FIELDS = Array.isArray(DATA.fields) ? DATA.fields : [];
const CONSENT_DEFAULTS = DATA.consentDefaults || {};
const LOCATIONS = Array.isArray(DATA.locations) ? DATA.locations : [];

const SELECT_DEFAULTS = {
	select1: __('Interested in', 'bridge'),
	select2: __('Location', 'bridge'),
};

/**
 * A dropdown's choices, as render.php will draw them.
 *
 * @param {Object} attributes Block attributes.
 * @param {string} key        select1 or select2.
 * @return {string[]} Choices.
 */
const selectChoices = (attributes, key) =>
	attributes[`${key}Source`] === 'locations'
		? LOCATIONS
		: (attributes[`${key}Choices`] || '')
				.split('\n')
				.map((choice) => choice.trim())
				.filter(Boolean);

/**
 * An inert dropdown for the preview.
 *
 * @param {Object} attributes Block attributes.
 * @param {string} key        select1 or select2.
 * @return {Object|null} Element, or nothing when it would not be drawn.
 */
const previewSelect = (attributes, key) => {
	const choices = selectChoices(attributes, key);

	if (!attributes[`${key}Enabled`] || !choices.length) {
		return null;
	}

	return el(
		'p',
		{
			className: `bridge-field bridge-field--${key} bridge-field--select`,
			key,
		},
		el(
			'span',
			{ className: 'bridge-field__label' },
			attributes[`${key}Label`] || SELECT_DEFAULTS[key],
			attributes[`${key}Required`] &&
				el(
					'span',
					{
						className: 'bridge-field__required',
						'aria-hidden': true,
					},
					'*'
				)
		),
		el(
			'select',
			{
				className: 'bridge-field__control',
				disabled: true,
				tabIndex: -1,
			},
			el('option', null, __('Please choose…', 'bridge'))
		)
	);
};

/**
 * An inert consent checkbox for the preview.
 *
 * @param {Object} attributes Block attributes.
 * @param {string} key        consent1 or consent2.
 * @return {Object|null} Element.
 */
const previewConsent = (attributes, key) =>
	attributes[`${key}Enabled`]
		? el(
				'p',
				{
					className: `bridge-field bridge-field--${key} bridge-field--consent`,
					key,
				},
				el('input', {
					className: 'bridge-field__control',
					type: 'checkbox',
					disabled: true,
					tabIndex: -1,
				}),
				el(
					'span',
					{ className: 'bridge-field__label' },
					(attributes[`${key}Text`] || CONSENT_DEFAULTS[key] || '')
						// The preview shows the words; links are drawn on
						// the page.
						.replace(/<[^>]+>/g, ''),
					attributes[`${key}Required`] &&
						el(
							'span',
							{
								className: 'bridge-field__required',
								'aria-hidden': true,
							},
							'*'
						)
				)
			)
		: null;

const TEMPLATE = [
	['core/heading', { level: 2, placeholder: __('Get in touch', 'bridge') }],
	[
		'core/paragraph',
		{
			placeholder: __(
				'Tell us what you need and we will come back to you.',
				'bridge'
			),
		},
	],
];

/**
 * One inert field, wearing exactly the classes render.php gives it so the
 * canvas and the page are painted by the same stylesheet.
 *
 * @param {Object}  field    Field description from PHP.
 * @param {boolean} required Whether this form asks for it.
 * @return {Object} Element.
 */
const previewField = (field, required) =>
	el(
		'p',
		{
			className: `bridge-field bridge-field--${field.key}`,
			key: field.key,
		},
		el(
			'span',
			{ className: 'bridge-field__label' },
			field.label,
			required &&
				el(
					'span',
					{
						className: 'bridge-field__required',
						'aria-hidden': true,
					},
					'*'
				)
		),
		'textarea' === field.type
			? el('textarea', {
					className: 'bridge-field__control',
					rows: field.rows || 5,
					placeholder: field.placeholder,
					disabled: true,
					// The preview is furniture, not a form: nothing in it
					// should be reachable by tab from the block toolbar.
					tabIndex: -1,
				})
			: el('input', {
					className: 'bridge-field__control',
					type: 'text',
					placeholder: field.placeholder,
					disabled: true,
					tabIndex: -1,
				})
	);

const Edit = ({ attributes, setAttributes }) => {
	const {
		width,
		panel,
		requirePhone,
		buttonLabel,
		successMessage,
		fieldLayout = 'stacked',
	} = attributes;

	const hasConsents = !!(
		attributes.consent1Enabled || attributes.consent2Enabled
	);

	// One dropdown's controls.
	const selectPanel = (key, title) =>
		el(
			PanelBody,
			{ title, initialOpen: false },
			el(ToggleControl, {
				label: __('Show this dropdown', 'bridge'),
				checked: !!attributes[`${key}Enabled`],
				onChange: (value) =>
					setAttributes({ [`${key}Enabled`]: value }),
				__nextHasNoMarginBottom: true,
			}),
			attributes[`${key}Enabled`] &&
				el(
					Fragment,
					null,
					el(TextControl, {
						label: __('Label', 'bridge'),
						value: attributes[`${key}Label`],
						placeholder: SELECT_DEFAULTS[key],
						onChange: (value) =>
							setAttributes({ [`${key}Label`]: value }),
						__nextHasNoMarginBottom: true,
					}),
					el(SelectControl, {
						label: __('Choices from', 'bridge'),
						value: attributes[`${key}Source`],
						options: [
							{
								label: __('The list below', 'bridge'),
								value: 'custom',
							},
							{
								label: __('Site Options → Locations', 'bridge'),
								value: 'locations',
							},
						],
						onChange: (value) =>
							setAttributes({ [`${key}Source`]: value }),
						help:
							attributes[`${key}Source`] === 'locations'
								? sprintf(
										/* translators: %s: list of location names. */
										__('Currently: %s', 'bridge'),
										LOCATIONS.join(', ') ||
											__('no locations yet', 'bridge')
									)
								: undefined,
						__nextHasNoMarginBottom: true,
					}),
					attributes[`${key}Source`] !== 'locations' &&
						el(TextareaControl, {
							label: __('Choices', 'bridge'),
							help: __(
								'One per line. The dropdown is hidden until it has at least one.',
								'bridge'
							),
							value: attributes[`${key}Choices`],
							rows: 5,
							onChange: (value) =>
								setAttributes({ [`${key}Choices`]: value }),
							__nextHasNoMarginBottom: true,
						}),
					el(ToggleControl, {
						label: __('Required', 'bridge'),
						checked: !!attributes[`${key}Required`],
						onChange: (value) =>
							setAttributes({ [`${key}Required`]: value }),
						__nextHasNoMarginBottom: true,
					})
				)
		);

	// One consent checkbox's controls. Switching it on fills in the standard
	// wording, so it can be edited rather than retyped.
	const consentPanel = (key, title) =>
		el(
			PanelBody,
			{ title, initialOpen: false },
			el(ToggleControl, {
				label: __('Show this checkbox', 'bridge'),
				checked: !!attributes[`${key}Enabled`],
				onChange: (value) =>
					setAttributes({
						[`${key}Enabled`]: value,
						...(value && !attributes[`${key}Text`]
							? { [`${key}Text`]: CONSENT_DEFAULTS[key] || '' }
							: {}),
					}),
				__nextHasNoMarginBottom: true,
			}),
			attributes[`${key}Enabled`] &&
				el(
					Fragment,
					null,
					el(TextareaControl, {
						label: __('Text', 'bridge'),
						help: __(
							'“Privacy Policy” links to the privacy page set in Settings → Privacy. A link of your own can be written as <a href="…">…</a>.',
							'bridge'
						),
						value: attributes[`${key}Text`],
						placeholder: CONSENT_DEFAULTS[key],
						rows: 3,
						onChange: (value) =>
							setAttributes({ [`${key}Text`]: value }),
						__nextHasNoMarginBottom: true,
					}),
					el(ToggleControl, {
						label: __('Required', 'bridge'),
						help: __(
							'The form cannot be sent until it is ticked.',
							'bridge'
						),
						checked: !!attributes[`${key}Required`],
						onChange: (value) =>
							setAttributes({ [`${key}Required`]: value }),
						__nextHasNoMarginBottom: true,
					})
				)
		);

	const blockProps = useBlockProps({
		className: [
			'bridge-contact',
			`bridge-contact--${width}`,
			panel ? 'bridge-contact--panel' : '',
			'bridge-section bridge-band alignfull',
		]
			.filter(Boolean)
			.join(' '),
	});

	// Merged onto the intro wrapper rather than nested inside one, so the
	// editor's tree matches render.php's and one set of selectors is correct
	// in both.
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'bridge-section__intro bridge-contact__intro' },
		{ template: TEMPLATE, templateLock: false }
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
					value: width,
					options: [
						{ label: __('Narrow', 'bridge'), value: 'narrow' },
						{ label: __('Wide', 'bridge'), value: 'wide' },
					],
					onChange: (value) => setAttributes({ width: value }),
					__nextHasNoMarginBottom: true,
				}),
				el(SelectControl, {
					label: __('Field layout', 'bridge'),
					value: fieldLayout,
					options: [
						{ label: __('Stacked', 'bridge'), value: 'stacked' },
						{ label: __('Rows', 'bridge'), value: 'rows' },
					],
					help:
						fieldLayout === 'rows'
							? __(
									'On a wide screen: name, email and phone in one row; the dropdowns in two columns; the message beside the checkboxes.',
									'bridge'
								)
							: __(
									'Name and email side by side, then each field on its own row.',
									'bridge'
								),
					onChange: (value) => setAttributes({ fieldLayout: value }),
					__nextHasNoMarginBottom: true,
				}),
				el(ToggleControl, {
					label: __('Draw the form on a card', 'bridge'),
					help: __(
						'Lifts the form off the band on its own panel. Turn it off for a form that sits flat in the page.',
						'bridge'
					),
					checked: !!panel,
					onChange: (value) => setAttributes({ panel: value }),
					__nextHasNoMarginBottom: true,
				})
			),
			el(
				PanelBody,
				{ title: __('Form', 'bridge'), initialOpen: true },
				el(ToggleControl, {
					label: __('Require a phone number', 'bridge'),
					help: requirePhone
						? __(
								'A visitor cannot send the form without one.',
								'bridge'
							)
						: __(
								'The field is still shown, and is optional.',
								'bridge'
							),
					checked: !!requirePhone,
					onChange: (value) => setAttributes({ requirePhone: value }),
					__nextHasNoMarginBottom: true,
				}),
				el(TextControl, {
					label: __('Button label', 'bridge'),
					value: buttonLabel,
					placeholder: __('Send enquiry', 'bridge'),
					onChange: (value) => setAttributes({ buttonLabel: value }),
					__nextHasNoMarginBottom: true,
				}),
				el(TextareaControl, {
					label: __('Message after sending', 'bridge'),
					help: __(
						'What replaces the form once an enquiry has gone. Leave it empty for the standard wording.',
						'bridge'
					),
					value: successMessage,
					rows: 3,
					onChange: (value) =>
						setAttributes({ successMessage: value }),
					__nextHasNoMarginBottom: true,
				})
			),
			selectPanel('select1', __('Dropdown 1', 'bridge')),
			selectPanel('select2', __('Dropdown 2', 'bridge')),
			consentPanel('consent1', __('Checkbox 1', 'bridge')),
			consentPanel('consent2', __('Checkbox 2', 'bridge')),
			el(
				PanelBody,
				{
					title: __('Where enquiries go', 'bridge'),
					initialOpen: false,
				},
				el(
					'p',
					null,
					__(
						'Every enquiry is filed under Enquiries in the admin menu, whether or not the email arrives. The sender is thanked automatically.',
						'bridge'
					)
				),
				DATA.notifyEmail
					? el(
							'p',
							null,
							sprintf(
								/* translators: %s: email address. */
								__('Notifications go to %s.', 'bridge'),
								DATA.notifyEmail
							)
						)
					: el(
							Notice,
							{ status: 'warning', isDismissible: false },
							__(
								'No notification address is set, so notifications go to the site administrator.',
								'bridge'
							)
						),
				DATA.optionsUrl &&
					el(
						ExternalLink,
						{ href: DATA.optionsUrl },
						__('Change it in Site Options', 'bridge')
					)
			)
		),
		el(
			'section',
			blockProps,
			el(
				'div',
				{ className: 'bridge-contact__inner' },
				el('div', innerBlocksProps),
				el(
					'div',
					{ className: 'bridge-contact__panel' },
					el(
						'div',
						{
							className: 'bridge-contact__form',
							// Announced as a picture of a form rather than
							// walked as one: none of it is operable here.
							role: 'presentation',
						},
						el(
							'div',
							{
								className: [
									'bridge-contact__grid',
									fieldLayout === 'rows'
										? 'bridge-contact__grid--rows'
										: '',
									fieldLayout === 'rows' && hasConsents
										? 'bridge-contact__grid--has-consents'
										: '',
								]
									.filter(Boolean)
									.join(' '),
							},
							FIELDS.flatMap((field) => [
								...(field.key === 'message'
									? [
											previewSelect(
												attributes,
												'select1'
											),
											previewSelect(
												attributes,
												'select2'
											),
										]
									: []),
								previewField(
									field,
									'phone' === field.key
										? !!requirePhone
										: !!field.required
								),
							]).filter(Boolean),
							hasConsents &&
								el(
									'div',
									{ className: 'bridge-contact__consents' },
									previewConsent(attributes, 'consent1'),
									previewConsent(attributes, 'consent2')
								)
						),
						el(
							'div',
							{ className: 'bridge-contact__actions' },
							el(
								'span',
								{
									className:
										'wp-element-button bridge-contact__submit',
								},
								buttonLabel || __('Send enquiry', 'bridge')
							),
							el(
								'p',
								{ className: 'bridge-contact__legend' },
								__(
									'* Required. We only use these details to answer your enquiry.',
									'bridge'
								)
							)
						)
					)
				)
			)
		)
	);
};

export default Edit;
