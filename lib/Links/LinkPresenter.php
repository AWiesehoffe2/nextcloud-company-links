<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

final class LinkPresenter {
	public function __construct(
		private readonly LinkUrls $urls,
	) {
	}

	/**
	 * @param array<string, string> $categoryTitles category id => title
	 * @return list<LinkView>
	 */
	public function views(VisibleLinks $links, array $categoryTitles = []): array {
		$views = [];
		foreach ($links->links() as $link) {
			$views[] = new LinkView(
				(string)$link->id,
				$link->title,
				$this->subtitle($link, $categoryTitles),
				$this->urls->openUrl($link),
				$this->urls->iconUrl($link),
				'',
			);
		}

		return $views;
	}

	/**
	 * Host, or the category title in front of the host when the link is grouped.
	 *
	 * @param array<string, string> $categoryTitles
	 */
	private function subtitle(CompanyLink $link, array $categoryTitles): string {
		$host = $link->href->host();
		if ($link->categoryId === null) {
			return $host;
		}
		$title = $categoryTitles[(string)$link->categoryId] ?? '';
		if ($title === '') {
			return $host;
		}

		return $title . ' · ' . $host;
	}
}
