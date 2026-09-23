<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Tests\Release;

final readonly class Violation {
	public function __construct(
		public Rule $rule,
		public string $path,
		public string $detail,
	) {
	}

	public function __toString(): string {
		return $this->rule->name . ': ' . $this->path . ': ' . $this->detail;
	}
}
