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
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\Core\Process;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Fieldset;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Input;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Legend;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\None;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Single;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Exception;

class Manage extends Process
{
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

            $piwik_service_uri = $settings->piwik_service_uri ?? '';
            $piwik_site        = $settings->piwik_site        ?? '';
            $piwik_ips         = $settings->piwik_ips         ?? '';
            $piwik_fancy       = $settings->piwik_fancy       ?? false;

            $piwik_uri   = '';
            $piwik_token = '';

            try {
                Piwik::parseServiceURI($piwik_service_uri, $piwik_uri, $piwik_token);
            } catch (Exception) {
            }

            if (isset($_POST['piwik_uri']) && isset($_POST['piwik_token'])) {
                $piwik_uri   = $_POST['piwik_uri'];
                $piwik_token = $_POST['piwik_token'];

                if ($piwik_uri && $piwik_token) {
                    $piwik_service_uri = Piwik::getServiceURI($piwik_uri, $piwik_token);
                    new Piwik($piwik_service_uri);
                } else {
                    $piwik_service_uri = '';
                }

                # Dotclear piwik setting
                $settings->put('piwik_service_uri', $piwik_service_uri);

                # More stuff to set
                if ($piwik_uri && isset($_POST['piwik_site'])) {
                    $piwik_site  = $_POST['piwik_site'];
                    $piwik_ips   = $_POST['piwik_ips'];
                    $piwik_fancy = $_POST['piwik_fancy'];

                    if ($piwik_site !== '') {
                        $o = new Piwik($piwik_service_uri);
                        if (!$o->siteExists((int) $piwik_site)) {
                            throw new Exception(__('Piwik site does not exist.'));
                        }
                    }
                    $settings->put('piwik_site', $piwik_site);
                    $settings->put('piwik_ips', $piwik_ips);
                    $settings->put('piwik_fancy', $piwik_fancy, 'boolean');
                }

                App::blog()->triggerBlog();
                Notices::addSuccessNotice(__('Configuration successfully updated.'));
                My::redirect();
            }

            if ($piwik_uri) {
                $o = new Piwik($piwik_service_uri);

                # Create a new site
                if (!empty($_POST['site_name']) && !empty($_POST['site_url'])) {
                    $o->addSite($_POST['site_name'], $_POST['site_url']);

                    Notices::addSuccessNotice(__('Configuration successfully updated.'));
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

        $piwik_service_uri = $settings->piwik_service_uri ?? '';
        $piwik_site        = $settings->piwik_site        ?? '';
        $piwik_ips         = $settings->piwik_ips         ?? '';
        $piwik_fancy       = $settings->piwik_fancy       ?? false;

        $site_url  = preg_replace('/\?$/', '', (string) App::blog()->url());
        $site_name = App::blog()->name();

        $piwik_uri   = '';
        $piwik_token = '';

        try {
            Piwik::parseServiceURI($piwik_service_uri, $piwik_uri, $piwik_token);
        } catch (Exception) {
        }

        $sites_combo = [__('Disable Piwik') => ''];
        if ($piwik_uri !== '') {
            $o = new Piwik($piwik_service_uri);

            // Get sites list
            $piwik_sites = $o->getSitesWithAdminAccess();

            if ($piwik_sites === []) {
                throw new Exception(__('No Piwik sites configured.'));
            }

            foreach ($piwik_sites as $k => $v) {
                $sites_combo[html::escapeHTML($k . ' - ' . $v['name'])] = $k;
            }

            if ($piwik_site && !isset($piwik_sites[$piwik_site])) {
                $piwik_site = '';
            }
        }

        Page::openModule(My::name());

        echo Page::breadcrumb(
            [
                Html::escapeHTML(App::blog()->name()) => '',
                __('Piwik configuration')             => '',
            ]
        );
        echo Notices::GetNotices();

        // Form

        if ($piwik_uri !== '') {
            $track = [
                (new Note())
                    ->class('info')
                    ->text(__('Your Piwik installation is not configured yet.')),
            ];
        } else {
            $track = [
                (new Single('hr')),
                (new Para())
                    ->items([
                        (new Select('piwik_site'))
                            ->items($sites_combo)
                            ->default($piwik_site)
                            ->label((new Label(__('Piwik website to track:'), Label::OUTSIDE_TEXT_BEFORE))->class('classic')),
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
                            ->value(Html::escapeHTML((string) $piwik_ips))
                            ->size(50)
                            ->maxlength(600)
                            ->label((new Label(__('Do not track following IP addresses:'), Label::OUTSIDE_LABEL_BEFORE))),
                    ]),
                (new Note())
                    ->class('info')
                    ->text(sprintf(__('Your current IP address is: %s'), http::realIP())),
            ];
        }

        if ($piwik_site && $piwik_uri) {
            $stats = (new Para())
                ->items([
                    (new Link())
                        ->href($piwik_uri)
                        ->text(sprintf(__('View "%s" statistics'), html::escapeHTML($piwik_sites[$piwik_site]['name']))),
                ]);
        } else {
            $stats = (new None());
        }

        echo (new Form('piwik'))
            ->action(App::backend()->getPageURL())
            ->method('post')
            ->fields([
                (new Fieldset('config'))
                    ->legend(new Legend(__('Your Piwik configuration')))
                    ->fields([
                        (new Para())
                            ->items([
                                (new Input('piwik_uri'))
                                    ->value(Html::escapeHTML($piwik_uri))
                                    ->size(40)
                                    ->maxlength(255)
                                    ->label((new Label(__('Your Piwik URL:'), Label::OUTSIDE_LABEL_BEFORE))),
                            ]),
                        (new Para())
                            ->items([
                                (new Input('piwik_token'))
                                    ->value(Html::escapeHTML($piwik_token))
                                    ->size(40)
                                    ->maxlength(255)
                                    ->label((new Label(__('Your Piwik Token:'), Label::OUTSIDE_LABEL_BEFORE))),
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

        if ($piwik_uri !== '') {
            echo (new Form('piwik_create'))
                ->action(App::backend()->getPageURL())
                ->method('post')
                ->fields([
                    (new Fieldset('create'))
                        ->legend(new Legend(__('Create a new Piwik site for this blog')))
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

        Page::helpBlock('piwik');

        Page::closeModule();
    }
}
