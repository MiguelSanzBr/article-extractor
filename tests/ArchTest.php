<?php

declare(strict_types=1);

arch('src não depende de framework')
    ->expect('ArticleExtractor')
    ->not->toUse(['Illuminate', 'Symfony', 'GuzzleHttp']);

arch('sem debug esquecido')
    ->expect(['dd', 'dump', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('strict types em tudo')
    ->expect('ArticleExtractor')
    ->toUseStrictTypes();

arch('fontes de metadado implementam a interface')
    ->expect('ArticleExtractor\MetadataSources')
    ->classes()
    ->toImplement(ArticleExtractor\MetadataSources\MetadataSource::class)
    ->ignoring([ArticleExtractor\MetadataSources\MetadataExtractor::class, ArticleExtractor\MetadataSources\Fields::class]);
