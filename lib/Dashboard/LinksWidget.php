<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Dashboard;

use OCA\DashboardLinks\AppInfo\Application;
use OCA\DashboardLinks\Links\CatalogStore;
use OCA\DashboardLinks\Links\LinkId;
use OCA\DashboardLinks\Links\LinkPresenter;
use OCA\DashboardLinks\Links\LinkUrls;
use OCA\DashboardLinks\Links\LinkView;
use OCP\AppFramework\Services\IInitialState;
use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetItem;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\Util;

final class LinksWidget implements IAPIWidget, IIconWidget, IButtonWidget {
	public const ID = Application::APP_ID;
	public const ORDER = 20;

	private const WEB_TILE_LIMIT = 7;

	public function __construct(
		private readonly IL10N $l10n,
		private readonly IGroupManager $groupManager,
		private readonly CatalogStore $store,
		private readonly LinkPresenter $presenter,
		private readonly LinkUrls $urls,
		private readonly IUserSession $userSession,
		private readonly IInitialState $initialState,
	) {
	}

	#[\Override]
	public function getId(): string {
		return self::ID;
	}

	#[\Override]
	public function getTitle(): string {
		return $this->l10n->t('Company links');
	}

	#[\Override]
	public function getOrder(): int {
		return self::ORDER;
	}

	#[\Override]
	public function getIconClass(): string {
		return 'icon-dashboard_links';
	}

	#[\Override]
	public function getIconUrl(): string {
		return $this->urls->defaultIconUrl();
	}

	#[\Override]
	public function getUrl(): ?string {
		return $this->urls->allLinksUrl();
	}

	#[\Override]
	public function load(): void {
		$user = $this->userSession->getUser();
		$this->initialState->provideInitialState(
			'tile',
			$this->tileState($user?->getUID() ?? ''),
		);
		Util::addScript(Application::APP_ID, Application::APP_ID . '-dashboard');
		Util::addStyle(Application::APP_ID, 'dashboard');
	}

	/** @return list<WidgetItem> */
	#[\Override]
	public function getItems(string $userId, ?string $since = null, int $limit = 7): array {
		return $this->page($since, $limit);
	}

	/**
	 * Grouped tile for the browser. Clients still use the flat {@see getItems()} list.
	 *
	 * @return array{
	 *     sections: list<array{label: string, links: list<array{title: string, subtitle: string, href: string}>}>,
	 *     emptyTitle: string,
	 *     moreLabel: string,
	 *     moreUrl: ?string,
	 *     setupLabel: string,
	 *     setupUrl: ?string
	 * }
	 */
	public function tileState(string $userId): array {
		$visibleCount = count($this->store->current()->visible());

		return [
			'sections' => $this->tileSections(),
			'emptyTitle' => $this->l10n->t('No company links configured yet'),
			'moreLabel' => $this->l10n->t('All links'),
			'moreUrl' => $visibleCount > self::WEB_TILE_LIMIT ? $this->urls->allLinksUrl() : null,
			'setupLabel' => $this->l10n->t('Configure'),
			'setupUrl' => $visibleCount === 0 && $this->groupManager->isAdmin($userId)
				? $this->urls->settingsUrl()
				: null,
		];
	}

	/** @return list<WidgetButton> */
	#[\Override]
	public function getWidgetButtons(string $userId): array {
		$visible = $this->store->current()->visible();
		if (count($visible) > self::WEB_TILE_LIMIT) {
			return [
				new WidgetButton(
					WidgetButton::TYPE_MORE,
					$this->urls->allLinksUrl(),
					$this->l10n->t('All links'),
				),
			];
		}
		if (count($visible) === 0 && $this->groupManager->isAdmin($userId)) {
			return [
				new WidgetButton(
					WidgetButton::TYPE_SETUP,
					$this->urls->settingsUrl(),
					$this->l10n->t('Configure'),
				),
			];
		}

		return [];
	}

	/** @return list<WidgetItem> */
	private function page(?string $since, int $limit): array {
		$catalog = $this->store->current();
		$views = $this->presenter->views(
			$catalog->visible()->after(LinkId::tryParse($since))->take($limit),
			$catalog->categoryTitles(),
		);

		return array_map(
			static fn (LinkView $view): WidgetItem => new WidgetItem(
				$view->title,
				$view->subtitle,
				$view->href,
				$view->iconUrl,
				$view->id,
				$view->overlayIconUrl,
			),
			$views,
		);
	}

	/**
	 * First seven visible links, grouped under their category heading.
	 *
	 * @return list<array{label: string, links: list<array{title: string, subtitle: string, href: string}>}>
	 */
	private function tileSections(): array {
		$catalog = $this->store->current();
		$remaining = self::WEB_TILE_LIMIT;
		$sections = [];
		foreach ($catalog->sections($this->l10n->t('Uncategorized')) as $section) {
			if ($remaining < 1) {
				break;
			}
			$views = $this->presenter->views($section['links']->take($remaining));
			if ($views === []) {
				continue;
			}
			$remaining -= count($views);
			$links = [];
			foreach ($views as $view) {
				$links[] = [
					'title' => $view->title,
					'subtitle' => $view->subtitle,
					'href' => $view->href,
				];
			}
			$sections[] = [
				'label' => $section['label'],
				'links' => $links,
			];
		}

		return $sections;
	}
}
