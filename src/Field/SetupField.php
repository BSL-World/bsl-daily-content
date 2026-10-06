<?php

/**
 * @package     BSL Daily Content
 * @copyright   Copyright (C) 2026 Vasilyev Alexander. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace BSLWorld\Plugin\Content\Bsldailycontent\Field;

defined('_JEXEC') or die;

use BSLWorld\Plugin\Content\Bsldailycontent\Service\DateFieldManager;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

final class SetupField extends FormField
{
    protected $type = 'Setup';

    protected function getInput(): string
    {
        $database = Factory::getContainer()->get(DatabaseInterface::class);
        $fieldId = (int) $this->form->getValue('date_field_id', 'params', 0);
        $primaryLanguage = trim((string) $this->form->getValue(
            'primary_language',
            'params',
            ''
        ));
        $primaryCategoryId = (int) $this->form->getValue(
            'primary_category_id',
            'params',
            0
        );
        $secondaryLanguage = trim((string) $this->form->getValue(
            'secondary_language',
            'params',
            ''
        ));
        $secondaryCategoryId = (int) $this->form->getValue(
            'secondary_category_id',
            'params',
            0
        );
        $selectedCategories = array_values(array_unique(array_filter([
            $primaryCategoryId,
            $secondaryCategoryId,
        ])));
        $ready = false;
        $languagesReady = false;
        $configurationReady = $primaryLanguage !== ''
            && $primaryCategoryId > 0
            && (($secondaryLanguage === '') === ($secondaryCategoryId < 1))
            && ($secondaryLanguage === '' || $secondaryLanguage !== $primaryLanguage)
            && ($secondaryCategoryId < 1 || $secondaryCategoryId !== $primaryCategoryId);

        if ($configurationReady) {
            $query = $database->getQuery(true)
                ->select([
                    $database->quoteName('id'),
                    $database->quoteName('language'),
                ])
                ->from($database->quoteName('#__categories'))
                ->whereIn($database->quoteName('id'), $selectedCategories);
            $categories = $database->setQuery($query)->loadAssocList('id');
            $languagesReady = (
                ($categories[$primaryCategoryId]['language'] ?? null)
                === $primaryLanguage
            )
                && (
                    $secondaryCategoryId < 1
                    || ($categories[$secondaryCategoryId]['language'] ?? null)
                        === $secondaryLanguage
                );
        }

        $query = $database->getQuery(true)
            ->select([
                $database->quoteName('field.id', 'id'),
                $database->quoteName('field.name', 'name'),
                $database->quoteName('field.context', 'context'),
                $database->quoteName('field.type', 'type'),
                $database->quoteName('field.required', 'required'),
                $database->quoteName('field.state', 'state'),
                $database->quoteName('field.group_id', 'group_id'),
                $database->quoteName('field_group.id', 'field_group_id'),
                $database->quoteName('field_group.state', 'field_group_state'),
                $database->quoteName('field_group.language', 'field_group_language'),
            ])
            ->from($database->quoteName('#__fields', 'field'))
            ->leftJoin(
                $database->quoteName('#__fields_groups', 'field_group')
                . ' ON ' . $database->quoteName('field_group.id')
                . ' = ' . $database->quoteName('field.group_id')
            );

        if ($fieldId > 0) {
            $query->where($database->quoteName('field.id') . ' = ' . $fieldId);
        } else {
            $query->where(
                $database->quoteName('field.name')
                . ' = ' . $database->quote(DateFieldManager::FIELD_NAME)
            );
        }

        $field = $database->setQuery($query, 0, 1)->loadAssoc();
        $fieldId = is_array($field) ? (int) $field['id'] : 0;
        $metadataReady = is_array($field)
            && $field['name'] === DateFieldManager::FIELD_NAME
            && $field['context'] === 'com_content.article'
            && $field['type'] === 'text'
            && (int) $field['required'] === 1
            && (int) $field['state'] === 1
            && (int) $field['group_id'] > 0
            && (int) $field['field_group_id'] === (int) $field['group_id']
            && (int) $field['field_group_state'] === 1
            && $field['field_group_language'] === '*';

        if (
            $fieldId > 0
            && $selectedCategories !== []
            && $metadataReady
            && $languagesReady
        ) {
            $query = $database->getQuery(true)
                ->select($database->quoteName('category_id'))
                ->from($database->quoteName('#__fields_categories'))
                ->where($database->quoteName('field_id') . ' = ' . $fieldId)
                ->order($database->quoteName('category_id') . ' ASC');
            $assignedCategories = array_map(
                'intval',
                $database->setQuery($query)->loadColumn()
            );
            sort($selectedCategories);
            $ready = $assignedCategories === $selectedCategories;
        }

        $statusClass = $ready ? 'alert-success' : 'alert-warning';
        $statusText = $ready
            ? Text::_('PLG_CONTENT_BSLDAILYCONTENT_SETUP_READY')
            : Text::_('PLG_CONTENT_BSLDAILYCONTENT_SETUP_REQUIRED');
        return '<div class="alert ' . $statusClass . '">'
            . '<strong>' . htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') . '</strong>'
            . '<div class="mt-2">'
            . htmlspecialchars(Text::_('PLG_CONTENT_BSLDAILYCONTENT_SETUP_EXPLANATION'), ENT_QUOTES, 'UTF-8')
            . '</div></div>';
    }
}
