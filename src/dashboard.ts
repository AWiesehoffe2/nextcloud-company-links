// SPDX-FileCopyrightText: 2026 André Wiesehoff
// SPDX-License-Identifier: AGPL-3.0-or-later

interface DashboardApi {
	register: (app: string, callback: (el: HTMLElement) => void) => void
}

interface TileLink {
	title: string
	subtitle: string
	href: string
}

interface TileSection {
	label: string
	links: TileLink[]
}

interface TileState {
	sections: TileSection[]
	emptyTitle: string
	moreLabel: string
	moreUrl: string | null
	setupLabel: string
	setupUrl: string | null
}

const APP_ID = 'dashboard_links'

interface InitialStateWindow extends Window {
	_nc_initial_state?: Map<string, unknown>
}

const EMPTY_TILE: TileState = {
	sections: [],
	emptyTitle: '',
	moreLabel: '',
	moreUrl: null,
	setupLabel: '',
	setupUrl: null,
}

/**
 * @param className CSS class
 * @param text Text content
 */
function span(className: string, text: string): HTMLSpanElement {
	const el = document.createElement('span')
	el.className = className
	el.textContent = text
	return el
}

/**
 * @param tile Grouped links for this user
 */
function render(tile: TileState): HTMLDivElement {
	const root = document.createElement('div')
	root.className = 'dashboard-links-tile'

	if (tile.sections.length === 0) {
		const empty = document.createElement('p')
		empty.className = 'dashboard-links-tile-empty'
		empty.textContent = tile.emptyTitle
		root.append(empty)
	}

	for (const section of tile.sections) {
		const block = document.createElement('section')
		block.className = 'dashboard-links-tile-section'
		if (section.label !== '') {
			const heading = document.createElement('h3')
			heading.className = 'dashboard-links-tile-heading'
			heading.textContent = section.label
			block.append(heading)
		}
		const list = document.createElement('ul')
		list.className = 'dashboard-links-tile-list'
		for (const link of section.links) {
			const item = document.createElement('li')
			const anchor = document.createElement('a')
			anchor.className = 'dashboard-links-tile-link'
			anchor.href = link.href
			const arrow = span('dashboard-links-tile-arrow', '→')
			arrow.setAttribute('aria-hidden', 'true')
			const body = document.createElement('span')
			body.className = 'dashboard-links-tile-text'
			body.append(
				span('dashboard-links-tile-title', link.title),
				span('dashboard-links-tile-host', link.subtitle),
			)
			anchor.append(arrow, body)
			item.append(anchor)
			list.append(item)
		}
		block.append(list)
		root.append(block)
	}

	if (tile.moreUrl !== null && tile.moreUrl !== '') {
		const more = document.createElement('a')
		more.className = 'dashboard-links-tile-more'
		more.href = tile.moreUrl
		more.textContent = tile.moreLabel
		root.append(more)
	}
	if (tile.setupUrl !== null && tile.setupUrl !== '') {
		const setup = document.createElement('a')
		setup.className = 'button'
		setup.href = tile.setupUrl
		setup.textContent = tile.setupLabel
		root.append(setup)
	}

	return root
}

/**
 * Read the tile payload PHP put on the page. Kept local so this bundle
 * does not share a chunk with the admin settings script.
 */
function loadTile(): TileState {
	const key = '#initial-state-dashboard_links-tile'
	const cached = (window as InitialStateWindow)._nc_initial_state
	if (cached?.has(key)) {
		return cached.get(key) as TileState
	}
	const input = document.querySelector<HTMLInputElement>(key)
	if (input === null) {
		return EMPTY_TILE
	}
	try {
		return JSON.parse(atob(input.value)) as TileState
	} catch {
		return EMPTY_TILE
	}
}

/**
 * Mount once per panel node. A replaced node can mount again.
 *
 * @param el Panel content node
 */
function mountTile(el: HTMLElement): void {
	if (el.dataset.dashboardLinksMounted === '1') {
		return
	}
	el.dataset.dashboardLinksMounted = '1'
	el.replaceChildren(render(loadTile()))
}

/**
 * @return Dashboard register API once the dashboard app has published it
 */
function dashboardApi(): DashboardApi | null {
	const oca = (window as Window & { OCA?: { Dashboard?: DashboardApi } }).OCA
	return oca?.Dashboard ?? null
}

/**
 * Register with the dashboard, and mount directly if that callback already ran.
 *
 * @param attempt How many times this has retried
 */
function boot(attempt: number): void {
	dashboardApi()?.register(APP_ID, mountTile)
	const el = document.querySelector<HTMLElement>(`[data-id="${APP_ID}"]`)
	if (el !== null) {
		mountTile(el)
	}
	if (el?.dataset.dashboardLinksMounted !== '1' && attempt < 20) {
		window.setTimeout(() => boot(attempt + 1), 50)
	}
}

boot(0)
