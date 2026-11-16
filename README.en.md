# article-extractor

[![ci](https://github.com/MiguelSanzBr/article-extractor/actions/workflows/ci.yml/badge.svg)](https://github.com/MiguelSanzBr/article-extractor/actions/workflows/ci.yml)
![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4)
![coverage 100%](https://img.shields.io/badge/coverage-100%25-brightgreen)
![mutation 97%](https://img.shields.io/badge/mutation-97%25-brightgreen)
[![MIT license](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

A PHP 8.4 library that takes the HTML of a news page and returns the article: title, standfirst, authors, dates, image, tags and the body as blocks, with no per-site selectors. It also finds and reads a site's feeds (RSS, Atom, sitemap, news sitemap) and its `robots.txt`. No dependencies beyond `ext-dom` and `ext-mbstring`.

[Versão em português](README.md)

**The problem.** News sites have no API, and each one builds its HTML differently: WordPress, Arc XP, Next.js, a custom theme, a page full of teasers and ads. Keeping a selector per site doesn't scale to hundreds of sites, and it breaks on every redesign. This came out of a real problem like that; this is the open version, rewritten from scratch, with generic rules that work on sites the library has never seen.

**What it guarantees** (each item has a test):

- **It tells you where the body came from.** The result carries `strategy`, the layer that found the text, so extraction is explainable and debuggable.
- **A teaser is not an article.** A body needs 300+ characters of text: a one-paragraph teaser in an `<article>`, a home-page card or a paywall page come back without a body (`hasBody()` is false), with the title, and no exception.
- **One broken source doesn't wipe the others.** A `headline` given as a list or a numeric date in JSON-LD only nulls that field; the rest still comes from the other sources.
- **No step breaks another.** JSON-LD and embedded app state (Next.js, Arc XP) are read before any cleaning, and all cleaning happens on a copy: the page document never changes.
- **Safe output HTML.** `html()` is rebuilt from the blocks with every piece of text escaped, whether the body came from the DOM, JSON-LD or app state.
- **Dates in UTC.** A date without a timezone is read in the timezone you pass; "yesterday" and 31/02 become `null`, never a wrong date.
- **Feed namespaces don't matter.** Atom with `xmlns:media`, Yoast sitemaps and Google News sitemaps all work; items come newest first, with absolute URLs.
- **Broken JSON and dirty feeds still get read.** JSON-LD with raw newlines inside strings, wrapped in CDATA or comments, and feeds with a bare `&`, `&nbsp;` or invalid bytes are repaired and read; one odd field doesn't take the whole page down.
- **Only http(s) URLs come out.** Images, canonicals and feed items with `file:`, `data:`, `javascript:` or any other scheme become `null`.
- **Linear, even on malformed pages.** 3 MB, 40k loose paragraphs or 8k levels of unclosed `<div>` each go through in under 2 s (tested in CI): no step walks the page once per node.

## Install

```bash
composer require miguelsanzbr/article-extractor
```

## Usage

```php
use ArticleExtractor\Extractor;

$article = Extractor::create(new DateTimeZone('America/Sao_Paulo'))->extract($html, $url);

$article->metadata->title;   // "Prefeitura amplia horário das bibliotecas"
$article->strategy;          // "priority-selector"
$article->text();            // body as text, paragraphs separated by a blank line
$article->toArray();         // everything, ready for json_encode
```

The timezone is what the library assumes for dates the page publishes without one; the default is UTC. Pages without `<meta charset>` take the encoding as a third argument: `extract($html, $url, 'windows-1252')`.

## From the command line

```bash
curl -s https://example.com/news | vendor/bin/article-extract https://example.com/news
```

It prints the same array as `toArray()`, as JSON. Exit code 64 means a missing or invalid URL, 65 means empty stdin.

## How it decides

**Body:** six layers, in order. The first one that finds a body above the 300-character floor wins, after dropping the block that repeats the title and the inline "related" widget.

| # | `strategy` | Where it looks |
|---|---|---|
| 1 | `priority-selector` | classes and attributes common CMSs use for the body (`[itemprop=articleBody]`, `.entry-content`, `.article-body`…); keeps the candidate with the most text |
| 2 | `semantic-container` | `article`, `main`, `[role=main]`, discounted for teaser lists and index pages |
| 3 | `json-ld` | JSON-LD `articleBody` |
| 4 | `embedded-app` | JS app state: Arc XP `content_elements` and the article in `__NEXT_DATA__` |
| 5 | `density` | each paragraph scores its parent and half its grandparent; link-heavy containers are skipped |
| 6 | `paragraph-fallback` | every `<p>` on the page outside noise |

Noise (menus, footers, share bars, related links, comments, cookie banners, paywalls…) is matched on whole class or id names or on a prefix: `sidebar__widget` is noise, `has-sidebar` is not. Boilerplate like "read more" only drops short blocks, so an article about the advertising market keeps its body. Besides paragraphs and section headings, lists, quotes and captions are extracted.

**Metadata:** five sources, field by field, first value wins: meta tags (Open Graph and `article:*`), JSON-LD, Twitter Card, app state (Arc XP, Next.js) and finally the visible HTML (`h1`, `<title>`, `time` inside the article, breadcrumbs).

## Discovery

```php
use ArticleExtractor\Discovery\{ArticleUrlFilter, FeedLinkFinder, FeedLocations, FeedParser, RobotsTxt};

$feeds = FeedLinkFinder::find($homeHtml, $homeUrl);            // feeds the home page announces, minus comment feeds
$feeds = $feeds ?: FeedLocations::candidates($homeUrl);        // otherwise, common feed and sitemap paths
$doc = FeedParser::parse($xml, $feedUrl);                      // items newest first; for an index, $doc->sitemaps
$rules = RobotsTxt::parse($robotsTxt, 'my-bot/1.0');           // RFC 9309: exact product-name group, isAllowed(), crawlDelay, sitemaps
ArticleUrlFilter::accepts($url);                               // drops tag, author, pagination, search and file URLs
```

The library makes no HTTP requests: you (or the API built on it) fetch the page and the feed. That keeps it testable without a network and without dependencies.

## What it doesn't do

- It doesn't run JavaScript. Pages that build the text in the browser work when the state is embedded (Next.js, Arc XP); otherwise they don't.
- It doesn't download anything (see above).
- It has no per-site selectors, on purpose.

## Tests

```bash
composer install
composer test        # Pest
composer types       # PHPStan level max, no baseline
composer lint:check  # Pint
docker compose run --rm php composer coverage   # 100% minimum coverage
docker compose run --rm php composer mutate     # pest --mutate, 97% minimum
```

Coverage and mutation run in the container because they need pcov. The same four gates run in CI. The test pages and feeds in `tests/Fixtures` are synthetic and hand-written: no real article is stored in the repository.

Why each decision was made (in Portuguese): [docs/decisions.md](docs/decisions.md).

## License

MIT
