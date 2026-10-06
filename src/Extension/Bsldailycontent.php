<?php

/**
 * @package     BSL Daily Content
 * @copyright   Copyright (C) 2026 Vasilyev Alexander. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace BSLWorld\Plugin\Content\Bsldailycontent\Extension;

defined('_JEXEC') or die;

use DateTimeImmutable;
use DateTimeZone;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Event\Model\BeforeSaveEvent;
use Joomla\CMS\Event\Model\PrepareFormEvent;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

final class Bsldailycontent extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    private const FULL_SHORTCODE = '{bsldailycontent}';

    private const TODAY_SHORTCODE = '{bsldailycontenttoday}';

    private const VERSION = '1.1.0';

    private const DEFAULT_DATE_FIELD_ID = 0;

    private const DATE_FIELD_NAME = 'bsl-daily-date';

    protected $autoloadLanguage = true;

    private bool $scriptIncluded = false;

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare'            => 'onContentPrepare',
            'onContentPrepareForm'        => 'onContentPrepareForm',
            'onContentBeforeSave'         => 'onContentBeforeSave',
            'onAjaxBsldailycontent'       => 'onAjaxBsldailycontent',
        ];
    }

    public function onContentPrepareForm(PrepareFormEvent $event): void
    {
        $application = $this->getApplication();
        $form = $event->getForm();

        if (
            !$application->isClient('administrator')
            || $form->getName() !== 'com_content.article'
        ) {
            return;
        }

        $categoryIds = array_values(array_unique(array_column(
            $this->getConfiguredLanguageSlots(),
            'category_id'
        )));

        if ($categoryIds === []) {
            return;
        }

        $document = $application->getDocument();

        if (!$document instanceof HtmlDocument) {
            return;
        }

        $document->addScriptOptions(
            'plg_content_bsldailycontent.admin',
            [
                'categoryIds' => $categoryIds,
                'fieldName'   => self::DATE_FIELD_NAME,
            ]
        );
        $document->getWebAssetManager()->registerAndUseScript(
            'plg_content_bsldailycontent.admin',
            'plg_content_bsldailycontent/admin-daily-content.js',
            ['version' => self::VERSION],
            ['defer' => true],
            ['core']
        );
    }

    public function onAjaxBsldailycontent(AjaxEvent $event): void
    {
        $requestedDate = $this->getApplication()->getInput()->getString(
            'date',
            (new DateTimeImmutable())->format('d-m')
        );
        $requestedLanguage = $this->getApplication()->getInput()->getString(
            'language',
            $this->getApplication()->getLanguage()->getTag()
        );
        $languageConfiguration = $this->getConfigurationForLanguage(
            $requestedLanguage
        );
        $languageTag = $languageConfiguration['language'] ?? $requestedLanguage;
        $categoryId = $languageConfiguration['category_id'] ?? 0;
        $fieldId = $this->resolveDateFieldId(
            (int) $this->params->get(
                'date_field_id',
                self::DEFAULT_DATE_FIELD_ID
            )
        );
        $calendarDate = DateTimeImmutable::createFromFormat(
            '!d-m-Y',
            $requestedDate . '-2000'
        );
        $dateIsValid = $calendarDate !== false
            && $calendarDate->format('d-m') === $requestedDate;
        $previousDate = $dateIsValid
            ? $calendarDate->modify('-1 day')->format('d-m')
            : null;
        $nextDate = $dateIsValid
            ? $calendarDate->modify('+1 day')->format('d-m')
            : null;
        $article = $dateIsValid && $categoryId > 0 && $fieldId > 0
            ? $this->findArticleByDate(
                $requestedDate,
                $languageTag,
                $categoryId,
                $fieldId
            )
            : null;

        $result = [
            'success'        => $article !== null,
            'requested_date' => $requestedDate,
            'language'       => $languageTag,
            'prev_date'      => $previousDate,
            'next_date'      => $nextDate,
            'article_link'   => '',
            'title'          => '',
            'introtext'      => '',
            'fulltext'       => '',
        ];

        if ($article !== null) {
            $result['article_link'] = Uri::root(true)
                . '/index.php?option=com_content&view=article&id='
                . (int) $article['id']
                . '&catid=' . (int) $article['catid'];
            $result['title'] = (string) $article['title'];
            $result['introtext'] = (string) $article['introtext'];
            $result['fulltext'] = (string) ($article['fulltext'] ?? '');
            $result['article_id'] = (int) $article['id'];
        }

        $event->addResult($result);
    }

    public function onContentPrepare(ContentPrepareEvent $event): void
    {
        if (!$this->getApplication()->isClient('site')) {
            return;
        }

        $item = $event->getItem();

        if (!is_object($item) || !property_exists($item, 'text')) {
            return;
        }

        $text = (string) $item->text;

        $hasFullShortcode = strpos($text, self::FULL_SHORTCODE) !== false;
        $hasTodayShortcode = strpos($text, self::TODAY_SHORTCODE) !== false;

        if (!$hasFullShortcode && !$hasTodayShortcode) {
            return;
        }

        if ($hasFullShortcode) {
            $text = $this->replaceShortcode(
                $text,
                self::FULL_SHORTCODE,
                $this->renderFullPageMarkup()
            );
        }

        if ($hasTodayShortcode) {
            $text = $this->replaceShortcode(
                $text,
                self::TODAY_SHORTCODE,
                $this->renderTodayMarkup()
            );
        }

        $item->text = $text;
    }

    public function onContentBeforeSave(BeforeSaveEvent $event): void
    {
        if ($event->getContext() !== 'com_content.article') {
            return;
        }

        $data = $event->getData();

        if (!is_array($data)) {
            return;
        }

        $categoryId = (int) ($data['catid'] ?? $event->getItem()->catid ?? 0);
        $configuredCategories = array_column(
            $this->getConfiguredLanguageSlots(),
            'category_id'
        );

        if (!in_array($categoryId, $configuredCategories, true)) {
            return;
        }

        $fieldId = $this->resolveDateFieldId(
            (int) $this->params->get(
                'date_field_id',
                self::DEFAULT_DATE_FIELD_ID
            )
        );
        $fieldName = $this->getFieldName($fieldId);
        $value = $fieldName !== ''
            ? trim((string) ($data['com_fields'][$fieldName] ?? ''))
            : '';
        $calendarDate = DateTimeImmutable::createFromFormat(
            '!d-m-Y',
            $value . '-2000'
        );
        $valid = $calendarDate !== false
            && $calendarDate->format('d-m') === $value;

        if (!$valid) {
            $this->getApplication()->enqueueMessage(
                Text::_('PLG_CONTENT_BSLDAILYCONTENT_DATE_FIELD_INVALID'),
                'error'
            );
            $event->addResult(false);

            return;
        }

        $articleId = (int) ($data['id'] ?? $event->getItem()->id ?? 0);
        $duplicate = $this->findDuplicateDateArticle(
            $value,
            $categoryId,
            $fieldId,
            $articleId
        );

        if ($duplicate === null) {
            return;
        }

        $this->getApplication()->enqueueMessage(
            Text::sprintf(
                'PLG_CONTENT_BSLDAILYCONTENT_DATE_FIELD_DUPLICATE',
                $value,
                (string) $duplicate['title']
            ),
            'error'
        );
        $event->addResult(false);
    }

    private function renderFullPageMarkup(): string
    {
        $day = $this->escape(Text::_('PLG_CONTENT_BSLDAILYCONTENT_DAY'));
        $month = $this->escape(Text::_('PLG_CONTENT_BSLDAILYCONTENT_MONTH'));
        $show = $this->escape(Text::_('PLG_CONTENT_BSLDAILYCONTENT_SHOW'));
        $previous = $this->escape(Text::_('PLG_CONTENT_BSLDAILYCONTENT_PREVIOUS'));
        $next = $this->escape(Text::_('PLG_CONTENT_BSLDAILYCONTENT_NEXT'));
        $loading = $this->escape(Text::_('PLG_CONTENT_BSLDAILYCONTENT_LOADING'));
        $notFound = $this->escape(Text::_('PLG_CONTENT_BSLDAILYCONTENT_NOT_FOUND'));
        $loadError = $this->escape(Text::_('PLG_CONTENT_BSLDAILYCONTENT_LOAD_ERROR'));
        $jcommentsUnavailable = Text::_('PLG_CONTENT_BSLDAILYCONTENT_JCOMMENTS_UNAVAILABLE');

        $months = [];

        for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++) {
            $months[] = Text::_('PLG_CONTENT_BSLDAILYCONTENT_MONTH_' . $monthNumber);
        }

        $encodedMonths = json_encode(
            $months,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        $monthsAttribute = $this->escape($encodedMonths ?: '[]');
        $languageTag = $this->getApplication()->getLanguage()->getTag();
        $languages = LanguageHelper::getLanguages('lang_code');
        $languageSef = isset($languages[$languageTag])
            ? (string) $languages[$languageTag]->sef
            : strtolower(substr($languageTag, 0, 2));
        $endpoint = $this->escape(
            Uri::root(true)
                . '/index.php?option=com_ajax&plugin=bsldailycontent'
                . '&group=content&format=json'
                . '&lang=' . rawurlencode($languageSef)
                . '&language=' . rawurlencode($languageTag)
        );
        $jcommentsEndpoint = $this->escape(
            Uri::root(true)
                . '/component/jcomments?tmpl=component'
                . '&lang=' . rawurlencode($languageSef)
        );
        $jcommentsCore = $this->escape(
            Uri::root(true) . '/media/com_jcomments/js/jcomments-v4.0.js'
        );
        $jcommentsAjax = $this->escape(
            Uri::root(true) . '/components/com_jcomments/libraries/joomlatune/ajax.js?v=4'
        );
        $jcommentsStylesheet = $this->escape(
            Uri::root(true) . '/components/com_jcomments/tpl/default/style.css'
        );
        $smiliesBase = $this->escape(
            Uri::root(true) . '/media/com_jcomments/images/smilies/'
        );

        $commentsStrings = [
            'unavailable' => $jcommentsUnavailable,
            'formatPrompt' => Text::_('PLG_CONTENT_BSLDAILYCONTENT_FORMAT_PROMPT'),
            'imagePrompt' => Text::_('PLG_CONTENT_BSLDAILYCONTENT_IMAGE_PROMPT'),
            'urlPrompt' => Text::_('PLG_CONTENT_BSLDAILYCONTENT_URL_PROMPT'),
            'hiddenPrompt' => Text::_('PLG_CONTENT_BSLDAILYCONTENT_HIDDEN_PROMPT'),
            'quotePrompt' => Text::_('PLG_CONTENT_BSLDAILYCONTENT_QUOTE_PROMPT'),
            'listPrompt' => Text::_('PLG_CONTENT_BSLDAILYCONTENT_LIST_PROMPT'),
            'bold' => Text::_('PLG_CONTENT_BSLDAILYCONTENT_BOLD'),
            'italic' => Text::_('PLG_CONTENT_BSLDAILYCONTENT_ITALIC'),
            'underline' => Text::_('PLG_CONTENT_BSLDAILYCONTENT_UNDERLINE'),
            'strike' => Text::_('PLG_CONTENT_BSLDAILYCONTENT_STRIKE'),
            'image' => Text::_('PLG_CONTENT_BSLDAILYCONTENT_IMAGE'),
            'link' => Text::_('PLG_CONTENT_BSLDAILYCONTENT_LINK'),
            'hidden' => Text::_('PLG_CONTENT_BSLDAILYCONTENT_HIDDEN'),
            'quote' => Text::_('PLG_CONTENT_BSLDAILYCONTENT_QUOTE'),
            'list' => Text::_('PLG_CONTENT_BSLDAILYCONTENT_LIST'),
        ];
        $encodedCommentsStrings = json_encode(
            $commentsStrings,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        $commentsStringsAttribute = $this->escape($encodedCommentsStrings ?: '{}');

        $assets = '';

        if (!$this->scriptIncluded) {
            $scriptUri = Uri::root(true)
                . '/media/plg_content_bsldailycontent/js/daily-content.js?v='
                . rawurlencode(self::VERSION);
            $escapedScriptUri = htmlspecialchars($scriptUri, ENT_QUOTES, 'UTF-8');
            $assets = "\n<script src=\"{$escapedScriptUri}\" defer></script>";
            $this->scriptIncluded = true;
        }

        return <<<HTML
<div id="daily-navigation"
     class="bsl-daily-content"
     data-bsl-daily-content
     data-endpoint="{$endpoint}"
     data-months="{$monthsAttribute}"
     data-loading-message="{$loading}"
     data-not-found-message="{$notFound}"
     data-load-error-message="{$loadError}"
     data-jcomments-endpoint="{$jcommentsEndpoint}"
     data-jcomments-core="{$jcommentsCore}"
     data-jcomments-ajax="{$jcommentsAjax}"
     data-jcomments-stylesheet="{$jcommentsStylesheet}"
     data-smilies-base="{$smiliesBase}"
     data-comments-strings="{$commentsStringsAttribute}">
    <div class="row mb-4">
        <div class="col-auto">
            <select id="day-select" class="form-select" data-bsl-day aria-label="{$day}"></select>
        </div>
        <div class="col-auto">
            <select id="month-select" class="form-select" data-bsl-month aria-label="{$month}"></select>
        </div>
        <div class="col-auto">
            <button id="show-btn" type="button" class="btn btn-primary" data-bsl-show>{$show}</button>
        </div>
    </div>

    <div id="daily-content" data-bsl-content>
        <p>{$loading}</p>
    </div>

    <div id="daily-comments" data-bsl-comments hidden>
        <div id="jc">
            <div id="comments">
                <div id="comments-list"></div>
            </div>
            <div id="comments-form-container"></div>
        </div>
    </div>

    <div class="mt-3 d-flex justify-content-between">
        <button id="prev-btn" type="button" class="btn btn-outline-secondary" data-bsl-previous>&larr; {$previous}</button>
        <button id="next-btn" type="button" class="btn btn-outline-secondary" data-bsl-next>{$next} &rarr;</button>
    </div>
</div>{$assets}
HTML;
    }

    private function renderTodayMarkup(): string
    {
        $languageTag = $this->getCurrentLanguageTag();
        $languageConfiguration = $this->getConfigurationForLanguage($languageTag);
        $categoryId = $languageConfiguration['category_id'] ?? 0;
        $fieldId = $this->resolveDateFieldId(
            (int) $this->params->get(
                'date_field_id',
                self::DEFAULT_DATE_FIELD_ID
            )
        );
        $timezone = new DateTimeZone(
            (string) $this->getApplication()->get('offset', 'UTC')
        );
        $today = (new DateTimeImmutable('now', $timezone))->format('d-m');
        $article = $categoryId > 0 && $fieldId > 0
            ? $this->findArticleByDate($today, $languageTag, $categoryId, $fieldId)
            : null;

        if ($article === null) {
            $notFound = $this->escape(
                Text::_('PLG_CONTENT_BSLDAILYCONTENT_TODAY_NOT_FOUND')
            );

            return <<<HTML
<section class="bsl-daily-content-today">
    <p class="bsl-daily-content-today__not-found">{$notFound}</p>
</section>
HTML;
        }

        $title = $this->escape((string) $article['title']);
        $introtext = (string) $article['introtext'];
        $readMore = $this->escape(
            Text::_('PLG_CONTENT_BSLDAILYCONTENT_READ_MORE')
        );
        $pageItemId = $languageConfiguration['page_item_id'] ?? 0;
        $readMoreMarkup = '';

        if ($pageItemId > 0) {
            $url = Route::_(
                'index.php?Itemid=' . $pageItemId . '&date=' . rawurlencode($today),
                false
            );
            $escapedUrl = $this->escape($url);
            $readMoreMarkup = <<<HTML

    <p class="bsl-daily-content-today__read-more">
        <a href="{$escapedUrl}">{$readMore}</a>
    </p>
HTML;
        }

        return <<<HTML
<section class="bsl-daily-content-today">
    <h3 class="bsl-daily-content-today__title">{$title}</h3>
    <div class="bsl-daily-content-today__introtext">{$introtext}</div>{$readMoreMarkup}
</section>
HTML;
    }

    private function replaceShortcode(
        string $text,
        string $shortcode,
        string $markup
    ): string
    {
        // Remove the paragraph automatically added by visual editors around a standalone shortcode.
        $pattern = '~<p>\s*' . preg_quote($shortcode, '~') . '\s*</p>~i';
        $replaced = preg_replace($pattern, $markup, $text);

        return str_replace($shortcode, $markup, $replaced ?? $text);
    }

    private function getCurrentLanguageTag(): string
    {
        return $this->getApplication()->getLanguage()->getTag();
    }

    private function getConfiguredLanguageSlots(): array
    {
        $slots = [
            [
                'language' => trim((string) $this->params->get(
                    'primary_language',
                    ''
                )),
                'category_id' => (int) $this->params->get(
                    'primary_category_id',
                    0
                ),
                'page_item_id' => (int) $this->params->get(
                    'primary_full_page_item_id',
                    0
                ),
            ],
            [
                'language' => trim((string) $this->params->get(
                    'secondary_language',
                    ''
                )),
                'category_id' => (int) $this->params->get(
                    'secondary_category_id',
                    0
                ),
                'page_item_id' => (int) $this->params->get(
                    'secondary_full_page_item_id',
                    0
                ),
            ],
        ];

        return array_values(array_filter(
            $slots,
            static fn (array $slot): bool => $slot['language'] !== ''
                && $slot['category_id'] > 0
        ));
    }

    private function getConfigurationForLanguage(string $languageTag): ?array
    {
        foreach ($this->getConfiguredLanguageSlots() as $slot) {
            if ($slot['language'] === $languageTag) {
                return $slot;
            }
        }

        return null;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function resolveDateFieldId(int $legacyFieldId): int
    {
        $database = $this->getDatabase();
        $query = $database->getQuery(true)
            ->select($database->quoteName('id'))
            ->from($database->quoteName('#__fields'))
            ->where(
                $database->quoteName('name')
                . ' = ' . $database->quote(self::DATE_FIELD_NAME)
            )
            ->where(
                $database->quoteName('context')
                . ' = ' . $database->quote('com_content.article')
            )
            ->where($database->quoteName('type') . ' = ' . $database->quote('text'))
            ->order($database->quoteName('id') . ' ASC');
        $fieldId = (int) $database->setQuery($query, 0, 1)->loadResult();

        return $fieldId > 0 ? $fieldId : $legacyFieldId;
    }

    private function getFieldName(int $fieldId): string
    {
        if ($fieldId < 1) {
            return '';
        }

        $database = $this->getDatabase();
        $query = $database->getQuery(true)
            ->select($database->quoteName('name'))
            ->from($database->quoteName('#__fields'))
            ->where($database->quoteName('id') . ' = ' . $fieldId)
            ->where(
                $database->quoteName('context')
                . ' = ' . $database->quote('com_content.article')
            );

        return (string) $database->setQuery($query, 0, 1)->loadResult();
    }

    private function findDuplicateDateArticle(
        string $date,
        int $categoryId,
        int $fieldId,
        int $articleId
    ): ?array {
        if ($fieldId < 1) {
            return null;
        }

        $database = $this->getDatabase();
        $query = $database->getQuery(true)
            ->select([
                $database->quoteName('content.id'),
                $database->quoteName('content.title'),
            ])
            ->from($database->quoteName('#__content', 'content'))
            ->innerJoin(
                $database->quoteName('#__fields_values', 'field_values')
                . ' ON ' . $database->quoteName('field_values.item_id')
                . ' = ' . $database->quoteName('content.id')
            )
            ->where($database->quoteName('content.catid') . ' = ' . $categoryId)
            ->where($database->quoteName('content.state') . ' <> -2')
            ->where($database->quoteName('field_values.field_id') . ' = ' . $fieldId)
            ->where(
                $database->quoteName('field_values.value')
                . ' = ' . $database->quote($date)
            )
            ->order($database->quoteName('content.id') . ' ASC');

        if ($articleId > 0) {
            $query->where($database->quoteName('content.id') . ' <> ' . $articleId);
        }

        $duplicate = $database->setQuery($query, 0, 1)->loadAssoc();

        return is_array($duplicate) ? $duplicate : null;
    }

    private function findArticleByDate(
        string $requestedDate,
        string $languageTag,
        int $categoryId,
        int $fieldId
    ): ?array
    {
        $database = $this->getDatabase();
        $viewLevels = array_map(
            'intval',
            $this->getApplication()->getIdentity()->getAuthorisedViewLevels()
        );

        if ($viewLevels === []) {
            return null;
        }

        $accessList = implode(',', $viewLevels);
        $now = $database->quote(
            (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->format('Y-m-d H:i:s')
        );
        $query = $database->getQuery(true)
            ->select([
                $database->quoteName('content.id'),
                $database->quoteName('content.title'),
                $database->quoteName('content.introtext'),
                $database->quoteName('content.fulltext'),
                $database->quoteName('content.catid'),
            ])
            ->from($database->quoteName('#__content', 'content'))
            ->innerJoin(
                $database->quoteName('#__categories', 'category')
                . ' ON ' . $database->quoteName('category.id')
                . ' = ' . $database->quoteName('content.catid')
            )
            ->innerJoin(
                $database->quoteName('#__fields_values', 'field_values')
                . ' ON ' . $database->quoteName('field_values.item_id')
                . ' = ' . $database->quoteName('content.id')
            )
            ->where(
                $database->quoteName('content.catid')
                . ' = ' . $categoryId
            )
            ->where(
                $database->quoteName('field_values.field_id')
                . ' = ' . $fieldId
            )
            ->where(
                $database->quoteName('field_values.value')
                . ' = ' . $database->quote($requestedDate)
            )
            ->where($database->quoteName('content.state') . ' = 1')
            ->where($database->quoteName('category.published') . ' = 1')
            ->where(
                $database->quoteName('content.access')
                . ' IN (' . $accessList . ')'
            )
            ->where(
                $database->quoteName('category.access')
                . ' IN (' . $accessList . ')'
            )
            ->where(
                '(' . $database->quoteName('content.publish_up')
                . ' IS NULL OR ' . $database->quoteName('content.publish_up')
                . ' <= ' . $now . ')'
            )
            ->where(
                '(' . $database->quoteName('content.publish_down')
                . ' IS NULL OR ' . $database->quoteName('content.publish_down')
                . ' >= ' . $now . ')'
            )
            ->where(
                $database->quoteName('content.language')
                . ' = ' . $database->quote($languageTag)
            )
            ->where(
                $database->quoteName('category.language')
                . ' = ' . $database->quote($languageTag)
            )
            ->order($database->quoteName('content.id') . ' ASC');

        $database->setQuery($query, 0, 1);
        $article = $database->loadAssoc();

        return is_array($article) ? $article : null;
    }
}
