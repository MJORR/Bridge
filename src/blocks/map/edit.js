/**
 * Editor view for `bridge/map`.
 *
 * A place finder in the block's settings, and a live map on the canvas with a
 * pin you can drag.
 *
 * ---- The one thing that makes this awkward --------------------------------
 *
 * The block editor draws the canvas in an iframe. The Inspector is not in it.
 * So the finder and the map are in two different documents, and the Maps
 * JavaScript API is not a thing you load once and use anywhere: a library
 * loaded into one window measuring, styling and listening to a node in another
 * is the kind of thing that half works — the element appears and nothing
 * responds to it.
 *
 * `boot()` therefore takes a window and keeps one promise per window. The
 * finder boots the window it is rendered in; the map boots the window its own
 * container belongs to, found from the node rather than assumed. Neither cares
 * where the other ended up.
 *
 * ---- Modern APIs only ------------------------------------------------------
 *
 * `importLibrary` for loading and `AutocompleteSuggestion` for the finder. The
 * legacy `Autocomplete` widget has not been served to new API projects since
 * 1 March 2025 and is not referenced here. No iframes anywhere.
 */

const { InspectorControls, useBlockProps } = window.wp.blockEditor;
const {
	createElement: el,
	Fragment,
	useCallback,
	useEffect,
	useRef,
	useState,
} = window.wp.element;
const {
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	Notice,
	Spinner,
	ExternalLink,
} = window.wp.components;
const { __ } = window.wp.i18n;

const SETTINGS = window.bridgeMap || {};
const KEY = SETTINGS.key || '';
const MAP_ID = SETTINGS.mapId || '';

/**
 * The Maps JavaScript API, loaded once per window.
 *
 * The documented async bootstrap — one script, then `importLibrary` per piece.
 * Keyed by window because the editor has two of them and each needs its own
 * copy; keyed at all because Google's loader is a global side effect and a
 * second script in the same window throws rather than resolving twice.
 *
 * @param {Window} win The window to load into.
 * @return {Promise} Resolves with that window's `google.maps`.
 */
const booted = new WeakMap();

function boot(win) {
	if (booted.has(win)) {
		return booted.get(win);
	}

	const promise = new Promise((resolve, reject) => {
		if (win.google?.maps?.importLibrary) {
			resolve(win.google.maps);
			return;
		}

		const callback = '__bridgeMapsReady';
		const doc = win.document;

		win[callback] = () => resolve(win.google.maps);

		const script = doc.createElement('script');

		script.src =
			'https://maps.googleapis.com/maps/api/js' +
			`?key=${encodeURIComponent(KEY)}&v=weekly&loading=async` +
			`&callback=${callback}`;
		script.async = true;
		script.onerror = () => reject(new Error('maps'));

		doc.head.appendChild(script);
	});

	booted.set(win, promise);

	return promise;
}

/**
 * The place finder, in the Inspector.
 *
 * A plain WordPress `TextControl` and a list of suggestions under it, talking
 * to the Places API directly.
 *
 * ---- Why not Google's own element ----------------------------------------
 *
 * `PlaceAutocompleteElement` renders a sealed input: its shadow root is closed,
 * so `::part()` selects nothing, `font-size` on the host is ignored, and the
 * clear button cannot be removed. Measured four ways, it comes out 50px tall
 * whatever it is told — which is a foreign control sitting in a column of
 * WordPress ones, at the wrong size, with a button that does not belong.
 *
 * `AutocompleteSuggestion` is the same data one layer down, and drawing the
 * list is a dozen lines. So the field is a `TextControl` like every other field
 * in the panel, and the suggestions are buttons.
 *
 * ---- Session tokens --------------------------------------------------------
 *
 * Google bills a run of keystrokes plus the pick that ends it as one session,
 * but only when they share a token. One is minted per search and replaced the
 * moment a place is chosen, which is what makes a search cost a search.
 *
 * @param {Object} props Component props.
 */
function PlaceFinder({ address, onPick }) {
	const [query, setQuery] = useState(address || '');
	const [suggestions, setSuggestions] = useState([]);
	const [state, setState] = useState('idle');
	const token = useRef(null);
	const typed = useRef(false);

	const latest = useRef(onPick);
	latest.current = onPick;

	useEffect(() => {
		// Nothing on mount: the saved address is already the answer, and
		// searching for it again would spend a request to say so.
		if (!typed.current || query.trim().length < 3) {
			setSuggestions([]);
			return undefined;
		}

		let dead = false;

		// One request per pause, not one per keystroke.
		const timer = setTimeout(() => {
			boot(window)
				.then(async (maps) => {
					const places = await maps.importLibrary('places');

					if (!token.current) {
						token.current = new places.AutocompleteSessionToken();
					}

					const { suggestions: found } =
						await places.AutocompleteSuggestion.fetchAutocompleteSuggestions(
							{
								input: query,
								sessionToken: token.current,
							}
						);

					if (dead) {
						return;
					}

					setSuggestions(found || []);
					setState('idle');
				})
				.catch(() => !dead && setState('error'));
		}, 300);

		return () => {
			dead = true;
			clearTimeout(timer);
		};
	}, [query]);

	const choose = async (suggestion) => {
		const place = suggestion.placePrediction.toPlace();

		// The new API hands back a Place carrying nothing until it is asked.
		await place.fetchFields({
			fields: ['location', 'formattedAddress', 'id', 'displayName'],
		});

		const point = place.location;

		if (!point) {
			return;
		}

		const chosen = place.formattedAddress || place.displayName || '';

		latest.current({
			lat: typeof point.lat === 'function' ? point.lat() : point.lat,
			lng: typeof point.lng === 'function' ? point.lng() : point.lng,
			placeId: place.id || '',
			address: chosen,
		});

		// The session ends with the pick it paid for.
		token.current = null;
		typed.current = false;
		setQuery(chosen);
		setSuggestions([]);
	};

	return el(
		Fragment,
		null,
		el(TextControl, {
			label: __('Find a place', 'bridge'),
			help: __(
				'Search, then drag the pin on the map to the exact point.',
				'bridge'
			),
			value: query,
			autoComplete: 'off',
			onChange: (value) => {
				typed.current = true;
				setQuery(value);
			},
			__nextHasNoMarginBottom: true,
		}),
		!!suggestions.length &&
			el(
				'ul',
				{ className: 'bridge-map__suggestions' },
				suggestions.map((suggestion, index) =>
					el(
						'li',
						{ key: index },
						el(
							'button',
							{
								type: 'button',
								onClick: () => choose(suggestion),
							},
							suggestion.placePrediction.text.toString()
						)
					)
				)
			),
		state === 'error' &&
			el(
				Notice,
				{ status: 'warning', isDismissible: false },
				__(
					'Places could not be reached. Check that Places API (New) is enabled for the key.',
					'bridge'
				)
			)
	);
}

/**
 * The live map, on the canvas.
 *
 * Boots the API into the canvas iframe's own window — see the file header for
 * why that is not the same window this file is running in.
 *
 * @param {Object} props Component props.
 */
function LiveMap({ lat, lng, zoom, onPick, onZoom }) {
	const host = useRef(null);
	const map = useRef(null);
	const marker = useRef(null);
	const [state, setState] = useState('loading');

	const latest = useRef({ onPick, onZoom, lat, lng, zoom });
	latest.current = { onPick, onZoom, lat, lng, zoom };

	useEffect(() => {
		const node = host.current;

		if (!node) {
			return undefined;
		}

		let dead = false;
		const listeners = [];

		boot(node.ownerDocument.defaultView)
			.then(async (maps) => {
				const [{ Map }, { AdvancedMarkerElement, Marker }] =
					await Promise.all([
						maps.importLibrary('maps'),
						maps.importLibrary('marker'),
					]);

				if (dead || !node.isConnected) {
					return;
				}

				const at = latest.current;
				const placed =
					typeof at.lat === 'number' && typeof at.lng === 'number';

				// Any centre would do before a place is chosen; this one makes
				// "nothing picked yet" look deliberate rather than like a map
				// of the Atlantic.
				const centre = placed
					? { lat: at.lat, lng: at.lng }
					: { lat: 51.5072, lng: -0.1276 };

				map.current = new Map(node, {
					center: centre,
					zoom: at.zoom,
					mapTypeControl: false,
					streetViewControl: false,
					fullscreenControl: false,
					// The style saved in the Cloud console, when the site has
					// one. Sent only when it does: `mapId: undefined` and no
					// mapId at all are not the same thing to Google.
					...(MAP_ID ? { mapId: MAP_ID } : {}),
				});

				// The pin is a control, not a report: dragging it is how the
				// marker lands on the entrance rather than on whatever the
				// geocoder thought the postcode was.
				//
				// The modern marker needs a Map ID before it will draw, so
				// which one is used is decided by the setting, not taste.
				const drag = (position) =>
					latest.current.onPick({
						lat: position.lat(),
						lng: position.lng(),
						// The words no longer describe the point, and new ones
						// would cost a reverse lookup.
						placeId: '',
					});

				if (MAP_ID && AdvancedMarkerElement) {
					marker.current = new AdvancedMarkerElement({
						position: centre,
						map: map.current,
						gmpDraggable: true,
					});

					listeners.push(
						marker.current.addListener('dragend', (event) =>
							drag(event.latLng)
						)
					);
				} else {
					marker.current = new Marker({
						position: centre,
						map: map.current,
						draggable: true,
					});

					listeners.push(
						marker.current.addListener('dragend', (event) =>
							drag(event.latLng)
						)
					);
				}

				listeners.push(
					map.current.addListener('zoom_changed', () => {
						const next = map.current.getZoom();

						if (next) {
							latest.current.onZoom(next);
						}
					})
				);

				setState('ready');
			})
			.catch(() => !dead && setState('error'));

		return () => {
			dead = true;
			listeners.forEach((listener) => listener?.remove?.());
			map.current = null;
			marker.current = null;
		};
	}, []);

	// The block's attributes are the source of truth; the map follows them.
	useEffect(() => {
		if (
			state !== 'ready' ||
			typeof lat !== 'number' ||
			typeof lng !== 'number'
		) {
			return;
		}

		// `AdvancedMarkerElement` takes a plain object on `position`; the
		// classic marker wants its setter. One line each rather than a wrapper.
		if ('setPosition' in marker.current) {
			marker.current.setPosition({ lat, lng });
		} else {
			marker.current.position = { lat, lng };
		}

		map.current.panTo({ lat, lng });
	}, [state, lat, lng]);

	useEffect(() => {
		if (state === 'ready' && map.current.getZoom() !== zoom) {
			map.current.setZoom(zoom);
		}
	}, [state, zoom]);

	return el(
		Fragment,
		null,
		el('div', { ref: host, className: 'bridge-map__canvas' }),
		state === 'loading' &&
			el('div', { className: 'bridge-map__loading' }, el(Spinner)),
		state === 'error' &&
			el(
				'div',
				{ className: 'bridge-map__loading' },
				el(
					'p',
					{ className: 'bridge-map__note' },
					__(
						'Google Maps could not be loaded. Check the key and its enabled APIs.',
						'bridge'
					)
				)
			)
	);
}

const Edit = ({ attributes, setAttributes }) => {
	const { address, lat, lng, zoom, height, label, width } = attributes;

	const pick = useCallback((next) => setAttributes(next), [setAttributes]);
	const setZoom = useCallback(
		(next) => setAttributes({ zoom: next }),
		[setAttributes]
	);

	const blockProps = useBlockProps({
		className: [
			'bridge-map',
			'bridge-section',
			'bridge-band',
			'alignfull',
			`bridge-map--${width}`,
			// Full width means the map reaches the glass, so the band stops
			// spending a gutter on it. Matches render.php.
			width === 'full' ? 'bridge-band--flush' : '',
		]
			.filter(Boolean)
			.join(' '),
	});

	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Map settings', 'bridge'), initialOpen: true },
				KEY
					? el(PlaceFinder, { address, onPick: pick })
					: el(TextControl, {
							label: __('Address', 'bridge'),
							help: __(
								'A postal address, a place name, or a "latitude,longitude" pair.',
								'bridge'
							),
							value: address,
							onChange: (value) =>
								setAttributes({ address: value }),
							__nextHasNoMarginBottom: true,
						}),
				el(TextControl, {
					label: __('Caption', 'bridge'),
					help: __(
						'Printed under the map. Optional — it does not move the pin.',
						'bridge'
					),
					value: label,
					onChange: (value) => setAttributes({ label: value }),
					__nextHasNoMarginBottom: true,
				}),
				el(RangeControl, {
					label: __('Zoom', 'bridge'),
					value: zoom,
					min: 1,
					max: 21,
					onChange: (value) => setAttributes({ zoom: value || 14 }),
					__nextHasNoMarginBottom: true,
				}),
				el(SelectControl, {
					label: __('Width', 'bridge'),
					help:
						width === 'full'
							? __(
									'The map runs to both edges of the window.',
									'bridge'
								)
							: __('The container the map sits in.', 'bridge'),
					value: width,
					options: [
						{ label: __('Wide', 'bridge'), value: 'wide' },
						{ label: __('Narrow', 'bridge'), value: 'narrow' },
						{ label: __('Full window', 'bridge'), value: 'full' },
					],
					onChange: (value) => setAttributes({ width: value }),
					__nextHasNoMarginBottom: true,
				}),
				el(RangeControl, {
					label: __('Height (px)', 'bridge'),
					value: height,
					min: 160,
					max: 1200,
					step: 20,
					onChange: (value) =>
						setAttributes({ height: value || 420 }),
					__nextHasNoMarginBottom: true,
				}),
				!KEY &&
					SETTINGS.optionsUrl &&
					el(
						'p',
						{ className: 'bridge-map__note' },
						__(
							'Add a Google Maps API key to search for places and place the pin on a map.',
							'bridge'
						),
						' ',
						el(
							ExternalLink,
							{ href: SETTINGS.optionsUrl },
							__('Site Options', 'bridge')
						)
					)
			)
		),
		el(
			'section',
			blockProps,
			el(
				'div',
				{
					className: 'bridge-map__inner',
					style: { '--bridge-map-height': `${height}px` },
				},
				KEY
					? el(LiveMap, {
							lat,
							lng,
							zoom,
							onPick: pick,
							onZoom: setZoom,
						})
					: el(
							'div',
							{ className: 'bridge-map__empty' },
							__(
								'Add a Google Maps API key in Site Options to show a map.',
								'bridge'
							)
						),
				label && el('p', { className: 'bridge-map__label' }, label)
			)
		)
	);
};

export default Edit;
