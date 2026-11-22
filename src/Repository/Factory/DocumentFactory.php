<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Repository\Factory;

use NuraBiz\SiteContent\Entity\Document;
use NuraBiz\SiteContent\Repository\DocumentsRepository;

final class DocumentFactory
{
    public function fromRow(array $row): Document
    {
        return new Document(
            (int) $row[DocumentsRepository::FIELD_ID],
            (int) $row[DocumentsRepository::FIELD_TYPE_ID],
            $row[DocumentsRepository::FIELD_TRANSLIT],
        );
    }
}
