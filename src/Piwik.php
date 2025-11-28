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
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\HttpClient;
use Exception;

class Piwik
{
    protected string $api_base;
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

        if (!HttpClient::readURL($base, $ssl, $host, $port, $path, $user, $pass)) {
            throw new Exception(__('Unable to read Piwik URI.'));
        }

        $this->api_base  = $base;
        $this->api_token = $token;
    }

    /**
     * Determines if site exists.
     *
     * @param      int     $id     The identifier
     *
     * @return     bool    True if site exists, False otherwise.
     */
    public function siteExists(int $id): bool
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
     * @return     array<string, mixed>  The sites with admin access.
     */
    public function getSitesWithAdminAccess(): array
    {
        $res = [];

        $qs = http_build_query([
            'module' => 'API',
            'format' => 'json',
            'method' => 'SitesManager.getSitesWithAdminAccess',
        ]);

        $curl = curl_init();
        if ($curl) {
            curl_setopt_array($curl, [
                CURLOPT_URL            => $this->api_base . '?' . $qs,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => 'token_auth=' . $this->api_token,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/x-www-form-urlencoded',
                ],
            ]);

            // Don't care about certificates issuers but only in dev and debug mode
            if (App::config()->devMode() && App::config()->debugMode()) {
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            }

            $response = curl_exec($curl);
            $err      = curl_error($curl);

            if ($response !== false) {
                $response = json_decode((string) $response, true);
                if (isset($response['result']) && $response['result'] === 'error') {
                    $this->piwikError($response['message']);
                } else {
                    foreach ($response as $site) {
                        $res[$site['idsite']] = $site;
                    }
                }
            } else {
                $this->piwikError($err);
            }
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
        $res = null;

        $qs = http_build_query([
            'module'   => 'API',
            'format'   => 'json',
            'method'   => 'SitesManager.addSite',
            'siteName' => $name,
            'urls'     => $url,
        ]);

        $curl = curl_init();
        if ($curl) {
            curl_setopt_array($curl, [
                CURLOPT_URL            => $this->api_base . '?' . $qs,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => 'token_auth=' . $this->api_token,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/x-www-form-urlencoded',
                ],
            ]);

            // Don't care about certificates issuers but only in dev and debug mode
            if (App::config()->devMode() && App::config()->debugMode()) {
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            }

            $response = curl_exec($curl);
            $err      = curl_error($curl);

            if ($response !== false) {
                $res = json_decode((string) $response, true);
            } else {
                $this->piwikError($err);
            }
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
        if ($base === '') {
            throw new Exception('Invalid Piwik Base URI.');
        }

        if (!preg_match('/^[a-f0-9]{32}$/i', $token)) {
            throw new Exception('Invalid Piwik Token.');
        }

        $base = (string) preg_replace('/\?(.*)$/', '', $base);
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

        parse_str($p['query'], $query);
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
        '<!-- Piwik -->' . PHP_EOL .
        '<script src="' . Html::escapeURL($js) . '"></script>' . PHP_EOL .
        '<script>' .
        'piwik_tracker_pause = 250;' . PHP_EOL .
        "piwik_log('" . Html::escapeJS($action) . "', " . (int) $idsite . ", '" . Html::escapeJS($php) . "');" . PHP_EOL .
        '</script>' . PHP_EOL .
        '<noscript><div><img src="' . Html::escapeURL($php) . '" style="border:0" alt="piwik" width="0" height="0"></div></noscript>' . PHP_EOL .
        '<!-- /Piwik -->' . PHP_EOL ;
    }
}
