<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\GraphQl;

final readonly class DocumentView
{
    public function __construct(
        public int                $id,
        public int                $type_id,
        public ?DocumentType      $type,
        public string             $slug,
        public \DateTimeImmutable $created_at,
        public \DateTimeImmutable $updated_at,
    ) {
    }
}
