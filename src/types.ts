// SPDX-FileCopyrightText: 2026 André Wiesehoff
// SPDX-License-Identifier: AGPL-3.0-or-later

export interface Category {
	id: string
	title: string
}

export interface Row {
	id: string
	title: string
	href: string
	icon: string | null
	categoryId: string | null
	enabled: boolean
}

export interface CatalogEnvelope {
	revision: string
	categories: Category[]
	links: Row[]
}

export interface CoreIconChoice {
	id: string
	url: string
	label: string
}

export interface FieldError {
	index: number
	field: string
	message: string
}

export interface TileLink {
	title: string
	subtitle: string
	href: string
}

export interface TileSection {
	label: string
	links: TileLink[]
}

export interface TileState {
	sections: TileSection[]
	emptyTitle: string
	moreLabel: string
	moreUrl: string | null
	setupLabel: string
	setupUrl: string | null
}
