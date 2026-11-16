<?php

declare(strict_types=1);

namespace ArticleExtractor\Discovery;

enum FeedType: string
{
    case Rss = 'rss';
    case Atom = 'atom';
    case Sitemap = 'sitemap';
}
