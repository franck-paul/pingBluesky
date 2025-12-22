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

class Prepend
{
    use TraitProcess;

    public static function init(): bool
    {
        // Curl lib is mandatory for backend operations
        return self::status(My::checkContext(My::PREPEND) && function_exists('curl_init'));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        $settings  = My::settings();
        $auto_ping = $settings->auto_ping ?? true;

        if ($auto_ping) {
            // pingBluesky behavior
            App::behavior()->addBehavior('coreFirstPublicationEntries', Helper::ping(...));
        }

        return true;
    }
}
