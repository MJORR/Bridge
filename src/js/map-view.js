/**
 * Bridge — the front end's Google map.
 *
 * Every `[data-bridge-map]` on the page becomes a real Maps JavaScript API map
 * with a marker on it. A scripted map is what a custom pin, a map style and
 * pan-and-zoom need; an embedded frame can do none of them.
 *
 * Everything it needs is on the container's data attributes — the key, the
 * coordinates, the zoom, the marker's title. No inline script, no global, no
 * per-page configuration object: two maps on one page are two containers and
 * one copy of this file.
 *
 * ---- The API is not loaded until a map is in view -------------------------
 *
 * ~200KB and a billable map load per view. A map at the foot of a contact page
 * costs a visitor nothing until they scroll to it, which is what the iframe's
 * `loading="lazy"` used to buy and what an IntersectionObserver buys back.
 *
 * A browser without IntersectionObserver loads it immediately.
 */

const SELECTOR = '[data-bridge-map]';

/**
 * Load the Maps JavaScript API once per page, however many maps ask.
 *
 * Google's loader is a global side effect: a second <script> for the same key
 * throws rather than resolving twice. So the promise is memoised and every
 * caller after the first is handed the same one.
 *
 * @param {string} key The API key, from the container.
 * @return {Promise} Resolves with the `google.maps` namespace.
 */
let mapsPromise = null;

function loadMaps(key) {
	if (mapsPromise) {
		return mapsPromise;
	}

	mapsPromise = new Promise((resolve, reject) => {
		if (window.google?.maps) {
			resolve(window.google.maps);
			return;
		}

		const callback = 'bridgeMapViewReady';

		window[callback] = () => resolve(window.google.maps);

		const script = document.createElement('script');

		script.src =
			'https://maps.googleapis.com/maps/api/js' +
			`?key=${encodeURIComponent(key)}&v=weekly&loading=async` +
			`&callback=${callback}`;
		script.async = true;
		script.onerror = reject;

		document.head.appendChild(script);
	});

	return mapsPromise;
}

/**
 * Turn one container into a map.
 *
 * @param {Element} node The container.
 */
function build(node) {
	const key = node.dataset.key;

	if (!key) {
		return;
	}

	const lat = parseFloat(node.dataset.lat);
	const lng = parseFloat(node.dataset.lng);
	const placed = !Number.isNaN(lat) && !Number.isNaN(lng);

	if (!placed && !node.dataset.address) {
		return;
	}

	loadMaps(key)
		.then(async (maps) => {
			// A block saved before the finder existed carries an address and
			// no point. One geocode settles it — done here rather than in PHP
			// because the API is already loaded and the answer is a LatLng the
			// map can take directly.
			let position = placed ? { lat, lng } : null;

			if (!position) {
				const { Geocoder } = await maps.importLibrary('geocoding');
				const { results } = await new Geocoder().geocode({
					address: node.dataset.address,
				});

				if (!results?.length) {
					return;
				}

				position = results[0].geometry.location;
			}

			// A Map ID carries the style made in the Cloud console, and is
			// what an advanced marker needs before it will draw. Only sent
			// when the site has one, because `mapId: undefined` and no key at
			// all are not the same thing to Google.
			const mapId = node.dataset.mapId;

			const map = new maps.Map(node, {
				center: position,
				zoom: parseInt(node.dataset.zoom, 10) || 14,
				...(mapId ? { mapId } : {}),
				// The controls a visitor to a brochure site has a use for.
				// Street View and the map-type switcher are Google's product
				// rather than this page's, and both cover the pin on a phone.
				mapTypeControl: false,
				streetViewControl: false,
				fullscreenControl: true,
			});

			const { AdvancedMarkerElement, Marker } =
				await maps.importLibrary('marker');

			// The modern marker where the site has a Map ID to draw it on, and
			// the classic one where it does not — `AdvancedMarkerElement`
			// refuses to render without one, so this is not a preference.
			if (mapId && AdvancedMarkerElement) {
				new AdvancedMarkerElement({
					position,
					map,
					title: node.dataset.title || '',
				});
			} else {
				new Marker({
					position,
					map,
					title: node.dataset.title || '',
				});
			}
		})
		.catch(() => {
			// The key is wrong, restricted to another domain, or the Maps
			// JavaScript API is not enabled on it. Google logs the reason to
			// the console; this stops the container sitting there as an empty
			// box with a role it is not fulfilling.
			node.removeAttribute('role');
			node.removeAttribute('aria-label');
		});
}

/**
 * Build each map the first time it comes into view.
 */
function init() {
	const nodes = Array.from(document.querySelectorAll(SELECTOR));

	if (!nodes.length) {
		return;
	}

	if (!('IntersectionObserver' in window)) {
		nodes.forEach(build);
		return;
	}

	const observer = new IntersectionObserver(
		(entries) => {
			entries.forEach((entry) => {
				if (!entry.isIntersecting) {
					return;
				}

				// Once only: the map replaces the container's contents, and a
				// second build would throw the first one away mid-scroll.
				observer.unobserve(entry.target);
				build(entry.target);
			});
		},
		// A screen's worth of warning, so the map is drawn by the time it
		// arrives rather than assembling itself while it is being looked at.
		{ rootMargin: '200px' }
	);

	nodes.forEach((node) => observer.observe(node));
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}
