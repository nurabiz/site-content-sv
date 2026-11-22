<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\GraphQl;

use NuraBiz\SiteContent\Repository\DocumentsRepository;
use NuraBiz\SiteContent\Repository\DocumentTypesRepository;
use NuraBiz\SiteContent\Service\DocumentManager;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final readonly class DocumentQueries
{
    public function __construct(
        private DocumentManager $documents,
        private DocumentTypesRepository $types,
        private DocumentsRepository $contents,
    ) {
    }

    public function documents(): array
    {
        return $this->documents->list();
    }

    public function documentTypes(): array
    {
        return $this->types->list();
    }

    public function documentLocales(): array
    {
        return $this->contents->listLanguages();
    }
}
