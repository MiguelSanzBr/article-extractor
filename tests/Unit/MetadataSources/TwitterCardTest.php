<?php

declare(strict_types=1);

use ArticleExtractor\MetadataSources\TwitterCard;

it('lê twitter:* por name ou property', function () {
    $meta = (new TwitterCard())->read(page('<meta name="twitter:title" content="T"><meta property="twitter:description" content="D"><meta name="twitter:image" content="//cdn.a.com/i.jpg">'), utc());

    expect($meta->title)->toBe('T')
        ->and($meta->subtitle)->toBe('D')
        ->and($meta->image)->toBe('https://cdn.a.com/i.jpg');
});
