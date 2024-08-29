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
if (!defined('DC_CONTEXT_ADMIN')) {
    return;
}

$core->blog->settings->addNameSpace('piwik');
$piwik_service_uri = $core->blog->settings->piwik->piwik_service_uri;
$piwik_site        = $core->blog->settings->piwik->piwik_site;
$piwik_ips         = $core->blog->settings->piwik->piwik_ips;
$piwik_fancy       = $core->blog->settings->piwik->piwik_fancy;

$site_url  = preg_replace('/\?$/', '', $core->blog->url);
$site_name = $core->blog->name;

try {
    dcPiwik::parseServiceURI($piwik_service_uri, $piwik_uri, $piwik_token);
} catch (Exception $e) {
}

if (isset($_POST['piwik_uri']) && isset($_POST['piwik_token'])) {
    try {
        $piwik_uri   = $_POST['piwik_uri'];
        $piwik_token = $_POST['piwik_token'];

        if ($piwik_uri && $piwik_token) {
            $piwik_service_uri = dcPiwik::getServiceURI($piwik_uri, $piwik_token);
            new dcPiwik($piwik_service_uri);
        } else {
            $piwik_service_uri = '';
        }

        # Dotclear piwik setting
        $core->blog->settings->addNameSpace('piwik');
        $core->blog->settings->piwik->put('piwik_service_uri', $piwik_service_uri);

        # More stuff to set
        if ($piwik_uri && isset($_POST['piwik_site'])) {
            $piwik_site  = $_POST['piwik_site'];
            $piwik_ips   = $_POST['piwik_ips'];
            $piwik_fancy = $_POST['piwik_fancy'];

            if ($piwik_site != '') {
                $o = new dcPiwik($piwik_service_uri);
                if (!$o->siteExists($piwik_site)) {
                    throw new Exception(__('Piwik site does not exist.'));
                }
            }
            $core->blog->settings->piwik->put('piwik_site', $piwik_site);
            $core->blog->settings->piwik->put('piwik_ips', $piwik_ips);
            $core->blog->settings->piwik->put('piwik_fancy', $piwik_fancy, 'boolean');
            $core->blog->triggerBlog();
        }

        http::redirect($p_url . '&upd=1');
    } catch (Exception $e) {
        $core->error->add($e->getMessage());
    }
}

if ($piwik_uri) {
    $sites_combo = [__('Disable Piwik') => ''];

    try {
        $o = new dcPiwik($piwik_service_uri);

        # Create a new site
        if (!empty($_POST['site_name']) && !empty($_POST['site_url'])) {
            $o->addSite($_POST['site_name'], $_POST['site_url']);
            http::redirect($p_url . '&created=1');
        }

        # Get sites list
        $piwik_sites = $o->getSitesWithAdminAccess();

        if (count($piwik_sites) < 1) {
            throw new Exception(__('No Piwik sites configured.'));
        }

        foreach ($piwik_sites as $k => $v) {
            $sites_combo[html::escapeHTML($k . ' - ' . $v['name'])] = $k;
        }

        if ($piwik_site && !isset($piwik_sites[$piwik_site])) {
            $piwik_site = '';
        }
    } catch (Exception $e) {
        $core->error->add($e->getMessage());
    }
}
?>
<html>
<head>
  <title>Piwik</title>
</head>

<body>
<?php
echo '<h2>' . __('Piwik configuration') . '</h2>';

echo '<h3>' . __('Your Piwik configuration') . '</h3>';

echo
'<form action="' . $p_url . '" method="post">' .
'<fieldset><legend>' . __('Your Piwik configuration') . '</legend>' .
'<p class="field"><label>' . __('Your Piwik URL:') . ' ' .
form::field('piwik_uri', 40, 255, html::escapeHTML($piwik_uri)) . '</label></p>' .
'<p class="field"><label>' . __('Your Piwik Token:') . ' ' .
form::field('piwik_token', 40, 255, html::escapeHTML($piwik_token)) . '</label></p>';

if (!$piwik_uri) {
    echo '<p class="msg">' . __('Your Piwik installation is not configured yet.') . '</p>';
} else {
    echo
    '<p class="field"><label>' . __('Piwik website to track:') . ' ' .
    form::combo('piwik_site', $sites_combo, $piwik_site) . '</label></p>' .
    '<p class="field"><label>' . __('Use fancy page names:') . ' ' .
    form::checkbox('piwik_fancy', 1, $piwik_fancy) . '</label></p>' .
    '<p class="field"><label for="piwik_ips">' . __('Do not track following IP addresses:') . '</label> ' .
    form::field('piwik_ips', 50, 600, $piwik_ips) . '</p>' .
    '<p>' . sprintf(__('Your current IP address is: %s'), '<strong>' . http::realIP() . '</strong>') . '</p>';
}

echo
'<p><input type="submit" value="' . __('save') . '" />' .
$core->formNonce() . '</p>';

if ($piwik_site && $piwik_uri) {
    echo '<p><strong><a href="' . $piwik_uri . '">' .
    sprintf(__('View "%s" statistics'), html::escapeHTML($piwik_sites[$piwik_site]['name'])) . '</a></strong></p>';
}

echo '</fieldset></form>';

if ($piwik_uri) {
    echo
    '<form action="' . $p_url . '" method="post">' .
    '<fieldset><legend>' . __('Create a new Piwik site for this blog') . '</legend>' .
    '<p class="field"><label>' . __('Site name:') . ' ' .
    form::field('site_name', 40, 255, $site_name) . '</label></p>' .
    '<p class="field"><label>' . __('Site URL:') . ' ' .
    form::field('site_url', 40, 255, $site_url) . '</label></p>' .
    '<p><input type="submit" value="' . __('create site') . '" />' .
    $core->formNonce() . '</p>' .
    '</fieldset></form>';
}

dcPage::helpBlock('piwik');
?>
</body>
</html>
