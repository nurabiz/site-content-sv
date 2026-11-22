<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\GraphQl;

final class DateTimeScalar
{
    public static function serialize(\DateTimeInterface $value): string
    {
        return $value->format('Y-m-d\TH:i:s');
    }
}
