<?php

declare(strict_types=1);

namespace ArticleExtractor\Content;

use ArticleExtractor\Support\Text;
use Dom\Element;

final class NoiseRules
{
    private const array TAGS = ['script', 'style', 'noscript', 'template', 'iframe', 'svg', 'nav', 'aside', 'footer', 'button', 'dialog'];

    private const array ROLES = ['navigation', 'complementary', 'contentinfo', 'search'];

    /**
     * Casam uma classe/id inteira ou o começo dela seguido de - ou _: "sidebar" pega
     * "sidebar__widget" mas não "has-sidebar"; "ad" pega "ad-slot" mas não "admin-bar".
     */
    private const array PATTERNS = [
        'ad', 'ads', 'advert', 'advertisement', 'adslot', 'publicidade', 'anuncio', 'sponsor', 'sponsored', 'patrocinado',
        'share', 'sharing', 'social', 'newsletter', 'related', 'recommended', 'recomendados', 'leia-tambem', 'veja-tambem',
        'saiba-mais', 'read-more', 'more-stories', 'most-read', 'mais-lidas', 'trending', 'sidebar', 'comments', 'comentarios',
        'author-bio', 'cookie', 'consent', 'lgpd', 'paywall', 'subscribe', 'assine', 'popup', 'modal', 'breadcrumb',
        'pagination', 'toolbar', 'outbrain', 'taboola',
    ];

    /** chamadas q, num bloco curto, não são texto da matéria */
    private const array BOILERPLATE = [
        'publicidade', 'continua depois da publicidade', 'continua após a publicidade', 'leia também', 'leia mais',
        'veja também', 'saiba mais', 'assine', 'newsletter', 'compartilhe', 'siga-nos', 'siga no', 'todos os direitos reservados',
        'clique aqui', 'baixe o app', 'advertisement', 'read more', 'see also', 'subscribe', 'sign up', 'share this',
        'follow us', 'all rights reserved', 'click here',
    ];

    private const int BOILERPLATE_MAX_LENGTH = 200;

    private const string DATE_STAMP = '~^(?:(?:publicado|atualizado|published|updated|por|by)\s+(?:(?:em|on)\s+)?)?(?:\d{1,2}/\d{1,2}/\d{2,4}|\d{4}-\d{2}-\d{2})~iu';

    public static function isNoise(Element $element): bool
    {
        if (in_array($element->localName, self::TAGS, true)
            || in_array(strtolower($element->getAttribute('role') ?? ''), self::ROLES, true)
            || $element->hasAttribute('hidden')
            || $element->getAttribute('aria-hidden') === 'true') {
            return true;
        }

        $names = preg_split('/\s+/', strtolower(($element->getAttribute('class') ?? '') . ' ' . ($element->getAttribute('id') ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($names ?: [] as $name) {
            foreach (self::PATTERNS as $pattern) {
                if ($name === $pattern || str_starts_with($name, $pattern . '-') || str_starts_with($name, $pattern . '_')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Só bloco curto: um parágrafo longo sobre o mercado de publicidade é matéria, não anúncio.
     */
    public static function isBoilerplate(string $text): bool
    {
        if (Text::length($text) >= self::BOILERPLATE_MAX_LENGTH) {
            return false;
        }
        $lower = mb_strtolower($text);
        foreach (self::BOILERPLATE as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * "Publicado em 05/03/2026 10:30" é carimbo; "05/03/2026 foi o dia em que o país mudou"
     * é frase. Carimbo é curto, começa com data e tem no máximo 6 palavras de letra.
     */
    public static function isDateStamp(string $text): bool
    {
        return Text::length($text) <= 80
            && preg_match(self::DATE_STAMP, $text) === 1
            && preg_match_all('/(?<![\p{L}\d])\p{L}+(?![\p{L}\d])/u', $text) <= 6;
    }
}
