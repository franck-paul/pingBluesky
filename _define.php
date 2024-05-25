<?php
/**
 * @brief pingBluesky, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Franck Paul
 *
 * @copyright Franck Paul carnet.franck.paul@gmail.com
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
$this->registerModule(
    'Ping Bluesky',
    'Ping Bluesky',
    'Franck Paul',
    '1.8',
    [
        'requires'    => [['core', '2.28']],
        'type'        => 'plugin',
        'permissions' => 'My',
        'details'     => 'https://open-time.net/docs/plugins/pingBluesky',
        'support'     => 'https://github.com/franck-paul/pingBluesky',
        'repository'  => 'https://raw.githubusercontent.com/franck-paul/pingBluesky/main/dcstore.xml',
    ]
);
