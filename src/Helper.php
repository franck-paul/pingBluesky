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
use Dotclear\Interface\Core\BlogInterface;
use Dotclear\Schema\Extension\Post;
use Exception;

class Helper
{
    /**
     * Ping Bluesky server
     *
     * @param      BlogInterface        $blog   The blog
     * @param      array<int>           $ids    The identifiers
     */
    public static function ping(BlogInterface $blog, array $ids): string
    {
        $settings = My::settings();
        if (!$settings->active) {
            return '';
        }

        if (!function_exists('curl_version')) {
            return '';
        }

        $instance = $settings->instance;
        $account  = $settings->account;
        $token    = $settings->token;
        $prefix   = $settings->prefix;
        $addtags  = $settings->tags;

        if (empty($instance) || empty($token) || $ids === []) {
            return '';
        }

        // Prepare instance URI
        if (!parse_url($instance, PHP_URL_HOST)) {
            $instance = 'https://' . $instance;
        }

        // First step, create a new session
        $payload = [
            'identifier' => $account,
            'password'   => $token,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => rtrim($instance, '/') . '/xrpc/com.atproto.server.createSession',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        if ($response !== false) {
            $session = json_decode($response, true);
            if (!is_null($session)) {
                $uri = rtrim($instance, '/') . '/xrpc/com.atproto.repo.createRecord';

                // Second step, post entries
                try {
                    // Get posts information
                    $rs = $blog->getPosts(['post_id' => $ids]);
                    $rs->extend(Post::class);
                    while ($rs->fetch()) {
                        $elements = [];
                        // Prefix
                        if (!empty($prefix)) {
                            $elements[] = $prefix;
                        }
                        // Title
                        $elements[] = $rs->post_title;
                        // Tags
                        if ($addtags) {
                            $tags = [];
                            $meta = App::meta()->getMetaRecordset($rs->post_meta, 'tag');
                            $meta->sort('meta_id_lower', 'asc');
                            while ($meta->fetch()) {
                                $tags[] = '#' . $meta->meta_id;
                            }
                            $elements[] = implode(' ', $tags);
                        }
                        // URL
                        $elements[] = $rs->getURL();

                        // Compose message
                        $message = implode(' ', $elements);

                        $payload = [
                            'repo'       => $session['did'],
                            'collection' => 'app.bsky.feed.post',
                            'record'     => [
                                '$type'     => 'app.bsky.feed.post',
                                'createdAt' => date('c'),
                                'text'      => $message,
                            ],
                        ];

                        $curl = curl_init();
                        curl_setopt_array($curl, [
                            CURLOPT_URL            => $uri,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING       => '',
                            CURLOPT_MAXREDIRS      => 10,
                            CURLOPT_TIMEOUT        => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST  => 'POST',
                            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES),
                            CURLOPT_HTTPHEADER     => [
                                'Content-Type: application/json',
                                'Authorization: Bearer ' . $session['accessJwt'],
                            ],
                        ]);
                        $response = curl_exec($curl);
                        curl_close($curl);
                    }
                } catch (Exception) {
                }
            }
        }

        return '';
    }
}
