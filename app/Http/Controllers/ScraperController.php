<?php

namespace App\Http\Controllers;

use Error;
use Illuminate\Http\Request;
use voku\helper\HtmlDomParser;
use voku\helper\SimpleHtmlDomInterface;

class ScraperController extends Controller
{
    private function countWordsOnPage(HtmlDomParser $dom) {
        $nodesInnerText = $dom->findMulti('body > *:not(script)')->innertext();

        $wordCount = array_reduce($nodesInnerText, function (int $wordCount, string $innerText) {
            $innerText = preg_replace('/\s+/', ' ', $innerText);
            $innerText = strtolower($innerText);
            $innerText = trim($innerText);
            $innerText = explode(' ', $innerText);

            return $wordCount + count($innerText);
        }, 0);

        return $wordCount;
    }

    private function crawlPage(string $url) {
        $startLoad = hrtime(true);
        $contents = file_get_contents($url);
        $endLoad = hrtime(true);

        if ($contents === false) {
            throw new Error('Failed to download file');
        }

        $loadTimeInMs = ($endLoad - $startLoad) / 1e+6;

        $dom = HtmlDomParser::str_get_html($contents);

        $imageElements = $dom->findMulti('img');
        $uniqueImages = array_unique(array_map(fn (SimpleHtmlDomInterface $img) => $img->getAttribute('src'), iterator_to_array($imageElements)));
        $images = array_values(array_map(function (string $src) use ($url) {
            $isExternal = str_starts_with($src, 'http') && !str_starts_with($src, $url);

            return [
                'src' => $src,
                'isExternal' => $isExternal
            ];
        }, $uniqueImages));

        $linkElements = $dom->findMulti('a');
        $uniqueLinks = array_unique(array_map(fn (SimpleHtmlDomInterface $link) => $link->getAttribute('href'), iterator_to_array($linkElements)));
        $links = array_values(array_map(function (string $href) use ($url) {
            $isExternal = str_starts_with($href, 'http') && !str_starts_with($href, $url);

            return [
                'href' => $href,
                'isExternal' => $isExternal
            ];
        }, $uniqueLinks));

        $title = $dom->findOne('title');

        return [
            'loadTimeInMs' => $loadTimeInMs,
            'wordCount' => $this->countWordsOnPage($dom),
            'title' => $title->text(),
            'images' => $images,
            'links' => $links
        ];
    }

    /**
     * Crawl a given URL
     */
    public function crawl(Request $request) {
        $url = (string)$request->input('url', '');

        try {
            if (empty($url)) {
                throw new Error('URL is required');
            }

            if (!str_starts_with($url, 'http')) {
                throw new Error('URL must start with http');
            }

            $crawledPage = $this->crawlPage($url);

            return response()->json([
                'url' => $url,
                'loadTimeInMs' => $crawledPage['loadTimeInMs'],
                'wordCount' => $crawledPage['wordCount'],
                'title' => $crawledPage['title'],
                'images' => $crawledPage['images'],
                'links' => $crawledPage['links']
            ]);
        } catch (Error $err) {
            return response()->json([
                'error' => $err->getMessage()
            ], 400);
        }
    }

    /**
     * Crawl a given URL recursively.
     * It will crawl the URL and then go to each link on the page and crawl those.
     */
    public function crawlRecursive(Request $request) {
        $url = (string)$request->input('url', '');
        $maxPages = (int)$request->input('max_pages', 1);

        try {
            if (empty($url)) {
                throw new Error('URL is required');
            }

            if (!str_starts_with($url, 'http')) {
                throw new Error('URL must start with http');
            }

            if ($maxPages < 1) {
                throw new Error('Max pages must be greater than 0');
            }

            $crawledRootPage = $this->crawlPage($url);

            $crawledPages = [
                [
                    'url' => $url,
                    'loadTimeInMs' => $crawledRootPage['loadTimeInMs'],
                    'wordCount' => $crawledRootPage['wordCount'],
                    'title' => $crawledRootPage['title'],
                    'images' => $crawledRootPage['images'],
                    'links' => $crawledRootPage['links']
                ]
            ];

            $pagesCrawled = 1;

            $crawledPages = array_reduce($crawledPages, function (array $crawledPages, array $currentCrawledPage) use ($maxPages, &$pagesCrawled) {
                $links = $currentCrawledPage['links'];

                foreach ($links as $link) {
                    if ($pagesCrawled >= $maxPages) {
                        break;
                    }

                    if ($link['isExternal']) {
                        continue;
                    }

                    if (str_starts_with($link['href'], '#')) {
                        continue;
                    }

                    if ($link['href'] === '/') {
                        continue;
                    }

                    $href = $link['href'];

                    if (!str_starts_with($href, 'http')) {
                        $urlParts = [$currentCrawledPage['url'], trim($href, '/')];
                        $joinedUrl = join('/', $urlParts);
                        $normalizedUrl = preg_replace('#(?<!:)/+#','/', $joinedUrl);

                        $href = $normalizedUrl;
                    }

                    if (array_key_exists($href, $crawledPages)) {
                        continue;
                    }

                    $pagesCrawled++;

                    $newCrawledPage = $this->crawlPage($href);
                    array_push($crawledPages, [
                        'url' => $href,
                        'loadTimeInMs' => $newCrawledPage['loadTimeInMs'],
                        'wordCount' => $newCrawledPage['wordCount'],
                        'title' => $newCrawledPage['title'],
                        'images' => $newCrawledPage['images'],
                        'links' => $newCrawledPage['links']
                    ]);
                }

                return $crawledPages;
            }, $crawledPages);

            return response()->json([
                'url' => $url,
                'pagesCrawled' => $pagesCrawled,
                'averages' => [
                    'loadTimeInMs' => ceil(array_reduce($crawledPages, fn (int $total, array $page) => $total + $page['loadTimeInMs'], 0) / $pagesCrawled),
                    'wordCount' => ceil(array_reduce($crawledPages, fn (int $total, array $page) => $total + $page['wordCount'], 0) / $pagesCrawled),
                    'titleLength' => ceil(array_reduce($crawledPages, fn (int $total, array $page) => $total + strlen($page['title']), 0) / $pagesCrawled),
                    'images' => ceil(array_reduce($crawledPages, fn (int $total, array $page) => $total + count($page['images']), 0) / $pagesCrawled),
                    'links' => ceil(array_reduce($crawledPages, fn (int $total, array $page) => $total + count($page['links']), 0) / $pagesCrawled)
                ],
                'pages' => $crawledPages
            ]);
        } catch (Error $err) {
            return response()->json([
                'error' => $err->getMessage()
            ], 400);
        }
    }
}
