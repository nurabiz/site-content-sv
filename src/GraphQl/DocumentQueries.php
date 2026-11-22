<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\GraphQl;

use NuraBiz\SiteContent\Repository\DocumentLocalesRepository;
use NuraBiz\SiteContent\Repository\DocumentsRepository;
use NuraBiz\SiteContent\Repository\DocumentTypesRepository;
use NuraBiz\SiteContent\Service\DocumentManager;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Autoconfigure(public: true)]
final readonly class DocumentQueries
{
    public function __construct(
        private DocumentManager $documents,
        private DocumentTypesRepository $types,
        private DocumentsRepository $contents,
        private DocumentLocalesRepository $locales,
    ) {
    }

    /** @return list<DocumentView> */
    public function documents(): array
    {
        return array_map(function (array $document): DocumentView {
            $type = $this->types->findName($document['type_id']);

            return new DocumentView(
                $document['id'],
                $document['type_id'],
                null === $type ? null : DocumentType::from($type),
                $document['translit'],
                new \DateTimeImmutable($document['created_at'], new \DateTimeZone('UTC')),
                new \DateTimeImmutable($document['updated_at'], new \DateTimeZone('UTC')),
            );
        }, $this->documents->list());
    }

    /** @return list<DocumentContentView>|null */
    public function documentContents(int $documentId): ?array
    {
        try {
            $contents = $this->documents->listLocales($documentId);
        } catch (NotFoundHttpException) {
            return null;
        }

        return array_map(DocumentContentView::fromArray(...), $contents);
    }

    public function documentContent(int $documentId, string $locale): ?DocumentContentView
    {
        $document = $this->contents->find($documentId);
        $content = null === $document ? null : $this->contents->getDocumentLocale($document, $locale);

        return null === $content ? null : DocumentContentView::fromArray($content->toArray());
    }

    /** @return list<DocumentType> */
    public function documentTypes(): array
    {
        return array_map(DocumentType::from(...), $this->types->list());
    }

    /** @return list<string> */
    public function documentLocales(): array
    {
        return $this->locales->list();
    }
}
