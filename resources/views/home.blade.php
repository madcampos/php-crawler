<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Crawler</title>
    </head>
    <body>
        <form method="post" action="/api/crawl">
            <label for="url">URL to crawl:</label>
            <input type="url" name="url" placeholder="Enter URL">

            <label for="max_pages">Max pages to crawl:</label>
            <input type="number" name="max_pages" placeholder="Maximum number of pages to crawl" value="6">

            <button type="submit">Crawl</button>
        </form>
    </body>
</html>
