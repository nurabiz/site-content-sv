<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Service;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use NuraBiz\SiteContent\Dto\DocContentDto;
use NuraBiz\SiteContent\Dto\DocDto;
use NuraBiz\SiteContent\Entity\Document;
use NuraBiz\SiteContent\Entity\DocumentContent;
use NuraBiz\SiteContent\Repository\DocumentsRepository;
use NuraBiz\SiteContent\Repository\DocumentTypesRepository;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class DocumentManager
{
    public function __construct(
        private DocumentsRepository     $docs,
        private DocumentTypesRepository $types,
    ) {
    }

    public function list(): array
    {
        return array_map(
            static fn (Document $doc): array => $doc->toArray(),
            $this->docs->list(),
        );
    }

    public function get(int $id): Document
    {
        return $this->docs->find($id) ?? throw new NotFoundHttpException('Document not found.');
    }

    public function getWithLocales(int $id): array
    {
        $doc = $this->docs->documentGet($id) ?? throw new NotFoundHttpException('Document not found.');

        return $doc->toArray();
    }

    public function getByTranslitAndLocale(string $translit, string $langId): array
    {
        $langId = strtolower($langId);
        $matches = $this->docs->findByTranslitAndLocale($translit, $langId);
        if (\count($matches) > 1) {
            throw new ConflictHttpException('Multiple documents match this translit and locale.');
        }

        $doc = $matches[0] ?? throw new NotFoundHttpException('Document not found.');
        $localized = $this->docs->documentGetLocale($doc->getId(), $langId)
            ?? throw new NotFoundHttpException('Document locale not found.');

        return $localized->toArray();
    }

    public function updateContent(int $docId, string $langId, string $content): DocumentContent
    {
        return $this->docs->saveDocumentLocale($this->get($docId), strtolower($langId), $content)
            ?? throw new NotFoundHttpException('Document locale not found.');
    }

    public function deleteContent(int $docId, string $langId): void
    {
        if (!$this->docs->documentDeleteLocale($docId, strtolower($langId))) {
            throw new NotFoundHttpException('Document locale not found.');
        }
    }

    public function deleteAllContent(int $docId): void
    {
        $this->get($docId);
        $this->docs->deleteDocumentLocales($docId);
    }

    public function save(DocDto $dto, ?int $id = null): Document
    {
        if (!$this->types->containsId($dto->type_id)) {
            throw new UnprocessableEntityHttpException('Unknown document type.');
        }

        $locales = [];
        foreach ($dto->locales as $langId => $content) {
            if (!\is_string($langId) || !preg_match('/\A[a-z]{2}\z/i', $langId) || array_key_exists(strtolower($langId), $locales)) {
                throw new UnprocessableEntityHttpException('Locales must use distinct two-letter language codes.');
            }

            $locales[strtolower($langId)] = $content;
        }

        if (null === $id) {
            return $this->docs->documentCreate($dto->type_id, $dto->translit, $locales);
        }

        return $this->docs->documentUpdate($id, $dto->type_id, $dto->translit, $locales)
            ?? throw new NotFoundHttpException('Document not found.');
    }

    public function delete(int $id): void
    {
        if (null === $this->docs->documentDelete($id)) {
            throw new NotFoundHttpException('Document not found.');
        }
    }

    public function listLocales(int $docId): array
    {
        return $this->formatLocales($this->get($docId));
    }

    private function formatLocales(Document $doc): array
    {
        return array_map(
            static fn (DocumentContent $content): array => $content->toArray(),
            $this->docs->listForDoc($doc),
        );
    }

    public function getLocale(int $docId, int $id): DocumentContent
    {
        return $this->docs->findForDoc($this->get($docId), $id)
            ?? throw new NotFoundHttpException('Document locale not found.');
    }

    public function saveLocale(int $docId, DocContentDto $dto, ?int $id = null): DocumentContent
    {
        $doc = $this->get($docId);
        $langId = strtolower($dto->lang_id);
        try {
            if (null === $id) {
                return $this->docs->createLocale($doc, $langId, $dto->doc_content);
            }

            return $this->docs->saveLocale($doc, $id, $langId, $dto->doc_content)
                ?? throw new NotFoundHttpException('Document locale not found.');
        } catch (UniqueConstraintViolationException $exception) {
            throw new ConflictHttpException('This locale already exists for the document.', $exception);
        } catch (ForeignKeyConstraintViolationException $exception) {
            throw new NotFoundHttpException('Document not found.', $exception);
        }
    }

    public function deleteLocale(int $docId, int $id): void
    {
        if (null === $this->docs->deleteLocaleById($this->get($docId), $id)) {
            throw new NotFoundHttpException('Document locale not found.');
        }
    }
}
