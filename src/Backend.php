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
use Dotclear\Core\Process;

class Backend extends Process
{
    public static function init(): bool
    {
        // dead but useful code, in order to have translations
        __('pingBluesky');
        __('Ping Bluesky');

        return self::status(My::checkContext(My::BACKEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        My::addBackendMenuItem(App::backend()->menus()::MENU_BLOG);

        $settings = My::settings();
        // Add posts/pages action
        if ($settings->active && App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            App::behavior()->addBehaviors([
                /* Add behavior callbacks for posts actions */
                'adminPostsActions' => BackendBehaviors::adminPostsActions(...),
                'adminPagesActions' => BackendBehaviors::adminPagesActions(...),
            ]);
        }

        return true;
    }
}
