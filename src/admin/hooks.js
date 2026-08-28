/**
 * Bridge — Theme Options, hooks.
 */

const { useEffect } = wp.element;

/**
 * Load the selected Google families into the admin page so the preview can
 * actually render them.
 *
 * This is the one place the theme talks to Google at render time, and it is
 * deliberate: the options screen is behind authentication and only operators
 * reach it, so no visitor's IP is ever handed over. It also has to be the
 * CDN rather than the self-hosted copies — a family is downloaded on *save*,
 * and a preview that could only show fonts you had already committed to would
 * be no preview at all.
 *
 * The front end never touches this path. It serves the downloaded woff2 files
 * and nothing else.
 *
 * @param {string[]} families Family names currently selected.
 */
export function useGooglePreviewFonts(families) {
	const key = families.filter(Boolean).sort().join('|');

	useEffect(() => {
		const id = 'bridge-google-preview';
		const wanted = key ? key.split('|') : [];
		let link = document.getElementById(id);

		if (!wanted.length) {
			if (link) {
				link.remove();
			}

			return;
		}

		if (!link) {
			link = document.createElement('link');
			link.id = id;
			link.rel = 'stylesheet';
			document.head.appendChild(link);
		}

		// 400 and 700 only: every family in the catalogue publishes both, and
		// the CSS2 API fails the whole request if any one family lacks a
		// requested weight. Intermediate weights synthesise well enough for a
		// preview.
		const query = wanted
			.map(
				(family) => `family=${encodeURIComponent(family)}:wght@400;700`
			)
			.join('&');

		link.href = `https://fonts.googleapis.com/css2?${query}&display=swap`;
	}, [key]);
}
