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
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Fieldset;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Input;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Legend;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\None;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Option;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Single;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Process\TraitProcess;
use Dotclear\Interface\Core\BlogWorkspaceInterface;
use Exception;

class Manage
{
    use TraitProcess;

    /**
     * Initializes the page.
     */
    public static function init(): bool
    {
        self::status(My::checkContext(My::MANAGE));

        return self::status();
    }

    /**
     * Processes the request(s).
     */
    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        try {
            $settings = My::settings();

            $piwik_service_uri = is_string($piwik_service_uri = $settings->piwik_service_uri) ? $piwik_service_uri : '';
            $piwik_site        = is_string($piwik_site = $settings->piwik_site) ? $piwik_site : '';
            $piwik_ips         = is_string($piwik_ips = $settings->piwik_ips) ? $piwik_ips : '';
            $piwik_fancy       = is_bool($piwik_fancy = $settings->piwik_fancy) && $piwik_fancy;

            $piwik_uri   = '';
            $piwik_token = '';

            try {
                Piwik::parseServiceURI($piwik_service_uri, $piwik_uri, $piwik_token);
            } catch (Exception) {
            }

            if (isset($_POST['piwik_uri']) && isset($_POST['piwik_token'])) {
                $piwik_uri   = is_string($piwik_uri = $_POST['piwik_uri']) ? $piwik_uri : '';
                $piwik_token = is_string($piwik_token = $_POST['piwik_token']) ? $piwik_token : '';

                if ($piwik_uri !== '' && $piwik_token !== '') {
                    $piwik_service_uri = Piwik::getServiceURI($piwik_uri, $piwik_token);
                    new Piwik($piwik_service_uri);
                } else {
                    $piwik_service_uri = '';
                }

                # Dotclear piwik setting
                $settings->put('piwik_service_uri', $piwik_service_uri);

                # More stuff to set
                if ($piwik_uri && isset($_POST['piwik_site'])) {
                    $piwik_site  = is_numeric($piwik_site = $_POST['piwik_site']) ? (int) $piwik_site : -1;
                    $piwik_ips   = isset($_POST['piwik_ips'])   && is_string($piwik_ips = $_POST['piwik_ips']) ? $piwik_ips : '';
                    $piwik_fancy = isset($_POST['piwik_fancy']) && is_bool($piwik_fancy = $_POST['piwik_fancy']) && $piwik_fancy;

                    if ($piwik_site !== -1) {
                        $o = new Piwik($piwik_service_uri);
                        if (!$o->siteExists($piwik_site)) {
                            throw new Exception(__('Piwik site does not exist.'));
                        }
                    }
                    $settings->put('piwik_site', $piwik_site, BlogWorkspaceInterface::NS_INT);
                    $settings->put('piwik_ips', $piwik_ips, BlogWorkspaceInterface::NS_STRING);
                    $settings->put('piwik_fancy', $piwik_fancy, BlogWorkspaceInterface::NS_BOOL);
                }

                App::blog()->triggerBlog();
                App::backend()->notices()->addSuccessNotice(__('Configuration successfully updated.'));
                My::redirect();
            }

            if ($piwik_uri !== '') {
                $o = new Piwik($piwik_service_uri);

                // Create a new site
                $site_name = isset($_POST['site_name']) && is_string($site_name = $_POST['site_name']) ? $site_name : '';
                $site_url  = isset($_POST['site_url'])  && is_string($site_url = $_POST['site_url']) ? $site_url : '';

                if ($site_name !== '' && $site_url !== '') {
                    $o->addSite($site_name, $site_url);

                    App::backend()->notices()->addSuccessNotice(__('Configuration successfully updated.'));
                    My::redirect();
                }
            }
        } catch (Exception $exception) {
            App::error()->add($exception->getMessage());
        }

        return true;
    }

    /**
     * Renders the page.
     */
    public static function render(): void
    {
        if (!self::status()) {
            return;
        }

        $settings = My::settings();

        $piwik_service_uri = is_string($piwik_service_uri = $settings->piwik_service_uri) ? $piwik_service_uri : '';
        $piwik_site        = is_numeric($piwik_site = $settings->piwik_site) ? (int) $piwik_site : -1;
        $piwik_ips         = is_string($piwik_ips = $settings->piwik_ips) ? $piwik_ips : '';
        $piwik_fancy       = is_bool($piwik_fancy = $settings->piwik_fancy) && $piwik_fancy;

        $site_url  = preg_replace('/\?$/', '', (string) App::blog()->url());
        $site_name = App::blog()->name();

        $piwik_uri   = '';
        $piwik_token = '';

        $piwik_sites    = [];
        $no_piwik_sites = true;

        try {
            Piwik::parseServiceURI($piwik_service_uri, $piwik_uri, $piwik_token);
        } catch (Exception) {
        }

        $sites_combo = [
            (new Option(__('Disable Matomo'), '')),
        ];
        if ($piwik_uri !== '') {
            $o = new Piwik($piwik_service_uri);

            try {
                // Get sites list
                $piwik_sites = $o->getSitesWithAdminAccess();

                if ($piwik_sites !== []) {
                    $no_piwik_sites = false;
                }

                foreach ($piwik_sites as $k => $name) {
                    if ($name !== '') {
                        $sites_combo[] = (new Option(html::escapeHTML($k . ' - ' . $name), (string) $k));
                    }
                }

                if ($piwik_site !== -1 && !isset($piwik_sites[$piwik_site])) {
                    $piwik_site = -1;
                }
            } catch (Exception) {
            }
        }

        App::backend()->page()->openModule(My::name());

        echo App::backend()->page()->breadcrumb(
            [
                Html::escapeHTML(App::blog()->name()) => '',
                __('Matomo configuration')            => '',
            ]
        );
        echo App::backend()->notices()->getNotices();

        // Form

        if ($no_piwik_sites || $piwik_uri === '') {
            $track = [
                (new Note())
                    ->class('info')
                    ->text(__('Your Matomo installation is not configured yet.')),
            ];
        } else {
            $track = [
                (new Single('hr')),
                (new Para())
                    ->items([
                        (new Select('piwik_site'))
                            ->items($sites_combo)
                            ->default($piwik_site)
                            ->label((new Label(__('Matomo website to track:'), Label::OUTSIDE_TEXT_BEFORE))->class('classic')),
                    ]),
                (new Para())
                    ->items([
                        (new Checkbox('piwik_fancy', $piwik_fancy))
                            ->value(1)
                            ->label((new Label(__('Use fancy page names:'), Label::INSIDE_LABEL_BEFORE))),
                    ]),
                (new Para())
                    ->items([
                        (new Input('piwik_ips'))
                            ->value(Html::escapeHTML($piwik_ips))
                            ->size(50)
                            ->maxlength(600)
                            ->label((new Label(__('Do not track following IP addresses:'), Label::OUTSIDE_LABEL_BEFORE))),
                    ]),
                (new Note())
                    ->class('info')
                    ->text(sprintf(__('Your current IP address is: %s'), http::realIP())),
            ];
        }

        $stats = (new None());
        if ($no_piwik_sites === false && $piwik_sites !== [] && $piwik_site !== -1 && $piwik_uri !== '') {
            $name = $piwik_sites[$piwik_site] ?? '';
            if ($name !== '') {
                $stats = (new Para())
                    ->items([
                        (new Link())
                            ->href($piwik_uri)
                            ->text(sprintf(__('View "%s" statistics'), html::escapeHTML($name))),
                    ]);
            }
        }

        echo (new Form('piwik'))
            ->action(App::backend()->getPageURL())
            ->method('post')
            ->fields([
                (new Fieldset('config'))
                    ->legend(new Legend(__('Your Matomo configuration')))
                    ->fields([
                        (new Para())
                            ->items([
                                (new Input('piwik_uri'))
                                    ->value(Html::escapeHTML($piwik_uri))
                                    ->size(40)
                                    ->maxlength(255)
                                    ->label((new Label(__('Your Matomo URL:'), Label::OUTSIDE_LABEL_BEFORE))),
                            ]),
                        (new Para())
                            ->items([
                                (new Input('piwik_token'))
                                    ->value(Html::escapeHTML($piwik_token))
                                    ->size(40)
                                    ->maxlength(255)
                                    ->label((new Label(__('Your Matomo Token:'), Label::OUTSIDE_LABEL_BEFORE))),
                            ]),
                        ...$track,
                        (new Para())->items([
                            (new Submit(['saveconfig']))
                                ->value(__('Save')),
                            ...My::hiddenFields(),
                        ]),
                        $stats,
                    ]),
            ])
        ->render();

        if ($no_piwik_sites === false && $piwik_uri !== '') {
            echo (new Form('piwik_create'))
                ->action(App::backend()->getPageURL())
                ->method('post')
                ->fields([
                    (new Fieldset('create'))
                        ->legend(new Legend(__('Create a new Matomo site for this blog')))
                        ->fields([
                            (new Para())
                                ->items([
                                    (new Input('site_name'))
                                        ->value(Html::escapeHTML((string) $site_name))
                                        ->size(40)
                                        ->maxlength(255)
                                        ->label((new Label(__('Site name:'), Label::OUTSIDE_LABEL_BEFORE))),
                                ]),
                            (new Para())
                                ->items([
                                    (new Input('site_url'))
                                        ->value(Html::escapeHTML((string) $site_url))
                                        ->size(40)
                                        ->maxlength(255)
                                        ->label((new Label(__('Site URL:'), Label::OUTSIDE_LABEL_BEFORE))),
                                ]),
                            (new Para())->items([
                                (new Submit(['createsite']))
                                    ->value(__('Save')),
                                ...My::hiddenFields(),
                            ]),
                        ]),
                ])
            ->render();
        }

        App::backend()->page()->helpBlock('piwik');

        App::backend()->page()->closeModule();
    }
}
