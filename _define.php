<?php

/**
 * @brief pingBluesky, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Franck Paul
 *
 * @copyright Franck Paul contact@open-time.net
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
$this->registerModule(
    'Ping Bluesky',
    'Ping Bluesky',
    'Franck Paul',
    '4.0.2',
    [
        'date'        => '2026-08-05T10:13:55+0200',
        'requires'    => [['core', '2.39']],
        'type'        => 'plugin',
        'permissions' => 'My',
        'details'     => 'https://open-time.net/docs/plugins/pingBluesky',
        'support'     => 'https://github.com/franck-paul/pingBluesky',
        'repository'  => 'https://raw.githubusercontent.com/franck-paul/pingBluesky/main/dcstore.xml',
        'license'     => 'gpl2',
    ]
);
