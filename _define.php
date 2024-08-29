<?php
/**
 * @brief pingMastodon, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Olivier Meunier, Franck Paul and contributors
 *
 * @copyright Franck Paul carnet.franck.paul@gmail.com
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
$this->registerModule(
    'Piwik',
    'Matomo (ex Piwik) statistics integration',
    'Olivier Meunier',
    '1.0.1',
    [
        'requires'    => [['core', '2.31']],
        'type'        => 'plugin',
        'permissions' => 'My',
        'details'     => 'https://open-time.net/docs/plugins/piwik',
        'support'     => 'https://github.com/franck-paul/piwik',
        'repository'  => 'https://raw.githubusercontent.com/franck-paul/piwik/main/dcstore.xml',
    ]
);
