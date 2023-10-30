<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Crawler</title>
        <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    </head>
    <body>
        <main>
            <h1>Page crawler</h1>

            <form method="post" action="/api/crawl-recursive">
                <fieldset>
                    <legend>Page to crawl</legend>

                    <label for="url">URL:</label>
                    <input
                        type="url"
                        name="url"
                        placeholder="Enter URL"
                        required
                    >

                    <label for="max_pages">Max pages:</label>
                    <input
                        type="number"
                        name="max_pages"
                        placeholder="Maximum number of pages to crawl"
                        min="1"
                        max="100"
                        value="6"
                    >

                    <button type="submit">Crawl</button>
                </fieldset>
            </form>

            <article id="results" class="loading" hidden>
                <h2>Results</h2>
                <progress></progress>

                <div id="results-error">
                    <h3>Error</h3>
                    <p></p>
                </div>

                <div id="results-data">
                    <h3>Averages</h3>
                    <table id="averages-results">
                        <thead>
                            <tr>
                                <th>Title Length</th>
                                <th>Word Count</th>
                                <th>Load time</th>
                                <th>Images</th>
                                <th>Links</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>

                    <h3>Pages</h3>
                    <table id="page-results">
                        <thead>
                            <tr>
                                <th>URL</th>
                                <th>Page title</th>
                                <th>Word Count</th>
                                <th>Load time</th>
                                <th>Images</th>
                                <th>Links</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </article>
        </main>
        <script src="{{ asset('js/app.js') }}" type="module"></script>
    </body>
</html>
