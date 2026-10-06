# Changelog

## 1.1.0 — 2026-10-05

### Added

- Configurable primary and optional secondary Joomla content languages.
- Support for any two installed content languages instead of fixed Russian and English settings.
- Support for single-language sites by leaving the secondary language and category empty.

### Changed

- Existing 1.0.0 Russian and English settings are migrated automatically to the new language slots during update.

### Notes

- Existing categories, compact-module link targets, the date field, category assignments, and saved date values are preserved during migration.
- English and Russian remain available as administrator and site interface translations; they are no longer hard-coded content-language requirements.
- Clean installation and in-place update were verified on Joomla 5 and Joomla 6.

## 1.0.0 — 2026-10-05

- First stable BSL Daily Content release.
- Verified clean installation, in-place update, multilingual full pages and compact modules, and migration from BSL Daily Reflections without loss of saved dates.
- Promoted the fully tested `0.1.0-alpha7` build without functional code changes.

## 0.1.0-alpha7

- Automatically opens the Daily Content tab whenever an article is assigned to a configured daily-content category.

## 0.1.0-alpha6

- Clarified compact-module link target settings and installation guidance.
- Changed the English compact-module link text to “Read full entry”.

## 0.1.0-alpha5

- Restored Joomla's standard article-editor appearance.

## 0.1.0-alpha4

- Improved application of the experimental Daily Content tab styling.

## 0.1.0-alpha3

- Added duplicate-date prevention within the same configured category.
- Added safe import of empty BSL Daily Reflections settings.

## 0.1.0-alpha2

- Added an explicit Select option to category settings.

## 0.1.0-alpha1

- Added automatic creation of the `bsl-daily-date` custom field.
- Added `{bsldailycontent}` and `{bsldailycontenttoday}`.
- Added multilingual article lookup and optional language-specific compact-module links.
- Added English and Russian interface strings.
