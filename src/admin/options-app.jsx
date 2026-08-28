/**
 * Bridge — Theme Options.
 *
 * The operator-facing editor for the design system. Everything it writes goes
 * through POST /bridge/v1/tokens, which sanitises and clamps server-side; the
 * response is treated as the truth and replaces local state, so an
 * out-of-range value visibly snaps rather than leaving the page disagreeing
 * with the site.
 *
 * The live preview is compiled by POST /bridge/v1/tokens/preview rather than
 * in JavaScript. Reimplementing the modular scale here would give us two
 * implementations to keep in step, and the one the operator is looking at
 * would be the one that drifts.
 *
 * JSX compiles to wp.element.createElement via the esbuild config, so there
 * are no imports of React and no runtime — the same window.wp.* approach the
 * editor scripts use.
 *
 * ---- What is where --------------------------------------------------------
 *
 * This file holds the shell and nothing else: the draft, the round trips to
 * the server, and which tab is on screen. It was 2,900 lines with all four
 * tabs inline, which is navigable right up until it is not.
 *
 *   lib.js        Pure helpers — hex parsing, the custom properties a skin is.
 *   hooks.js      The Google-font loader.
 *   controls.jsx  Everything an operator touches. Takes an `onChange`.
 *   preview.jsx   Everything the screen draws from compiled values.
 *   tabs/         One module per tab, each exporting its body and, where it
 *                 has one, its sidebar.
 *
 * A tab receives the draft, the payload and the setters it needs, and derives
 * the rest itself. Nothing is threaded through that a tab does not use.
 */

import '../scss/admin/_options.scss';

import { useGooglePreviewFonts } from './hooks';
import { BlocksTab } from './tabs/blocks';
import { CardsPreview, CardsTab } from './tabs/cards';
import { ButtonsPreview, ButtonsTab } from './tabs/buttons';
import { DesignPreview, DesignTab } from './tabs/design';
import { TemplatesTab } from './tabs/templates';

const {
	createRoot,
	render,
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} = wp.element;
const { Button, Notice, Spinner, TabPanel } = wp.components;
const { __ } = wp.i18n;
const apiFetch = wp.apiFetch;

const PREVIEW_DEBOUNCE_MS = 250;

function OptionsApp() {
	const [payload, setPayload] = useState(null);
	const [draft, setDraft] = useState(null);
	const [preview, setPreview] = useState(null);
	const [saving, setSaving] = useState(false);
	const [notice, setNotice] = useState(null);
	const [error, setError] = useState(null);

	// Guards against a slow preview response landing after a newer one.
	const previewToken = useRef(0);

	useEffect(() => {
		apiFetch({ path: '/bridge/v1/tokens' })
			.then((data) => {
				setPayload(data);
				setDraft(data.tokens);
			})
			.catch((e) =>
				setError(
					e.message ||
						__('Could not load the design system.', 'bridge')
				)
			);
	}, []);

	// Recompile the preview whenever the draft settles.
	useEffect(() => {
		if (!draft) {
			return undefined;
		}

		const ticket = ++previewToken.current;
		const timer = setTimeout(() => {
			apiFetch({
				path: '/bridge/v1/tokens/preview',
				method: 'POST',
				data: { tokens: draft },
			})
				.then((data) => {
					if (ticket === previewToken.current) {
						setPreview(data);
					}
				})
				.catch(() => {});
		}, PREVIEW_DEBOUNCE_MS);

		return () => clearTimeout(timer);
	}, [draft]);

	// Pull the chosen faces into the admin page so the preview panel renders
	// them the moment they are picked, rather than only after a save.
	useGooglePreviewFonts(
		draft?.typography?.googleFonts
			? [draft.typography.googleHeading, draft.typography.googleBody]
			: []
	);

	const isDirty = useMemo(
		() =>
			Boolean(payload && draft) &&
			JSON.stringify(payload.tokens) !== JSON.stringify(draft),
		[payload, draft]
	);

	const setGroup = useCallback((group, key, value) => {
		setDraft((current) => ({
			...current,
			[group]: { ...current[group], [key]: value },
		}));
	}, []);

	const setSlot = useCallback((slug, patch) => {
		setDraft((current) => ({
			...current,
			brand: {
				...current.brand,
				palette: {
					...current.brand.palette,
					[slug]: { ...current.brand.palette[slug], ...patch },
				},
			},
		}));
	}, []);

	const setButtonFill = useCallback((key, slug) => {
		setDraft((current) => ({
			...current,
			buttons: {
				...current.buttons,
				colors: { ...current.buttons.colors, [key]: slug },
			},
		}));
	}, []);

	const setCardColor = useCallback((key, color) => {
		setDraft((current) => ({
			...current,
			cards: {
				...current.cards,
				colors: { ...current.cards.colors, [key]: color },
			},
		}));
	}, []);

	/**
	 * One field of one card style.
	 *
	 * A third level of nesting, so it gets a setter of its own rather than
	 * being spelled out at four call sites: `cards.styles.portrait.avatar` is
	 * three spreads deep, and a missing one silently drops the other styles.
	 */
	const setCardStyle = useCallback((style, key, value) => {
		setDraft((current) => ({
			...current,
			cards: {
				...current.cards,
				styles: {
					...current.cards.styles,
					[style]: { ...current.cards.styles?.[style], [key]: value },
				},
			},
		}));
	}, []);

	const setHeader = useCallback((path, value) => {
		setDraft((current) => {
			const header = { ...current.header };
			const [group, key] = path.split('.');

			if (key) {
				header[group] = { ...header[group], [key]: value };
			} else {
				header[group] = value;
			}

			return { ...current, header };
		});
	}, []);

	/**
	 * Switch blocks on or off.
	 *
	 * Stores the difference from what the theme ships rather than the whole
	 * list: switching a default-on block off records it as disabled, switching
	 * it back on simply forgets it again. A theme release that adds a block
	 * then arrives switched on, instead of missing from a list written before
	 * it existed.
	 *
	 * Both lists are kept sorted because the server sorts them too — the save
	 * button reads its enabled state from a comparison of the two records.
	 */
	const setBlocks = useCallback((blocks, on) => {
		setDraft((current) => {
			const enabled = new Set(current.blocks.enabled);
			const disabled = new Set(current.blocks.disabled);

			blocks.forEach((block) => {
				if (block.required) {
					return;
				}

				if (on) {
					disabled.delete(block.name);
					if (block.default) {
						enabled.delete(block.name);
					} else {
						enabled.add(block.name);
					}

					return;
				}

				enabled.delete(block.name);
				if (block.default) {
					disabled.add(block.name);
				} else {
					disabled.delete(block.name);
				}
			});

			return {
				...current,
				blocks: {
					enabled: [...enabled].sort(),
					disabled: [...disabled].sort(),
				},
			};
		});
	}, []);

	const save = () => {
		setSaving(true);
		setNotice(null);

		apiFetch({
			path: '/bridge/v1/tokens',
			method: 'POST',
			data: { tokens: draft },
		})
			.then((data) => {
				const clamped =
					JSON.stringify(data.tokens) !== JSON.stringify(draft);

				setPayload(data);
				setDraft(data.tokens);
				setNotice({
					status: clamped ? 'warning' : 'success',
					message: clamped
						? __(
								'Saved. Some values were outside their allowed range and were adjusted to the nearest permitted value.',
								'bridge'
							)
						: __(
								'Saved. The editor and the front end are already using these values.',
								'bridge'
							),
				});
			})
			.catch((e) =>
				setNotice({
					status: 'error',
					message: e.message || __('Could not save.', 'bridge'),
				})
			)
			.finally(() => setSaving(false));
	};

	if (error) {
		return (
			<Notice status="error" isDismissible={false}>
				{error}
			</Notice>
		);
	}

	if (!draft || !payload) {
		return (
			<div className="bridge-options__loading">
				<Spinner />
				<span>{__('Loading the design system…', 'bridge')}</span>
			</div>
		);
	}
	// The setters every tab shares, gathered once so a tab's props say what it
	// writes rather than repeating the same five lines four times.
	const writers = {
		setGroup,
		setSlot,
		setCardColor,
		setCardStyle,
		setButtonFill,
		setHeader,
		setBlocks,
	};

	return (
		<TabPanel
			className="bridge-options__tabs"
			tabs={[
				{ name: 'design', title: __('Design', 'bridge') },
				{ name: 'buttons', title: __('Buttons', 'bridge') },
				{ name: 'cards', title: __('Cards', 'bridge') },
				{ name: 'blocks', title: __('Blocks', 'bridge') },
				{ name: 'templates', title: __('Templates', 'bridge') },
			]}
		>
			{(tab) => {
				// Two tabs run full width. Templates, because its layout
				// pickers are drawings and are already the preview — a sidebar
				// repeating them smaller would be the same information twice.
				// Blocks, because the library is seventy-odd switches and a
				// half-width column turns it into a scroll.
				const single =
					'templates' === tab.name || 'blocks' === tab.name;

				// Cards splits 60/40 instead of taking the fixed sidebar the
				// other two-column tabs use. Its preview is twelve cards
				// rather than a swatch strip — the whole point of it is
				// comparing three styles side by side across four grounds, and
				// at 26rem each card is drawn a hundred pixels wide. The extra
				// column is the difference between reading the comparison and
				// squinting at it.
				const split = 'cards' === tab.name;

				const shared = { draft, payload, preview, ...writers };

				const actions = (
					<>
						<div className="bridge-options__actions">
							<Button
								variant="primary"
								onClick={save}
								isBusy={saving}
								disabled={saving || !isDirty}
							>
								{saving
									? __('Saving…', 'bridge')
									: __('Save design system', 'bridge')}
							</Button>
							<Button
								variant="tertiary"
								onClick={() => setDraft(payload.tokens)}
								disabled={saving || !isDirty}
							>
								{__('Discard changes', 'bridge')}
							</Button>
						</div>

						{isDirty && (
							<p className="bridge-options__dirty">
								{__(
									'Unsaved changes — the preview is live, the site is not.',
									'bridge'
								)}
							</p>
						)}
					</>
				);

				return (
					<div
						className={[
							'bridge-options',
							single && 'bridge-options--single',
							split && 'bridge-options--split',
						]
							.filter(Boolean)
							.join(' ')}
					>
						<div className="bridge-options__main">
							{notice && (
								<Notice
									status={notice.status}
									onRemove={() => setNotice(null)}
								>
									{notice.message}
								</Notice>
							)}

							{single && (
								<div className="bridge-options__bar">
									{actions}
								</div>
							)}

							{tab.name === 'design' && <DesignTab {...shared} />}
							{tab.name === 'buttons' && (
								<ButtonsTab {...shared} />
							)}
							{tab.name === 'cards' && <CardsTab {...shared} />}
							{tab.name === 'blocks' && <BlocksTab {...shared} />}
							{tab.name === 'templates' && (
								<TemplatesTab {...shared} />
							)}
						</div>

						{!single && (
							<aside className="bridge-options__preview">
								<div className="bridge-options__preview-inner">
									{/*
									 * The actions live in every tab rather than once
									 * above the tabs: a draft spans all four, so Save
									 * has to be reachable wherever the last edit happened.
									 */}
									{actions}

									{/*
									 * A sidebar with nothing in it yet is a spinner
									 * rather than an empty column, because the first
									 * preview is one debounced round trip away.
									 */}
									{!preview && (
										<div className="bridge-options__loading">
											<Spinner />
										</div>
									)}

									{preview && tab.name === 'design' && (
										<DesignPreview
											draft={draft}
											payload={payload}
											preview={preview}
										/>
									)}

									{preview && tab.name === 'cards' && (
										<CardsPreview
											draft={draft}
											payload={payload}
											preview={preview}
										/>
									)}

									{preview && tab.name === 'buttons' && (
										<ButtonsPreview
											payload={payload}
											preview={preview}
										/>
									)}
								</div>
							</aside>
						)}
					</div>
				);
			}}
		</TabPanel>
	);
}

function mount() {
	const root = document.getElementById('bridge-options-root');

	if (!root) {
		return;
	}

	// createRoot landed in WP 6.2; render is the fallback for older cores.
	if (typeof createRoot === 'function') {
		createRoot(root).render(<OptionsApp />);
	} else {
		render(<OptionsApp />, root);
	}
}

// A footer script normally runs before DOMContentLoaded, but anything that
// defers or async-loads it — a performance plugin, a future core strategy —
// would land after the event and leave the screen permanently empty. Checking
// readyState makes the mount independent of when the script arrives.
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', mount);
} else {
	mount();
}
