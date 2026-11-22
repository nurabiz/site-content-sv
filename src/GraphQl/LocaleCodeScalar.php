<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\GraphQl;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\ValueNode;

final class LocaleCodeScalar
{
    public static function serialize(string $value): string
    {
        if (!self::isValid($value)) {
            throw new \UnexpectedValueException('Expected a two-letter document locale code.');
        }

        return $value;
    }

    public static function parseValue(mixed $value): string
    {
        if (!\is_string($value) || !self::isValid($value)) {
            throw new Error('Expected a two-letter document locale code.');
        }

        return strtolower($value);
    }

    public static function parseLiteral(ValueNode $value): string
    {
        return self::parseValue($value instanceof StringValueNode ? $value->value : null);
    }

    private static function isValid(string $value): bool
    {
        return 1 === preg_match('/\A[a-z]{2}\z/i', $value);
    }
}
