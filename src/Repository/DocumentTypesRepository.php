<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Repository;

final readonly class DocumentTypesRepository
{
    public const string TYPE_RULES = 'rules';
    public const string TYPE_SERVICES = 'services';

    const array LIST = [
        self::TYPE_RULES,
        self::TYPE_SERVICES,
    ];

    private const array TYPES_BY_ID = [
        1 => self::TYPE_RULES,
        2 => self::TYPE_SERVICES,
    ];

    public function list(): array
    {
        return self::LIST;
    }

    public function findName(int $id): ?string
    {
        return self::TYPES_BY_ID[$id] ?? null;
    }

    public function containsId(int $id): bool
    {
        return isset(self::TYPES_BY_ID[$id]);
    }

    public function contains(string $type): bool
    {
        return \in_array($type, self::LIST, true);
    }
}
