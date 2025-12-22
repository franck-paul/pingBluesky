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
use Dotclear\Helper\Process\TraitProcess;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Fieldset;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Input;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Legend;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Radio;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Html;
use Exception;

class Manage
{
    use TraitProcess;

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
                $settings->put('tags_mode', (int) $_POST['pb_tags_mode'], App::blogWorkspace()::NS_INT);
                $settings->put('cats', !empty($_POST['pb_cats']));
                $settings->put('cats_mode', (int) $_POST['pb_cats_mode'], App::blogWorkspace()::NS_INT);
                $settings->put('auto_ping', !empty($_POST['pb_auto_ping']), App::blogWorkspace()::NS_BOOL);

                App::blog()->triggerBlog();

                App::backend()->notices()->addSuccessNotice(__('Settings have been successfully updated.'));
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

        App::backend()->page()->openModule(My::name());

        echo App::backend()->page()->breadcrumb(
            [
                Html::escapeHTML(App::blog()->name()) => '',
                __('Ping Bluesky')                    => '',
            ]
        );
        echo App::backend()->notices()->getNotices();

        // Form
        $references_mode_options_tags = [
            My::REFS_MODE_NONE       => __('No conversion'),
            My::REFS_MODE_NOSPACE    => __('Spaces will be removed'),
            My::REFS_MODE_CAMELCASE  => __('Spaces will be removed and tag will then be convert to <samp>camelCase</samp>'),
            My::REFS_MODE_PASCALCASE => __('Spaces will be removed and tag will then be convert to <samp>PascalCase</samp>'),
        ];
        $references_mode_options_cats = [
            My::REFS_MODE_NONE       => __('No conversion'),
            My::REFS_MODE_NOSPACE    => __('Spaces will be removed'),
            My::REFS_MODE_CAMELCASE  => __('Spaces will be removed and category name will then be convert to <samp>camelCase</samp>'),
            My::REFS_MODE_PASCALCASE => __('Spaces will be removed and category name will then be convert to <samp>PascalCase</samp>'),
        ];
        $tagsmodes = [];
        $catsmodes = [];
        $tags_mode = $settings->tags_mode ?? My::REFS_MODE_CAMELCASE;
        $cats_mode = $settings->cats_mode ?? My::REFS_MODE_CAMELCASE;

        $i = 0;
        foreach ($references_mode_options_tags as $k => $v) {
            $tagsmodes[] = (new Radio(['pb_tags_mode', 'pb_tags_mode-' . $i], $tags_mode == $k))
                    ->value($k)
                    ->label((new Label($v, Label::INSIDE_TEXT_AFTER)));
            ++$i;
        }
        $i = 0;
        foreach ($references_mode_options_cats as $k => $v) {
            $catsmodes[] = (new Radio(['pb_cats_mode', 'pb_cats_mode-' . $i], $cats_mode == $k))
                    ->value($k)
                    ->label((new Label($v, Label::INSIDE_TEXT_AFTER)));
            ++$i;
        }

        $auto_ping = $settings->auto_ping ?? true;

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
                        ->label((new Label(__('Status prefix:'), Label::OUTSIDE_TEXT_BEFORE))),
                ]),
                (new Para())->items([
                    (new Checkbox('pb_auto_ping', $auto_ping))
                        ->value(1)
                        ->label((new Label(__('Automatically ping when an entry is first published'), Label::INSIDE_TEXT_AFTER))),
                ]),
                (new Fieldset())
                ->legend(new Legend(__('Tags')))
                ->fields([
                    (new Para())->items([
                        (new Checkbox('pb_tags', (bool) $settings->tags))
                            ->value(1)
                            ->label((new Label(__('Include tags'), Label::INSIDE_TEXT_AFTER))),
                    ]),
                    (new Note())
                        ->class('form-note')
                        ->text(__('The tags, inserted as hashtags are currently not recognized on Bluesky.')),
                    (new Para())->class('pretty-title')->items([
                        (new Text(null, __('Tags conversion mode:'))),
                    ]),
                    ...$tagsmodes,
                ]),
                (new Fieldset())
                ->legend(new Legend(__('Categories')))
                ->fields([
                    (new Para())->items([
                        (new Checkbox('pb_cats', (bool) $settings->cats))
                            ->value(1)
                            ->label((new Label(__('Include categories'), Label::INSIDE_TEXT_AFTER))),
                    ]),
                    (new Note())
                        ->class('form-note')
                        ->text(__('Will include category\'s parents')),
                    (new Para())->class('pretty-title')->items([
                        (new Text(null, __('Categories conversion mode:'))),
                    ]),
                    ...$catsmodes,
                ]),
                // Submit
                (new Para())->items([
                    (new Submit(['frmsubmit']))
                        ->value(__('Save')),
                    ... My::hiddenFields(),
                ]),
            ])
        ->render();

        App::backend()->page()->closeModule();
    }
}
