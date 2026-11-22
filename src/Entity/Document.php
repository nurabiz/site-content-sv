<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Entity;

use NuraBiz\SiteContent\Repository\DocumentsRepository;

readonly class Document
{
    public function __construct(
        private int    $id,
        private int    $typeId,
        private string $translit,
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
        return new self($this->id, $this->typeId, $this->translit, $locales);
    }

    public function toArray(): array
    {
        $data = [
            DocumentsRepository::FIELD_ID => $this->id,
            DocumentsRepository::FIELD_TYPE_ID => $this->typeId,
            DocumentsRepository::FIELD_TRANSLIT => $this->translit
        ];
        if (null !== $this->locales) {
            $data['locales'] = array_map(static fn (DocumentContent $locale): array => $locale->toArray(), $this->locales);
        }

        return $data;
    }
}
