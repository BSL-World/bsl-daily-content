# BSL Daily Content

BSL Daily Content is a free Joomla content plugin for publishing calendar-based articles. It can render a full daily-content page from `{bsldailycontent}` and an optional compact “Today in...” block from `{bsldailycontenttoday}`.

The plugin is suitable for daily reflections, holidays, quotes, historical events, affirmations, recipes, tips, horoscopes, and other content assigned to a recurring day and month.

## Features

- full daily-content page with date navigation;
- compact monthly calendar inside a collapsible Date control;
- visual markers for dates with available published articles;
- configurable calendar size, background opacity and bold text;
- compact block for the current date;
- automatic creation of the required `bsl-daily-date` custom field;
- `DD-MM` date format;
- duplicate-date prevention within the same configured category;
- configurable primary and optional secondary Joomla content languages;
- automatic opening of the Daily Content tab for configured categories;
- language-aware article lookup and compact-module links;
- English and Russian administrator and site interface translations;
- Joomla Update System support;
- migration from BSL Daily Reflections and BSL Daily Content 1.0.0 without deleting saved date values.

## Requirements

- Joomla 5.x or Joomla 6.x;
- PHP 8.3 or later.

## Installation

1. Download `plg_content_bsldailycontent-1.3.0.zip` from the Releases page or the project website.
2. In Joomla Administrator, open **System → Extensions → Install Extensions**.
3. Upload the ZIP package.
4. Open **System → Manage → Plugins** and select **Content - BSL Daily Content**.
5. Select the primary content language and category.
6. Optionally select a secondary language and category.
7. Optionally select a compact-module link target for either language.
8. Save the plugin settings.

## Shortcodes

Place this shortcode in a Joomla article to render the full daily-content page:

```text
{bsldailycontent}
```

Place this shortcode in a Joomla Custom module to render today's compact block:

```text
{bsldailycontenttoday}
```

The shortcode should be stored as plain text. No PHP, iframe, script, or custom HTML player code is required.

## Adding daily content

1. Create or edit an article in one of the configured categories.
2. Open the **Daily Content** tab.
3. Enter the recurring date in `DD-MM` format, for example `25-12`.
4. Save and publish the article.

Each date may be used only once within the same configured category. Changing the configured category does not move articles and does not delete stored dates.

## Calendar appearance

The full daily-content page uses a compact monthly calendar inside a collapsible Date control. In the plugin settings, **Calendar background opacity (%)** controls the background of both elements from `0` (fully transparent) to `100` (fully opaque). The default value is `75`. **Calendar size** provides Small, Medium and Large versions of the complete control, including its text, cells and spacing. **Bold calendar text** can emphasize all calendar labels and dates independently of the selected size.

The administrator settings provide the site defaults. Visitors can use the compact **Aa** control on the full page to choose their own calendar size and bold text. The preference is stored locally in the visitor's browser and can be removed with **Reset**.

## Updating and migration

The plugin supports normal in-place updates through the Joomla Update System.

When updating from version 1.0.0, existing Russian and English settings are moved to the primary and secondary language slots. Categories, compact-module link targets, the custom date field, category assignments, and saved date values are preserved.

Migration from the legacy BSL Daily Reflections plugin is also supported. Replace the legacy shortcodes and verify BSL Daily Content before disabling or uninstalling the old plugin.

## Release package

Official downloads are served through the BSL-World download gateway:

https://bsl-world.ru/download.php?product=bsl-daily-content&file=plg_content_bsldailycontent-1.3.0.zip&download=1&source=site

SHA-256 for version 1.3.0:

```text
94f77af3733eb03478b2a21460e14af827f4df3d4f18566fbbde6bebfedc23ae
```

## License

GNU General Public License version 2 or later. See [LICENSE.txt](LICENSE.txt).

## About the author

My name is Alexander Vasilyev. I am an engineer and system administrator, not a professional software developer. After choosing sobriety in January 2025, I began rebuilding my life and creating practical tools for my own projects with the help of AI.

BSL Daily Content grew out of a real need on [BSL-World.ru](https://bsl-world.ru): I wanted a convenient way to publish my comments on Alcoholics Anonymous Daily Reflections. The original solution gradually evolved into a universal Joomla plugin for any calendar-based content.

BSL stands for **Beautiful Sober Life**.

Website: https://bsl-world.ru  
Email: soft@BSL-World.ru

## Support the project

If BSL Daily Content is useful to you, you can support the author and the continued development of BSL-World projects.

[Support through CloudTips](https://pay.cloudtips.ru/p/5bea09f2)

**BTC:** `bc1q3l8pgrj34re0q3whmjl96dgp3kgr623afnd8h3`
