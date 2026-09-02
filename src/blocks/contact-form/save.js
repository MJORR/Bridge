/**
 * Save for `bridge/contact-form` — the intro's inner blocks only.
 *
 * The form is never serialised. It carries a nonce and a signed timestamp,
 * both of which are minted per request, so a saved copy would be a form that
 * expired the moment it was written to the database.
 */
const { InnerBlocks } = window.wp.blockEditor;
const { createElement: el } = window.wp.element;

export default () => el(InnerBlocks.Content);
