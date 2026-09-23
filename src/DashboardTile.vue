<!--
  SPDX-FileCopyrightText: 2026 André Wiesehoff
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="dashboard-links-tile">
		<p v-if="tile.sections.length === 0" class="dashboard-links-tile-empty">
			{{ tile.emptyTitle }}
		</p>
		<section
			v-for="(section, sectionIndex) in tile.sections"
			:key="sectionIndex"
			class="dashboard-links-tile-section">
			<h3 v-if="section.label !== ''" class="dashboard-links-tile-heading">
				{{ section.label }}
			</h3>
			<ul class="dashboard-links-tile-list">
				<li v-for="(link, linkIndex) in section.links" :key="sectionIndex + '-' + linkIndex">
					<a class="dashboard-links-tile-link" :href="link.href">
						<span class="dashboard-links-tile-arrow" aria-hidden="true">→</span>
						<span class="dashboard-links-tile-text">
							<span class="dashboard-links-tile-title">{{ link.title }}</span>
							<span class="dashboard-links-tile-host">{{ link.subtitle }}</span>
						</span>
					</a>
				</li>
			</ul>
		</section>
		<a v-if="tile.moreUrl" class="dashboard-links-tile-more" :href="tile.moreUrl">
			{{ tile.moreLabel }}
		</a>
		<a v-if="tile.setupUrl" class="button" :href="tile.setupUrl">
			{{ tile.setupLabel }}
		</a>
	</div>
</template>

<script setup lang="ts">
import { loadState } from '@nextcloud/initial-state'

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

const tile = loadState<TileState>('dashboard_links', 'tile', {
	sections: [],
	emptyTitle: '',
	moreLabel: '',
	moreUrl: null,
	setupLabel: '',
	setupUrl: null,
})
</script>
