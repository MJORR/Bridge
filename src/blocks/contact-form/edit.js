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
	const { width, panel, requirePhone, buttonLabel, successMessage } =
		attributes;

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
							{ className: 'bridge-contact__grid' },
							FIELDS.map((field) =>
								previewField(
									field,
									'phone' === field.key
										? !!requirePhone
										: !!field.required
								)
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
