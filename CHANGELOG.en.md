<!--
  SPDX-FileCopyrightText: 2026 André Wiesehoff
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- The Dashboard tile shows each category as a heading, with its links listed underneath. The host stays on the link, without the category name in front of it.
- Links without a category stay in that list and get no heading.
- All links uses the same heading and no longer repeats the category in front of the host.
- Importing an External sites iframe stores the in-Nextcloud page `/apps/external/{id}/`. Redirect sites still store the https URL.
- Import from External sites is shown only when that app has at least one site.

## 1.0.0 - 2026-09-23

First version of Company Links Dashboard (`dashboard_links`).

### Added

- Dashboard tile of admin-configured company links for Nextcloud 33–35 and PHP 8.2–8.5.
- `IAPIWidgetV2` tile with no dashboard JavaScript.
- `/open/{id}` responds with 303 to the https URL so bookmarks keep working.
- Admin OCS `GET`/`PUT` `/ocs/v2.php/apps/dashboard_links/api/v1/catalog`.
- Catalog stored in one lazy `IAppConfig` key `catalog`.
- Admin settings Vue page with Save over the OCS envelope and optional browser-only External sites import.
- Privacy notice for opening a link (browser request to the destination).
- Password confirmation on catalog save and icon upload.
- German translations (`l10n/de`).

### Changed

- Catalog is one list plus optional custom categories. Featured, Company, and Reference lanes are gone.
- A categorized link shows its category on the Dashboard tile and on All links, in front of the host (`Category · host`). All links still groups those links under the category heading.
- The widget title stays the translated app name.
- Admins pick a Nextcloud icon or upload one.
- Save shows a success note.
- Admin OCS body is `{revision, categories, links}`. Schema 1 catalogs migrate on read.

### Removed

- Open modes are gone. `/open/{id}` always responds with 303 to the https URL.
