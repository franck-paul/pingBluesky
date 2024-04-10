<?php
/**
 * @brief pingBluesky, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Jean-Christian Denis, Franck Paul and contributors
 *
 * @copyright Jean-Christian Denis, Franck Paul
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

namespace Dotclear\Plugin\pingBluesky;

use Dotclear\Module\MyPlugin;

/**
 * Plugin definitions
 */
class My extends MyPlugin
{
    // Tag conversion modes

    /**
     * No conversion
     *
     * @var        int
     */
    public const TAGS_MODE_NONE = 0;

    /**
     * Spaces removed
     *
     * @var        int
     */
    public const TAGS_MODE_NOSPACE = 1;

    /**
     * Spaces removed and converted to camelCase
     *
     * @var        int
     */
    public const TAGS_MODE_CAMELCASE = 2;

    /**
     * Spaces removed and converted to PascalCase
     *
     * @var        int
     */
    public const TAGS_MODE_PASCALCASE = 3;
}
