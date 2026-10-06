<?php

/**
 * @package     BSL Daily Content
 * @copyright   Copyright (C) 2026 Vasilyev Alexander. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace BSLWorld\Plugin\Content\Bsldailycontent\Rule;

defined('_JEXEC') or die;

use BSLWorld\Plugin\Content\Bsldailycontent\Service\DateFieldManager;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

final class SetupRule extends FormRule
{
    public function test(
        \SimpleXMLElement $element,
        $value,
        $group = null,
        ?Registry $input = null,
        ?Form $form = null
    ): bool {
        $application = Factory::getApplication();

        if (!$application instanceof AdministratorApplication || $input === null) {
            throw new \RuntimeException('Daily content setup requires the Joomla administrator.');
        }

        $manager = new DateFieldManager(
            $application,
            Factory::getContainer()->get(DatabaseInterface::class)
        );
        $manager->configure(
            (string) $input->get('params.primary_language', ''),
            (int) $input->get('params.primary_category_id', 0),
            (string) $input->get('params.secondary_language', ''),
            (int) $input->get('params.secondary_category_id', 0),
            (int) $input->get('params.date_field_id', 0)
        );

        return true;
    }
}
