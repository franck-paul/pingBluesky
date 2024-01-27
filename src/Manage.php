<?php
/**
 * @brief pingBluesky, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Franck Paul and contributors
 *
 * @copyright Franck Paul carnet.franck.paul@gmail.com
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

namespace Dotclear\Plugin\pingBluesky;

use Dotclear\App;
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\Core\Process;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Input;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Html;
use Exception;

class Manage extends Process
{
    /**
     * Initializes the page.
     */
    public static function init(): bool
    {
        return self::status(My::checkContext(My::MANAGE));
    }

    /**
     * Processes the request(s).
     */
    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        if ($_POST !== []) {
            try {
                $settings = My::settings();

                $settings->put('active', !empty($_POST['pb_active']));

                $settings->put('instance', trim(Html::escapeHTML($_POST['pb_instance'])));
                $settings->put('account', trim(Html::escapeHTML($_POST['pb_account'])));
                $settings->put('token', trim(Html::escapeHTML($_POST['pb_token'])));
                $settings->put('prefix', trim(Html::escapeHTML($_POST['pb_prefix'])));
                $settings->put('tags', !empty($_POST['pb_tags']));

                App::blog()->triggerBlog();

                Notices::addSuccessNotice(__('Settings have been successfully updated.'));
                My::redirect();
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
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

        Page::openModule(My::name());

        echo Page::breadcrumb(
            [
                Html::escapeHTML(App::blog()->name()) => '',
                __('Ping Bluesky')                    => '',
            ]
        );
        echo Notices::getNotices();

        // Form
        echo
        (new Form('ping_bluesky_params'))
            ->action(App::backend()->getPageURL())
            ->method('post')
            ->fields([
                (new Para())->items([
                    (new Checkbox('pb_active', (bool) $settings->active))
                        ->value(1)
                        ->label((new Label(__('Activate pingBluesky plugin'), Label::INSIDE_TEXT_AFTER))),
                    (new Note())
                        ->class('form-note')
                        ->text(sprintf(
                            __('The mandatory cURL library is <strong>%s</strong>.'),
                            function_exists('curl_version') ? __('installed and enabled') : __('missing or disabled')
                        )),
                ]),
                (new Para())->items([
                    (new Input('pb_instance'))
                        ->size(48)
                        ->maxlength(128)
                        ->value(Html::escapeHTML((string) $settings->instance))
                        ->required(true)
                        ->label((new Label(
                            (new Text('abbr', '*'))->title(__('Required field'))->render() . __('Bluesky instance:'),
                            Label::OUTSIDE_TEXT_BEFORE
                        ))->id('a11yc_label_label')->class('required')->title(__('Required field'))),
                ]),
                (new Para())->items([
                    (new Input('pb_account'))
                        ->size(48)
                        ->maxlength(128)
                        ->value(Html::escapeHTML((string) $settings->account))
                        ->required(true)
                        ->label((new Label(
                            (new Text('abbr', '*'))->title(__('Required field'))->render() . __('Account handle:'),
                            Label::OUTSIDE_TEXT_BEFORE
                        ))->id('a11yc_label_label')->class('required')->title(__('Required field'))),
                ]),
                (new Para())->items([
                    (new Input('pb_token'))
                        ->size(64)
                        ->maxlength(128)
                        ->value(Html::escapeHTML((string) $settings->token))
                        ->required(true)
                        ->label((new Label(
                            (new Text('abbr', '*'))->title(__('Required field'))->render() . __('Application token:'),
                            Label::OUTSIDE_TEXT_BEFORE
                        ))->id('a11yc_label_label')->class('required')->title(__('Required field'))),
                ]),
                (new Para())->items([
                    (new Input('pb_prefix'))
                        ->size(30)
                        ->maxlength(128)
                        ->value(Html::escapeHTML((string) $settings->prefix))
                        ->required(true)
                        ->label((new Label(__('Status prefix:'), Label::OUTSIDE_TEXT_BEFORE))),
                ]),
                (new Para())->items([
                    (new Checkbox('pb_tags', (bool) $settings->tags))
                        ->value(1)
                        ->label((new Label(__('Include tags'), Label::INSIDE_TEXT_AFTER))),
                    (new Note())
                        ->class('form-note')
                        ->text(__('The tags, inserted as hashtags are currently not recognized on Bluesky.')),
                ]),
                // Submit
                (new Para())->items([
                    (new Submit(['frmsubmit']))
                        ->value(__('Save')),
                    ... My::hiddenFields(),
                ]),
            ])
        ->render();

        Page::closeModule();
    }
}
