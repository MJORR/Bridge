/**
 * Bridge — Landing Page editor enhancer.
 *
 * One behaviour: choosing the Landing Page template sets the Title Banner to
 * "No title", because a landing page opens on its hero and a banner above it
 * would be the page announcing itself twice. The setting's panel is hidden on
 * that template — see page-banner-panel.js — so this is what decides it.
 *
 * ---- Why the title field is no longer hidden -------------------------------
 *
 * It used to be, on the reasoning that the hero carries the headline. But the
 * post title is not the page's heading, it is the page's *name*: it is what
 * appears in a menu, in the browser tab, in a search result and in the Pages
 * list. Hiding the field did not stop the page having one — it stopped anyone
 * setting it, on the one template most likely to need a good one.
 *
 * The heading and the name are different things, and the block editor has one
 * field for each: the hero's H1, and the title.
 *
 * ---- What this file used to do, and why it stopped -------------------------
 *
 * It also inserted the hero. Choosing the template auto-inserted a Hero Slider
 * carrying two hard-coded Covers: a heading and a subtitle in the first, a
 * heading in the second. That is where the two "Slide title…" panels came from
 * on every new landing page — not from the template, which has never held a
 * hero, and not from the slider block, whose own seed is a separate thing that
 * has also gone.
 *
 * Two slides was the visible half of the problem. The deeper half is that a
 * Cover with inner blocks in it never shows core's media placeholder — see the
 * note on `slide()` in the hero slider's edit view — so a hero built this way
 * arrived with nowhere obvious to put the picture, which is the one thing it
 * needs.
 *
 * And a hero written by a script is a hero nobody chose. A landing page can
 * open on a slider, a static banner, a video, or a headline over a colour;
 * inserting one of them automatically makes the other three feel like a fight
 * with the editor. The block is one click away in the inserter, and it now asks
 * for an image the moment it lands.
 *
 * @param {Object} wp The WordPress globals this file is wrapped around.
 */

(function (wp) {
	const { select, dispatch, subscribe } = wp.data;

	const TEMPLATE_SLUG = 'page-landing';

	/**
	 * What the empty canvas says on a landing page.
	 *
	 * Core's default is "Type / to choose a block", which is true of every post
	 * on the site and therefore says nothing about this one. A landing page has
	 * a first move, and this is it.
	 */
	const PLACEHOLDER = 'Add a Hero Slider to start this page';
	/**
	 * Whether the post has finished loading.
	 *
	 * The editor mounts before the post entity is fetched, and the template
	 * attribute arrives empty and then fills in. Without this, that second
	 * change looks exactly like a visitor choosing the template — and an
	 * existing landing page would have its banner setting rewritten every time
	 * it was opened.
	 *
	 * @return {boolean} True once the record has resolved.
	 */
	let original = null;

	const isPostReady = () => {
		const editor = select('core/editor');
		const core = select('core');

		if (!editor || !core) {
			return false;
		}

		const postId = editor.getCurrentPostId();
		const postType = editor.getCurrentPostType();

		if (!postId || !postType) {
			return false;
		}

		return core.hasFinishedResolution('getEntityRecord', [
			'postType',
			postType,
			postId,
		]);
	};

	/**
	 * The prompt on an empty canvas.
	 *
	 * `bodyPlaceholder` is the editor setting core's default block appender
	 * reads before falling back to its own wording — see `DefaultBlockAppender`
	 * in the block editor package. Changing the setting is how you change the
	 * sentence, and it is per editor session rather than per post type, which
	 * is why the original is put back the moment the template changes to
	 * something else.
	 *
	 * Guarded by comparison: `updateSettings` is a store change, and a store
	 * change runs this subscriber again.
	 *
	 * @param {boolean} landing Whether the landing template is in force.
	 */
	const setPlaceholder = (landing) => {
		const blockEditor = select('core/block-editor');

		if (!blockEditor) {
			return;
		}

		const current = blockEditor.getSettings().bodyPlaceholder;

		if (null === original) {
			original = current || '';
		}

		const wanted = landing ? PLACEHOLDER : original;

		if (current === wanted) {
			return;
		}

		dispatch('core/block-editor').updateSettings({
			bodyPlaceholder: wanted,
		});
	};

	/**
	 * A landing page has no title banner.
	 *
	 * Set as meta rather than defaulted in the four places that read it — the
	 * panel, the banner preview, the page-title block's editor view and its
	 * render.php all resolve `bridge_banner_style` — because a default taught
	 * to four readers is three chances for them to disagree. Written once, they
	 * all already agree, and the panel visibly says "No title" rather than
	 * saying "Standard" while behaving otherwise.
	 *
	 * Only over `none`, which is the registered default. A page that has asked
	 * for a contained or full-width banner has asked for it, and switching
	 * template is not a retraction; `hidden` is already the destination.
	 */
	const clearBanner = () => {
		const meta = select('core/editor').getEditedPostAttribute('meta') || {};

		if ('none' !== (meta.bridge_banner_style || 'none')) {
			return;
		}

		dispatch('core/editor').editPost({
			meta: { ...meta, bridge_banner_style: 'hidden' },
		});
	};

	const start = () => {
		if (
			!select('core/editor') ||
			!select('core/block-editor') ||
			!select('core')
		) {
			setTimeout(start, 100);
			return;
		}

		let known = false;
		let last = null;

		subscribe(() => {
			if (select('core/editor').getCurrentPostType() !== 'page') {
				return;
			}

			const template =
				select('core/editor').getEditedPostAttribute('template');

			setPlaceholder(template === TEMPLATE_SLUG);

			/*
			 * The banner is set when the template *changes*, never on load.
			 *
			 * Opening a page is not a decision about it. The first template
			 * this sees after the post has loaded is recorded and nothing else
			 * — only a change after that is the editor choosing a template, and
			 * only that writes anything.
			 */
			if (isPostReady()) {
				if (!known) {
					known = true;
					last = template;
				} else if (template !== last) {
					last = template;

					if (template === TEMPLATE_SLUG) {
						clearBanner();
					}
				}
			}
		});
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start, { once: true });
	} else {
		start();
	}
})(window.wp);
