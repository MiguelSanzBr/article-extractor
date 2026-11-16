<?php

declare(strict_types=1);

use ArticleExtractor\Discovery\FeedLink;
use ArticleExtractor\Discovery\FeedLocations;
use ArticleExtractor\Discovery\FeedType;

it('lista os caminhos comuns de feed e sitemap na origem da home', function () {
    $candidates = FeedLocations::candidates('https://www.a.com:8443/secao/pagina?x=1');

    expect($candidates)->toHaveCount(11)
        ->and($candidates[0])->toEqual(new FeedLink('https://www.a.com:8443/feed', FeedType::Rss))
        ->and($candidates[4])->toEqual(new FeedLink('https://www.a.com:8443/atom.xml', FeedType::Atom))
        ->and($candidates[6])->toEqual(new FeedLink('https://www.a.com:8443/feeds/posts/default', FeedType::Atom))
        ->and($candidates[10])->toEqual(new FeedLink('https://www.a.com:8443/sitemap.xml', FeedType::Sitemap));
});

it('recusa home que não é http(s)', function () {
    FeedLocations::candidates('ftp://a.com/');
})->throws(InvalidArgumentException::class);
