<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Service;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use NuraBiz\SiteContent\Dto\DocDto;
use NuraBiz\SiteContent\Entity\Document;
use NuraBiz\SiteContent\Entity\DocumentContent;
use NuraBiz\SiteContent\Repository\DocumentLocalesRepository;
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
        private DocumentLocalesRepository $locales,
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
        $doc = $matches[0] ?? throw new NotFoundHttpException('Document not found.');
        $localized = $this->docs->documentGetLocale($doc->getId(), $langId)
            ?? throw new NotFoundHttpException('Document locale not found.');

        return $localized->toArray();
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

            $locale = strtolower($langId);
            if (null === $id && !$this->locales->contains($locale)) {
                throw new UnprocessableEntityHttpException(sprintf('Unknown document locale "%s".', $locale));
            }

            $locales[$locale] = $content;
        }

        try {
            if (null === $id) {
                return $this->docs->documentCreate(
                    $dto->type_id,
                    $dto->translit,
                    array_replace(array_fill_keys($this->locales->list(), ''), $locales),
                );
            }

            return $this->docs->documentUpdate($id, $dto->type_id, $dto->translit, $locales)
                ?? throw new NotFoundHttpException('Document not found.');
        } catch (UniqueConstraintViolationException $exception) {
            throw new ConflictHttpException('This document translit already exists.', $exception);
        }
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
}
