<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Tests\Release;

use SimpleXMLElement;

final class StorePolicy {
	public const NEXTCLOUD_LATEST_STABLE = 35;
	public const NEXTCLOUD_MIN_FLOOR = 33;

	/** @var list<string> */
	private const BANNED_PATTERNS = [
		'/AI[- ]generated/i',
		'/built with AI/i',
		'/AI-built/i',
		'/with AI/i',
		'/entirely with AI/i',
		'/completely with AI/i',
		'/mit KI/u',
		'/vollständig mit KI/ui',
		'/öffentliche Referenz/ui',
		'/public reference/i',
	];

	/** @var list<string> */
	private const SHIPPED_DEV_SEGMENTS = [
		'.git',
		'.github',
		'.audit',
		'src',
		'tests',
		'docs',
		'node_modules',
		'vendor',
		'build',
		'Makefile',
		'package.json',
		'package-lock.json',
		'composer.json',
		'composer.lock',
		'phpunit.xml.dist',
		'vite.config.ts',
		'tsconfig.json',
		'eslint.config.js',
		'README.md',
	];

	/** @var list<string> */
	private const KEY_SUFFIXES = ['.key', '.csr', '.pem', '.p12', '.pfx'];

	/** @var list<string>|null */
	private readonly ?array $archiveEntries;

	private function __construct(
		private readonly string $root,
		private readonly string $scope,
		?array $archiveEntries = null,
	) {
		$this->archiveEntries = $archiveEntries;
	}

	public static function repository(string $root): self {
		return new self($root, 'repository');
	}

	public static function stagedApp(string $root): self {
		return new self($root, 'staged');
	}

	/**
	 * @param list<string> $entries
	 */
	public static function archive(string $appId, array $entries): self {
		return new self($appId, 'archive', $entries);
	}

	/**
	 * @return list<Violation>
	 */
	public function violations(): array {
		return match ($this->scope) {
			'repository' => $this->repositoryViolations(),
			'staged' => array_merge($this->repositoryViolations(), $this->stagedExtraViolations()),
			'archive' => $this->archiveViolations(),
			default => throw new \InvalidArgumentException('unknown scope: ' . $this->scope),
		};
	}

	/**
	 * @return list<Violation>
	 */
	private function repositoryViolations(): array {
		$infoPath = $this->root . '/appinfo/info.xml';
		$info = $this->loadInfo($infoPath);
		$violations = [];
		$violations = array_merge($violations, $this->bannedCopyViolations($info, $infoPath));
		$violations = array_merge($violations, $this->versionSyncViolations($info, $infoPath));
		$violations = array_merge($violations, $this->changelogSyncViolations());
		$violations = array_merge($violations, $this->nextcloudRangeViolations($info, $infoPath));
		$violations = array_merge($violations, $this->nameRulesViolations($info, $infoPath));
		$violations = array_merge($violations, $this->keyMaterialViolations());
		return $violations;
	}

	/**
	 * @return list<Violation>
	 */
	private function stagedExtraViolations(): array {
		$violations = [];
		$violations = array_merge($violations, $this->shippedDevFileTreeViolations());
		$violations = array_merge($violations, $this->stagedLayoutViolations());
		return $violations;
	}

	/**
	 * @return list<Violation>
	 */
	private function archiveViolations(): array {
		$appId = $this->root;
		$prefix = $appId . '/';
		$entries = $this->archiveEntries ?? [];
		$violations = [];
		$hasInfo = false;
		$hasChangelog = false;

		foreach ($entries as $entry) {
			$entry = rtrim($entry, "\r\n");
			if ($entry === '') {
				continue;
			}
			if ($entry !== $prefix && $entry !== $appId && !str_starts_with($entry, $prefix)) {
				$violations[] = new Violation(Rule::ShippedLayout, $entry, 'entry is outside ' . $prefix);
				continue;
			}
			$segments = explode('/', $entry);
			foreach ($segments as $segment) {
				if ($segment === '.git') {
					$violations[] = new Violation(Rule::ShippedLayout, $entry, 'contains .git path segment');
					break;
				}
			}
			if ($entry === $prefix . 'appinfo/info.xml') {
				$hasInfo = true;
			}
			if ($entry === $prefix . 'CHANGELOG.md') {
				$hasChangelog = true;
			}
			$basename = basename($entry);
			if (str_ends_with($basename, '.map')) {
				$violations[] = new Violation(Rule::ShippedDevFile, $entry, 'ships .map file');
			}
			foreach ($segments as $segment) {
				if ($segment === '' || $segment === $appId) {
					continue;
				}
				if (in_array($segment, self::SHIPPED_DEV_SEGMENTS, true)) {
					$violations[] = new Violation(Rule::ShippedDevFile, $entry, 'forbidden path segment: ' . $segment);
				}
			}
		}

		if (!$hasInfo) {
			$violations[] = new Violation(Rule::ShippedLayout, $prefix . 'appinfo/info.xml', 'missing from archive');
		}
		if (!$hasChangelog) {
			$violations[] = new Violation(Rule::ShippedLayout, $prefix . 'CHANGELOG.md', 'missing from archive');
		}

		return $violations;
	}

	private function loadInfo(string $infoPath): SimpleXMLElement {
		if (!is_file($infoPath)) {
			throw new \RuntimeException('missing info.xml at ' . $infoPath);
		}
		$previous = libxml_use_internal_errors(true);
		$info = simplexml_load_file($infoPath);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		if ($info === false) {
			throw new \RuntimeException('failed to parse ' . $infoPath);
		}
		return $info;
	}

	/**
	 * @return list<Violation>
	 */
	private function bannedCopyViolations(SimpleXMLElement $info, string $infoPath): array {
		$violations = [];
		foreach ($info->name as $name) {
			$text = (string)$name;
			foreach ($this->bannedHits($text) as $hit) {
				$violations[] = new Violation(Rule::BannedCopy, $infoPath, 'name: ' . $hit);
			}
		}
		foreach ($info->description as $description) {
			$text = (string)$description;
			foreach ($this->bannedHits($text) as $hit) {
				$violations[] = new Violation(Rule::BannedCopy, $infoPath, 'description: ' . $hit);
			}
		}

		$files = [
			$this->root . '/CHANGELOG.md',
			$this->root . '/CHANGELOG.en.md',
			$this->root . '/README.md',
		];
		$docsDir = $this->root . '/docs';
		if (is_dir($docsDir)) {
			foreach (glob($docsDir . '/*.md') ?: [] as $doc) {
				$files[] = $doc;
			}
		}
		foreach ($files as $file) {
			if (!is_file($file)) {
				continue;
			}
			$bytes = file_get_contents($file);
			if ($bytes === false) {
				continue;
			}
			foreach ($this->bannedHits($bytes) as $hit) {
				$violations[] = new Violation(Rule::BannedCopy, $file, $hit);
			}
		}
		return $violations;
	}

	/**
	 * @return list<string>
	 */
	private function bannedHits(string $text): array {
		$hits = [];
		foreach (self::BANNED_PATTERNS as $pattern) {
			if (preg_match($pattern, $text, $m) === 1) {
				$hits[] = 'matched ' . $pattern . ' (' . $m[0] . ')';
			}
		}
		return $hits;
	}

	/**
	 * @return list<Violation>
	 */
	private function versionSyncViolations(SimpleXMLElement $info, string $infoPath): array {
		$version = trim((string)$info->version);
		$changelogPath = $this->root . '/CHANGELOG.md';
		if (!is_file($changelogPath)) {
			return [new Violation(Rule::VersionSync, $changelogPath, 'CHANGELOG.md missing')];
		}
		$lines = file($changelogPath, FILE_IGNORE_NEW_LINES);
		if ($lines === false) {
			return [new Violation(Rule::VersionSync, $changelogPath, 'unreadable CHANGELOG.md')];
		}
		$changelogVersion = null;
		foreach ($lines as $line) {
			if (preg_match('/^## (\d+\.\d+\.\d+)/', $line, $m) === 1) {
				$changelogVersion = $m[1];
				break;
			}
		}
		if ($changelogVersion === null) {
			return [new Violation(Rule::VersionSync, $changelogPath, 'no ## x.y.z heading')];
		}
		if ($changelogVersion !== $version) {
			return [new Violation(
				Rule::VersionSync,
				$infoPath,
				'info.xml version ' . $version . ' != CHANGELOG.md ' . $changelogVersion,
			)];
		}
		return [];
	}

	/**
	 * @return list<Violation>
	 */
	private function changelogSyncViolations(): array {
		$a = $this->root . '/CHANGELOG.md';
		$b = $this->root . '/CHANGELOG.en.md';
		if (!is_file($b)) {
			return [new Violation(Rule::ChangelogSync, $b, 'CHANGELOG.en.md missing')];
		}
		if (!is_file($a)) {
			return [new Violation(Rule::ChangelogSync, $a, 'CHANGELOG.md missing')];
		}
		$bytesA = file_get_contents($a);
		$bytesB = file_get_contents($b);
		if ($bytesA === false || $bytesB === false) {
			return [new Violation(Rule::ChangelogSync, $b, 'unreadable changelog files')];
		}
		if ($bytesA === $bytesB) {
			return [];
		}
		$linesA = preg_split("/\r\n|\n|\r/", $bytesA) ?: [];
		$linesB = preg_split("/\r\n|\n|\r/", $bytesB) ?: [];
		$max = max(count($linesA), count($linesB));
		$line = 1;
		for ($i = 0; $i < $max; $i++) {
			$la = $linesA[$i] ?? null;
			$lb = $linesB[$i] ?? null;
			if ($la !== $lb) {
				$line = $i + 1;
				break;
			}
		}
		return [new Violation(Rule::ChangelogSync, $b, 'differs from CHANGELOG.md at line ' . $line)];
	}

	/**
	 * @return list<Violation>
	 */
	private function nextcloudRangeViolations(SimpleXMLElement $info, string $infoPath): array {
		$nc = $info->dependencies->nextcloud ?? null;
		if ($nc === null) {
			return [new Violation(Rule::NextcloudRange, $infoPath, 'missing dependencies/nextcloud')];
		}
		$min = (int)(string)$nc['min-version'];
		$max = (int)(string)$nc['max-version'];
		$ceiling = self::NEXTCLOUD_LATEST_STABLE + 1;
		if ($min < self::NEXTCLOUD_MIN_FLOOR || $min > $max || $max > $ceiling) {
			return [new Violation(
				Rule::NextcloudRange,
				$infoPath,
				sprintf(
					'need %d <= min-version (%d) <= max-version (%d) <= %d',
					self::NEXTCLOUD_MIN_FLOOR,
					$min,
					$max,
					$ceiling,
				),
			)];
		}
		return [];
	}

	/**
	 * @return list<Violation>
	 */
	private function nameRulesViolations(SimpleXMLElement $info, string $infoPath): array {
		$violations = [];
		foreach ($info->name as $name) {
			$text = (string)$name;
			if (stripos($text, 'Nextcloud') !== false) {
				$violations[] = new Violation(Rule::NameRules, $infoPath, 'name contains Nextcloud: ' . $text);
			}
		}
		return $violations;
	}

	/**
	 * @return list<Violation>
	 */
	private function keyMaterialViolations(): array {
		$violations = [];
		$skip = ['vendor', 'node_modules', 'build', '.git'];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
		);
		/** @var \SplFileInfo $file */
		foreach ($iterator as $file) {
			if (!$file->isFile()) {
				continue;
			}
			$relative = substr($file->getPathname(), strlen($this->root) + 1);
			$parts = explode('/', str_replace('\\', '/', $relative));
			$top = $parts[0] ?? '';
			if (in_array($top, $skip, true)) {
				continue;
			}
			$name = $file->getFilename();
			foreach (self::KEY_SUFFIXES as $suffix) {
				if (str_ends_with($name, $suffix)) {
					$violations[] = new Violation(Rule::KeyMaterial, $relative, 'key material file');
					break;
				}
			}
		}
		return $violations;
	}

	/**
	 * @return list<Violation>
	 */
	private function shippedDevFileTreeViolations(): array {
		$violations = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST,
		);
		/** @var \SplFileInfo $file */
		foreach ($iterator as $file) {
			$relative = substr($file->getPathname(), strlen($this->root) + 1);
			$relative = str_replace('\\', '/', $relative);
			$segments = explode('/', $relative);
			$basename = basename($relative);
			if ($file->isFile() && str_ends_with($basename, '.map')) {
				$violations[] = new Violation(Rule::ShippedDevFile, $relative, 'ships .map file');
			}
			foreach ($segments as $segment) {
				if (in_array($segment, self::SHIPPED_DEV_SEGMENTS, true)) {
					$violations[] = new Violation(Rule::ShippedDevFile, $relative, 'forbidden path segment: ' . $segment);
					break;
				}
			}
		}
		return $violations;
	}

	/**
	 * @return list<Violation>
	 */
	private function stagedLayoutViolations(): array {
		$violations = [];
		$infoPath = $this->root . '/appinfo/info.xml';
		$info = $this->loadInfo($infoPath);
		$id = (string)$info->id;
		$basename = basename($this->root);
		if ($basename !== $id) {
			$violations[] = new Violation(
				Rule::ShippedLayout,
				$this->root,
				'basename ' . $basename . ' != id ' . $id,
			);
		}
		if (!is_file($infoPath)) {
			$violations[] = new Violation(Rule::ShippedLayout, 'appinfo/info.xml', 'missing');
		}
		if (!is_file($this->root . '/CHANGELOG.md')) {
			$violations[] = new Violation(Rule::ShippedLayout, 'CHANGELOG.md', 'missing');
		}
		return $violations;
	}
}
