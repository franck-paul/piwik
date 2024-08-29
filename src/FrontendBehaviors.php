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

use Dotclear\Helper\Network\Http;

class FrontendBehaviors
{
    public static function publicFooterContent(): string
    {
        $settings = My::settings();

        $piwik_service_uri = $settings->piwik_service_uri ?? '';
        $piwik_site        = $settings->piwik_site        ?? '';
        $piwik_ips         = $settings->piwik_ips         ?? '';

        if (!$piwik_service_uri || !$piwik_site) {
            return '';
        }

        $ips = preg_split('/(\s*[;,]\s*|\s+)/', trim($piwik_ips), -1, PREG_SPLIT_NO_EMPTY);
        if ($ips) {
            $piwik_ips = array_flip($ips);
        }

        if (isset($piwik_ips[Http::realIP()])) {
            return '';
        }

        $action = $_SERVER['URL_REQUEST_PART'];
        if ($settings->piwik_fancy) {
            $action = $action == '' ? 'home' : str_replace('/', ' : ', $action);
        }

        # Check for 404 response
        $h = headers_list();
        foreach ($h as $v) {
            if (preg_match('/^status: 404/i', $v)) {
                $action = '404 Not Found/' . $action;
            }
        }

        echo Piwik::getScriptCode($piwik_service_uri, $piwik_site, $action);

        return '';
    }
}
