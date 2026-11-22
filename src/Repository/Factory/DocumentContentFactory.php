<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Repository\Factory;

use NuraBiz\SiteContent\Entity\Document;
use NuraBiz\SiteContent\Entity\DocumentContent;
use NuraBiz\SiteContent\Repository\DocumentsRepository;

final class DocumentContentFactory
{
    public function fromRow(Document $doc, array $row): DocumentContent
    {
        return new DocumentContent(
            (int) $row[DocumentsRepository::FIELD_ID],
            $doc->getId(),
            $row[DocumentsRepository::FIELD_LOCALE],
            $row[DocumentsRepository::FIELD_DOC_CONTENT],
            $row[DocumentsRepository::FIELD_CREATED_AT],
            $row[DocumentsRepository::FIELD_UPDATED_AT],
        );
    }
}
