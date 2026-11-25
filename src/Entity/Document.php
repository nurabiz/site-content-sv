<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Entity;

use NuraBiz\SiteContent\Repository\DocumentsRepository;

readonly class Document
{
    public function __construct(
        private int    $id,
        private int    $typeId,
        private string $slug,
        private string $createdAt,
        private string $updatedAt,
        /** @var list<DocumentContent>|null */
        private ?array $locales = null,
    )
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    /** @param list<DocumentContent> $locales */
    public function withLocales(array $locales): self
    {
        return new self($this->id, $this->typeId, $this->slug, $this->createdAt, $this->updatedAt, $locales);
    }

    public function toArray(): array
    {
        $data = [
            DocumentsRepository::FIELD_ID => $this->id,
            DocumentsRepository::FIELD_TYPE_ID => $this->typeId,
            DocumentsRepository::FIELD_SLUG => $this->slug,
            DocumentsRepository::FIELD_CREATED_AT => $this->createdAt,
            DocumentsRepository::FIELD_UPDATED_AT => $this->updatedAt,
        ];
        if (null !== $this->locales) {
            $data['locales'] = array_map(static fn (DocumentContent $locale): array => $locale->toArray(), $this->locales);
        }

        return $data;
    }
}
