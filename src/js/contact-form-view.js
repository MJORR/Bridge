/**
 * Bridge — the contact form's progressive enhancement.
 *
 * The form works with this file blocked: it is an ordinary POST to
 * admin-post.php, which validates, files the enquiry, sends the emails and
 * redirects back with the result. Everything here is the difference between
 * that and staying on the page — no reload, the visitor's typing kept, errors
 * drawn against the fields they belong to.
 *
 * So nothing below may be load-bearing, and one thing follows from that: if
 * the fetch fails for any reason — offline, a proxy, a 500 — the form is
 * submitted natively instead. A visitor never loses a message because an
 * enhancement did not work.
 *
 * It also posts the one signal that says a browser ran a script at all, which
 * is a point on the server's spam score and never enough on its own to stop a
 * submission. See inc/enquiries.php.
 */

const FORMS = '[data-bridge-enquiry]';

/**
 * Take down the errors from the previous attempt.
 *
 * @param {HTMLFormElement} form The form.
 */
const clearErrors = (form) => {
	form.querySelectorAll('.bridge-field.is-invalid').forEach((field) => {
		field.classList.remove('is-invalid');

		const control = field.querySelector('.bridge-field__control');

		if (control) {
			control.removeAttribute('aria-invalid');
			control.removeAttribute('aria-describedby');
		}

		field.querySelector('.bridge-field__error')?.remove();
	});

	form.querySelector('[data-bridge-notice]')?.remove();
};

/**
 * Draw one field's error, joined to its control the way render.php joins it.
 *
 * @param {HTMLFormElement} form    The form.
 * @param {string}          key     Field key.
 * @param {string}          message What is wrong with it.
 */
const showError = (form, key, message) => {
	const field = form.querySelector(`.bridge-field--${key}`);
	const control = field?.querySelector('.bridge-field__control');

	if (!field || !control) {
		return;
	}

	const id = `${control.id}-error`;

	field.classList.add('is-invalid');
	control.setAttribute('aria-invalid', 'true');
	control.setAttribute('aria-describedby', id);

	const error = document.createElement('span');
	error.className = 'bridge-field__error';
	error.id = id;
	error.textContent = message;
	field.appendChild(error);
};

/**
 * Put the summary back at the top of the form.
 *
 * `role="alert"` so it is announced when it appears: the visitor is looking at
 * the button they just pressed, not at the top of the form.
 *
 * @param {HTMLFormElement} form   The form.
 * @param {string}          notice The wording.
 */
const showNotice = (form, notice) => {
	if (!notice) {
		return;
	}

	const banner = document.createElement('div');
	banner.className = 'bridge-contact__notice';
	banner.setAttribute('role', 'alert');
	banner.setAttribute('data-bridge-notice', '');
	banner.textContent = notice;

	form.prepend(banner);
};

/**
 * Show the thank-you in place of the form, and move focus into it.
 *
 * The panel is already in the page, printed by PHP and carrying the `hidden`
 * attribute — the same markup, the same wording and the same icon the redirect
 * path ends on. Building one here would have been a second copy of all three,
 * in a language that has none of the block's attributes to build it from.
 *
 * @param {HTMLFormElement} form The form.
 * @return {boolean} Whether the swap happened.
 */
const succeed = (form) => {
	const panel = form.parentElement?.querySelector('[data-bridge-success]');

	if (!panel) {
		return false;
	}

	form.remove();
	panel.hidden = false;
	panel.focus();

	return true;
};

/**
 * Post the form and read the answer.
 *
 * Null means the exchange itself failed — offline, a proxy, a 500 with an HTML
 * error page where the JSON should be. It does not mean the submission was
 * rejected: a rejection comes back as JSON with a 422 and is a perfectly good
 * payload.
 *
 * @param {HTMLFormElement} form The form.
 * @return {Promise<Object|null>} The answer, or null if there was not one.
 */
const send = async (form) => {
	try {
		const response = await fetch(form.action, {
			method: 'POST',
			body: new FormData(form),
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
		});

		return await response.json();
	} catch (error) {
		return null;
	}
};

/**
 * Wire one form up.
 *
 * @param {HTMLFormElement} form The form.
 */
const enhance = (form) => {
	// The signal that a browser ran this file. Set on load rather than on
	// submit so that a script which fails later has still said so.
	const flag = form.querySelector('[data-bridge-js]');

	if (flag) {
		flag.value = '1';
	}

	form.addEventListener('submit', async (event) => {
		// Native validation has already passed by the time a submit event
		// fires, so the browser's own required-field handling still runs
		// first and this only ever sees a form worth sending.
		event.preventDefault();

		// A double press, or a press while the first is still in flight.
		if (form.dataset.sending) {
			return;
		}

		const button = form.querySelector('.bridge-contact__submit');

		form.dataset.sending = '1';
		form.classList.add('is-sending');

		if (button) {
			button.disabled = true;
		}

		clearErrors(form);

		const payload = await send(form);

		if (!payload) {
			// The enhancement failed, so stand out of the way. `submit()` on
			// the element does not fire this handler again, so the browser
			// posts the form the ordinary way and the server answers with a
			// redirect. The visitor loses the smoothness, not the message.
			delete form.dataset.sending;
			form.submit();
			return;
		}

		delete form.dataset.sending;
		form.classList.remove('is-sending');

		if (button) {
			button.disabled = false;
		}

		if (payload.ok) {
			// The message is filed and the emails have gone either way. If the
			// panel is somehow not there, reload onto the redirect path's own
			// answer rather than leaving a sent form on the screen.
			if (!succeed(form)) {
				window.location.reload();
			}

			return;
		}

		const errors = payload.errors || {};

		Object.keys(errors).forEach((key) => showError(form, key, errors[key]));

		showNotice(form, payload.notice);

		// After the summary, so the summary is what is announced and this is
		// where the caret lands to fix it.
		form.querySelector(
			'.bridge-field.is-invalid .bridge-field__control'
		)?.focus();
	});
};

document.querySelectorAll(FORMS).forEach(enhance);
