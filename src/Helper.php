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
        $tagsmode = $settings->tags_mode;
        $addcats  = $settings->cats;
        $catsmode = $settings->cats_mode;

        if (empty($instance) || empty($token) || $ids === []) {
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
        curl_setopt_array($curl, [
            CURLOPT_URL            => $instance . '/xrpc/com.atproto.server.createSession',
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
                // Second step, post entries
                try {
                    // Get posts information
                    $rs = $blog->getPosts(['post_id' => $ids]);
                    $rs->extend(Post::class);
                    while ($rs->fetch()) {
                        $url = $rs->getURL();

                        $elements = [];
                        // Prefix
                        if (!empty($prefix)) {
                            $elements[] = $prefix;
                        }
                        // Title
                        $elements[] = $rs->post_title;
                        // References (categories, tags)
                        $ref_facets = [];
                        $references = [];
                        if ($addtags || $addcats) {
                            $message = implode(' ', $elements) . ' ';
                            $start   = strlen($message);
                            $refs    = [];
                            if ($addcats) {
                                if ($rs->cat_id) {
                                    // Parents categories
                                    $rscats = App::blog()->getCategoryParents((int) $rs->cat_id, ['cat_title']);
                                    while ($rscats->fetch()) {
                                        $refs[] = self::convertRef($rscats->cat_title, $catsmode);
                                    }
                                    $refs[] = self::convertRef($rs->cat_title, $catsmode);
                                }
                            }
                            if ($addtags) {
                                // Tags
                                $meta = App::meta()->getMetaRecordset($rs->post_meta, 'tag');
                                $meta->sort('meta_id_lower', 'asc');
                                while ($meta->fetch()) {
                                    $refs[] = self::convertRef($meta->meta_id, $tagsmode);
                                }
                            }
                            if (count($refs)) {
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
                        if (count($ref_facets)) {
                            $message .= implode(' ', $references) . "\n";
                        }

                        // Add URL
                        $start = strlen($message);
                        $message .= $url;
                        $link_facet = [
                            'index' => [
                                'byteStart' => $start,
                                'byteEnd'   => $start + strlen((string) $url),
                            ],
                            'features' => [
                                [
                                    'uri'   => $url,
                                    '$type' => 'app.bsky.richtext.facet#link',
                                ],
                            ],
                        ];

                        // Get post lang if any else set to blog default lang
                        $lang = $rs->post_lang;
                        if ((string) $lang === '') {
                            $lang = App::blog()->settings()->system->lang;
                        }

                        $payload = [
                            'repo'       => $session['did'],
                            'collection' => 'app.bsky.feed.post',
                            'record'     => [
                                '$type'     => 'app.bsky.feed.post',
                                'createdAt' => date('c'),
                                'text'      => $message,
                                'langs'     => [$lang],
                                'facets'    => [],
                            ],
                        ];
                        if (count($ref_facets)) {
                            foreach ($ref_facets as $facet) {
                                $payload['record']['facets'][] = $facet;
                            }
                        }
                        $payload['record']['facets'][] = $link_facet;

                        // Try to compose an Entry card
                        $embed = self::fetchEntry($instance, $session, $url);
                        if ($embed !== null) {
                            $payload['record']['embed'] = $embed;
                        }

                        // Post entry
                        $curl = curl_init();
                        curl_setopt_array($curl, [
                            CURLOPT_URL            => $instance . '/xrpc/com.atproto.repo.createRecord',
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

    /**
     * Convert a tag depending on mode
     *
     * @param      string  $reference   The tag
     * @param      int     $mode        The mode
     *
     * @return     string
     */
    private static function convertRef(string $reference, int $mode = My::REFS_MODE_NONE): string
    {
        // Mastodon Hashtags can contain alphanumeric characters and underscores,
        // Replace other (but spaces) with underscores.
        // \pL stands for any character in any language
        $reference = preg_replace('/[^\pL\s\d]/mu', '_', $reference);

        if (strtoupper((string) $reference) === $reference) {
            // Don't touch all uppercased tag
            return $reference;
        }

        return match ($mode) {
            // Remove spaces
            My::REFS_MODE_NOSPACE => str_replace(
                ' ',
                '',
                $reference
            ),
            // Uppercase each words and remove spaces
            My::REFS_MODE_PASCALCASE => str_replace(
                ' ',
                '',
                ucwords(strtolower((string) $reference))
            ),
            // Uppercase each words but the first and remove spaces
            My::REFS_MODE_CAMELCASE => lcfirst(
                str_replace(
                    ' ',
                    '',
                    ucwords(strtolower((string) $reference))
                )
            ),
            My::REFS_MODE_NONE => $reference,
            default            => $reference,
        };
    }

    /**
     * Fetches an entry.
     *
     * @param      string      $instance  The Bluesky instance
     * @param      array       $session   The Current Bluesky session
     * @param      string      $url       The entry URL
     *
     * @return     array|null  The entry card to embed or null on error.
     */
    private static function fetchEntry(string $instance, array $session, string $url): ?array
    {
        // The required fields for every embed card
        $card = [
            'uri'         => $url,
            'title'       => '',
            'description' => '',
        ];

        // Get HTML content
        $curl = curl_init();
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
        curl_close($curl);
        if ($response === false) {
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
        if ($title_tag->length > 0) {
            $card['title'] = $title_tag[0]->nodeValue;
        } else {
            // Title missing: no card
            return null;
        }

        $description_tag = $xpath->query('//meta[@property="og:description"]/@content');
        if ($description_tag->length > 0) {
            $card['description'] = $description_tag[0]->nodeValue;
        } else {
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
        if ($image_tag->length > 0) {
            $img_url = $image_tag[0]->nodeValue;
            $image   = self::uploadMediaToBluesky($instance, $session, $img_url);
            if ($image !== null) {
                $embed['external']['thumb'] = $image;
            }
        }

        return $embed;
    }

    /**
     * Uploads a media to Bluesky and get thumb to use in embed entry.
     *
     * @param      string       $instance  The Bluesky instance
     * @param      array        $session   The current Bluesky session
     * @param      string       $img_src   The image URL
     *
     * @return     null|array  The thumb image to use in embed entry or null on error
     */
    private static function uploadMediaToBluesky(string $instance, array $session, string $img_src): ?array
    {
        // Fetch image and get mime type
        $curl = curl_init();
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
        $mime     = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        curl_close($curl);
        if ($response === false) {
            return null;
        }

        // upload the file
        $curl = curl_init();
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
                'Authorization: Bearer ' . $session['accessJwt'],
            ],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        if ($response === false) {
            return null;
        }

        $blob_resp = json_decode($response, true);
        if (is_null($blob_resp)) {
            return null;
        }

        return $blob_resp['blob'];
    }
}
