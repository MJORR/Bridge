/**
 * Editor view for `bridge/hero-banner`.
 *
 * The banner draws its own background — a photograph, a colour, that colour
 * graded, a colour panel with a picture beside it, or a run of colour bands at
 * an angle over the picture — and holds one column of ordinary blocks. It used to be a locked `core/cover` with a columns block
 * inside it, which meant the background was configured in Cover's own controls
 * two levels down the block tree and the layout was a template nobody could
 * change without unlocking it. Both are settings here instead.
 *
 * The canvas renders the same tree render.php prints — media element, content
 * element, same class names — so one stylesheet paints both and the preview is
 * the page.
 */

const {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	BlockControls,
	MediaUpload,
	MediaUploadCheck,
	ColorPalette,
} = window.wp.blockEditor;
const {
	createElement: el,
	Fragment,
	useEffect,
	useRef,
	useState,
} = window.wp.element;
const { useSelect } = window.wp.data;
const {
	PanelBody,
	BaseControl,
	Button,
	ToolbarGroup,
	ToolbarButton,
	ToggleControl,
	RangeControl,
	SelectControl,
	FocalPointPicker,
} = window.wp.components;
const { __ } = window.wp.i18n;

/**
 * The chosen height, as a CSS length, for the editor canvas.
 *
 * bridge_hero_metrics() in inc/hero-blocks.php is authoritative — it is what
 * the front end renders — and this is the preview's copy of the same four
 * presets. The two lists have to agree. It lives here rather than in a shared
 * module because every editor bundle is wrapped as a self-contained IIFE, so a
 * module imported by two entries becomes a chunk the wrapper cannot import.
 *
 * The header allowance is deliberately not repeated: the canvas is not the
 * window, and the stylesheet zeroes the inset for the preview for that reason.
 *
 * @param {string} preset One of full, tall, medium, custom.
 * @param {number} size   Custom height, when the preset is custom.
 * @param {string} unit   Custom unit, when the preset is custom.
 * @return {string} A CSS length.
 */
const heroHeight = (preset, size, unit) => {
	if (preset === 'tall') {
		return '80dvh';
	}

	if (preset === 'medium') {
		return '60dvh';
	}

	if (preset === 'custom') {
		const safeUnit = ['vh', 'dvh', 'px'].includes(unit) ? unit : 'vh';
		const safeSize = Math.max(20, Math.min(4000, Number(size) || 80));

		return `${safeSize}${safeUnit}`;
	}

	return '100dvh';
};

/**
 * Is this colour light enough to carry dark type?
 *
 * bridge_is_light_color() in functions.php, in JavaScript: the same weights and
 * the same threshold, because the canvas has to reach the same answer the front
 * end will. Without it the preview would paint a Primary banner's headline in
 * whichever colour the editor's own styles happened to give it, and an editor
 * choosing a ground would be choosing it against the wrong label.
 *
 * @param {string} hex A colour, as `#rgb` or `#rrggbb`.
 * @return {boolean} Whether dark type reads on it.
 */
const isLight = (hex) => {
	const value = String(hex || '').replace('#', '');
	const full =
		value.length === 3
			? value
					.split('')
					.map((part) => part + part)
					.join('')
			: value;

	if (!/^[0-9a-f]{6}$/i.test(full)) {
		return false;
	}

	const channel = (at) => parseInt(full.slice(at, at + 2), 16);

	return (
		0.299 * channel(0) + 0.587 * channel(2) + 0.114 * channel(4) > 0.6 * 255
	);
};

/**
 * How far the curve's circle reaches past the band, as a multiple of its half
 * height. BRIDGE_HERO_CURVE_CROP in inc/hero-blocks.php, which explains it.
 */
const CURVE_CROP = 1.6;

/**
 * The radius at the round end of the Radius slider, how wide a hero is taken to
 * be for the word "circle" to mean anything, how many times taller than that
 * the tallest oval is, how far sideways the straight run beyond the arc may
 * travel per unit of height, and the bulge below which the edge is drawn
 * straight. BRIDGE_HERO_CURVE_ROUND, _ASPECT, _OVAL, _RUN and _FLAT in
 * inc/hero-blocks.php, which explain all five.
 */
const CURVE_RUN = 1;

/**
 * How far the picture may travel off the middle of the band, each way.
 * BRIDGE_HERO_FOCAL_TRAVEL in inc/hero-blocks.php, which explains why half.
 */
const FOCAL_TRAVEL = 50;

/**
 * How much the picture has to grow to stay behind the move.
 *
 * bridge_hero_focal_zoom() in inc/hero-blocks.php is authoritative — it is what
 * the front end publishes — and this is the canvas's copy of the same
 * arithmetic, kept here for the reason every other duplicated sum in this file
 * is: every editor bundle is wrapped as a self-contained IIFE, so a module
 * shared with another entry becomes a chunk the wrapper cannot import.
 *
 * The short of it: the picture is scaled about its centre and then moved, so at
 * `z` it reaches `z / 2` of a band either side and a move of `t` leaves its near
 * edge at `t - z / 2`. Holding that at the band's own edge gives `z = 1 + 2|t|`,
 * the smallest scale that never shows the ground through.
 *
 * @param {Object} point The focal point, two shares of the picture.
 * @return {number} A scale factor, 1 or greater.
 */
const focalZoom = (point) =>
	1 + 2 * Math.max(Math.abs(0.5 - point.x), Math.abs(0.5 - point.y));

/**
 * How many bands one banner may carry. BRIDGE_HERO_GRADIENT_MAX in
 * inc/hero-blocks.php, which is what actually enforces it — this is the number
 * Add band stops at, so an operator is never offered a band that would be
 * dropped on save.
 */
const GRADIENT_MAX = 8;

/**
 * What a new band starts as: a solid stretch of the brand colour fading to
 * nothing across the middle third of the banner. The same shape block.json
 * defaults to, because it is the shape this background is for — solid where the
 * words are, gone by the time the picture matters.
 *
 * Only Reset uses the two positions. Add band overrides them, because a new
 * band picks up where the last one finished rather than landing on top of it.
 */
const GRADIENT_BAND = {
	start: 30,
	end: 60,
	from: 'primary',
	fromAlpha: 100,
	to: 'primary',
	toAlpha: 0,
};

/**
 * What Reset puts back. The same band block.json defaults to, for the reason
 * CURVE_DEFAULTS gives.
 */
const GRADIENT_DEFAULTS = {
	gradientAngle: 90,
	gradientStops: [{ ...GRADIENT_BAND }],
};

/**
 * The gradient's line, as a unit vector in screen coordinates.
 *
 * CSS measures a gradient angle clockwise from straight up, and the screen's y
 * axis points down — so `0deg` is `(0, -1)` and `90deg` is `(1, 0)`, which is
 * `to right`. Getting this wrong is not subtle: the handles run the opposite
 * way down the banner from the colours they are holding.
 *
 * @param {number} angle Degrees.
 * @return {Object} The unit vector.
 */
const gradientAxis = (angle) => {
	const radians = (angle * Math.PI) / 180;

	return { dx: Math.sin(radians), dy: -Math.cos(radians) };
};

/**
 * How long that line is across a box this size.
 *
 * Not the width of the box, and not its diagonal: the gradient line runs
 * through the centre at the angle, and it is exactly long enough that the two
 * corners nearest its ends project onto its endpoints — `|W·sin a| + |H·cos a|`
 * (CSS Images 3, §3.4). This is the whole reason the rail is drawn from a
 * measurement rather than from corner to corner: at 45° on a wide banner the
 * gradient line is half as long again as the banner is wide, and 50% on it is
 * nowhere near the middle of the diagonal.
 *
 * @param {number} angle  Degrees.
 * @param {number} width  The band's width, in pixels.
 * @param {number} height The band's height, in pixels.
 * @return {number} The length, in pixels.
 */
const gradientLength = (angle, width, height) => {
	const radians = (angle * Math.PI) / 180;

	return (
		Math.abs(width * Math.sin(radians)) +
		Math.abs(height * Math.cos(radians))
	);
};

/**
 * Where a percentage along that line lands, in the band's own coordinates.
 *
 * @param {number} percent 0–100.
 * @param {number} angle   Degrees.
 * @param {Object} box     The band's measured width and height.
 * @return {Object} A point in pixels from the band's top-left.
 */
const gradientPoint = (percent, angle, box) => {
	const { dx, dy } = gradientAxis(angle);
	const along = (percent / 100 - 0.5) * gradientLength(angle, box.w, box.h);

	return { x: box.w / 2 + dx * along, y: box.h / 2 + dy * along };
};

/**
 * And back: where a point in the band falls along the line, as a percentage.
 *
 * The projection of the pointer onto the axis, which is what makes the drag
 * forgiving — an operator dragging a handle does not have to stay on the rail,
 * only to move along it, and however far off the line the pointer strays the
 * handle tracks the part of the movement that counts.
 *
 * @param {number} x     Pixels from the band's left edge.
 * @param {number} y     Pixels from the band's top edge.
 * @param {number} angle Degrees.
 * @param {Object} box   The band's measured width and height.
 * @return {number} 0–100.
 */
const gradientPercent = (x, y, angle, box) => {
	const { dx, dy } = gradientAxis(angle);
	const length = gradientLength(angle, box.w, box.h) || 1;
	const along = (x - box.w / 2) * dx + (y - box.h / 2) * dy;

	return Math.max(0, Math.min(100, (along / length + 0.5) * 100));
};

/**
 * The bands, as one `linear-gradient()`.
 *
 * bridge_hero_gradient_css() in inc/hero-blocks.php is authoritative — it is
 * what the front end prints — and this says the same thing for the canvas, kept
 * here for the reason every other piece of arithmetic in this file is
 * duplicated: every editor bundle is wrapped as a self-contained IIFE, so a
 * module shared with another entry becomes a chunk the wrapper cannot import.
 *
 * Two stops per band, plus a flat pair across any clear air between one band
 * and the next. The PHP has the long version of why.
 *
 * @param {Array}  bands The bands, already sorted.
 * @param {number} angle Degrees.
 * @return {string} A `linear-gradient()`, or '' when there are no bands.
 */
const gradientCss = (bands, angle) => {
	if (!bands.length) {
		return '';
	}

	const color = (slug, alpha) => {
		const value = `var(--wp--preset--color--${slug})`;

		return alpha >= 100
			? value
			: `color-mix(in srgb, ${value} ${alpha}%, transparent)`;
	};

	const stops = [];
	let held = null;
	let at = null;

	bands.forEach((band) => {
		const from = color(band.from, band.fromAlpha);
		const to = color(band.to, band.toAlpha);

		if (held !== null && band.start > at) {
			stops.push(`${held} ${at}%`, `${held} ${band.start}%`);
		}

		stops.push(`${from} ${band.start}%`, `${to} ${band.end}%`);
		held = to;
		at = band.end;
	});

	return `linear-gradient(${angle}deg, ${stops.join(', ')})`;
};

/**
 * The bands in the order they are laid across the banner.
 *
 * bridge_hero_gradient() sorts before it builds, so the canvas has to as well
 * or the two draw different pictures from the same attributes. It is a copy
 * rather than a sort in place: the array is block state, and the drag handlers
 * below have to be able to work out which band an index belongs to after a
 * handle has been dragged past its neighbour.
 *
 * @param {Array} bands The stored bands.
 * @return {Array} The same bands, ascending, with every value made a number.
 */
const gradientOrder = (bands) =>
	(Array.isArray(bands) ? bands : [])
		.map((band) => ({
			start: Math.max(0, Math.min(100, Number(band?.start) || 0)),
			end: Math.max(0, Math.min(100, Number(band?.end ?? 100))),
			from: band?.from || 'primary',
			fromAlpha: Math.max(
				0,
				Math.min(100, Number(band?.fromAlpha ?? 100))
			),
			to: band?.to || 'primary',
			toAlpha: Math.max(0, Math.min(100, Number(band?.toAlpha ?? 0))),
		}))
		.map((band) => ({ ...band, end: Math.max(band.start, band.end) }))
		.sort((a, b) => a.start - b.start)
		// And no band overlapping the one before it: the earlier one keeps the
		// ground it covers and the later one starts where it finishes.
		// bridge_hero_gradient() says at length why — two bands sharing a
		// stretch of the line is a stop list that goes backwards, which a
		// browser turns into a hard edge nobody asked for. The handles below
		// cannot be dragged across a neighbour, so this only ever catches
		// hand-edited markup.
		.reduce((kept, band) => {
			const edge = kept.length ? kept[kept.length - 1].end : 0;
			const start = Math.max(edge, band.start);

			kept.push({ ...band, start, end: Math.max(start, band.end) });

			return kept;
		}, []);

/**
 * The picture, dragged into place on the banner itself.
 *
 * ---- Why it is a surface and not a handle ---------------------------------
 *
 * Because the thing being moved is the whole photograph, and the gesture for
 * moving a photograph is pushing it. There is no point on it that is the
 * subject, so there is no point on it to put a grip; what an operator wants is
 * to take hold of the part they are looking at and slide it to where they want
 * it, which is what a surface over the band gives them.
 *
 * ---- Why it is a mode -----------------------------------------------------
 *
 * Because the banner is a block people spend most of their time typing into.
 * The content is a grid item the size of the band and sits above the media, so
 * a drag surface under the words would never see a pointer and one above them
 * would swallow every click meant for a heading. The toolbar toggle is what
 * makes the trade explicit instead of arbitrary, and it costs one press.
 *
 * ---- What a drag actually changes -----------------------------------------
 *
 * The focal point, which is what the sidebar's picker sets and what the front
 * end already spends — so nothing new is stored and the two controls are two
 * ways of saying the same thing. A share of the band per pixel dragged, and
 * the sign is inverted on both axes: dragging the picture right means showing
 * more of its left, which is a *smaller* x.
 *
 * The scale follows on its own, because focalZoom() is derived from the point.
 * So the picture grows as it is pushed and the band never shows through, which
 * is the whole reason the drag is worth having on the banner rather than on a
 * thumbnail.
 *
 * @param {Object} props The focal point and the setter.
 * @return {Object} The overlay.
 */
const ReframeSurface = ({ point, onChange }) => {
	const frame = useRef(null);
	const origin = useRef(null);
	const [dragging, setDragging] = useState(false);
	const [moved, setMoved] = useState(false);

	// Where the pointer started and what the point was then, so the picture
	// tracks the cursor exactly rather than accumulating rounding per frame —
	// a delta-per-move drag drifts away from the pointer over a long push.
	const start = (event) => {
		event.preventDefault();
		event.stopPropagation();
		event.currentTarget.setPointerCapture(event.pointerId);

		origin.current = {
			x: event.clientX,
			y: event.clientY,
			point: { ...point },
		};
		setDragging(true);
	};

	const move = (event) => {
		if (!dragging || !origin.current || !frame.current) {
			return;
		}

		const rect = frame.current.getBoundingClientRect();

		if (rect.width <= 0 || rect.height <= 0) {
			return;
		}

		const clamp = (value) => Math.max(0, Math.min(1, value));

		setMoved(true);
		onChange({
			x: clamp(
				origin.current.point.x -
					(event.clientX - origin.current.x) / rect.width
			),
			y: clamp(
				origin.current.point.y -
					(event.clientY - origin.current.y) / rect.height
			),
		});
	};

	const stop = () => {
		origin.current = null;
		setDragging(false);
	};

	return el(
		'div',
		{
			ref: frame,
			className: [
				'bridge-hero-banner__reframe',
				dragging && 'is-dragging',
			]
				.filter(Boolean)
				.join(' '),
			onPointerDown: start,
			onPointerMove: move,
			onPointerUp: stop,
			onPointerCancel: stop,
			// A drag on the banner is not a click on the block, and without
			// this the editor treats the mouse-up as one and moves the
			// selection out from under the mode.
			onClick: (event) => event.stopPropagation(),
		},
		!moved &&
			el(
				'div',
				{ className: 'bridge-hero-banner__reframe-hint' },
				__(
					'Drag the picture to reframe it. It grows as it moves, so the banner never shows through.',
					'bridge'
				)
			)
	);
};

/**
 * The tool laid over the preview: a rail where the gradient actually runs, a
 * handle at each end of each band, and one more past the far end for the angle.
 *
 * ---- Why this is on the banner and not in the sidebar ---------------------
 *
 * Because what is being set is where a colour stops, and where it stops is a
 * place on the picture. Nudging a number in the Inspector and looking up to see
 * what happened is a guess-and-check loop with six inches between the control
 * and the answer. The sidebar keeps every number — for typing an exact one, and
 * for the settings that are genuinely numbers — and this puts the two that are
 * positions where the position is.
 *
 * ---- Why it measures rather than assuming ---------------------------------
 *
 * Because the gradient line is not the banner's width: it runs through the
 * centre at the angle and its length depends on both sides of the box. So the
 * band is measured with a ResizeObserver and the rail is drawn from that,
 * which is what makes 50% on the rail the same 50% the browser paints at every
 * angle and every window size.
 *
 * ---- Why the handles are buttons ------------------------------------------
 *
 * So they are in the tab order and take the arrow keys. A percentage is exactly
 * the kind of value a pointer is bad at landing exactly on and a keyboard is
 * good at, and a drag-only control is one an operator who cannot use a mouse
 * has no way to reach at all.
 *
 * @param {Object} props The angle, the ordered bands, the two setters and the
 *                       travel each handle is allowed.
 * @return {Object} The overlay.
 */
const GradientTool = ({ angle, bands, range, onBand, onAngle }) => {
	const frame = useRef(null);
	const [box, setBox] = useState({ w: 0, h: 0 });
	const [dragging, setDragging] = useState(null);

	// Measured rather than assumed, and re-measured rather than measured once:
	// the canvas resizes with the sidebar, the device preview and the window,
	// and a rail drawn against a stale width is a rail the handles have walked
	// off the end of.
	useEffect(() => {
		const node = frame.current;

		if (!node) {
			return undefined;
		}

		const measure = () =>
			setBox({ w: node.clientWidth, h: node.clientHeight });

		measure();

		if (typeof ResizeObserver === 'undefined') {
			return undefined;
		}

		const observer = new ResizeObserver(measure);

		observer.observe(node);

		return () => observer.disconnect();
	}, []);

	if (box.w <= 0 || box.h <= 0) {
		// Measured on the first paint, so this is one frame rather than a
		// state. Drawing a rail against a zero-width box would put every handle
		// in the corner for that frame, which reads as a flicker.
		return el('div', {
			ref: frame,
			className: 'bridge-hero-banner__gradient-tool',
			'aria-hidden': 'true',
		});
	}

	const ends = [
		{ x: 0, ...gradientPoint(0, angle, box) },
		{ x: 100, ...gradientPoint(100, angle, box) },
	];

	// Where the pointer is, in the band's own coordinates. `getBoundingClientRect`
	// on every move rather than once at the start of the drag, because the
	// canvas is a scrolling document and the banner moves under a drag that
	// reaches its edge.
	const pointerPercent = (event) => {
		const rect = frame.current.getBoundingClientRect();

		return gradientPercent(
			event.clientX - rect.left,
			event.clientY - rect.top,
			angle,
			box
		);
	};

	/**
	 * One handle, and the drag that moves it.
	 *
	 * The pointer is captured on the handle itself, so a drag that leaves the
	 * banner — or leaves the canvas iframe's visible area — keeps arriving here
	 * rather than being lost to whatever it passed over.
	 *
	 * @param {number} index Which band.
	 * @param {string} which 'start' or 'end'.
	 * @param {number} value The band's current percentage at that end.
	 * @param {string} label What the readout says while it is being dragged.
	 * @return {Object} The button.
	 */
	const handle = (index, which, value, label) => {
		const point = gradientPoint(value, angle, box);
		const id = `${index}:${which}`;
		const held = dragging === id;
		// How far this end may travel: up to its own other end, and no further
		// than the neighbour on that side. bandRange() in Edit decides, and
		// setBandEdge() enforces it — this is only what the handle announces,
		// so a screen reader reads the range the drag will actually respect.
		const { min, max } = range(index, which);

		const move = (percent) => onBand(index, which, Math.round(percent));

		return el(
			Fragment,
			{ key: id },
			el('button', {
				type: 'button',
				className: [
					'bridge-hero-banner__gradient-handle',
					held && 'is-dragging',
				]
					.filter(Boolean)
					.join(' '),
				style: { left: `${point.x}px`, top: `${point.y}px` },
				'aria-label': label,
				// A slider in every sense but the markup: the value, its range
				// and its meaning, so a screen reader reads a percentage rather
				// than announcing an unlabelled button.
				role: 'slider',
				'aria-valuemin': Math.round(min),
				'aria-valuemax': Math.round(max),
				'aria-valuenow': Math.round(value),
				'aria-valuetext': `${Math.round(value)}%`,
				onPointerDown: (event) => {
					event.preventDefault();
					event.stopPropagation();
					event.currentTarget.setPointerCapture(event.pointerId);
					setDragging(id);
				},
				onPointerMove: (event) => {
					if (dragging !== id) {
						return;
					}

					move(pointerPercent(event));
				},
				onPointerUp: () => setDragging(null),
				onPointerCancel: () => setDragging(null),
				onKeyDown: (event) => {
					// Ten at a time with Shift, one without — the same pair
					// every RangeControl in the sidebar offers, so the two
					// controls for the same number behave the same way.
					const step = event.shiftKey ? 10 : 1;
					const keys = {
						ArrowLeft: -step,
						ArrowDown: -step,
						ArrowRight: step,
						ArrowUp: step,
						Home: -100,
						End: 100,
					};

					if (!(event.key in keys)) {
						return;
					}

					event.preventDefault();
					move(value + keys[event.key]);
				},
			}),
			held &&
				el(
					'div',
					{
						className: 'bridge-hero-banner__gradient-readout',
						style: { left: `${point.x}px`, top: `${point.y}px` },
					},
					`${Math.round(value)}%`
				)
		);
	};

	return el(
		'div',
		{
			ref: frame,
			className: 'bridge-hero-banner__gradient-tool',
		},
		/*
		 * The rail, drawn twice: a wide dark stroke with a narrow white one over
		 * it, which is a white line with a dark outline. There is no single
		 * stroke colour that survives an arbitrary photograph, and an outline is
		 * the cheapest thing that does not have to know what is underneath it —
		 * see the rule in blocks/_hero-banner, which explains why the blend mode
		 * that would say this in one line cannot be used here.
		 */
		el(
			'svg',
			{
				className: 'bridge-hero-banner__gradient-rail',
				viewBox: `0 0 ${box.w} ${box.h}`,
				preserveAspectRatio: 'none',
				focusable: 'false',
				'aria-hidden': 'true',
			},
			el('line', {
				className: 'is-shadow',
				x1: ends[0].x,
				y1: ends[0].y,
				x2: ends[1].x,
				y2: ends[1].y,
			}),
			el('line', {
				x1: ends[0].x,
				y1: ends[0].y,
				x2: ends[1].x,
				y2: ends[1].y,
			})
		),
		bands.map((band, index) =>
			el(
				Fragment,
				{ key: index },
				handle(
					index,
					'start',
					band.start,
					// eslint-disable-next-line @wordpress/i18n-translator-comments
					__('Band start', 'bridge')
				),
				handle(
					index,
					'end',
					band.end,
					// eslint-disable-next-line @wordpress/i18n-translator-comments
					__('Band end', 'bridge')
				)
			)
		),
		/*
		 * The angle, past the far end of the rail.
		 *
		 * The one place on the line that is not a band position, so there is
		 * nothing for it to be confused with, and dragging it sweeps the whole
		 * rail round under the pointer. It is not a percentage, so it takes a
		 * square rather than a circle and its own readout in degrees.
		 */
		(() => {
			const { dx, dy } = gradientAxis(angle);
			const reach = gradientLength(angle, box.w, box.h) / 2 + 26;
			const point = {
				x: box.w / 2 + dx * reach,
				y: box.h / 2 + dy * reach,
			};

			const sweep = (event) => {
				const rect = frame.current.getBoundingClientRect();
				const x = event.clientX - rect.left - box.w / 2;
				const y = event.clientY - rect.top - box.h / 2;
				// The inverse of gradientAxis(): clockwise from straight up,
				// with the screen's y axis pointing down.
				const degrees = (Math.atan2(x, -y) * 180) / Math.PI;
				// Fifteen at a time with Shift, so the angles people actually
				// ask for — 0, 45, 90 — are reachable without nudging.
				const snap = event.shiftKey ? 15 : 1;

				onAngle(
					(((Math.round(degrees / snap) * snap) % 360) + 360) % 360
				);
			};

			return el(
				Fragment,
				null,
				el('button', {
					type: 'button',
					className: [
						'bridge-hero-banner__gradient-handle',
						'is-angle',
						dragging === 'angle' && 'is-dragging',
					]
						.filter(Boolean)
						.join(' '),
					style: { left: `${point.x}px`, top: `${point.y}px` },
					'aria-label': __('Gradient angle', 'bridge'),
					role: 'slider',
					'aria-valuemin': 0,
					'aria-valuemax': 359,
					'aria-valuenow': Math.round(angle),
					'aria-valuetext': `${Math.round(angle)}°`,
					onPointerDown: (event) => {
						event.preventDefault();
						event.stopPropagation();
						event.currentTarget.setPointerCapture(event.pointerId);
						setDragging('angle');
					},
					onPointerMove: (event) => {
						if (dragging !== 'angle') {
							return;
						}

						sweep(event);
					},
					onPointerUp: () => setDragging(null),
					onPointerCancel: () => setDragging(null),
					onKeyDown: (event) => {
						const step = event.shiftKey ? 15 : 1;
						const keys = {
							ArrowLeft: -step,
							ArrowDown: -step,
							ArrowRight: step,
							ArrowUp: step,
						};

						if (!(event.key in keys)) {
							return;
						}

						event.preventDefault();
						onAngle(
							(((Math.round(angle) + keys[event.key]) % 360) +
								360) %
								360
						);
					},
				}),
				dragging === 'angle' &&
					el(
						'div',
						{
							className: 'bridge-hero-banner__gradient-readout',
							style: {
								left: `${point.x}px`,
								top: `${point.y}px`,
							},
						},
						`${Math.round(angle)}°`
					)
			);
		})()
	);
};

/**
 * Where the panel's edge crosses the sides of a stacked banner.
 * BRIDGE_HERO_CURVE_STACK in inc/hero-blocks.php, which explains it.
 */
const CURVE_STACK = 62;

/**
 * What Reset puts back.
 *
 * The composition the block was drawn for: one curve from the banner's
 * top-right corner, sweeping down and left to land a little under halfway along
 * the bottom edge, with the colour filling everything to the left of it and a
 * second copy of the curve a little to the right showing faintly through the
 * picture.
 *
 * These have to be the same numbers block.json defaults to, or Reset would put
 * back something other than what a new banner starts as. That is two lists
 * saying one thing, which is a real cost — but block.json is read by WordPress
 * before any of this runs and cannot be asked at this point, and a Reset that
 * disagrees with a fresh block is worse than a list to keep in step.
 */
const CURVE_DEFAULTS = {
	curveAlign: 44,
	curveVertical: 0,
	curveRadius: 29,
	curveGap: 9,
	curveOpacity: 35,
	curvePadding: 4,
};

/**
 * The curve, as three points. bridge_hero_curve_points() in inc/hero-blocks.php
 * is authoritative — it is what the front end draws — and this is the canvas's
 * copy of the same arithmetic, kept here for the same reason the height presets
 * are: every editor bundle is wrapped as a self-contained IIFE, so a module
 * shared with another entry becomes a chunk the wrapper cannot import.
 *
 * The short of it: a parabola, pinned by where it meets the right-hand edge
 * (`vertical`, normally above the top corner) and the bottom edge (`align`),
 * bowed away from the line between them by `bulge`.
 *
 * @param {Object} curve The settings.
 * @param {number} shift Units to move the whole curve right by.
 * @return {Object} Start, control and end.
 */
const curvePoints = (curve, shift = 0) => {
	const sx = 100 + shift;
	const sy = curve.vertical;
	const ex = 100 - curve.align + shift;
	const ey = 100;

	const dx = ex - sx;
	const dy = ey - sy;
	const len = Math.hypot(dx, dy);

	if (len <= 0) {
		return { sx, sy, cx: sx, cy: sy, ex, ey };
	}

	let nx = -dy / len;
	let ny = dx / len;

	if (nx > 0) {
		nx = -nx;
		ny = -ny;
	}

	return {
		sx,
		sy,
		cx: (sx + ex) / 2 + 2 * curve.bulge * nx,
		cy: (sy + ey) / 2 + 2 * curve.bulge * ny,
		ex,
		ey,
	};
};

/**
 * Everything to the left of the curve, as a closed path.
 * bridge_hero_curve_path() in inc/hero-blocks.php, which explains the overdraw.
 *
 * @param {Object} curve The settings.
 * @param {number} shift Units to move the whole curve right by.
 * @return {string} An SVG path `d` attribute.
 */
const curvePath = (curve, shift = 0) => {
	const p = curvePoints(curve, shift);
	const n = (value) => Number(value.toFixed(3));
	const out = 400;

	return [
		`M${n(-out)},100`,
		`L${n(p.ex)},100`,
		`Q${n(p.cx)},${n(p.cy)} ${n(p.sx)},${n(p.sy)}`,
		`L${n(p.sx + out)},${n(p.sy)}`,
		`L${n(p.sx + out)},${n(-out)}`,
		`L${n(-out)},${n(-out)}`,
		'Z',
	].join(' ');
};

/**
 * How far in from the right edge the words have to stop.
 * bridge_hero_curve_inset() in inc/hero-blocks.php, which explains why the
 * curve is walked rather than solved.
 *
 * @param {Object} curve The settings.
 * @return {number} A percentage of the banner's width, from its right edge.
 */
const curveInset = (curve) => {
	const p = curvePoints(curve);
	let deepest = 100;

	for (let step = 0; step <= 100; step++) {
		const t = step / 100;
		const u = 1 - t;
		const y = u * u * p.sy + 2 * u * t * p.cy + t * t * p.ey;

		if (y < 0 || y > 100) {
			continue;
		}

		deepest = Math.min(
			deepest,
			u * u * p.sx + 2 * u * t * p.cx + t * t * p.ex
		);
	}

	return 100 - deepest;
};

/**
 * The same curve, turned on its side for a stacked banner.
 *
 * bridge_hero_curve_path_stacked() in inc/hero-blocks.php is authoritative;
 * this is the canvas's copy, kept here for the same reason as the one above.
 *
 * @param {Object} curve  The mobile settings.
 * @param {number} narrow How much shallower to draw the bulge, as a percentage.
 * @return {string} An SVG path `d` attribute.
 */
const curvePathStacked = (curve, narrow = 0) => {
	const stack = curve.stack ?? CURVE_STACK;
	const offset = curve.offset ?? 0;
	const tilt = (curve.angle ?? 0) * 0.5;
	const n = (value) => Number(value.toFixed(3));

	// The second curve: shallower by the gap, the same two side crossings.
	const depth = curve.radius * (1 - narrow / 100);

	// No bulge is a straight edge, but a leaning one — see the PHP.
	if (depth <= 0) {
		return `M0,100 H100 V${n(stack - tilt)} L0,${n(stack + tilt)} Z`;
	}

	// The crop is the answer rather than the floor: `offset` no longer widens
	// the ellipse, because growing it to keep reaching the far side is what
	// used to flatten the shape as it slid. See the PHP.
	const rx = 50 * CURVE_CROP;
	const sag = 1 - Math.sqrt(1 - (50 / rx) ** 2);
	const ry = depth / sag;

	const cx = 50 + offset;
	const cy = stack - depth + ry;

	// A band's width of arc and no more, so that moving it moves all of it.
	const left = cx - 50;
	const right = cx + 50;

	const arc = (x) => {
		const reach = Math.max(-1, Math.min(1, (x - cx) / rx));

		return cy - ry * Math.sqrt(1 - reach ** 2);
	};

	const cut = 50 / rx;
	const slope = Math.min(
		CURVE_RUN,
		(ry * cut) / (rx * Math.sqrt(Math.max(1e-9, 1 - cut ** 2)))
	);

	const edge = (x) => {
		let y;

		if (x < left) {
			y = arc(left) + slope * (left - x);
		} else if (x > right) {
			y = arc(right) + slope * (x - right);
		} else {
			y = arc(x);
		}

		return y - tilt * ((x - cx) / 50);
	};

	const arcLeft = Math.max(0, left);
	const arcRight = Math.min(100, right);

	const path = ['M0,100', 'H100', `V${n(edge(100))}`];

	if (right < 100) {
		path.push(`L${n(arcRight)},${n(edge(arcRight))}`);
	}

	path.push(`A${n(rx)},${n(ry)} 0 0 0 ${n(arcLeft)},${n(edge(arcLeft))}`);

	if (left > 0) {
		path.push(`L0,${n(edge(0))}`);
	}

	path.push('Z');

	return path.join(' ');
};

/*
 * A headline, a line under it and a button — which is what a hero is, and the
 * three things an editor would otherwise insert by hand every time.
 *
 * Both text blocks are placeholders rather than content: an unfilled paragraph
 * serialises to nothing, so a banner published with only its headline filled in
 * is a banner with only a headline in it. The same seed the slider builds a
 * slide from, for the same reasons — see its `slide()`.
 *
 * Not locked. The template used to be `templateLock: 'all'` around a Cover,
 * which is a reasonable thing to do to a background and an unreasonable thing
 * to do to the words on top of it.
 */
const TEMPLATE = [
	['core/heading', { level: 1, placeholder: __('Banner title…', 'bridge') }],
	[
		'core/paragraph',
		{ placeholder: __('Optional supporting line', 'bridge') },
	],
	['core/buttons', {}, [['core/button']]],
];

const Edit = ({ attributes, setAttributes, clientId, isSelected }) => {
	const {
		background,
		color,
		imageId,
		imageUrl,
		imageAlt,
		focalPoint,
		overlay,
		fixed,
		titleLeft,
		gradientAngle,
		gradientStops,
		curveAlign,
		curveRadius,
		curveGap,
		curveOpacity,
		curvePadding,
		curveVertical,
		curveBreakpoint,
		curveRadiusMobile,
		curveGapMobile,
		curveOpacityMobile,
		curveStackMobile,
		curveAngleMobile,
		curveOffsetMobile,
		curvePaddingMobile,
		curvePaddingBlockMobile,
		heightPreset,
		customHeight,
		customHeightUnit,
	} = attributes;

	/*
	 * Whether the picture is being reframed.
	 *
	 * Component state rather than an attribute: it is a thing the editor is
	 * doing, not a thing the banner is. Stored it would be saved into the post,
	 * shared with everyone who opened it afterwards, and mark the block dirty
	 * for being looked at.
	 */
	const [reframing, setReframing] = useState(false);

	// The site's palette, which is the whole choice: custom colours are off in
	// theme.json, and a hero painted in a colour the palette does not hold is a
	// hero that will not follow a rebrand.
	const themeColors = useSelect((select) => {
		const settings = select('core/block-editor').getSettings();

		return settings.colors || settings.colorPalette || [];
	}, []);

	// The slug is what is stored, never the hex — see the same pair in the
	// Feature Block, which explains why.
	const slugToHex = (slug) =>
		themeColors.find((item) => item.slug === slug)?.color || undefined;

	/*
	 * A palette colour, however it is spelled.
	 *
	 * The two ends of this round trip do not agree on case. The palette is
	 * whatever an operator typed into the brand settings — this site has
	 * `#ffb32b` next to `#FFFFFF` — and the colour a picker hands back has been
	 * through a normaliser on the way. Compared with `===` a swatch then matches
	 * nothing, the lookup falls through to its default, and every colour in the
	 * panel silently becomes Primary.
	 *
	 * Lower-cased and expanded, so `#FFF`, `#ffffff` and `#FFFFFF` are one
	 * colour. The alpha of an eight-digit value is dropped: the palette has no
	 * alpha to match it against, and a picker that returns one is describing the
	 * same swatch.
	 */
	const hexKey = (value) => {
		const raw = String(value ?? '')
			.trim()
			.toLowerCase()
			.replace('#', '');

		if (/^[0-9a-f]{3}$/.test(raw)) {
			return raw
				.split('')
				.map((part) => part + part)
				.join('');
		}

		return /^[0-9a-f]{6,8}$/.test(raw) ? raw.slice(0, 6) : raw;
	};

	const hexToSlug = (hex) =>
		themeColors.find((item) => hexKey(item.color) === hexKey(hex))?.slug ||
		'primary';

	const hasImage =
		background === 'image' ||
		background === 'curve' ||
		background === 'custom-gradient';
	const hasColor = background !== 'image';
	const showsImage = hasImage && !!imageUrl;
	const isFixed = background === 'image' && !!fixed && showsImage;

	// A curve with no picture behind it yet is drawn as a solid panel — the
	// curve reserves half the band for an image, and half a band of nothing is
	// not a state to show an editor mid-decision. The custom gradient makes no
	// such fallback: its bands are laid over the band's own colour when there is
	// no photograph, which is a finished composition. render.php makes both
	// calls the same way.
	const kind = background === 'curve' && !showsImage ? 'solid' : background;

	// Core's picker deals in two shares of the image and hands back the same
	// shape; a banner saved before the control existed has neither, and the
	// middle of the picture is where it was being cropped to anyway.
	const point = {
		x: Number(focalPoint?.x ?? 0.5),
		y: Number(focalPoint?.y ?? 0.5),
	};

	const curve = {
		align: Number(curveAlign) || 0,
		bulge: Number(curveRadius) || 0,
		gap: Number(curveGap) || 0,
		opacity: Number(curveOpacity) || 0,
		padding: Number(curvePadding) || 0,
		vertical: Number(curveVertical) || 0,
	};

	// The four the stacked arrangement has anything to do with —
	// bridge_hero_curve_mobile() in inc/hero-blocks.php says why it is four and
	// not seven — and the width they take over at.
	const curveMobile = {
		radius: Number(curveRadiusMobile) || 0,
		gap: Number(curveGapMobile) || 0,
		opacity: Number(curveOpacityMobile) || 0,
		stack: Number(curveStackMobile) || CURVE_STACK,
		angle: Number(curveAngleMobile) || 0,
		offset: Number(curveOffsetMobile) || 0,
		padding: Number(curvePaddingMobile) || 0,
		paddingBlock: Number(curvePaddingBlockMobile) || 0,
	};

	// The bands in the order they are painted, which is not necessarily the
	// order they are stored in: a handle dragged past its neighbour reorders
	// them, and both the canvas and bridge_hero_gradient() sort before they
	// build. The sidebar lists them in this order too, so the third card is
	// always the third band across the banner.
	const bands = gradientOrder(gradientStops);
	const angle = ((Math.round(Number(gradientAngle) || 0) % 360) + 360) % 360;
	const gradient =
		kind === 'custom-gradient' ? gradientCss(bands, angle) : '';

	// Every setter works on the sorted list and writes the sorted list back, so
	// an index in the sidebar and an index on the rail mean the same band.
	const setBands = (next) => setAttributes({ gradientStops: next });

	const setBand = (index, changes) =>
		setBands(
			bands.map((band, at) =>
				at === index ? { ...band, ...changes } : band
			)
		);

	/*
	 * How far one end of one band may travel.
	 *
	 * Up to its own other end, and no further than the neighbour on that side.
	 * Stopping at the neighbour is what keeps the list in order under a drag:
	 * without it a handle taken past the band next door reorders both, the
	 * index the pointer is holding starts meaning a different band, and the
	 * handle jumps out from under the cursor. It is also the only arrangement
	 * that paints — overlapping bands are a stop list that goes backwards, see
	 * bridge_hero_gradient().
	 */
	const bandRange = (index, which) => ({
		min:
			which === 'start'
				? (bands[index - 1]?.end ?? 0)
				: bands[index].start,
		max:
			which === 'start'
				? bands[index].end
				: (bands[index + 1]?.start ?? 100),
	});

	const setBandEdge = (index, which, value) => {
		const { min, max } = bandRange(index, which);

		setBand(index, { [which]: Math.max(min, Math.min(max, value)) });
	};

	const breakpoint = Math.max(
		360,
		Math.min(1600, Number(curveBreakpoint) || 1024)
	);

	/*
	 * The canvas's copy of the banner's own stylesheet.
	 *
	 * bridge_hero_curve_css() is authoritative and this says the same thing for
	 * the editor, for the reason every other piece of arithmetic in this file is
	 * duplicated: the front end's copy is printed by render.php, which the
	 * canvas never runs.
	 *
	 * It has to be a real <style> here too. The switch is a media query with a
	 * per-block number in it, and a media query is the one thing a React style
	 * object cannot hold — so the rules that used to live in
	 * blocks/_hero-banner.scss under the theme's own breakpoint are written out
	 * per instance, in the canvas as on the page, and match on the canvas's
	 * width the same way they match on the window's.
	 *
	 * The class is the block's own client id, which is unique per instance and
	 * already on the element — nothing has to be generated to scope this.
	 */
	const curveClass = `bridge-hero-banner-curve-${clientId.replace(
		/[^a-zA-Z0-9_-]/g,
		''
	)}`;

	const curveCss =
		kind === 'curve'
			? [
					`.${curveClass}{--bridge-hero-banner-curve-opacity:${
						curve.opacity / 100
					}}`,
					`@media (max-width:${breakpoint - 1}px){`,
					`.${curveClass} .bridge-hero-banner__curve .is-wide{display:none}`,
					`.${curveClass} .bridge-hero-banner__curve .is-stacked{display:inline}`,
					// And the drawing box back to the shape of the band — see
					// bridge_hero_curve_css(), which explains why the stacked
					// path cannot live in a square one.
					`.${curveClass} .bridge-hero-banner__curve-box{top:0;height:100%;min-height:0;aspect-ratio:auto;transform:none}`,
					`.${curveClass}.bridge-hero-banner.is-bg-curve{grid-template-rows:${
						curveMobile.stack
					}fr ${100 - curveMobile.stack}fr;`,
					`--bridge-hero-banner-curve-opacity:${
						curveMobile.opacity / 100
					}}`,
					`.${curveClass}.bridge-hero-banner.is-bg-curve .bridge-hero-banner__content{grid-row:2;align-items:center;text-align:center;padding-inline:calc(var(--bridge-hero-banner-gutter) + ${curveMobile.padding}%);padding-block:${curveMobile.paddingBlock}px}`,
					// `:where()` so the editor's own alignment always wins — see
					// bridge_hero_curve_css(), which explains why a default
					// written the ordinary way outranks the choice it yields to.
					`:where(.${curveClass}.bridge-hero-banner.is-bg-curve .bridge-hero-banner__content .wp-block-buttons:not([class*="is-content-justification"])){justify-content:center}`,
					'}',
				].join('')
			: '';

	/*
	 * White type over a photograph whatever the palette says; elsewhere the
	 * ground decides — except under a custom gradient, where what is behind the
	 * words is whichever of the two it turns out to be. A band poured on at half
	 * strength or more is a cover the type sits on, and it answers; anything
	 * thinner is a veil, and the picture or the band's own colour answers
	 * instead. bridge_hero_gradient_cover() makes the same call on the same
	 * threshold, and is the authority.
	 */
	const cover =
		kind === 'custom-gradient'
			? bands.reduce((found, band) => {
					const strongest =
						band.fromAlpha >= band.toAlpha
							? { slug: band.from, alpha: band.fromAlpha }
							: { slug: band.to, alpha: band.toAlpha };

					return strongest.alpha >= Math.max(50, found?.alpha ?? 50)
						? strongest
						: found;
				}, null)
			: null;

	const inverted =
		background === 'image' ||
		(kind === 'custom-gradient' && !cover && showsImage) ||
		!isLight(slugToHex(cover ? cover.slug : color));

	const blockProps = useBlockProps({
		className: [
			'bridge-hero-banner',
			'is-editor-preview',
			// Written here rather than left to core's alignment support, which
			// the block no longer declares: a hero is the full width of the
			// window and there is nothing to choose between. See render.php.
			'alignfull',
			`is-bg-${kind}`,
			inverted && 'is-inverted',
			isFixed && 'has-fixed-media',
			// The scope for the stylesheet below, on the same element its
			// selectors are written against. render.php adds the front end's
			// equivalent from wp_unique_id().
			kind === 'curve' && curveClass,
			// render.php writes the same class from the same attribute.
			!!titleLeft && 'is-title-left',
		]
			.filter(Boolean)
			.join(' '),
		style: {
			// The chosen height, published for the preview. Without it the
			// canvas always drew the 100dvh fallback and the height control
			// looked broken.
			'--bridge-hero-banner-height': heroHeight(
				heightPreset,
				customHeight,
				customHeightUnit
			),
			'--bridge-hero-banner-ground': slugToHex(color),
			'--bridge-hero-banner-fg': inverted
				? 'var(--wp--preset--color--background)'
				: 'var(--wp--preset--color--text)',
			/*
			 * The whole gradient, built rather than assembled by the
			 * stylesheet: the number of stops is itself a setting, and CSS can
			 * hold a value it does not understand but cannot loop to make one.
			 *
			 * Inline here and in a `<style>` on the page, which is the one
			 * place the canvas and render.php deliberately disagree. WordPress
			 * runs front-end inline styles through `safecss_filter_attr()`,
			 * which allows a gradient in a custom property only as far as one
			 * level of nested functions — and a palette colour at less than
			 * full strength is a `color-mix()` with a `var()` inside it, which
			 * is two, so the whole declaration is dropped. The editor's canvas
			 * is not filtered, so the page needs a scoped rule to get the value
			 * through and the canvas does not; see bridge_hero_gradient_style().
			 * Both publish the same custom property, which is what the
			 * stylesheet paints, so the two draw the same picture.
			 */
			...(gradient !== ''
				? { '--bridge-hero-banner-gradient': gradient }
				: {}),
			'--bridge-hero-banner-overlay':
				Math.max(0, Math.min(80, Number(overlay) || 0)) / 100,
			// What the curve publishes: how far in from the right the words have
			// to stop, and the clear air they keep off it. render.php writes the
			// same pair, and the canvas is drawing the same picture.
			// Where the picture sits. The `<img>` is translated, so the picker
			// actually moves it rather than choosing a crop; the position is kept
			// beside it for the fixed-background case, which cannot be
			// transformed. bridge_hero_focal_shift() explains both.
			'--bridge-hero-banner-focal': `${point.x * 100}% ${point.y * 100}%`,
			'--bridge-hero-banner-shift': `${(0.5 - point.x) * 2 * FOCAL_TRAVEL}%,${
				(0.5 - point.y) * 2 * FOCAL_TRAVEL
			}%`,
			// And the scale that keeps the ground from showing behind the move.
			// render.php publishes the same number from the same point.
			'--bridge-hero-banner-zoom': focalZoom(point),
			// The opacity is not here. It has a second value below the
			// breakpoint, and a style object is an inline declaration that no
			// rule in the stylesheet below could outrank — the same reason
			// render.php stopped publishing it on the wrapper.
			'--bridge-hero-banner-curve-inset': `${curveInset(curve)}%`,
			'--bridge-hero-banner-curve-pad': `${curve.padding}%`,
		},
	});

	// useInnerBlocksProps on the content element rather than a nested
	// <InnerBlocks />: the latter renders its children inside an extra
	// `block-editor-block-list__layout` div that exists only in the editor,
	// which would put the content one level deeper here than render.php puts it
	// and make every direct-child rule in the stylesheet match one tree and
	// miss the other.
	const contentProps = useInnerBlocksProps(
		{ className: 'bridge-hero-banner__content' },
		{ template: TEMPLATE }
	);

	// What the chooser says the picture is for — a different sentence for each
	// background that carries one. A map rather than a chain of ternaries,
	// which is what three cases had turned it into.
	const imageHelp = {
		image: __('Fills the banner behind the words.', 'bridge'),
		curve: __('Sits beside the words, behind the curve.', 'bridge'),
		'custom-gradient': __(
			'Fills the banner. The gradient is laid over it, and shows the picture through wherever a band is not at full strength.',
			'bridge'
		),
	};

	// And what the colour under it all is for, which is a different sentence
	// again on the two backgrounds that put something over it. A map for the
	// reason imageHelp is one.
	const colorHelp = {
		solid: __(
			'The words take whichever label colour this ground can carry.',
			'bridge'
		),
		gradient: __(
			'Graded across the banner, a shade darker at one edge and lighter at the other.',
			'bridge'
		),
		'custom-gradient': __(
			'What the banner stands on, behind the picture and behind the bands. It is what shows wherever a band has faded to nothing.',
			'bridge'
		),
	};

	const media = el(
		MediaUploadCheck,
		null,
		el(MediaUpload, {
			allowedTypes: ['image'],
			// So the library opens on the image the banner already has rather
			// than making the editor find it again.
			value: imageId,
			onSelect: (image) =>
				setAttributes({
					imageId: image.id,
					// The size the front end renders is chosen by the browser
					// from a srcset; this is only the canvas preview, and a
					// 4000px original as one makes the editor crawl.
					imageUrl:
						image.sizes?.large?.url ||
						image.sizes?.full?.url ||
						image.url,
					imageAlt: image.alt || '',
				}),
			render: ({ open }) =>
				el(
					Button,
					{ variant: 'secondary', onClick: open },
					imageUrl
						? __('Replace image', 'bridge')
						: __('Choose image', 'bridge')
				),
		})
	);

	return el(
		Fragment,
		null,
		/*
		 * Reframe, in the block's own toolbar.
		 *
		 * Here rather than in the sidebar because it is not a setting — it
		 * changes nothing about the banner — and because a mode that takes over
		 * the canvas belongs next to the other things that act on the block
		 * rather than among the things that describe it.
		 *
		 * Only when there is a picture to reframe, which is the one state the
		 * button would otherwise be a no-op in.
		 */
		showsImage &&
			el(
				BlockControls,
				{ group: 'other' },
				el(
					ToolbarGroup,
					null,
					el(ToolbarButton, {
						icon: 'move',
						label: __('Reframe picture', 'bridge'),
						isPressed: reframing,
						onClick: () => setReframing(!reframing),
					})
				)
			),
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __('Background', 'bridge'), initialOpen: true },
				el(SelectControl, {
					label: __('Style', 'bridge'),
					value: background,
					options: [
						{ label: __('Image', 'bridge'), value: 'image' },
						{ label: __('Solid colour', 'bridge'), value: 'solid' },
						{
							label: __('Gradient', 'bridge'),
							value: 'gradient',
						},
						{
							label: __('Custom curve', 'bridge'),
							value: 'curve',
						},
						{
							label: __('Custom gradient', 'bridge'),
							value: 'custom-gradient',
						},
					],
					onChange: (value) => setAttributes({ background: value }),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				}),
				/*
				 * The two-column arrangement, under the style it applies to.
				 *
				 * Here rather than in a panel of its own because it is the
				 * second half of the same question the select above asks: what
				 * the banner looks like. Every background takes it, and the
				 * arithmetic is the page's own grid — see `.is-title-left` in
				 * blocks/_hero-banner.
				 */
				el(ToggleControl, {
					label: __('Title block on the left', 'bridge'),
					help: __(
						'The heading, the line under it and the buttons sit in the left-hand half of the banner, on the page’s own grid, rather than centred across the whole of it. Full width on a phone, where half a screen is not a column.',
						'bridge'
					),
					checked: !!titleLeft,
					onChange: (value) => setAttributes({ titleLeft: value }),
					__nextHasNoMarginBottom: true,
				}),
				hasImage &&
					el(
						BaseControl,
						{
							label: __('Image', 'bridge'),
							help: imageHelp[background] || imageHelp.image,
							__nextHasNoMarginBottom: true,
						},
						/*
						 * The shared chooser wrapper, which this block was the
						 * only one not using.
						 *
						 * `.bridge-media-field` is a flex row with a gap —
						 * editor/_media-field.scss, shipped in inspector.css —
						 * and every other block's chooser is inside one. Here
						 * the two controls were bare children of the
						 * BaseControl, so "Replace image" sat hard against
						 * "Remove image" with nothing between them.
						 */
						el(
							'div',
							{ className: 'bridge-media-field' },
							media,
							!!imageUrl &&
								el(
									Button,
									{
										variant: 'link',
										isDestructive: true,
										onClick: () =>
											setAttributes({
												imageId: 0,
												imageUrl: '',
												imageAlt: '',
											}),
									},
									__('Remove image', 'bridge')
								)
						)
					),
				/*
				 * Where the crop is taken from.
				 *
				 * It earns its place on the curve above all: there the picture
				 * is shown in a tall slice of itself, half the band at most and
				 * less where the curve cuts in, so a subject that is not dead
				 * centre is a subject that is not in the banner. The full-bleed
				 * image crops far less and takes the same control, since it is
				 * the same picture in the same element.
				 */
				showsImage &&
					el(FocalPointPicker, {
						label: __('Focal point', 'bridge'),
						help: __(
							'The part of the picture the crop keeps. Drag the marker to whatever the banner is about.',
							'bridge'
						),
						url: imageUrl,
						value: point,
						onChange: (value) =>
							setAttributes({
								focalPoint: {
									x: Number(value.x),
									y: Number(value.y),
								},
							}),
						__nextHasNoMarginBottom: true,
					}),
				// Only the photograph needs darkening, and only when there is
				// one: behind a curve the picture is beside the words rather
				// than under them, and a colour already carries its own label
				// colour.
				background === 'image' &&
					showsImage &&
					el(RangeControl, {
						label: __('Darken image', 'bridge'),
						help: __(
							'How much the photograph is dimmed so the words on it stay readable.',
							'bridge'
						),
						value: overlay,
						onChange: (value) => setAttributes({ overlay: value }),
						min: 0,
						max: 80,
						step: 5,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					}),
				background === 'image' &&
					showsImage &&
					el(ToggleControl, {
						label: __('Hold image still', 'bridge'),
						help: __(
							'The banner scrolls and the photograph stays put. Ignored on iOS, which always scrolls it.',
							'bridge'
						),
						checked: !!fixed,
						onChange: (value) => setAttributes({ fixed: value }),
						__nextHasNoMarginBottom: true,
					}),
				hasColor &&
					el(
						BaseControl,
						{
							label: __('Colour', 'bridge'),
							help: colorHelp[background] || colorHelp.solid,
							__nextHasNoMarginBottom: true,
						},
						el(ColorPalette, {
							colors: themeColors,
							value: slugToHex(color),
							disableCustomColors: true,
							clearable: false,
							onChange: (hex) =>
								setAttributes({ color: hexToSlug(hex) }),
						})
					)
			),
			/*
			 * The gradient's own panel, and only when there is a gradient.
			 *
			 * One angle at the top, then a card per band: where it starts and
			 * stops, and what colour and strength it is at each of those two
			 * places. The same values the rail over the preview holds — this is
			 * where they are typed rather than dragged, which is what a
			 * gradient that has to line up with a photograph at an exact
			 * percentage needs.
			 *
			 * The cards are listed in the order the bands are painted, not the
			 * order they were added, so the third card down is always the third
			 * stretch across the banner. Dragging a handle past its neighbour
			 * reorders both at once.
			 */
			background === 'custom-gradient' &&
				el(
					PanelBody,
					{ title: __('Gradient', 'bridge'), initialOpen: true },
					el(RangeControl, {
						label: __('Angle', 'bridge'),
						help: __(
							'Which way the gradient runs, clockwise from straight up: 0 is bottom to top, 90 is left to right, 180 is top to bottom. One angle for every band — they are stretches of one line across the banner, not separate gradients. Drag the square handle on the preview to sweep it; hold Shift to step 15° at a time.',
							'bridge'
						),
						value: angle,
						onChange: (value) =>
							setAttributes({
								gradientAngle: Math.max(
									0,
									Math.min(359, Number(value) || 0)
								),
							}),
						min: 0,
						max: 359,
						step: 1,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					}),
					/*
					 * What the bands actually come out as.
					 *
					 * The panel is six controls a band and none of them shows a
					 * result: an operator setting a colour at an end that is at
					 * zero opacity is setting something with no effect, and
					 * without this the only evidence either way is a banner
					 * behind the sidebar with a photograph in the middle of it.
					 *
					 * Laid out left to right rather than at the gradient's own
					 * angle, because what it is here to show is the positions —
					 * where each band starts, where the clear air is — and the
					 * angle is the one thing already visible on the banner. Over
					 * a chequerboard, so transparent reads as transparent rather
					 * than as whatever the sidebar's background happens to be.
					 */
					bands.length > 0 &&
						el(
							'div',
							{
								className: 'bridge-hero-gradient-preview',
								'aria-hidden': 'true',
							},
							el('div', {
								className:
									'bridge-hero-gradient-preview__bands',
								style: {
									backgroundImage: gradientCss(bands, 90),
								},
							})
						),
					bands.length === 0 &&
						el(
							'p',
							{ className: 'bridge-hero-gradient-empty' },
							__(
								'No bands yet — the banner is showing its plain colour. Add one below.',
								'bridge'
							)
						),
					/*
					 * One card per band.
					 *
					 * A fieldset rather than a run of controls, because the six
					 * settings in it are one object and the sidebar has to say
					 * so: with three bands open this panel is eighteen controls,
					 * and nothing but the grouping tells an operator which Start
					 * belongs to which End.
					 */
					bands.map((band, index) =>
						el(
							'fieldset',
							{
								key: index,
								className: 'bridge-hero-gradient-band',
							},
							el(
								'legend',
								null,
								/* translators: %d: the band's position in the gradient. */
								__('Band', 'bridge') + ` ${index + 1}`
							),
							el(RangeControl, {
								label: __('Start point', 'bridge'),
								help:
									index === 0
										? __(
												'How far along the gradient this band begins, as a percentage. Drag its handle on the preview instead if the answer is a place on the picture rather than a number.',
												'bridge'
											)
										: undefined,
								value: band.start,
								onChange: (value) =>
									setBandEdge(
										index,
										'start',
										Number(value) || 0
									),
								...bandRange(index, 'start'),
								step: 1,
								__nextHasNoMarginBottom: true,
								__next40pxDefaultSize: true,
							}),
							el(RangeControl, {
								label: __('End point', 'bridge'),
								help:
									index === 0
										? __(
												'And where it finishes. Between one band and the next, the gradient holds whatever colour the last band ended on — so a band that fades to nothing leaves nothing behind it until the next one starts.',
												'bridge'
											)
										: undefined,
								value: band.end,
								onChange: (value) =>
									setBandEdge(
										index,
										'end',
										Number(value) || 0
									),
								...bandRange(index, 'end'),
								step: 1,
								__nextHasNoMarginBottom: true,
								__next40pxDefaultSize: true,
							}),
							el(
								BaseControl,
								{
									label: __('Start colour', 'bridge'),
									// A colour at zero opacity is transparent,
									// and transparent has no hue to show — so
									// this control is genuinely doing nothing
									// and should say so rather than look broken.
									help:
										band.fromAlpha === 0
											? __(
													'This end is transparent, so its colour has no effect — the fade takes its hue from the other end of the band. Raise Start opacity to bring it back.',
													'bridge'
												)
											: undefined,
									__nextHasNoMarginBottom: true,
								},
								el(ColorPalette, {
									colors: themeColors,
									value: slugToHex(band.from),
									disableCustomColors: true,
									clearable: false,
									onChange: (value) =>
										setBand(index, {
											from: hexToSlug(value),
										}),
								})
							),
							el(RangeControl, {
								label: __('Start opacity', 'bridge'),
								help:
									index === 0
										? __(
												'0 is transparent — whatever is behind the banner shows through untouched. That is how a band is made to fade to nothing, and it keeps the hue it is fading from rather than going grey on the way out.',
												'bridge'
											)
										: undefined,
								value: band.fromAlpha,
								onChange: (value) =>
									setBand(index, {
										fromAlpha: Math.max(
											0,
											Math.min(100, Number(value) || 0)
										),
									}),
								min: 0,
								max: 100,
								step: 1,
								__nextHasNoMarginBottom: true,
								__next40pxDefaultSize: true,
							}),
							el(
								BaseControl,
								{
									label: __('End colour', 'bridge'),
									help:
										band.toAlpha === 0
											? __(
													'This end is transparent, so its colour has no effect — the fade takes its hue from the other end of the band. Raise End opacity to bring it back.',
													'bridge'
												)
											: undefined,
									__nextHasNoMarginBottom: true,
								},
								el(ColorPalette, {
									colors: themeColors,
									value: slugToHex(band.to),
									disableCustomColors: true,
									clearable: false,
									onChange: (value) =>
										setBand(index, {
											to: hexToSlug(value),
										}),
								})
							),
							el(RangeControl, {
								label: __('End opacity', 'bridge'),
								value: band.toAlpha,
								onChange: (value) =>
									setBand(index, {
										toAlpha: Math.max(
											0,
											Math.min(100, Number(value) || 0)
										),
									}),
								min: 0,
								max: 100,
								step: 1,
								__nextHasNoMarginBottom: true,
								__next40pxDefaultSize: true,
							}),
							el(
								Button,
								{
									variant: 'link',
									isDestructive: true,
									onClick: () =>
										setBands(
											bands.filter(
												(item, at) => at !== index
											)
										),
								},
								__('Remove band', 'bridge')
							)
						)
					),
					/*
					 * A new band starts where the last one finished, at nothing,
					 * and runs to the end of the gradient.
					 *
					 * Which is the continuation an operator almost always wants:
					 * it picks up exactly where the picture left off, so adding
					 * a band changes nothing until one of its six settings is
					 * touched. A band that arrived at full strength across the
					 * whole banner would paint over the composition it was being
					 * added to.
					 */
					bands.length < GRADIENT_MAX &&
						el(
							Button,
							{
								variant: 'secondary',
								onClick: () => {
									const last = bands[bands.length - 1];

									setBands([
										...bands,
										{
											...GRADIENT_BAND,
											start: last ? last.end : 0,
											end: 100,
											from: last ? last.to : 'primary',
											fromAlpha: last
												? last.toAlpha
												: 100,
											to: last ? last.to : 'primary',
											toAlpha: last ? last.toAlpha : 0,
										},
									]);
								},
							},
							__('Add band', 'bridge')
						),
					/*
					 * Reset on a row of its own, under Add band rather than
					 * beside it.
					 *
					 * Both are buttons and the panel is narrow, so side by side
					 * they read as a pair of equal choices — and one of them
					 * throws away every band in the panel. A row to itself is
					 * the cheapest way to say they are not the same kind of
					 * thing.
					 */
					el(
						'div',
						{ className: 'bridge-hero-gradient-reset' },
						el(
							Button,
							{
								variant: 'link',
								onClick: () =>
									setAttributes({
										...GRADIENT_DEFAULTS,
										gradientStops:
											GRADIENT_DEFAULTS.gradientStops.map(
												(band) => ({ ...band })
											),
									}),
							},
							__('Reset to default settings', 'bridge')
						)
					)
				),
			/*
			 * The shape's own panel, and only when there is a shape.
			 *
			 * Numbers rather than a set of named presets, because the thing
			 * being chosen is a drawing: two place the curve, one shapes it,
			 * two decide how the second edge reads against the first, and the
			 * last two set the words against all of it. A preset list would be
			 * somebody else's numbers with the ability to nudge them taken
			 * away.
			 *
			 * Every one is a percentage of the band except the angle, so the
			 * shape keeps its proportions at any window size and nothing here
			 * has to be re-tuned per breakpoint.
			 */
			background === 'curve' &&
				el(
					PanelBody,
					{ title: __('Curve', 'bridge'), initialOpen: true },
					el(RangeControl, {
						label: __('Horizontal', 'bridge'),
						help: __(
							'Where the curve lands on the bottom of the banner, measured in from the bottom-right corner. 0 is the corner itself, 100 the bottom-left.',
							'bridge'
						),
						value: curveAlign,
						onChange: (value) =>
							setAttributes({ curveAlign: value }),
						min: 0,
						max: 100,
						step: 1,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					}),
					el(RangeControl, {
						label: __('Vertical', 'bridge'),
						help: __(
							'Where the curve’s other end sits on the right-hand edge, measured down from the top-right corner. Negative puts it above the banner, so what shows is the part of the curve after it has already turned over.',
							'bridge'
						),
						value: curveVertical,
						onChange: (value) =>
							setAttributes({ curveVertical: value }),
						min: -100,
						max: 100,
						step: 1,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					}),
					el(RangeControl, {
						label: __('Bulge', 'bridge'),
						help: __(
							'How far the curve bows away from the straight line between its two ends. 0 is that straight line; wind it up and the curve swells into the colour.',
							'bridge'
						),
						value: curveRadius,
						onChange: (value) =>
							setAttributes({ curveRadius: value }),
						min: 0,
						max: 100,
						step: 1,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					}),
					el(RangeControl, {
						label: __('Gap', 'bridge'),
						help: __(
							'How far the second curve sits to the right of the first. The same curve, moved — so the band between them is an even width the whole way down. Negative moves it the other way.',
							'bridge'
						),
						value: curveGap,
						onChange: (value) => setAttributes({ curveGap: value }),
						min: -40,
						max: 40,
						step: 1,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					}),
					el(RangeControl, {
						label: __('Opacity', 'bridge'),
						help: __(
							'How much of the banner colour the second curve lays over the picture. Zero hides it.',
							'bridge'
						),
						value: curveOpacity,
						onChange: (value) =>
							setAttributes({ curveOpacity: value }),
						min: 0,
						max: 100,
						step: 1,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					}),
					el(RangeControl, {
						label: __('Text padding', 'bridge'),
						help: __(
							'How much clear air the words keep off the curve. They start on the page’s own left margin, in line with the logo, which is not adjustable.',
							'bridge'
						),
						value: curvePadding,
						onChange: (value) =>
							setAttributes({ curvePadding: value }),
						min: 0,
						max: 20,
						step: 1,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					}),
					// Last in the panel, because it undoes everything above it
					// and a control that undoes the others belongs after them.
					// A link rather than a button: it is a way back, not a step
					// forward, and it should not read as the thing to press.
					el(
						Button,
						{
							variant: 'link',
							onClick: () => setAttributes({ ...CURVE_DEFAULTS }),
						},
						__('Reset to default settings', 'bridge')
					)
				),
			/*
			 * The same shape on a narrow screen, which is a different
			 * composition rather than the same one scaled.
			 *
			 * Six settings rather than the wide panel's seven, and they are not
			 * the same six. Turned on its side the panel is the floor of the
			 * band rather than one side of it, so the two that place it across
			 * the width change meaning: Align has none left — where the panel
			 * starts across the width is the one choice this arrangement does
			 * not have — and Stack position takes its place, the same question
			 * asked down the height. Vertical alignment becomes Horizontal
			 * alignment for the same reason, and Angle leans the join by moving
			 * its left and right ends instead of its top and bottom.
			 *
			 * Text padding is the one that simply goes: the words are centred
			 * under the curve with the page's own gutter either side, so there
			 * is no distance off the shape to set. bridge_hero_curve_mobile()
			 * in inc/hero-blocks.php says all of this at more length.
			 */
			background === 'curve' &&
				el(
					PanelBody,
					{
						title: __('Curve on mobile', 'bridge'),
						initialOpen: false,
					},
					el(RangeControl, {
						label: __('Breakpoint (px)', 'bridge'),
						help: __(
							'The window width at and above which this banner keeps the wide arrangement, with the picture beside the words. Below it the picture moves above them and the settings here take over. Per banner, because how far down a wide hero survives depends on how long its headline is.',
							'bridge'
						),
						value: curveBreakpoint,
						onChange: (value) =>
							setAttributes({ curveBreakpoint: value }),
						min: 360,
						max: 1600,
						step: 1,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					}),
					el(RangeControl, {
						label: __('Stack position', 'bridge'),
						help: __(
							'How far down the banner the curve crosses, as a share of its height. The picture is above the line and the words below it, so this is how much screen each one gets.',
							'bridge'
						),
						value: curveStackMobile,
						onChange: (value) =>
							setAttributes({ curveStackMobile: value }),
						min: 30,
						max: 85,
						step: 1,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					}),
					el(RangeControl, {
						label: __('Horizontal alignment', 'bridge'),
						help: __(
							'Slides the whole curve left and right across the banner, without changing its shape. The wide layout\u2019s vertical alignment, turned a quarter turn with the composition.',
							'bridge'
						),
						value: curveOffsetMobile,
						onChange: (value) =>
							setAttributes({ curveOffsetMobile: value }),
						min: -60,
						max: 60,
						step: 1,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					}),
					el(RangeControl, {
						label: __('Angle', 'bridge'),
						help: __(
							'Leans the curve, in degrees: the left and right ends move in opposite directions, so the join tilts rather than the picture rotating.',
							'bridge'
						),
						value: curveAngleMobile,
						onChange: (value) =>
							setAttributes({ curveAngleMobile: value }),
						min: -45,
						max: 45,
						step: 1,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					}),
					el(RangeControl, {
						label: __('Radius', 'bridge'),
						help: __(
							'How far the curve bulges up into the picture at its deepest.',
							'bridge'
						),
						value: curveRadiusMobile,
						onChange: (value) =>
							setAttributes({ curveRadiusMobile: value }),
						min: 0,
						max: 40,
						step: 1,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					}),
					el(RangeControl, {
						label: __('Gap', 'bridge'),
						help: __(
							'How much narrower the second curve is than the first, as a percentage of it. The crescent is widest where the curve is deepest and closes at either side.',
							'bridge'
						),
						value: curveGapMobile,
						onChange: (value) =>
							setAttributes({ curveGapMobile: value }),
						min: 0,
						max: 30,
						step: 1,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					}),
					el(RangeControl, {
						label: __('Opacity', 'bridge'),
						help: __(
							'How much of the banner colour the second curve lays over the picture. Zero leaves one clean edge.',
							'bridge'
						),
						value: curveOpacityMobile,
						onChange: (value) =>
							setAttributes({ curveOpacityMobile: value }),
						min: 0,
						max: 100,
						step: 1,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					}),
					el(RangeControl, {
						label: __('Horizontal padding', 'bridge'),
						help: __(
							'Clear air either side of the headline, the paragraph and the button, on top of the page\u2019s own gutter. Both sides here, not one: stacked, the words are centred in the panel under the curve rather than set against it. A share of the banner\u2019s width, so it holds its proportion on any phone.',
							'bridge'
						),
						value: curvePaddingMobile,
						onChange: (value) =>
							setAttributes({ curvePaddingMobile: value }),
						min: 0,
						max: 20,
						step: 1,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					}),
					el(RangeControl, {
						label: __('Vertical padding (px)', 'bridge'),
						help: __(
							'Air above and below the same three, inside the panel under the curve. In pixels rather than a share: a percentage padding measures against the width whichever edge it is on, so it would grow with the screen while the words stayed put.',
							'bridge'
						),
						value: curvePaddingBlockMobile,
						onChange: (value) =>
							setAttributes({ curvePaddingBlockMobile: value }),
						min: 0,
						max: 96,
						step: 1,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					})
				),
			el(
				PanelBody,
				{ title: __('Height', 'bridge'), initialOpen: true },
				el(SelectControl, {
					label: __('Minimum height', 'bridge'),
					help: __(
						'A floor, not a cap — content taller than this makes the banner taller rather than being cut off. Screen heights allow for the header and its top bar, so Full screen fills the window below them.',
						'bridge'
					),
					value: heightPreset,
					options: [
						{ label: __('Full screen', 'bridge'), value: 'full' },
						{ label: __('Tall (80%)', 'bridge'), value: 'tall' },
						{
							label: __('Medium (60%)', 'bridge'),
							value: 'medium',
						},
						{ label: __('Custom', 'bridge'), value: 'custom' },
					],
					onChange: (value) => setAttributes({ heightPreset: value }),
				}),
				heightPreset === 'custom' &&
					el(RangeControl, {
						label: __('Custom height', 'bridge'),
						value: customHeight,
						onChange: (value) =>
							setAttributes({ customHeight: value }),
						min: 20,
						max: customHeightUnit === 'px' ? 1200 : 200,
						step: 1,
					}),
				heightPreset === 'custom' &&
					el(SelectControl, {
						label: __('Unit', 'bridge'),
						value: customHeightUnit,
						options: [
							{ label: 'vh', value: 'vh' },
							{ label: 'dvh', value: 'dvh' },
							{ label: 'px', value: 'px' },
						],
						onChange: (value) =>
							setAttributes({ customHeightUnit: value }),
					})
			)
		),
		el(
			'div',
			blockProps,
			showsImage &&
				el(
					'div',
					{
						className: 'bridge-hero-banner__media',
						style: isFixed
							? { backgroundImage: `url(${imageUrl})` }
							: undefined,
					},
					!isFixed &&
						el('img', {
							className: 'bridge-hero-banner__image',
							src: imageUrl,
							alt: imageAlt || '',
						})
				),
			gradient !== '' &&
				el('div', {
					className: 'bridge-hero-banner__gradient',
					'aria-hidden': 'true',
					/*
					 * Painted straight onto the element here, rather than left
					 * to the stylesheet reading a custom property off the
					 * wrapper the way the page does.
					 *
					 * One less thing between a swatch and the canvas. The
					 * property route works — it is what render.php publishes,
					 * because a gradient cannot go in a front-end style
					 * attribute at all — but it means a colour change has to
					 * travel from the attribute, into a variable on an
					 * ancestor, down through inheritance, into a rule in a
					 * separate stylesheet, before anything moves. Set on the
					 * element it is one value on one node, which is the shortest
					 * path the editor can take and the one least able to go
					 * stale.
					 */
					style: { backgroundImage: gradient },
				}),
			kind === 'curve' &&
				el(
					'div',
					{
						className: 'bridge-hero-banner__curve',
						'aria-hidden': 'true',
					},
					el(
						'svg',
						{
							className: 'bridge-hero-banner__curve-box',
							viewBox: '0 0 100 100',
							preserveAspectRatio: 'none',
							focusable: 'false',
						},
						el('path', {
							className:
								'bridge-hero-banner__curve-veil is-stacked',
							d: curvePathStacked(curveMobile, curveMobile.gap),
						}),
						el('path', {
							className:
								'bridge-hero-banner__curve-panel is-stacked',
							d: curvePathStacked(curveMobile),
						}),
						// The second curve first and the colour over it, so
						// what shows is the band between the two edges. See
						// render.php.
						el('path', {
							className: 'bridge-hero-banner__curve-veil is-wide',
							d: curvePath(curve, curve.gap),
						}),
						el('path', {
							className:
								'bridge-hero-banner__curve-panel is-wide',
							d: curvePath(curve),
						})
					)
				),
			curveCss !== '' &&
				el('style', {
					dangerouslySetInnerHTML: { __html: curveCss },
				}),
			el('div', contentProps),
			/*
			 * The rail and its handles, last so they are over the words.
			 *
			 * Only while this block itself is selected — not an inner one. The
			 * tool's handles take pointer events, and a set of them floating
			 * over a headline somebody is in the middle of typing is a set of
			 * them in the way. Selecting the banner is the gesture that means
			 * "I am arranging this", which is exactly when the rail is wanted.
			 */
			isSelected &&
				kind === 'custom-gradient' &&
				bands.length > 0 &&
				!reframing &&
				el(GradientTool, {
					angle,
					bands,
					range: bandRange,
					onBand: setBandEdge,
					onAngle: (value) => setAttributes({ gradientAngle: value }),
				}),
			/*
			 * And the reframe surface, last of all and over everything —
			 * including the gradient's rail, which is why that one is held back
			 * above. While the mode is on, the only thing the banner does is
			 * move its picture.
			 */
			reframing &&
				showsImage &&
				el(ReframeSurface, {
					point,
					onChange: (value) => setAttributes({ focalPoint: value }),
				})
		)
	);
};

export default Edit;
