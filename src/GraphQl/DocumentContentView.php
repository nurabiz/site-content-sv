<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\GraphQl;

use NuraBiz\SiteContent\Repository\DocumentLocalesRepository;
use NuraBiz\SiteContent\Repository\DocumentsRepository;

final readonly class DocumentContentView
{
    public function __construct(
        public int $id,
        public int $doc_id,
        public string $locale,
        public string $doc_content,
        public \DateTimeImmutable $created_at,
        public \DateTimeImmutable $updated_at,
    ) {
    }

    public static function fromArray(array $content): self
    {
        $timezone = new \DateTimeZone('UTC');

        return new self(
            $content[DocumentsRepository::FIELD_ID],
            $content[DocumentsRepository::FIELD_DOC_ID],
            $content[DocumentsRepository::FIELD_LOCALE],
            $content[DocumentsRepository::FIELD_DOC_CONTENT],
            new \DateTimeImmutable($content[DocumentsRepository::FIELD_CREATED_AT], $timezone),
            new \DateTimeImmutable($content[DocumentsRepository::FIELD_UPDATED_AT], $timezone),
        );
    }
}
