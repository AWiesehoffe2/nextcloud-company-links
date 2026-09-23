// SPDX-FileCopyrightText: 2026 André Wiesehoff
// SPDX-License-Identifier: AGPL-3.0-or-later

import { createApp } from 'vue'
import DashboardTile from './DashboardTile.vue'

interface DashboardApi {
	register: (app: string, callback: (el: HTMLElement) => void) => void
}

const APP_ID = 'dashboard_links'

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
	createApp(DashboardTile).mount(el)
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
