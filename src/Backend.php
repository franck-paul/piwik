<?php

/**
 * @brief piwik, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Olivier Meunier, Franck Paul and contributors
 *
 * @copyright Franck Paul carnet.franck.paul@gmail.com
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

namespace Dotclear\Plugin\piwik;

use Dotclear\App;
use Dotclear\Helper\Process\TraitProcess;

class Backend
{
    use TraitProcess;

    public static function init(): bool
    {
        // dead but useful code, in order to have translations
        __('Piwik');
        __('Matomo (ex Piwik) statistics integration');

        // Curl lib is mandatory for backend operations
        return self::status(My::checkContext(My::BACKEND) && function_exists('curl_init'));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        if (My::checkContext(My::MENU)) {
            // Add menu
            My::addBackendMenuItem(App::backend()->menus()::MENU_PLUGINS);
        }

        return true;
    }
}
