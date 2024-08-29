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

use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\HttpClient;
use Exception;

class Piwik extends HttpClient
{
    protected string $api_path;

    protected string $api_token;

    /**
     * Constructs a new instance.
     *
     * @param      string     $uri    The uri
     *
     * @throws     Exception
     */
    public function __construct(string $uri)
    {
        self::parseServiceURI($uri, $base, $token);

        if (!self::readURL($base, $ssl, $host, $port, $path, $user, $pass)) {
            throw new Exception(__('Unable to read Piwik URI.'));
        }

        parent::__construct($host, $port, 10);
        $this->useSSL($ssl);
        $this->setAuthorization($user, $pass);
        $this->api_path  = $path;
        $this->api_token = $token;
    }

    public function siteExists(string $id): bool
    {
        try {
            $sites = $this->getSitesWithAdminAccess();
            foreach ($sites as $v) {
                if ($v['idsite'] === $id) {
                    return true;
                }
            }
        } catch (Exception) {
        }

        return false;
    }

    public function getSitesWithAdminAccess()
    {
        $get = $this->methodCall('SitesManager.getSitesWithAdminAccess');
        $this->get($get['path'], $get['data']);
        $rsp = $this->readResponse();
        $res = [];
        foreach ($rsp as $v) {
            $res[$v['idsite']] = $v;
        }

        return $res;
    }

    public function addSite(string $name, string $url)
    {
        $data = [
            'siteName' => $name,
            'urls'     => $url,
        ];
        $get = $this->methodCall('SitesManager.addSite', $data);
        $this->get($get['path'], $get['data']);

        return $this->readResponse();
    }

    protected function methodCall(string $method, array $data = []): array
    {
        $data['token_auth'] = $this->api_token;
        $data['module']     = 'API';
        $data['format']     = 'php';
        $data['method']     = $method;

        return [
            'path' => $this->api_path,
            'data' => $data,
        ];
    }

    protected function readResponse()
    {
        $res = $this->getContent();
        $res = @unserialize($res);

        if ($res === false) {
            throw new Exception(__('Invalid Piwik Response.'));
        }

        if (is_array($res) && !empty($res['result']) && $res['result'] == 'error') {
            $this->piwikError($res['message']);
        }

        return $res;
    }

    protected function piwikError(string $msg): void
    {
        throw new Exception(sprintf(__('Piwik returned an error: %s'), strip_tags($msg)));
    }

    public static function getServiceURI(string &$base, string $token): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/i', $token)) {
            throw new Exception('Invalid Piwik Token.');
        }

        $base = preg_replace('/\?(.*)$/', '', $base);
        if (!preg_match('/index\.php$/', $base)) {
            if (!preg_match('/\/$/', $base)) {
                $base .= '/';
            }
            $base .= 'index.php';
        }

        return $base . '?token_auth=' . $token;
    }

    public static function parseServiceURI(string &$uri, string &$base, string &$token): void
    {
        $err = new Exception(__('Invalid Service URI.'));

        $p = parse_url($uri);
        $p = array_merge(
            ['scheme' => '','host' => '','user' => '','pass' => '','path' => '','query' => '','fragment' => ''],
            $p
        );

        if ($p['scheme'] != 'http' && $p['scheme'] != 'https') {
            throw $err;
        }

        if (empty($p['query'])) {
            throw $err;
        }

        parse_str($p['query'], $query);
        if (empty($query['token_auth'])) {
            throw $err;
        }

        $base  = $uri;
        $token = $query['token_auth'];
        $uri   = self::getServiceURI($base, $token);
    }

    public static function getScriptCode(string $uri, string $idsite, string $action = ''): string
    {
        self::getServiceURI($uri, '00000000000000000000000000000000');
        $js  = dirname($uri) . '/piwik.js';
        $php = dirname($uri) . '/piwik.php';

        return
        "<!-- Piwik -->\n" .
        '<script type="text/javascript" src="' . Html::escapeURL($js) . '"></script>' . "\n" .
        '<script type="text/javascript">' .
        "//<![CDATA[\n" .
        "piwik_tracker_pause = 250;\n" .
        "piwik_log('" . Html::escapeJS($action) . "', " . (int) $idsite . ", '" . Html::escapeJS($php) . "');\n" .
        "//]]>\n" .
        "</script>\n" .
        '<noscript><div><img src="' . Html::escapeURL($php) . '" style="border:0" alt="piwik" width="0" height="0" /></div>' . "\n" .
        "</noscript>\n" .
        "<!-- /Piwik -->\n";
    }
}
