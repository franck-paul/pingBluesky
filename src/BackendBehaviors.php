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

use arrayObject;
use Dotclear\App;
use Dotclear\Core\Backend\Action\ActionsPosts;
use Dotclear\Core\Backend\Notices;
use Dotclear\Plugin\pages\BackendActions as PagesBackendActions;

class BackendBehaviors
{
    public static function adminPostsActions(ActionsPosts $ap): string
    {
        // Add menuitem in actions dropdown list
        if (App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            $ap->addAction(
                [__('Bluesky') => [__('Ping Bluesky') => 'pingBluesky']],
                self::adminPingBluesky(...)
            );
        }

        return '';
    }

    public static function adminPagesActions(PagesBackendActions $ap): string
    {
        // Add menuitem in actions dropdown list
        if (App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            $ap->addAction(
                [__('Bluesky') => [__('Ping Bluesky') => 'pingBluesky']],
                self::adminPingBluesky(...)
            );
        }

        return '';
    }

    /**
     * @param      ActionsPosts|PagesBackendActions     $ap     Actions
     * @param      ArrayObject<string, mixed>           $post   The post
     */
    public static function adminPingBluesky(ActionsPosts|PagesBackendActions $ap, arrayObject $post): void
    {
        $rs = $ap->getRS();
        if ($rs->rows()) {
            $ids = [];
            while ($rs->fetch()) {
                if ((int) $rs->post_status === App::blog()::POST_PUBLISHED) {
                    // Ping only published entry
                    $ids[] = $rs->post_id;
                }
            }

            if ($ids !== []) {
                Helper::ping(App::blog(), $ids);
                Notices::addSuccessNotice(__('All entries have been ping to Bluesky.'));
            }

            $ap->redirect(true);
        } else {
            $ap->redirect();
        }
    }
}
