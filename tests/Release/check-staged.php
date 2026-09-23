<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require_once __DIR__ . '/Rule.php';
require_once __DIR__ . '/Violation.php';
require_once __DIR__ . '/StorePolicy.php';

use OCA\DashboardLinks\Tests\Release\StorePolicy;
use OCA\DashboardLinks\Tests\Release\Violation;

if ($argc < 3) {
	fwrite(STDERR, "Usage: php tests/Release/check-staged.php staged <dir>\n");
	fwrite(STDERR, "       php tests/Release/check-staged.php archive <tar.gz>\n");
	exit(1);
}

$mode = $argv[1];
$target = $argv[2];

/** @var list<Violation> $violations */
$violations = match ($mode) {
	'staged' => StorePolicy::stagedApp($target)->violations(),
	'archive' => (static function (string $tar): array {
		$lines = [];
		$code = 0;
		exec('tar -tzf ' . escapeshellarg($tar) . ' 2>&1', $lines, $code);
		if ($code !== 0) {
			fwrite(STDERR, 'tar -tzf failed for ' . $tar . "\n");
			exit(1);
		}
		return StorePolicy::archive('dashboard_links', $lines)->violations();
	})($target),
	default => (static function (string $mode): never {
		fwrite(STDERR, 'unknown mode: ' . $mode . "\n");
		exit(1);
	})($mode),
};

if ($violations !== []) {
	foreach ($violations as $violation) {
		fwrite(STDERR, (string)$violation . "\n");
	}
	exit(1);
}

exit(0);
