<?php

/**
 * @brief pingMastodon, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Olivier Meunier, Franck Paul and contributors
 *
 * @copyright Franck Paul contact@open-time.net
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

if (isset($this) && is_object($this) && method_exists($this, 'registerModule') && isset($this->id) && is_string($this->id)) {
    $this->registerModule(
        'Matomo',
        'Matomo (ex Piwik) statistics integration',
        'Olivier Meunier',
        '3.0',
        [
            'date'        => '2025-09-07T15:51:45+03.0',
            'requires'    => [['core', '2.39']],
            'type'        => 'plugin',
            'permissions' => 'My',
            'details'     => 'https://open-time.net/docs/plugins/piwik',
            'support'     => 'https://github.com/franck-paul/piwik',
            'repository'  => 'https://raw.githubusercontent.com/franck-paul/piwik/main/dcstore.xml',
            'license'     => 'gpl2',
        ]
    );
}
