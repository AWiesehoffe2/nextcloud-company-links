<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Tests\Release;

enum Rule {
	case BannedCopy;
	case VersionSync;
	case ChangelogSync;
	case NextcloudRange;
	case NameRules;
	case KeyMaterial;
	case ShippedDevFile;
	case ShippedLayout;
}
