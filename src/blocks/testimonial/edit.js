/**
 * Editor view for `bridge/testimonial`.
 *
 * Edited in place with RichText rather than through sidebar fields: a
 * testimonial is mostly words, and typing them where they will appear is the
 * whole reason the block editor exists. Only the rating — which is a choice,
 * not prose — stays in the Inspector.
 */

const { useBlockProps, RichText, InspectorControls } = window.wp.blockEditor;
const { createElement: el, Fragment } = window.wp.element;
const { PanelBody, RangeControl } = window.wp.components;
const { __ } = window.wp.i18n;

const Edit = ({ attributes, setAttributes, context }) => {
	const { title, text, rating, name, company } = attributes;
	const cardStyle = context['bridge/cardStyle'] || 'solid';

	const blockProps = useBlockProps({
		className: `bridge-testimonial is-card-${cardStyle}`,
	});

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Rating', 'bridge'), initialOpen: true },
				el(RangeControl, {
					label: __('Stars', 'bridge'),
					help: __('Zero hides the rating.', 'bridge'),
					value: rating,
					onChange: (value) => setAttributes({ rating: value || 0 }),
					min: 0,
					max: 5,
					step: 1,
				})
			)
		),
		el(
			'figure',
			blockProps,
			rating > 0 &&
				el(
					'p',
					{ className: 'bridge-testimonial__rating' },
					[1, 2, 3, 4, 5].map((i) =>
						el(
							'span',
							{
								key: i,
								className:
									'bridge-testimonial__star' +
									(i <= rating ? ' is-filled' : ''),
							},
							'★'
						)
					)
				),
			el(
				'blockquote',
				{ className: 'bridge-testimonial__quote' },
				el(RichText, {
					tagName: 'p',
					className: 'bridge-testimonial__title',
					value: title,
					allowedFormats: [],
					onChange: (value) => setAttributes({ title: value }),
					placeholder: __('Headline (optional)', 'bridge'),
				}),
				el(RichText, {
					tagName: 'p',
					className: 'bridge-testimonial__text',
					value: text,
					allowedFormats: [],
					onChange: (value) => setAttributes({ text: value }),
					placeholder: __('What they said…', 'bridge'),
				})
			),
			el(
				'figcaption',
				{ className: 'bridge-testimonial__attribution' },
				el(RichText, {
					tagName: 'span',
					className: 'bridge-testimonial__name',
					value: name,
					allowedFormats: [],
					onChange: (value) => setAttributes({ name: value }),
					placeholder: __('Name', 'bridge'),
				}),
				el(RichText, {
					tagName: 'span',
					className: 'bridge-testimonial__company',
					value: company,
					allowedFormats: [],
					onChange: (value) => setAttributes({ company: value }),
					placeholder: __('Company or job title', 'bridge'),
				})
			)
		)
	);
};

export default Edit;
