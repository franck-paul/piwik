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
        $base  = '';
        $token = '';

        self::parseServiceURI($uri, $base, $token);

        $ssl  = false;
        $host = '';
        $port = 80;
        $path = '';
        $user = '';
        $pass = '';

        if (!self::readURL($base, $ssl, $host, $port, $path, $user, $pass)) {
            throw new Exception(__('Unable to read Piwik URI.'));
        }

        parent::__construct($host, $port, 10);
        $this->useSSL($ssl);
        $this->setAuthorization($user, $pass);
        $this->api_path  = $path;
        $this->api_token = $token;
    }

    /**
     * Determines if site exists.
     *
     * @param      string  $id     The identifier
     *
     * @return     bool    True if site exists, False otherwise.
     */
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

    /**
     * Gets the sites with admin access.
     *
     * @return     mixed  The sites with admin access.
     */
    public function getSitesWithAdminAccess()
    {
        $get = $this->methodCall('SitesManager.getSitesWithAdminAccess');
        $this->post($get['path'], $get['data']);
        $rsp = $this->readResponse();
        $res = [];
        foreach ($rsp as $v) {
            $res[$v['idsite']] = $v;
        }

        return $res;
    }

    /**
     * Adds a site.
     *
     * @param      string  $name   The name
     * @param      string  $url    The url
     *
     * @return     mixed
     */
    public function addSite(string $name, string $url)
    {
        $data = [
            'siteName' => $name,
            'urls'     => $url,
        ];
        $get = $this->methodCall('SitesManager.addSite', $data);
        $this->post($get['path'], $get['data']);

        return $this->readResponse();
    }

    /**
     * Prepare a method call
     *
     * @param      string                   $method  The method
     * @param      array<string, mixed>     $data    The data
     *
     * @return     array<string, mixed>
     */
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

    /**
     * Reads a response.
     *
     * @throws     Exception  (description)
     *
     * @return     mixed
     */
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

    /**
     * Gets the service uri.
     *
     * @param      string     $base   The base
     * @param      string     $token  The token
     *
     * @throws     Exception
     *
     * @return     string     The service uri.
     */
    public static function getServiceURI(string &$base, string $token): string
    {
        if (!$base) {
            throw new Exception('Invalid Piwik Base URI.');
        }

        if (!preg_match('/^[a-f0-9]{32}$/i', $token)) {
            throw new Exception('Invalid Piwik Token.');
        }

        $base = (string) preg_replace('/\?(.*)$/', '', (string) $base);
        if (!preg_match('/index\.php$/', $base)) {
            if (!preg_match('/\/$/', $base)) {
                $base .= '/';
            }
            $base .= 'index.php';
        }

        return $base . '?token_auth=' . $token;
    }

    /**
     * Parse a Service URI
     *
     * @param      string  $uri    The uri
     * @param      string  $base   The base
     * @param      string  $token  The token
     */
    public static function parseServiceURI(string &$uri, string &$base, string &$token): void
    {
        $err = new Exception(__('Invalid Service URI.'));

        $p = parse_url($uri);
        if (!$p) {
            $p = [];
        }
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

        parse_str((string) $p['query'], $query);
        if (empty($query['token_auth'])) {
            throw $err;
        }

        $base  = $uri;
        $token = is_array($query['token_auth']) ? $query['token_auth'][0] : $query['token_auth'];

        $uri = self::getServiceURI($base, $token);
    }

    /**
     * Gets the script code.
     *
     * @param      string  $uri     The URI
     * @param      string  $idsite  The site ID
     * @param      string  $action  The action
     *
     * @return     string  The script code.
     */
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
