<?php

/**
 * @package     BSL Daily Content
 * @copyright   Copyright (C) 2026 Vasilyev Alexander. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace BSLWorld\Plugin\Content\Bsldailycontent\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

final class DateFieldManager
{
    public const FIELD_NAME = 'bsl-daily-date';

    private const GROUP_TITLE = 'Daily Content';

    private const FIELD_TITLE = 'Daily content date';

    private const LEGACY_GROUP_TITLES = [
        'AA-Daily',
    ];

    private const LEGACY_GROUP_DESCRIPTIONS = [
        'Custom fields for AA Daily articles',
    ];

    private const LEGACY_FIELD_TITLES = [
        'AA-Date',
        'Daily item date',
    ];

    private const LEGACY_FIELD_NAMES = [
        'aa-date',
    ];

    public function __construct(
        private readonly AdministratorApplication $application,
        private readonly DatabaseInterface $database
    ) {
    }

    public function configure(
        string $primaryLanguage,
        int $primaryCategoryId,
        string $secondaryLanguage,
        int $secondaryCategoryId,
        int $legacyFieldId
    ): int {
        if (!$this->application->getIdentity()->authorise('core.manage', 'com_plugins')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $primaryLanguage = trim($primaryLanguage);
        $secondaryLanguage = trim($secondaryLanguage);

        if ($primaryLanguage === '') {
            throw new \RuntimeException(
                Text::_('PLG_CONTENT_BSLDAILYCONTENT_SETUP_PRIMARY_LANGUAGE_REQUIRED')
            );
        }

        if ($primaryCategoryId < 1) {
            throw new \RuntimeException(
                Text::_('PLG_CONTENT_BSLDAILYCONTENT_SETUP_PRIMARY_CATEGORY_REQUIRED')
            );
        }

        if (($secondaryLanguage === '') !== ($secondaryCategoryId < 1)) {
            throw new \RuntimeException(
                Text::_('PLG_CONTENT_BSLDAILYCONTENT_SETUP_SECONDARY_INCOMPLETE')
            );
        }

        if ($secondaryLanguage !== '' && $secondaryLanguage === $primaryLanguage) {
            throw new \RuntimeException(
                Text::_('PLG_CONTENT_BSLDAILYCONTENT_SETUP_LANGUAGES_DIFFERENT')
            );
        }

        if ($secondaryCategoryId > 0 && $secondaryCategoryId === $primaryCategoryId) {
            throw new \RuntimeException(
                Text::_('PLG_CONTENT_BSLDAILYCONTENT_SETUP_CATEGORIES_DIFFERENT')
            );
        }

        $categoryIds = array_values(array_filter([
            $primaryCategoryId,
            $secondaryCategoryId,
        ]));
        sort($categoryIds);
        $this->validateCategories(
            $primaryLanguage,
            $primaryCategoryId,
            $secondaryLanguage,
            $secondaryCategoryId
        );

        $namedField = $this->loadFieldByName(self::FIELD_NAME);
        $legacyField = $legacyFieldId > 0
            ? $this->loadFieldById($legacyFieldId)
            : null;

        if ($legacyField === null && $namedField === null) {
            foreach (self::LEGACY_FIELD_NAMES as $legacyFieldName) {
                $legacyField = $this->loadFieldByName($legacyFieldName);

                if ($legacyField !== null) {
                    break;
                }
            }
        }

        if ($legacyField !== null && !$this->isCompatible($legacyField)) {
            throw new \RuntimeException(
                Text::_('PLG_CONTENT_BSLDAILYCONTENT_SETUP_FIELD_CONFLICT')
            );
        }

        if ($namedField !== null && !$this->isCompatible($namedField)) {
            throw new \RuntimeException(
                Text::_('PLG_CONTENT_BSLDAILYCONTENT_SETUP_FIELD_CONFLICT')
            );
        }

        if (
            $legacyField !== null
            && $namedField !== null
            && (int) $legacyField['id'] !== (int) $namedField['id']
        ) {
            throw new \RuntimeException(
                Text::_('PLG_CONTENT_BSLDAILYCONTENT_SETUP_FIELD_CONFLICT')
            );
        }

        $field = $legacyField ?? $namedField;

        if ($field === null) {
            $groupId = $this->createGroup();
            $fieldId = $this->createField($categoryIds, $groupId);
            $this->application->enqueueMessage(
                Text::_('PLG_CONTENT_BSLDAILYCONTENT_SETUP_CREATED'),
                'success'
            );

            return $fieldId;
        }

        $fieldId = (int) $field['id'];
        $group = $this->prepareGroup((int) $field['group_id']);
        $groupId = $group['id'];
        $previousCategories = $this->loadAssignedCategories($fieldId);
        $categoriesChanged = $previousCategories !== $categoryIds;
        $metadataChanged = $field['name'] !== self::FIELD_NAME
            || (int) $field['required'] !== 1
            || (int) $field['state'] !== 1
            || (int) $field['group_id'] !== $groupId
            || $group['changed']
            || $this->usesLegacyFieldTitle($field);
        $this->updateField($fieldId, $groupId, $categoryIds);

        if ($categoriesChanged && $previousCategories !== []) {
            $this->application->enqueueMessage(
                Text::_('PLG_CONTENT_BSLDAILYCONTENT_SETUP_CATEGORY_CHANGED'),
                'warning'
            );
        } elseif ($categoriesChanged || $metadataChanged) {
            $this->application->enqueueMessage(
                Text::_('PLG_CONTENT_BSLDAILYCONTENT_SETUP_PREPARED'),
                'success'
            );
        }

        return $fieldId;
    }

    private function validateCategories(
        string $primaryLanguage,
        int $primaryCategoryId,
        string $secondaryLanguage,
        int $secondaryCategoryId
    ): void {
        $categoryIds = array_values(array_filter([
            $primaryCategoryId,
            $secondaryCategoryId,
        ]));
        $query = $this->database->getQuery(true)
            ->select([
                $this->database->quoteName('id'),
                $this->database->quoteName('language'),
            ])
            ->from($this->database->quoteName('#__categories'))
            ->whereIn($this->database->quoteName('id'), $categoryIds)
            ->where(
                $this->database->quoteName('extension')
                . ' = ' . $this->database->quote('com_content')
            );
        $categories = $this->database->setQuery($query)->loadAssocList('id');
        $foundIds = array_map('intval', array_keys($categories));

        sort($foundIds);
        sort($categoryIds);

        if ($foundIds !== $categoryIds) {
            throw new \RuntimeException(
                Text::_('PLG_CONTENT_BSLDAILYCONTENT_SETUP_CATEGORY_INVALID')
            );
        }

        if (
            ($categories[$primaryCategoryId]['language'] ?? null)
            !== $primaryLanguage
        ) {
            throw new \RuntimeException(
                Text::sprintf(
                    'PLG_CONTENT_BSLDAILYCONTENT_SETUP_CATEGORY_LANGUAGE_INVALID',
                    $primaryLanguage
                )
            );
        }

        if (
            $secondaryCategoryId > 0
            && ($categories[$secondaryCategoryId]['language'] ?? null)
                !== $secondaryLanguage
        ) {
            throw new \RuntimeException(
                Text::sprintf(
                    'PLG_CONTENT_BSLDAILYCONTENT_SETUP_CATEGORY_LANGUAGE_INVALID',
                    $secondaryLanguage
                )
            );
        }
    }

    private function loadFieldById(int $fieldId): ?array
    {
        $query = $this->fieldQuery()
            ->where($this->database->quoteName('id') . ' = ' . $fieldId);
        $field = $this->database->setQuery($query)->loadAssoc();

        return is_array($field) ? $field : null;
    }

    private function loadFieldByName(string $name): ?array
    {
        $query = $this->fieldQuery()
            ->where(
                $this->database->quoteName('name')
                . ' = ' . $this->database->quote($name)
            )
            ->order($this->database->quoteName('id') . ' ASC');
        $field = $this->database->setQuery($query, 0, 1)->loadAssoc();

        return is_array($field) ? $field : null;
    }

    private function fieldQuery()
    {
        return $this->database->getQuery(true)
            ->select([
                $this->database->quoteName('id'),
                $this->database->quoteName('name'),
                $this->database->quoteName('type'),
                $this->database->quoteName('context'),
                $this->database->quoteName('required'),
                $this->database->quoteName('state'),
                $this->database->quoteName('group_id'),
                $this->database->quoteName('title'),
                $this->database->quoteName('label'),
            ])
            ->from($this->database->quoteName('#__fields'));
    }

    private function loadAssignedCategories(int $fieldId): array
    {
        $query = $this->database->getQuery(true)
            ->select($this->database->quoteName('category_id'))
            ->from($this->database->quoteName('#__fields_categories'))
            ->where($this->database->quoteName('field_id') . ' = ' . $fieldId)
            ->order($this->database->quoteName('category_id') . ' ASC');
        $categoryIds = array_map(
            'intval',
            $this->database->setQuery($query)->loadColumn()
        );
        sort($categoryIds);

        return $categoryIds;
    }

    private function isCompatible(array $field): bool
    {
        return $field['context'] === 'com_content.article'
            && $field['type'] === 'text';
    }

    private function prepareGroup(int $groupId): array
    {
        if ($groupId < 1) {
            return [
                'id' => $this->createGroup(),
                'changed' => true,
            ];
        }

        $query = $this->database->getQuery(true)
            ->select([
                $this->database->quoteName('id'),
                $this->database->quoteName('title'),
                $this->database->quoteName('description'),
                $this->database->quoteName('state'),
                $this->database->quoteName('language'),
            ])
            ->from($this->database->quoteName('#__fields_groups'))
            ->where($this->database->quoteName('id') . ' = ' . $groupId)
            ->where(
                $this->database->quoteName('context')
                . ' = ' . $this->database->quote('com_content.article')
            );
        $existingGroup = $this->database->setQuery($query, 0, 1)->loadAssoc();

        if (!is_array($existingGroup)) {
            return [
                'id' => $this->createGroup(),
                'changed' => true,
            ];
        }

        $group = (object) [
            'id' => (int) $existingGroup['id'],
            'state' => 1,
            'language' => '*',
        ];

        if (in_array($existingGroup['title'], self::LEGACY_GROUP_TITLES, true)) {
            $group->title = self::GROUP_TITLE;
        }

        if (
            in_array(
                $existingGroup['description'],
                self::LEGACY_GROUP_DESCRIPTIONS,
                true
            )
        ) {
            $group->description = '';
        }

        $this->database->updateObject('#__fields_groups', $group, 'id');

        return [
            'id' => (int) $existingGroup['id'],
            'changed' => (int) $existingGroup['state'] !== 1
                || $existingGroup['language'] !== '*'
                || isset($group->title)
                || isset($group->description),
        ];
    }

    private function createGroup(): int
    {
        $component = $this->application->bootComponent('com_fields');
        $model = $component->getMVCFactory()->createModel(
            'Group',
            'Administrator',
            ['ignore_request' => true]
        );
        $data = [
            'id' => 0,
            'context' => 'com_content.article',
            'title' => self::GROUP_TITLE,
            'state' => 1,
            'language' => '*',
            'description' => '',
            'access' => 1,
            'created_by' => (int) $this->application->getIdentity()->id,
            'params' => [
                'display_readonly' => 1,
            ],
        ];

        if (!$model->save($data)) {
            throw new \RuntimeException(
                $model->getError()
                    ?: Text::_('PLG_CONTENT_BSLDAILYCONTENT_SETUP_GROUP_CREATE_FAILED')
            );
        }

        $groupId = (int) $model->getState('group.id');

        if ($groupId < 1) {
            throw new \RuntimeException(
                Text::_('PLG_CONTENT_BSLDAILYCONTENT_SETUP_GROUP_CREATE_FAILED')
            );
        }

        return $groupId;
    }

    private function createField(array $categoryIds, int $groupId): int
    {
        $component = $this->application->bootComponent('com_fields');
        $model = $component->getMVCFactory()->createModel(
            'Field',
            'Administrator',
            ['ignore_request' => true]
        );
        $data = [
            'id' => 0,
            'context' => 'com_content.article',
            'group_id' => $groupId,
            'assigned_cat_ids' => $categoryIds,
            'title' => self::FIELD_TITLE,
            'label' => self::FIELD_TITLE,
            'name' => self::FIELD_NAME,
            'type' => 'text',
            'required' => 1,
            'only_use_in_subform' => 0,
            'default_value' => '',
            'state' => 1,
            'language' => '*',
            'description' => 'PLG_CONTENT_BSLDAILYCONTENT_DATE_FIELD_HELP',
            'access' => 1,
            'created_user_id' => (int) $this->application->getIdentity()->id,
            'params' => [
                'hint' => '25-12',
                'show_on' => '',
                'showlabel' => 1,
                'display' => 0,
            ],
            'fieldparams' => [
                'filter' => 'raw',
                'maxlength' => 5,
            ],
        ];

        if (!$model->save($data)) {
            throw new \RuntimeException(
                $model->getError()
                    ?: Text::_('PLG_CONTENT_BSLDAILYCONTENT_SETUP_CREATE_FAILED')
            );
        }

        $fieldId = (int) $model->getState('field.id');

        if ($fieldId < 1) {
            throw new \RuntimeException(
                Text::_('PLG_CONTENT_BSLDAILYCONTENT_SETUP_CREATE_FAILED')
            );
        }

        return $fieldId;
    }

    private function updateField(
        int $fieldId,
        int $groupId,
        array $categoryIds
    ): void
    {
        $this->database->transactionStart();

        try {
            $field = (object) [
                'id' => $fieldId,
                'name' => self::FIELD_NAME,
                'required' => 1,
                'state' => 1,
                'group_id' => $groupId,
                'description' => 'PLG_CONTENT_BSLDAILYCONTENT_DATE_FIELD_HELP',
            ];

            $storedField = $this->loadFieldById($fieldId);

            if (is_array($storedField)) {
                if (in_array($storedField['title'], self::LEGACY_FIELD_TITLES, true)) {
                    $field->title = self::FIELD_TITLE;
                }

                if (in_array($storedField['label'], self::LEGACY_FIELD_TITLES, true)) {
                    $field->label = self::FIELD_TITLE;
                }
            }

            $this->database->updateObject('#__fields', $field, 'id');

            $query = $this->database->getQuery(true)
                ->delete($this->database->quoteName('#__fields_categories'))
                ->where($this->database->quoteName('field_id') . ' = ' . $fieldId);
            $this->database->setQuery($query)->execute();

            foreach ($categoryIds as $categoryId) {
                $mapping = (object) [
                    'field_id' => $fieldId,
                    'category_id' => $categoryId,
                ];
                $this->database->insertObject('#__fields_categories', $mapping);
            }

            $this->database->transactionCommit();
        } catch (\Throwable $error) {
            $this->database->transactionRollback();

            throw $error;
        }
    }

    private function usesLegacyFieldTitle(array $field): bool
    {
        return in_array($field['title'], self::LEGACY_FIELD_TITLES, true)
            || in_array($field['label'], self::LEGACY_FIELD_TITLES, true);
    }
}
