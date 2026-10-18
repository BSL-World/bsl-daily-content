<?php

/**
 * @package     BSL Daily Content
 * @copyright   Copyright (C) 2026 Vasilyev Alexander. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace BSLWorld\Plugin\Content\Bsldailycontent\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;

final class OpacityField extends FormField
{
    protected $type = 'Opacity';

    protected function getInput(): string
    {
        $value = max(0, min(100, (int) $this->value));
        $id = htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8');

        Factory::getApplication()
            ->getDocument()
            ->getWebAssetManager()
            ->registerAndUseScript(
                'plg_content_bsldailycontent.admin-opacity',
                'plg_content_bsldailycontent/admin-opacity.js',
                ['version' => '1.3.0'],
                ['defer' => true],
                ['core']
            );

        return '<div class="d-flex align-items-center gap-3">'
            . '<input'
            . ' type="range"'
            . ' id="' . $id . '"'
            . ' name="' . $name . '"'
            . ' value="' . $value . '"'
            . ' min="0"'
            . ' max="100"'
            . ' step="5"'
            . ' class="form-range flex-grow-1"'
            . ' data-bsl-opacity'
            . ' aria-describedby="' . $id . '-value"'
            . '>'
            . '<output'
            . ' id="' . $id . '-value"'
            . ' for="' . $id . '"'
            . ' class="badge bg-secondary fs-6"'
            . ' data-bsl-opacity-value'
            . '>' . $value . '%</output>'
            . '</div>';
    }
}
