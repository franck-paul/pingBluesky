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

use DOMDocument;
use DOMXPath;
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
    public static function ping(BlogInterface $blog, array $ids, bool $ignore_category = false): string
    {
        $settings = My::settings();
        if (!$settings->active) {
            return '';
        }

        if (!function_exists('curl_version')) {
            return '';
        }

        $instance    = is_string($instance = $settings->instance) ? $instance : '';
        $account     = is_string($account = $settings->account) ? $account : '';
        $token       = is_string($token = $settings->token) ? $token : '';
        $prefix      = is_string($prefix = $settings->prefix) ? $prefix : '';
        $addtags     = is_bool($addtags = $settings->tags) && $addtags;
        $tagsmode    = is_numeric($tagsmode = $settings->tags_mode) ? (int) $tagsmode : My::REFS_MODE_CAMELCASE;
        $addcats     = is_bool($addcats = $settings->cats) && $addcats;
        $catsmode    = is_numeric($catsmode = $settings->cats_mode) ? (int) $catsmode : My::REFS_MODE_CAMELCASE;
        $only_cat    = is_bool($only_cat = $settings->only_cat) && $only_cat;
        $only_cat_id = is_numeric($only_cat_id = $settings->only_cat_id) ? (int) $only_cat_id : 0;

        if ($instance === '' || $account === '' || $token === '' || $ids === []) {
            return '';
        }

        // Prepare instance URI
        if (!parse_url($instance, PHP_URL_HOST)) {
            $instance = 'https://' . ltrim($instance, '/');
        }
        $instance = rtrim($instance, '/');

        // First step, create a new session
        $payload = [
            'identifier' => $account,
            'password'   => $token,
        ];

        $curl = curl_init();
        if ($curl === false) {
            return '';
        }

        $json_payload = json_encode($payload, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES);
        if ($json_payload === false) {
            return '';
        }

        curl_setopt_array($curl, [
            CURLOPT_URL            => $instance . '/xrpc/com.atproto.server.createSession',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $json_payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($curl);
        if ($response === false || !is_string($response)) {
            return '';
        }

        /**
         * Session parameters (see https://docs.bsky.app/docs/api/com-atproto-server-create-session)
         *
         * @var ?array<string, mixed>
         */
        $session = json_decode($response, true);
        if (is_array($session)) {
            // Check mandatory information in response
            $did        = isset($session['did'])       && is_string($did = $session['did']) ? $did : '';
            $access_jwt = isset($session['accessJwt']) && is_string($access_jwt = $session['accessJwt']) ? $access_jwt : '';
            if ($access_jwt === '' || $did === '') {
                return '';
            }

            // Second step, post entries
            try {
                // Get posts information
                $rs = $blog->getPosts(['post_id' => $ids]);
                $rs->extend(Post::class);
                while ($rs->fetch()) {
                    $cat_id = is_numeric($cat_id = $rs->cat_id) ? (int) $cat_id : 0;
                    if ($ignore_category === false && $only_cat && $cat_id !== $only_cat_id) {
                        // We do not ignore category and
                        // the article's category isn't the only one that needs to be taken into account
                        continue;
                    }

                    $elements = [];

                    // Prefix
                    if ($prefix !== '') {
                        $elements[] = $prefix;
                    }

                    // Title
                    $post_title = is_string($post_title = $rs->post_title) ? $post_title : '';
                    $elements[] = $post_title;

                    // References (categories, tags)
                    $ref_facets = [];
                    $references = [];

                    // Categories
                    if ($addtags || $addcats) {
                        $message = implode(' ', $elements) . ' ';
                        $start   = strlen($message);
                        $refs    = [];
                        if ($addcats && $cat_id !== 0) {
                            // Parents categories
                            $rscats = App::blog()->getCategoryParents($cat_id);
                            while ($rscats->fetch()) {
                                $cat_title = is_string($cat_title = $rscats->cat_title) ? $cat_title : '';
                                if ($cat_title !== '') {
                                    $refs[] = self::convertRef($cat_title, $catsmode);
                                }
                            }
                            $cat_title = is_string($cat_title = $rs->cat_title) ? $cat_title : '';
                            if ($cat_title !== '') {
                                $refs[] = self::convertRef($cat_title, $catsmode);
                            }
                        }

                        // Tags
                        if ($addtags) {
                            $post_meta = is_string($post_meta = $rs->post_meta) ? $post_meta : '';
                            if ($post_meta !== '') {
                                $meta = App::meta()->getMetaRecordset($post_meta, 'tag');
                                $meta->sort('meta_id_lower', 'asc');
                                while ($meta->fetch()) {
                                    $meta_id = is_string($meta_id = $meta->meta_id) ? $meta_id : '';
                                    if ($meta_id !== '') {
                                        $refs[] = self::convertRef($meta_id, $tagsmode);
                                    }
                                }
                            }
                        }

                        if ($refs !== []) {
                            $refs = array_unique($refs);
                            foreach ($refs as $ref) {
                                $references[] = '#' . $ref;
                                $ref_facets[] = [
                                    'index' => [
                                        'byteStart' => $start,
                                        'byteEnd'   => $start + 1 + strlen($ref),
                                    ],
                                    'features' => [
                                        [
                                            'tag'   => $ref,
                                            '$type' => 'app.bsky.richtext.facet#tag',
                                        ],
                                    ],
                                ];
                                $start += strlen($ref) + 1 /* # */ + 1 /* space */;
                            }
                        }
                    }

                    // Compose message
                    $message = implode(' ', $elements) . "\n";
                    if ($ref_facets !== []) {
                        $message .= implode(' ', $references) . "\n";
                    }

                    // Add URL
                    $post_url = is_string($post_url = $rs->getURL()) ? $post_url : '';
                    if ($post_url === '') {
                        continue;
                    }

                    $start = strlen($message);
                    $message .= $post_url;
                    $link_facet = [
                        'index' => [
                            'byteStart' => $start,
                            'byteEnd'   => $start + strlen($post_url),
                        ],
                        'features' => [
                            [
                                'uri'   => $post_url,
                                '$type' => 'app.bsky.richtext.facet#link',
                            ],
                        ],
                    ];

                    // Get post lang if any else set to blog default lang
                    $lang = is_string($lang = $rs->post_lang) ? $lang : '';
                    if ($lang === '') {
                        $system_lang = is_string($system_lang = App::blog()->settings()->system->lang) ? $system_lang : 'en';
                        $lang        = $system_lang;
                    }

                    $payload = [
                        'repo'       => $did,
                        'collection' => 'app.bsky.feed.post',
                        'record'     => [
                            '$type'     => 'app.bsky.feed.post',
                            'createdAt' => date('c'),
                            'text'      => $message,
                            'langs'     => [$lang],
                            'facets'    => [],
                        ],
                    ];
                    foreach ($ref_facets as $ref_facet) {
                        $payload['record']['facets'][] = $ref_facet;
                    }
                    $payload['record']['facets'][] = $link_facet;

                    // Try to compose an Entry card
                    $embed = self::fetchEntry($instance, $access_jwt, $post_url);
                    if ($embed !== null) {
                        $payload['record']['embed'] = $embed;
                    }

                    // Post entry
                    $curl = curl_init();
                    if ($curl === false) {
                        return '';
                    }

                    $json_payload = json_encode($payload, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES);
                    if ($json_payload === false) {
                        return '';
                    }

                    curl_setopt_array($curl, [
                        CURLOPT_URL            => $instance . '/xrpc/com.atproto.repo.createRecord',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING       => '',
                        CURLOPT_MAXREDIRS      => 10,
                        CURLOPT_TIMEOUT        => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST  => 'POST',
                        CURLOPT_POSTFIELDS     => $json_payload,
                        CURLOPT_HTTPHEADER     => [
                            'Content-Type: application/json',
                            'Authorization: Bearer ' . $access_jwt,
                        ],
                    ]);
                    $response = curl_exec($curl);
                }
            } catch (Exception) {
            }
        }

        return '';
    }

    /**
     * Convert a tag depending on mode
     *
     * @param      string  $reference   The tag
     * @param      int     $mode        The mode
     */
    private static function convertRef(string $reference, int $mode = My::REFS_MODE_NONE): string
    {
        // Mastodon Hashtags can contain alphanumeric characters and underscores,
        // Replace other (but spaces) with underscores.
        // \pL stands for any character in any language
        $ref = preg_replace('/[^\pL\s\d]/mu', '_', $reference);
        if ($ref === null) {
            return $reference;
        }

        if (strtoupper((string) $ref) === $ref) {
            // Don't touch all uppercased tag
            return $ref;
        }

        return match ($mode) {
            // Remove spaces
            My::REFS_MODE_NOSPACE => str_replace(
                ' ',
                '',
                $ref
            ),
            // Uppercase each words and remove spaces
            My::REFS_MODE_PASCALCASE => str_replace(
                ' ',
                '',
                ucwords(mb_convert_case(strtolower($ref), MB_CASE_TITLE, 'UTF-8'))
            ),
            // Uppercase each words but the first and remove spaces
            My::REFS_MODE_CAMELCASE => lcfirst(
                str_replace(
                    ' ',
                    '',
                    ucwords(mb_convert_case(strtolower($ref), MB_CASE_TITLE, 'UTF-8'))
                )
            ),
            My::REFS_MODE_NONE => $ref,
            default            => $ref,
        };
    }

    /**
     * Fetches an entry.
     *
     * @param      string      $instance        The Bluesky instance
     * @param      string      $access_jwt      The session access token
     * @param      string      $url             The entry URL
     *
     * @return     null|array{'$type': string, external: array{uri: string, title: string, description: string, thumb?: string}}  The entry card to embed or null on error.
     */
    private static function fetchEntry(string $instance, string $access_jwt, string $url): ?array
    {
        if ($url === '' || $access_jwt === '') {
            return null;
        }

        // The required fields for every embed card
        $card = [
            'uri'         => $url,
            'title'       => '',
            'description' => '',
        ];

        // Get HTML content
        $curl = curl_init();
        if ($curl === false) {
            return null;
        }

        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_POST           => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        ]);
        if (App::config()->devMode() === true && App::config()->debugMode() === true) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        }
        $response = curl_exec($curl);
        if ($response === false || !is_string($response)) {
            return null;
        }

        // Create a new DOMDocument
        $doc = new DOMDocument();

        // Suppress errors for invalid HTML, if needed
        libxml_use_internal_errors(true);

        // Load the HTML from the URL
        $doc->loadHTML($response);

        // Restore error handling
        libxml_use_internal_errors(false);

        // Create a new DOMXPath object for querying the document
        $xpath = new DOMXPath($doc);

        // Query for "og:title" and "og:description" meta tags
        $title_tag = $xpath->query('//meta[@property="og:title"]/@content');
        if ($title_tag !== false && $title_tag->length > 0) {
            foreach ($title_tag as $node) {
                $title = $node->nodeValue ?? '';
                if ($title !== '') {
                    // Stop at 1st occurence
                    $card['title'] = $title;

                    break;
                }
            }
        }
        if ($card['title'] === '') {
            // Title missing: no card
            return null;
        }
        $description_tag = $xpath->query('//meta[@property="og:description"]/@content');
        if ($description_tag !== false && $description_tag->length > 0) {
            foreach ($description_tag as $node) {
                $description = $node->nodeValue ?? '';
                if ($description !== '') {
                    // Stop at 1st occurence
                    $card['description'] = $description;

                    break;
                }
            }
        } if ($card['description'] === '') {
            // Description missing: no card
            return null;
        }

        $embed = [
            '$type'    => 'app.bsky.embed.external',
            'external' => [
                'uri'         => $card['uri'],
                'title'       => $card['title'],
                'description' => $card['description'],
            ],
        ];

        // If there is an "og:image" meta tag, fetch and upload that image
        $image_tag = $xpath->query('//meta[@property="og:image"]/@content');
        if ($image_tag !== false && $image_tag->length > 0) {
            foreach ($image_tag as $node) {
                $img_url = $node->nodeValue ?? '';
                if ($img_url !== '') {
                    // Stop at 1st occurence
                    $image = self::uploadMediaToBluesky($instance, $access_jwt, $img_url);
                    if ($image !== null) {
                        $embed['external']['thumb'] = $image;
                    }

                    break;
                }
            }
        }

        return $embed;
    }

    /**
     * Uploads a media to Bluesky and get thumb to use in embed entry.
     *
     * @param      string       $instance       The Bluesky instance
     * @param      string       $access_jwt     The session access token
     * @param      string       $img_src        The image URL
     *
     * @return     null|string  The thumb image to use in embed entry or null on error
     */
    private static function uploadMediaToBluesky(string $instance, string $access_jwt, string $img_src): ?string
    {
        if ($img_src === '' || $access_jwt === '') {
            return null;
        }

        // Fetch image and get mime type
        $curl = curl_init();
        if ($curl === false) {
            return null;
        }

        curl_setopt_array($curl, [
            CURLOPT_URL            => $img_src,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_POST           => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        ]);
        if (App::config()->devMode() === true && App::config()->debugMode() === true) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        }
        $response = curl_exec($curl);
        if ($response === false || !is_string($response)) {
            return null;
        }
        $mime = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);

        // upload the file
        $curl = curl_init();
        if ($curl === false) {
            return null;
        }

        curl_setopt_array($curl, [
            CURLOPT_URL            => $instance . '/xrpc/com.atproto.repo.uploadBlob',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $response,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: ' . $mime,
                'Authorization: Bearer ' . $access_jwt,
            ],
        ]);
        $response = curl_exec($curl);
        if ($response === false || !is_string($response)) {
            return null;
        }

        $blob_resp = json_decode($response, true);
        if (is_array($blob_resp) && isset($blob_resp['blob']) && is_string($blob_resp['blob'])) {
            return $blob_resp['blob'];
        }

        return null;
    }
}
