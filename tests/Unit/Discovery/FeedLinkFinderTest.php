<?php

declare(strict_types=1);

use ArticleExtractor\Discovery\FeedLink;
use ArticleExtractor\Discovery\FeedLinkFinder;
use ArticleExtractor\Discovery\FeedType;

it('acha os feeds da home na ordem, sem o de comentários', function () {
    $html = '<html><head>'
        . '<link rel="alternate" type="application/rss+xml" title="Comentários" href="/comments/feed">'
        . '<link rel="alternate" type="application/rss+xml" title="Notícias" href="/feed">'
        . '<link rel="alternate" type="application/atom+xml" href="https://a.com/atom.xml">'
        . '<link rel="alternate" type="application/rss+xml" href="/feed">'
        . '<link rel="alternate" type="application/rss+xml">'
        . '<link rel="stylesheet" href="/x.css">'
        . '</head><body></body></html>';

    expect(FeedLinkFinder::find($html, 'https://a.com/'))->toEqual([
        new FeedLink('https://a.com/feed', FeedType::Rss),
        new FeedLink('https://a.com/atom.xml', FeedType::Atom),
    ]);
});

it('resolve href relativo ao protocolo e ignora feed de comentário pelo endereço', function () {
    $html = '<link rel="alternate" type="application/rss+xml" href="//a.com/feed"><link rel="alternate" type="application/rss+xml" href="/comentarios/rss">';

    expect(FeedLinkFinder::find($html, 'https://a.com/'))->toEqual([new FeedLink('https://a.com/feed', FeedType::Rss)]);
});

it('não acha nada numa home sem feed', function () {
    expect(FeedLinkFinder::find('<html><body><p>oi</p></body></html>', 'https://a.com/'))->toBe([]);
});

it('ignora feed de comentários pelo título', function () {
    expect(FeedLinkFinder::find('<link rel="alternate" type="application/rss+xml" title="Comentários do post" href="/feed/c">', 'https://a.com/'))->toBe([]);
});
