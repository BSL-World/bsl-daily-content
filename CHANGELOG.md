# Changelog

## 1.3.1 — 2026-10-23

### Added

- Configurable calendar accent-color source: H1–H6, navigation, links, or a custom color.
- Automatic contrasting foreground color for calendar controls.

### Changed

- Calendar controls now derive their accent color from the selected visible template element.
- If the selected template element is unavailable, the plugin falls back to H1 and then Bootstrap Primary.

### Fixed

- Calendar controls no longer remain blue on templates that do not expose their actual visual accent through Bootstrap's standard Primary variable.

### Release history note

- Version 1.2.0 was used during internal development and testing but was not published as a separate public release. Its completed changes were incorporated into version 1.3.0.

## 1.3.0 — 2026-10-18

### Added

- Compact responsive monthly calendar inside a collapsible Date control.
- Visual markers for dates whose articles are currently available to the visitor.
- Separate highlighting for the selected date and today's date.
- Calendar background opacity slider with a live percentage value.
- Small, Medium and Large sizes for the complete Date control and calendar.
- Independent Bold calendar text option.
- Compact Aa control for visitors to select calendar size and bold text on the full page.
- Browser-local visitor preferences with a Reset action that restores the site defaults.
- Translated visual tooltip for the Aa control on hover and keyboard focus.

### Changed

- Replaced the day and month dropdown lists and the Show button on the full daily-content page.
- Preserves the user's open or closed calendar state while navigating between dates.
- Loads calendar data and appearance settings without reusing a stale browser or Joomla page-cache response.
- Closes the Aa appearance panel when the visitor clicks anywhere outside it.

### Preserved

- Existing `?date=DD-MM` links, language switching, article loading, JComments integration, and Previous/Next navigation.
- Existing language, category, link-target, date-field and saved-date settings during update.

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
