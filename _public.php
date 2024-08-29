<?php

# -- BEGIN LICENSE BLOCK ----------------------------------
#
# This file is part of Dotclear 2.
#
# Copyright (c) 2003-2008 Olivier Meunier and contributors
# Licensed under the GPL version 2.0 license.
# See LICENSE file or
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
#
# -- END LICENSE BLOCK ------------------------------------
if (!defined('DC_RC_PATH')) {
    return;
}

$core->addBehavior('publicFooterContent', ['piwikPublic','publicFooterContent']);

class piwikPublic
{
    public static function publicFooterContent($core, $_ctx)
    {
        $piwik_service_uri = $core->blog->settings->piwik->piwik_service_uri;
        $piwik_site        = $core->blog->settings->piwik->piwik_site;
        $piwik_ips         = $core->blog->settings->piwik->piwik_ips;

        if (!$piwik_service_uri || !$piwik_site) {
            return;
        }

        $piwik_ips = array_flip(preg_split('/(\s*[;,]\s*|\s+)/', trim($piwik_ips), -1, PREG_SPLIT_NO_EMPTY));

        if (isset($piwik_ips[http::realIP()])) {
            return;
        }

        $action = $_SERVER['URL_REQUEST_PART'];
        if ($core->blog->settings->piwik->piwik_fancy) {
            $action = $action == '' ? 'home' : str_replace('/', ' : ', $action);
        }

        # Check for 404 response
        $h = headers_list();
        foreach ($h as $v) {
            if (preg_match('/^status: 404/i', $v)) {
                $action = '404 Not Found/' . $action;
            }
        }

        echo dcPiwik::getScriptCode($piwik_service_uri, $piwik_site, $action);
    }
}
