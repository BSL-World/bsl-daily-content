<?php

/**
 * @package     BSL Daily Content
 * @copyright   Copyright (C) 2026 Vasilyev Alexander. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

return new class implements InstallerScriptInterface {
    public function install(InstallerAdapter $adapter): bool
    {
        $database = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $database->getQuery(true)
            ->update($database->quoteName('#__extensions'))
            ->set($database->quoteName('enabled') . ' = 1')
            ->where($database->quoteName('type') . ' = ' . $database->quote('plugin'))
            ->where($database->quoteName('folder') . ' = ' . $database->quote('content'))
            ->where(
                $database->quoteName('element')
                . ' = ' . $database->quote('bsldailycontent')
            );
        $database->setQuery($query)->execute();

        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        if (!in_array($type, ['install', 'discover_install', 'update'], true)) {
            return true;
        }

        $database = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $database->getQuery(true)
            ->select($database->quoteName('extension_id'))
            ->from($database->quoteName('#__extensions'))
            ->where($database->quoteName('type') . ' = ' . $database->quote('plugin'))
            ->where($database->quoteName('folder') . ' = ' . $database->quote('content'))
            ->where(
                $database->quoteName('element')
                . ' = ' . $database->quote('bsldailycontent')
            );
        $extensionId = (int) $database->setQuery($query)->loadResult();

        if ($extensionId > 0) {
            $migrationSource = $this->migrateLanguageSettings(
                $database,
                $extensionId
            );

            if ($migrationSource === 'daily_reflections') {
                Factory::getApplication()->enqueueMessage(
                    Text::_('PLG_CONTENT_BSLDAILYCONTENT_INSTALL_MIGRATION_MESSAGE'),
                    'notice'
                );
            } elseif ($migrationSource === 'daily_content') {
                Factory::getApplication()->enqueueMessage(
                    Text::_('PLG_CONTENT_BSLDAILYCONTENT_INSTALL_LANGUAGE_MIGRATION_MESSAGE'),
                    'notice'
                );
            }

            $url = 'index.php?option=com_plugins&task=plugin.edit&extension_id=' . $extensionId;
            Factory::getApplication()->enqueueMessage(
                Text::sprintf(
                    'PLG_CONTENT_BSLDAILYCONTENT_INSTALL_SETUP_MESSAGE',
                    $url
                ),
                'notice'
            );
        }

        return true;
    }

    private function migrateLanguageSettings(
        DatabaseInterface $database,
        int $extensionId
    ): string {
        $query = $database->getQuery(true)
            ->select($database->quoteName('params'))
            ->from($database->quoteName('#__extensions'))
            ->where($database->quoteName('extension_id') . ' = ' . $extensionId);
        $currentParamsJson = (string) $database->setQuery($query, 0, 1)->loadResult();
        $currentParams = new Registry($currentParamsJson);

        if (
            trim((string) $currentParams->get('primary_language', '')) !== ''
            || (int) $currentParams->get('primary_category_id', 0) > 0
        ) {
            return '';
        }

        $sourceParams = $currentParams;
        $source = 'daily_content';
        $ruCategoryId = (int) $sourceParams->get('ru_category_id', 0);
        $enCategoryId = (int) $sourceParams->get('en_category_id', 0);
        $ruPageItemId = (int) $sourceParams->get('ru_full_page_item_id', 0);
        $enPageItemId = (int) $sourceParams->get('en_full_page_item_id', 0);

        if ($ruCategoryId < 1 && $enCategoryId < 1) {
            $query = $database->getQuery(true)
                ->select($database->quoteName('params'))
                ->from($database->quoteName('#__extensions'))
                ->where($database->quoteName('type') . ' = ' . $database->quote('plugin'))
                ->where($database->quoteName('folder') . ' = ' . $database->quote('content'))
                ->where(
                    $database->quoteName('element')
                    . ' = ' . $database->quote('bsldailyreflections')
                );
            $legacyParamsJson = $database->setQuery($query, 0, 1)->loadResult();

            if (!is_string($legacyParamsJson) || $legacyParamsJson === '') {
                return '';
            }

            $sourceParams = new Registry($legacyParamsJson);
            $source = 'daily_reflections';
            $ruCategoryId = (int) $sourceParams->get('ru_category_id', 0);
            $enCategoryId = (int) $sourceParams->get('en_category_id', 0);
            $ruPageItemId = (int) $sourceParams->get('ru_page_item_id', 0);
            $enPageItemId = (int) $sourceParams->get('en_page_item_id', 0);

            if ($ruCategoryId < 1 && $enCategoryId < 1) {
                return '';
            }
        }

        if ($ruCategoryId > 0) {
            $currentParams->set('primary_language', 'ru-RU');
            $currentParams->set('primary_category_id', $ruCategoryId);
            $currentParams->set('primary_full_page_item_id', $ruPageItemId);

            if ($enCategoryId > 0) {
                $currentParams->set('secondary_language', 'en-GB');
                $currentParams->set('secondary_category_id', $enCategoryId);
                $currentParams->set('secondary_full_page_item_id', $enPageItemId);
            }
        } else {
            $currentParams->set('primary_language', 'en-GB');
            $currentParams->set('primary_category_id', $enCategoryId);
            $currentParams->set('primary_full_page_item_id', $enPageItemId);
        }

        $legacyFieldId = (int) $sourceParams->get('date_field_id', 0);

        if (
            $legacyFieldId > 0
            && (int) $currentParams->get('date_field_id', 0) < 1
        ) {
            $currentParams->set('date_field_id', $legacyFieldId);
        }

        $extension = (object) [
            'extension_id' => $extensionId,
            'params' => $currentParams->toString(),
        ];
        $database->updateObject('#__extensions', $extension, 'extension_id');

        return $source;
    }
};
