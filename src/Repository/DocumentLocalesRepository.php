<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Repository;

class DocumentLocalesRepository
{
    public const string LOCALE_EN = 'en';
    public const string LOCALE_RU = 'ru';
    public const string LOCALE_UA = 'uk';

    const array LIST = [
        self::LOCALE_EN,
        self::LOCALE_RU,
        self::LOCALE_UA,
    ];

    public function list(): array
    {
        return self::LIST;
    }

    public function contains(string $locale): bool
    {
        return \in_array($locale, self::LIST, true);
    }
}


