<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Repository;

use Doctrine\DBAL\Connection;
use NuraBiz\SiteContent\Entity\Document;
use NuraBiz\SiteContent\Entity\DocumentContent;
use NuraBiz\SiteContent\Repository\Factory\DocumentContentFactory;
use NuraBiz\SiteContent\Repository\Factory\DocumentFactory;

final readonly class DocumentsRepository
{
    public const string TABLE_NAME = 'documents';
    public const string CONTENT_TABLE_NAME = 'documents_content';

    public const string FIELD_ID = 'id';
    public const string FIELD_TYPE_ID = 'type_id';
    public const string FIELD_SLUG = 'slug';
    public const string FIELD_DOC_ID = 'doc_id';
    public const string FIELD_LOCALE = 'locale';
    public const string FIELD_DOC_CONTENT = 'doc_content';
    public const string FIELD_CREATED_AT = 'created_at';
    public const string FIELD_UPDATED_AT = 'updated_at';
    public const string FIELD_UPDATED_BY = 'updated_by';

    private const string DOCUMENT_COLUMNS = self::FIELD_ID . ', ' . self::FIELD_TYPE_ID . ', ' . self::FIELD_SLUG . ', ' . self::FIELD_CREATED_AT . ', ' . self::FIELD_UPDATED_AT;
    private const string CONTENT_COLUMNS = self::FIELD_ID . ', ' . self::FIELD_LOCALE . ', ' . self::FIELD_DOC_CONTENT . ', ' . self::FIELD_CREATED_AT . ', ' . self::FIELD_UPDATED_AT . ', ' . self::FIELD_UPDATED_BY;

    public function __construct(
        private Connection             $connection,
        private DocumentFactory        $docsFactory,
        private DocumentContentFactory $documentContentFactory,
    )
    {
    }

    /** @return Document[] */
    public function list(): array
    {
        return \array_map($this->docsFactory->fromRow(...), $this->connection->fetchAllAssociative(
            'SELECT ' . self::DOCUMENT_COLUMNS . ' FROM ' . self::TABLE_NAME . ' ORDER BY ' . self::FIELD_ID,
        ));
    }

    public function find(int $id): ?Document
    {
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT %s FROM %s WHERE %s = :id', self::DOCUMENT_COLUMNS, self::TABLE_NAME, self::FIELD_ID),
            [
                'id' => $id
            ]
        );

        return false === $row ? null : $this->docsFactory->fromRow($row);
    }

    /**
     * @return Document[]
     */
    public function findBySlugAndLocale(string $slug, string $locale): array
    {
        return array_map($this->docsFactory->fromRow(...), $this->connection->fetchAllAssociative(
            'SELECT d.id, d.type_id, d.slug, d.created_at, d.updated_at
             FROM documents_content c
             JOIN documents d ON d.id = c.doc_id
             WHERE d.slug = :slug AND c.locale = :locale',
            [
                'slug' => $slug,
                'locale' => $locale
            ],
        ));
    }

    /** @param array<string, string> $locales */
    public function documentCreate(int $typeId, string $slug, array $locales = []): Document
    {
        return $this->connection->transactional(function () use ($typeId, $slug, $locales): Document {

            $this->connection->executeStatement(
                \sprintf('INSERT INTO %s (%s, %s) VALUES (:type_id, :slug)', self::TABLE_NAME, self::FIELD_TYPE_ID, self::FIELD_SLUG),
                [
                    'type_id' => $typeId,
                    'slug' => $slug,
                ],
            );

            $id = (int)$this->connection->lastInsertId();
            $doc = $this->find($id) ?? throw new \LogicException('The inserted document could not be read.');
            $this->writeLocales($doc, $locales);

            return $doc->withLocales($this->listForDoc($doc));
        });
    }

    /** @param array<string, string> $locales */
    public function documentUpdate(int $id, int $typeId, string $slug, array $locales = []): ?Document
    {
        return $this->connection->transactional(function () use ($id, $typeId, $slug, $locales): ?Document {
            $row = $this->connection->fetchAssociative(
                \sprintf('SELECT %s FROM %s WHERE %s = :id FOR UPDATE', self::FIELD_ID, self::TABLE_NAME, self::FIELD_ID),
                [
                    'id' => $id,
                ],
            );
            if (false === $row) {
                return null;
            }

            $this->connection->executeStatement(
                \sprintf(
                    'UPDATE %s SET %s = :type_id, %s = :slug, %s = CURRENT_TIMESTAMP WHERE %s = :id',
                    self::TABLE_NAME,
                    self::FIELD_TYPE_ID,
                    self::FIELD_SLUG,
                    self::FIELD_UPDATED_AT,
                    self::FIELD_ID,
                ),
                [
                    'type_id' => $typeId,
                    'slug' => $slug,
                    'id' => $id],
            );

            $doc = $this->find($id) ?? throw new \LogicException('The updated document could not be read.');
            $this->writeLocales($doc, $locales);

            return $doc->withLocales($this->listForDoc($doc));
        });
    }

    /** @param array<string, string> $locales */
    private function writeLocales(Document $doc, array $locales): void
    {
        foreach ($locales as $langId => $content) {
            $id = $this->connection->fetchOne(
                'SELECT id FROM documents_content WHERE doc_id = :doc_id AND locale = :locale FOR UPDATE',
                [
                    'doc_id' => $doc->getId(),
                    'locale' => $langId
                ],
            );

            if (false === $id) {
                $this->createLocale($doc, $langId, $content);
            } else {
                $this->saveDocumentLocale($doc, $langId, $content);
            }
        }
    }

    public function documentGet(int $id): ?Document
    {
        return $this->connection->transactional(function () use ($id): ?Document {
            $doc = $this->find($id);

            return $doc?->withLocales($this->listForDoc($doc));
        });
    }

    public function documentGetLocale(int $id, string $langId): ?Document
    {
        $doc = $this->find($id);
        $locale = null === $doc ? null : $this->getDocumentLocale($doc, $langId);

        return null === $locale ? null : $doc->withLocales([$locale]);
    }

    public function documentDelete(int $id): ?Document
    {
        return $this->connection->transactional(function () use ($id): ?Document {
            $row = $this->connection->fetchAssociative(\sprintf('SELECT %s FROM %s WHERE %s = :id FOR UPDATE', self::DOCUMENT_COLUMNS, self::TABLE_NAME, self::FIELD_ID), ['id' => $id]);
            if (false === $row) {
                return null;
            }

            $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE %s = :id', self::TABLE_NAME, self::FIELD_ID), ['id' => $id]);

            return $this->docsFactory->fromRow($row);
        });
    }

    /** @return list<string> */
    public function getDocumentLocale(Document $document, string $langId): ?DocumentContent
    {
        $docId = $document->getId();

        $row = $this->connection->fetchAssociative(
            \sprintf(
                'SELECT %s FROM %s WHERE %s = :doc_id AND %s = :locale',
                self::CONTENT_COLUMNS,
                self::CONTENT_TABLE_NAME,
                self::FIELD_DOC_ID,
                self::FIELD_LOCALE,
            ),
            [
                'doc_id' => $docId,
                'locale' => $langId,
            ],
        );

        return false === $row ? null : $this->documentContentFactory->fromRow($document, $row);
    }

    private function saveDocumentLocale(Document $document, string $locale, string $content): ?DocumentContent
    {
        return $this->connection->transactional(function () use ($document, $locale, $content): ?DocumentContent {
            $this->connection->executeStatement(
                \sprintf(
                    'UPDATE %s SET %s = :doc_content, %s = CURRENT_TIMESTAMP
                 WHERE %s = :doc_id AND %s = :locale AND BINARY %s <> BINARY :compared_content',
                    self::CONTENT_TABLE_NAME,
                    self::FIELD_DOC_CONTENT,
                    self::FIELD_UPDATED_AT,
                    self::FIELD_DOC_ID,
                    self::FIELD_LOCALE,
                    self::FIELD_DOC_CONTENT,
                ),
                [
                    'doc_content' => $content,
                    'doc_id' => $document->getId(),
                    'locale' => $locale,
                    'compared_content' => $content,
                ],
            );

            return $this->getDocumentLocale($document, $locale);
        });
    }

    public function listForDoc(Document $doc): array
    {
        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT %s FROM %s WHERE %s = :doc_id ORDER BY %s',
                self::CONTENT_COLUMNS,
                self::CONTENT_TABLE_NAME,
                self::FIELD_DOC_ID,
                self::FIELD_ID,
            ),
            ['doc_id' => $doc->getId()],
        );

        return array_map(fn(array $row): DocumentContent => $this->documentContentFactory->fromRow($doc, $row), $rows);
    }

    private function findForDoc(Document $doc, int $id): ?DocumentContent
    {
        $row = $this->connection->fetchAssociative(
            \sprintf(
                'SELECT %s FROM %s WHERE %s = :doc_id AND %s = :id',
                self::CONTENT_COLUMNS,
                self::CONTENT_TABLE_NAME,
                self::FIELD_DOC_ID,
                self::FIELD_ID,
            ),
            ['doc_id' => $doc->getId(), 'id' => $id],
        );

        return false === $row ? null : $this->documentContentFactory->fromRow($doc, $row);
    }

    private function createLocale(Document $doc, string $locale, string $content): ?DocumentContent
    {
        $inserted = $this->connection->executeStatement(
            'INSERT INTO documents_content (doc_id, locale, doc_content, updated_at)
             SELECT id, :locale, :doc_content, CURRENT_TIMESTAMP
             FROM documents WHERE id = :doc_id',
            [
                'doc_id' => $doc->getId(),
                'locale' => $locale,
                'doc_content' => $content],
        );

        if (0 === $inserted) {
            return null;
        }

        return $this->findForDoc($doc, (int)$this->connection->lastInsertId())
            ?? throw new \LogicException('The inserted document locale could not be read.');
    }
}
