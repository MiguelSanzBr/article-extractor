<?php

declare(strict_types=1);

namespace ArticleExtractor\Support;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

final class Dates
{
    private const string BRAZILIAN = '~^(\d{1,2})[/.](\d{1,2})[/.](\d{4})(?:,?\s+(?:às\s+)?(\d{1,2})[:h](\d{2}))?$~u';

    private const string ISO = '/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?)?(?:Z|[+-]\d{2}:?\d{2})?$/i';

    private const string RFC = '/^(?:[a-z]{3},\s*)?\d{1,2}\s+[a-z]{3}\s+\d{4}\s+\d{2}:\d{2}(?::\d{2})?\s*(?:GMT|UTC|Z|[+-]\d{4}|[a-z]{1,4})?$/i';

    /**
     * Lê só formatos conhecidos (ISO-8601, RFC 2822 e dd/mm/aaaa com hora opcional), nunca
     * linguagem natural: "ontem" não pode virar a data de hoje. Data sem fuso é lida no fuso
     * assumido; o resultado sai sempre em UTC.
     */
    public static function parse(?string $raw, DateTimeZone $assume): ?DateTimeImmutable
    {
        $raw = trim((string) $raw);

        if (preg_match(self::BRAZILIAN, $raw, $m) === 1) {
            [$day, $month, $year, $hour, $minute] = [(int) $m[1], (int) $m[2], (int) $m[3], (int) ($m[4] ?? 0), (int) ($m[5] ?? 0)];
            if (!checkdate($month, $day, $year) || $hour > 23 || $minute > 59) {
                return null;
            }
            $date = (new DateTimeImmutable('now', $assume))->setDate($year, $month, $day)->setTime($hour, $minute);
        } elseif (preg_match(self::ISO, $raw) === 1 || preg_match(self::RFC, $raw) === 1) {
            try {
                $date = new DateTimeImmutable($raw, $assume);
            } catch (Exception) {
                return null;
            }
            $errors = DateTimeImmutable::getLastErrors();
            if ($errors !== false && $errors['warning_count'] > 0) {
                return null;
            }
        } else {
            return null;
        }

        $year = (int) $date->format('Y');
        if ($year < 1990 || $year > 2100) {
            return null;
        }

        return $date->setTimezone(new DateTimeZone('UTC'));
    }
}
