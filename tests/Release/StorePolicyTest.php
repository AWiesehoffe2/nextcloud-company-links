<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Tests\Release;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StorePolicyTest extends TestCase {
	public function testRepositoryIsPublishable(): void {
		$root = dirname(__DIR__, 2);
		self::assertSame([], StorePolicy::repository($root)->violations());
	}

	public function testEveryRuleHasAMutation(): void {
		$covered = [];
		foreach (self::ruleMutationProvider() as [$rule]) {
			$covered[$rule->name] = true;
		}
		foreach (Rule::cases() as $rule) {
			self::assertArrayHasKey($rule->name, $covered, 'missing mutation for ' . $rule->name);
		}
	}

	/**
	 * @return \Generator<string, array{Rule, callable(): list<Violation>}>
	 */
	public static function ruleMutationProvider(): \Generator {
		yield 'BannedCopy' => [Rule::BannedCopy, static function (): array {
			$dir = self::copyPublishableTree();
			file_put_contents($dir . '/README.md', file_get_contents($dir . '/README.md') . "\nbuilt with AI\n");
			return StorePolicy::repository($dir)->violations();
		}];
		yield 'VersionSync' => [Rule::VersionSync, static function (): array {
			$dir = self::copyPublishableTree();
			$info = $dir . '/appinfo/info.xml';
			$xml = file_get_contents($info);
			self::assertNotFalse($xml);
			file_put_contents($info, preg_replace('#<version>[^<]+</version>#', '<version>9.9.9</version>', $xml, 1));
			return StorePolicy::repository($dir)->violations();
		}];
		yield 'ChangelogSync' => [Rule::ChangelogSync, static function (): array {
			$dir = self::copyPublishableTree();
			file_put_contents($dir . '/CHANGELOG.en.md', file_get_contents($dir . '/CHANGELOG.en.md') . "\nextra\n");
			return StorePolicy::repository($dir)->violations();
		}];
		yield 'NextcloudRange' => [Rule::NextcloudRange, static function (): array {
			$dir = self::copyPublishableTree();
			$info = $dir . '/appinfo/info.xml';
			$xml = file_get_contents($info);
			self::assertNotFalse($xml);
			file_put_contents($info, preg_replace('/max-version="\d+"/', 'max-version="99"', $xml, 1));
			return StorePolicy::repository($dir)->violations();
		}];
		yield 'NameRules' => [Rule::NameRules, static function (): array {
			$dir = self::copyPublishableTree();
			$info = $dir . '/appinfo/info.xml';
			$xml = file_get_contents($info);
			self::assertNotFalse($xml);
			file_put_contents($info, preg_replace(
				'#<name>Company Links Dashboard</name>#',
				'<name>Nextcloud Company Links</name>',
				$xml,
				1,
			));
			return StorePolicy::repository($dir)->violations();
		}];
		yield 'KeyMaterial' => [Rule::KeyMaterial, static function (): array {
			$dir = self::copyPublishableTree();
			file_put_contents($dir . '/secret.key', "secret\n");
			return StorePolicy::repository($dir)->violations();
		}];
		yield 'ShippedDevFile' => [Rule::ShippedDevFile, static function (): array {
			$dir = self::copyStagedTree();
			mkdir($dir . '/src', 0777, true);
			file_put_contents($dir . '/src/App.php', "<?php\n");
			return StorePolicy::stagedApp($dir)->violations();
		}];
		yield 'ShippedLayout' => [Rule::ShippedLayout, static function (): array {
			return StorePolicy::archive('dashboard_links', [
				'dashboard_links/',
				'dashboard_links/appinfo/info.xml',
				'dashboard_links/CHANGELOG.md',
				'dashboard_links/.git/config',
			])->violations();
		}];
	}

	#[DataProvider('ruleMutationProvider')]
	public function testMutationRaisesRule(Rule $rule, callable $run): void {
		$violations = $run();
		$rules = array_map(static fn (Violation $v): Rule => $v->rule, $violations);
		self::assertContains($rule, $rules, 'expected ' . $rule->name . ' among ' . implode(', ', array_map(
			static fn (Rule $r): string => $r->name,
			$rules,
		)));
	}

	private static function repoRoot(): string {
		return dirname(__DIR__, 2);
	}

	private static function copyPublishableTree(): string {
		$root = self::repoRoot();
		$dir = sys_get_temp_dir() . '/dl-storepolicy-' . uniqid('', true);
		mkdir($dir . '/appinfo', 0777, true);
		mkdir($dir . '/docs', 0777, true);
		copy($root . '/appinfo/info.xml', $dir . '/appinfo/info.xml');
		copy($root . '/CHANGELOG.md', $dir . '/CHANGELOG.md');
		copy($root . '/CHANGELOG.en.md', $dir . '/CHANGELOG.en.md');
		copy($root . '/README.md', $dir . '/README.md');
		foreach (glob($root . '/docs/*.md') ?: [] as $doc) {
			copy($doc, $dir . '/docs/' . basename($doc));
		}
		return $dir;
	}

	private static function copyStagedTree(): string {
		$root = self::repoRoot();
		$parent = sys_get_temp_dir() . '/dl-staged-' . uniqid('', true);
		$dir = $parent . '/dashboard_links';
		mkdir($dir . '/appinfo', 0777, true);
		copy($root . '/appinfo/info.xml', $dir . '/appinfo/info.xml');
		copy($root . '/CHANGELOG.md', $dir . '/CHANGELOG.md');
		copy($root . '/CHANGELOG.en.md', $dir . '/CHANGELOG.en.md');
		return $dir;
	}
}
