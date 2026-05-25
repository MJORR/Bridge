/**
 * Bridge — Landing Page editor enhancer.
 *
 * Behavior when a Page is assigned the "Landing Page" template:
 *   1. Hide the post-title field — the hero acts as the headline.
 *   2. Auto-insert a Hero Slider block at the top of the content if no
 *      slider exists yet. Any existing content stays below it.
 *   3. Seed the first slide's H1 with the current page title.
 *
 * Reverting the template removes the body class so the title reappears
 * and resets the per-session insert lock.
 */

(function (wp) {
	const { select, dispatch, subscribe } = wp.data;
	const { createBlock } = wp.blocks;

	const TEMPLATE_SLUG = 'page-landing';
	const HERO_BLOCK = 'bridge/hero-slider';
	const BODY_CLASS = 'bridge-landing-mode';

	let lastTemplate = null;
	let didAutoInsert = false;

	const setLandingMode = (enabled) => {
		document.body.classList.toggle(BODY_CLASS, enabled);
		document.querySelectorAll('iframe[name="editor-canvas"]').forEach((iframe) => {
			const doc = iframe.contentDocument;
			if (doc && doc.body) {
				doc.body.classList.toggle(BODY_CLASS, enabled);
			}
		});
	};

	const hasHeroSlider = () =>
		select('core/block-editor').getBlocks().some((block) => block.name === HERO_BLOCK);

	/**
	 * The editor mounts before the post entity is fully fetched. Inserting
	 * blocks during that window gets discarded when the saved content
	 * hydrates. Gate the insert on the entity resolver finishing.
	 */
	const isPostReady = () => {
		const editor = select('core/editor');
		const core = select('core');
		if (!editor || !core) return false;
		const postId = editor.getCurrentPostId();
		const postType = editor.getCurrentPostType();
		if (!postId || !postType) return false;
		return core.hasFinishedResolution('getEntityRecord', ['postType', postType, postId]);
	};

	const coverAttrs = {
		dimRatio: 50,
		minHeight: 80,
		minHeightUnit: 'vh',
		isUserOverlayColor: true,
		contentPosition: 'center center',
	};

	const buildHeroSlider = (pageTitle) =>
		createBlock(HERO_BLOCK, { align: 'full' }, [
			createBlock('core/cover', coverAttrs, [
				createBlock('core/heading', {
					level: 1,
					textAlign: 'center',
					content: pageTitle || '',
					placeholder: 'Slide title…',
				}),
				createBlock('core/paragraph', {
					align: 'center',
					placeholder: 'Optional subtitle',
				}),
			]),
			createBlock('core/cover', coverAttrs, [
				createBlock('core/heading', {
					level: 1,
					textAlign: 'center',
					placeholder: 'Slide title…',
				}),
			]),
		]);

	const autoInsertHero = () => {
		if (didAutoInsert || hasHeroSlider() || !isPostReady()) return;
		const title = select('core/editor').getEditedPostAttribute('title') || '';
		dispatch('core/block-editor').insertBlock(buildHeroSlider(title), 0);
		didAutoInsert = true;
	};

	const start = () => {
		if (!select('core/editor') || !select('core/block-editor') || !select('core')) {
			setTimeout(start, 100);
			return;
		}

		subscribe(() => {
			if (select('core/editor').getCurrentPostType() !== 'page') return;

			const template = select('core/editor').getEditedPostAttribute('template');

			// Reset per-session insert lock when the user changes templates.
			if (template !== lastTemplate) {
				lastTemplate = template;
				didAutoInsert = false;
				setLandingMode(template === TEMPLATE_SLUG);
			} else if (template === TEMPLATE_SLUG) {
				// Re-assert the body class — React may have re-rendered the iframe body.
				setLandingMode(true);
			}

			// Attempt insert whenever conditions are right; subscribe fires on
			// every store change, so we'll get a chance once the post is ready.
			if (template === TEMPLATE_SLUG) {
				autoInsertHero();
			}
		});
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start, { once: true });
	} else {
		start();
	}
})(window.wp);
