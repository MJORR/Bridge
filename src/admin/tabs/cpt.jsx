/**
 * Bridge — Theme Options, the Content types tab.
 *
 * Three words each: what one is called, what many are called, and the slug the
 * URLs are built from, plus three switches. Everything else about a declared
 * post type is fixed by the theme — see inc/post-types.php for why.
 *
 * ---- On, and removed ------------------------------------------------------
 *
 * Two different things, and the row shows both because they undo differently.
 *
 * The theme seeds Team and FAQs on activation, and plenty of sites want one of
 * them. Removing the row is the wrong tool for that: it is the operator's own
 * declaration that disappears, so putting it back means retyping the name and
 * the slug exactly, and the next theme activation would seed it again anyway.
 * The switch keeps the row — every word of it — and stops the type being
 * registered. Nothing else on the row is touched, so switching it back on is
 * one click and the content is where it was.
 *
 * Removing stays for the types an operator declared themselves and no longer
 * wants at all, and it deletes nothing either: see the note by the button.
 *
 * Full width rather than half. There is nothing to preview: the result of this
 * form is a menu item in the sidebar and an address on the front end, and both
 * are text, so they are shown as text under the row that produces them rather
 * than drawn in a panel beside it.
 *
 * ---- Why the slug follows the singular ------------------------------------
 *
 * A slug is the one field of the three an operator should never have to think
 * about, and it is the only one that is expensive to change later — it is in
 * every URL the type has ever published. So it is derived from the singular
 * name as that name is typed, and stops following the moment it is edited by
 * hand. The test for "edited by hand" is whether the field still holds what
 * the previous singular would have produced, which needs no extra state and so
 * cannot fall out of step with the value it describes.
 */

import { Section } from '../controls';

const { Button, Notice, TextControl, ToggleControl } = wp.components;
const { __, sprintf } = wp.i18n;

/**
 * A post type name from a display name.
 *
 * Deliberately the same transformation inc/tokens.php performs, so the field
 * shows what will be stored rather than something close to it. Underscores
 * rather than dashes, lower case, and no leading digit.
 *
 * @param {string} value Display name.
 * @param {number} max   Length limit from the server.
 * @return {string} A candidate post type name.
 */
function toSlug(value, max) {
	return String(value || '')
		.toLowerCase()
		.replace(/[\s-]+/g, '_')
		.replace(/[^a-z0-9_]/g, '')
		.replace(/_+/g, '_')
		.replace(/^[^a-z]+/, '')
		.slice(0, max)
		.replace(/_+$/, '');
}

/**
 * What is wrong with this row, if anything.
 *
 * The server is still the authority — it sanitises the same rules on save, and
 * a row it rejects simply disappears. This exists so that rejection is visible
 * while the operator is still looking at the field, rather than as a row that
 * silently fails to come back.
 *
 * @param {Object} row    The row being checked.
 * @param {number} index  Its position, so a duplicate blames the later one.
 * @param {Array}  rows   Every row.
 * @param {Object} limits The server's `constraints.postTypes`.
 * @return {string} An error message, or an empty string.
 */
function rowError(row, index, rows, limits) {
	const { reserved = [], slugMaxLength = 20 } = limits;

	if (!row.singular.trim()) {
		return __(
			'Give this a singular name — a row with no name is not saved.',
			'bridge'
		);
	}

	if (!row.slug) {
		return __('Give this a slug.', 'bridge');
	}

	if (!/^[a-z][a-z0-9_]*$/.test(row.slug)) {
		return __(
			'A slug must start with a letter and hold only lower-case letters, numbers and underscores.',
			'bridge'
		);
	}

	if (row.slug.length > slugMaxLength) {
		return sprintf(
			/* translators: %d: maximum slug length in characters. */
			__('A slug may be at most %d characters.', 'bridge'),
			slugMaxLength
		);
	}

	if (reserved.includes(row.slug)) {
		return sprintf(
			/* translators: %s: the reserved slug. */
			__(
				'“%s” is reserved by WordPress and cannot be used as a slug.',
				'bridge'
			),
			row.slug
		);
	}

	if (rows.findIndex((other) => other.slug === row.slug) !== index) {
		return sprintf(
			/* translators: %s: the duplicated slug. */
			__('“%s” is already used by another content type.', 'bridge'),
			row.slug
		);
	}

	return '';
}

export function CptTab({ draft, payload, setPostTypes }) {
	const rows = draft.postTypes || [];
	const limits = payload.constraints?.postTypes || {};
	const max = limits.max || 10;
	const slugMax = limits.slugMaxLength || 20;
	const counts = payload.postTypeCounts || {};
	const homeUrl = payload.homeUrl || '/';

	const patch = (index, changes) =>
		setPostTypes(
			rows.map((row, i) => (i === index ? { ...row, ...changes } : row))
		);

	const remove = (index) => setPostTypes(rows.filter((_, i) => i !== index));

	// A new row is on, has pages and has no categories — the same three
	// answers the sanitiser gives a row that does not mention them, so a type
	// added here and a type restored from an older record behave alike.
	const add = () =>
		setPostTypes([
			...rows,
			{
				singular: '',
				plural: '',
				slug: '',
				enabled: true,
				hasPages: true,
				hasCategories: false,
			},
		]);

	return (
		<Section
			title={__('Content types', 'bridge')}
			description={__(
				'Content that is neither a page nor a blog post — projects, venues, FAQs. Each one declared here becomes an item in the admin sidebar. Every type gets a title, a block editor and an order field, and a type with pages gets an excerpt and a featured image too; nothing else is configurable, because a content type nothing renders is worse than one type too few.',
				'bridge'
			)}
		>
			{rows.length === 0 && (
				<p className="bridge-options__hint">
					{__(
						'No content types yet. Pages and posts are all this site has.',
						'bridge'
					)}
				</p>
			)}

			{rows.map((row, index) => {
				const error = rowError(row, index, rows, {
					...limits,
					slugMaxLength: slugMax,
				});
				const count = counts[row.slug] || 0;
				// Absent means yes for both, the same reading the server takes:
				// a row written before either switch existed described a
				// registered, public type.
				const enabled = row.enabled !== false;
				const hasPages = row.hasPages !== false;
				// Absent means no here, and the sanitiser agrees — see the
				// comment beside it for why this one is the other way round.
				const hasCategories = !!row.hasCategories;

				return (
					<div
						className={enabled ? 'bridge-cpt' : 'bridge-cpt is-off'}
						key={index}
					>
						<div className="bridge-cpt__fields">
							<TextControl
								label={__('Singular', 'bridge')}
								help={__('One of them.', 'bridge')}
								value={row.singular}
								autoComplete="off"
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								onChange={(value) => {
									// The slug follows the name until someone
									// takes it over. See the file header.
									const following =
										!row.slug ||
										row.slug ===
											toSlug(row.singular, slugMax);

									patch(index, {
										singular: value,
										slug: following
											? toSlug(value, slugMax)
											: row.slug,
									});
								}}
							/>

							<TextControl
								label={__('Plural', 'bridge')}
								help={__(
									'What the menu item says. Defaults to the singular.',
									'bridge'
								)}
								value={row.plural}
								autoComplete="off"
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								onChange={(value) =>
									patch(index, { plural: value })
								}
							/>

							<TextControl
								label={__('Slug', 'bridge')}
								help={__(
									'Used in every URL. Changing it later breaks the old ones.',
									'bridge'
								)}
								value={row.slug}
								autoComplete="off"
								spellCheck="false"
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								onChange={(value) =>
									patch(index, {
										slug: toSlug(value, slugMax),
									})
								}
							/>

							<div className="bridge-cpt__remove">
								<Button
									variant="tertiary"
									isDestructive
									onClick={() => remove(index)}
								>
									{__('Remove', 'bridge')}
								</Button>
							</div>
						</div>

						<div className="bridge-cpt__switches">
							<ToggleControl
								label={__('On', 'bridge')}
								help={
									enabled
										? __(
												'In the admin sidebar and on the site.',
												'bridge'
											)
										: __(
												'Switched off. The type is not registered, so it has no menu item, no editor and no pages — but this row keeps every setting on it, and the content stays in the database. Switching it back on restores both.',
												'bridge'
											)
								}
								checked={enabled}
								__nextHasNoMarginBottom
								onChange={(value) =>
									patch(index, { enabled: value })
								}
							/>

							<ToggleControl
								label={__('Pages on the site', 'bridge')}
								help={
									hasPages
										? __(
												'This content has addresses of its own: a page per item and an archive listing them.',
												'bridge'
											)
										: __(
												'Material for blocks only. No page, no archive, no search results — nothing to link to. Authors still write it here as normal.',
												'bridge'
											)
								}
								checked={hasPages}
								__nextHasNoMarginBottom
								onChange={(value) =>
									patch(index, { hasPages: value })
								}
							/>

							<ToggleControl
								label={__('Categories', 'bridge')}
								help={
									hasCategories
										? sprintf(
												/* translators: %s: singular name of the content type. */
												__(
													'Each one can be filed under a %s Category. Blocks that draw from this type can then be pointed at one category rather than all of them.',
													'bridge'
												),
												row.singular ||
													__('content', 'bridge')
											)
										: __(
												'No categories. Everything of this type is one flat set.',
												'bridge'
											)
								}
								checked={hasCategories}
								__nextHasNoMarginBottom
								onChange={(value) =>
									patch(index, { hasCategories: value })
								}
							/>
						</div>

						{error ? (
							<Notice status="warning" isDismissible={false}>
								{error}
							</Notice>
						) : (
							// Not shown while the type is switched off: there
							// is no such address until it is switched back on,
							// and printing one is promising a page that 404s.
							enabled &&
							hasPages && (
								<p className="bridge-cpt__url">
									{homeUrl}
									<strong>{row.slug}</strong>
									{'/'}
								</p>
							)
						)}

						{/*
						 * Said next to the controls rather than behind a
						 * confirmation, because neither switching a type off
						 * nor removing it deletes anything — the posts stay in
						 * the database and come back with the slug. What an
						 * operator needs is to know that, not another dialog to
						 * click through.
						 */}
						{count > 0 && (
							<p className="bridge-cpt__count">
								{sprintf(
									/* translators: %d: number of posts. */
									__(
										'Holding %d items. Switching this off — or removing it — hides them. Neither deletes them, and both are undone by putting it back.',
										'bridge'
									),
									count
								)}
							</p>
						)}
					</div>
				);
			})}

			<div className="bridge-cpt__add">
				<Button
					variant="secondary"
					onClick={add}
					disabled={rows.length >= max}
				>
					{__('Add content type', 'bridge')}
				</Button>

				{rows.length >= max && (
					<p className="bridge-options__subhelp">
						{sprintf(
							/* translators: %d: maximum number of content types. */
							__(
								'%d is as many as this screen will declare. More than that is a conversation about the site, not another row in a form.',
								'bridge'
							),
							max
						)}
					</p>
				)}
			</div>
		</Section>
	);
}
