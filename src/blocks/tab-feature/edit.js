/**
 * Editor view for `bridge/tab-feature` — a title and a line of detail,
 * typed in place.
 */

const { useBlockProps, RichText } = window.wp.blockEditor;
const { createElement: el } = window.wp.element;
const { __ } = window.wp.i18n;

const Edit = ({ attributes, setAttributes }) => {
	const { title, text } = attributes;

	return el(
		'div',
		useBlockProps({ className: 'bridge-tab-feature' }),
		el(RichText, {
			tagName: 'p',
			className: 'bridge-tab-feature__title',
			value: title,
			allowedFormats: ['core/bold', 'core/italic'],
			placeholder: __('Feature', 'bridge'),
			onChange: (value) => setAttributes({ title: value }),
		}),
		el(RichText, {
			tagName: 'p',
			className: 'bridge-tab-feature__text',
			value: text,
			allowedFormats: ['core/bold', 'core/italic'],
			placeholder: __('A line of detail', 'bridge'),
			onChange: (value) => setAttributes({ text: value }),
		})
	);
};

export default Edit;
