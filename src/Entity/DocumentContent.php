<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Entity;

final readonly class DocumentContent
{
    public function __construct(
        private int $id,
        private int $docId,
        private string $langId,
        private string $content,
        private string $createdAt,
        private string $updatedAt,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'doc_id' => $this->docId,
            'lang_id' => $this->langId,
            'doc_content' => $this->content,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
