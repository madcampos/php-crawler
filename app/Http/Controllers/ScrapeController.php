<?php

namespace App\Http\Controllers;

use Error;
use Illuminate\Http\Request;
use voku\helper\HtmlDomParser;
use voku\helper\SimpleHtmlDomInterface;

class ScrapeController extends Controller
{
    /**
     * Scrape a given URL
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
                'crawledPage' => $crawledPage
            ]);
        } catch (Error $err) {
            return response()->json([
                'error' => $err->getMessage()
            ], 400);
        }
    }

    private function crawlPage(string $url) {
        $startLoad = hrtime(true);
        $contents = file_get_contents($url);
        $endLoad = hrtime(true);

        if ($contents === false) {
            throw new Error('Failed to download file');
        }

        $loadTime = $endLoad - $startLoad;

        $dom = HtmlDomParser::str_get_html($contents);

        $imageElements = $dom->findMulti('img');
        $uniqueImages = array_unique(array_map(fn (SimpleHtmlDomInterface $img) => $img->getAttribute('src'), iterator_to_array($imageElements)));
        $images = array_map(function (string $src) use ($url) {
            $isExternal = str_starts_with($src, 'http') && !str_starts_with($src, $url);

            return [
                'src' => $src,
                'isExternal' => $isExternal
            ];
        }, $uniqueImages);

        $linkElements = $dom->findMulti('a');
        $uniqueLinks = array_unique(array_map(fn (SimpleHtmlDomInterface $link) => $link->getAttribute('href'), iterator_to_array($linkElements)));
        $links = array_map(function (string $href) use ($url) {
            $isExternal = str_starts_with($href, 'http') && !str_starts_with($href, $url);

            return [
                'href' => $href,
                'isExternal' => $isExternal
            ];
        }, $uniqueLinks);

        $title = $dom->findOne('title');

        return [
            'loadTime' => $loadTime,
            'images' => $images,
            'links' => $links,
            'title' => $title->text()
        ];
    }
}
