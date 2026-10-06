<?php

/**
 * @package     BSL Daily Content
 * @copyright   Copyright (C) 2026 Vasilyev Alexander. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use BSLWorld\Plugin\Content\Bsldailycontent\Extension\Bsldailycontent;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(Bsldailycontent::class, static function (Container $container): PluginInterface {
                $plugin = new Bsldailycontent(
                    (array) PluginHelper::getPlugin('content', 'bsldailycontent')
                );
                $plugin->setApplication(Factory::getApplication());
                $plugin->setDatabase($container->get(DatabaseInterface::class));

                return $plugin;
            })
        );
    }
};
