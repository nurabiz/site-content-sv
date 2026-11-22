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
    public const string FIELD_TRANSLIT = 'translit';
    public const string FIELD_DOC_ID = 'doc_id';
    public const string FIELD_LOCALE = 'locale';
    public const string FIELD_DOC_CONTENT = 'doc_content';
    public const string FIELD_UPDATED_AT = 'updated_at';

    private const string DOCUMENT_COLUMNS = self::FIELD_ID.', '.self::FIELD_TYPE_ID.', '.self::FIELD_TRANSLIT;
    private const string CONTENT_COLUMNS = self::FIELD_ID.', '.self::FIELD_LOCALE.', '.self::FIELD_DOC_CONTENT.', '.self::FIELD_UPDATED_AT;

    public function __construct(
        private Connection      $connection,
        private DocumentFactory $docsFactory,
        private DocumentContentFactory $documentContentFactory,
    ) {
    }

    /** @return list<Document> */
    public function list(): array
    {
        return \array_map($this->docsFactory->fromRow(...), $this->connection->fetchAllAssociative(
            'SELECT '.self::DOCUMENT_COLUMNS.' FROM '.self::TABLE_NAME.' ORDER BY '.self::FIELD_ID,
        ));
    }

    public function find(int $id): ?Document
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT %s FROM %s WHERE %s = :id', self::DOCUMENT_COLUMNS, self::TABLE_NAME, self::FIELD_ID), ['id' => $id]);

        return false === $row ? null : $this->docsFactory->fromRow($row);
    }

    /** @return list<Document> */
    public function findByTranslitAndLocale(string $translit, string $langId): array
    {
        return array_map($this->docsFactory->fromRow(...), $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT d.%s, d.%s, d.%s FROM %s d
             WHERE d.%s = :translit AND EXISTS (
                 SELECT 1 FROM %s c WHERE c.%s = d.%s AND c.%s = :lang_id
             ) LIMIT 2',
                self::FIELD_ID,
                self::FIELD_TYPE_ID,
                self::FIELD_TRANSLIT,
                self::TABLE_NAME,
                self::FIELD_TRANSLIT,
                self::CONTENT_TABLE_NAME,
                self::FIELD_DOC_ID,
                self::FIELD_ID,
                self::FIELD_LOCALE,
            ),
            ['translit' => $translit, 'lang_id' => $langId],
        ));
    }

    /** @param array<string, string> $locales */
    public function documentCreate(int $typeId, string $translit, array $locales = []): Document
    {
        return $this->connection->transactional(function () use ($typeId, $translit, $locales): Document {
            $row = [self::FIELD_TYPE_ID => $typeId, self::FIELD_TRANSLIT => $translit];
            $this->connection->executeStatement(
                \sprintf('INSERT INTO %s (%s, %s) VALUES (:type_id, :translit)', self::TABLE_NAME, self::FIELD_TYPE_ID, self::FIELD_TRANSLIT),
                $row,
            );

            $id = (int) $this->connection->lastInsertId();
            $doc = $this->docsFactory->fromRow($row + [self::FIELD_ID => $id]);
            $this->writeLocales($id, $locales);

            return $doc->withLocales($this->listForDoc($doc));
        });
    }

    /** @param array<string, string> $locales */
    public function documentUpdate(int $id, int $typeId, string $translit, array $locales = []): ?Document
    {
        return $this->connection->transactional(function () use ($id, $typeId, $translit, $locales): ?Document {
            $row = $this->connection->fetchAssociative(
                \sprintf('SELECT %s FROM %s WHERE %s = :id FOR UPDATE', self::FIELD_ID, self::TABLE_NAME, self::FIELD_ID),
                ['id' => $id],
            );
            if (false === $row) {
                return null;
            }

            $this->connection->executeStatement(
                \sprintf(
                    'UPDATE %s SET %s = :type_id, %s = :translit WHERE %s = :id',
                    self::TABLE_NAME,
                    self::FIELD_TYPE_ID,
                    self::FIELD_TRANSLIT,
                    self::FIELD_ID,
                ),
                ['type_id' => $typeId, 'translit' => $translit, 'id' => $id],
            );

            $this->writeLocales($id, $locales);

            return $this->documentGet($id);
        });
    }

    /** @param array<string, string> $locales */
    private function writeLocales(int $id, array $locales): void
    {
        foreach ($locales as $langId => $content) {
            $this->connection->executeStatement(
                \sprintf(
                    'INSERT INTO %s (%s, %s, %s, %s)
                 VALUES (:doc_id, :lang_id, :doc_content, CURRENT_TIMESTAMP(6))
                 ON DUPLICATE KEY UPDATE
                     %s = IF(BINARY %s <> BINARY :compared_content, CURRENT_TIMESTAMP(6), %s),
                     %s = :updated_content',
                    self::CONTENT_TABLE_NAME,
                    self::FIELD_DOC_ID,
                    self::FIELD_LOCALE,
                    self::FIELD_DOC_CONTENT,
                    self::FIELD_UPDATED_AT,
                    self::FIELD_UPDATED_AT,
                    self::FIELD_DOC_CONTENT,
                    self::FIELD_UPDATED_AT,
                    self::FIELD_DOC_CONTENT,
                ),
                [
                    'doc_id' => $id,
                    'lang_id' => $langId,
                    'doc_content' => $content,
                    'compared_content' => $content,
                    'updated_content' => $content,
                ],
            );
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
    public function listLanguages(): array
    {
        return $this->connection->fetchFirstColumn(\sprintf('SELECT DISTINCT %s FROM %s ORDER BY %s', self::FIELD_LOCALE, self::CONTENT_TABLE_NAME, self::FIELD_LOCALE));
    }

    public function getDocumentLocale(Document $document, string $langId): ?DocumentContent
    {
        $docId = $document->getId();

        $row = $this->connection->fetchAssociative(
            \sprintf(
                'SELECT %s FROM %s WHERE %s = :doc_id AND %s = :lang_id',
                self::CONTENT_COLUMNS,
                self::CONTENT_TABLE_NAME,
                self::FIELD_DOC_ID,
                self::FIELD_LOCALE,
            ),
            [
                'doc_id' => $docId,
                'lang_id' => $langId,
            ],
        );

        return false === $row ? null : $this->documentContentFactory->fromRow($document, $row);
    }

    public function saveDocumentLocale(Document $document, string $locale, string $content): ?DocumentContent
    {
        return $this->connection->transactional(function () use ($document, $locale, $content): ?DocumentContent {
            $this->connection->executeStatement(
                \sprintf(
                    'UPDATE %s SET %s = :doc_content, %s = CURRENT_TIMESTAMP(6)
                 WHERE %s = :doc_id AND %s = :lang_id AND BINARY %s <> BINARY :compared_content',
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
                    'lang_id' => $locale,
                    'compared_content' => $content,
                ],
            );

            return $this->getDocumentLocale($document, $locale);
        });
    }

    public function documentDeleteLocale(int $docId, string $langId): bool
    {
        return 0 < $this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE %s = :doc_id AND %s = :lang_id', self::CONTENT_TABLE_NAME, self::FIELD_DOC_ID, self::FIELD_LOCALE),
            ['doc_id' => $docId, 'lang_id' => $langId],
        );
    }

    public function deleteDocumentLocales(int $docId): void
    {
        $this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE %s = :doc_id', self::CONTENT_TABLE_NAME, self::FIELD_DOC_ID),
            [
                'doc_id' => $docId,
            ],
        );
    }

    /** @return list<DocumentContent> */
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

        return array_map(fn (array $row): DocumentContent => $this->documentContentFactory->fromRow($doc, $row), $rows);
    }

    public function findForDoc(Document $doc, int $id): ?DocumentContent
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

    public function createLocale(Document $doc, string $langId, string $content): DocumentContent
    {
        $row = [self::FIELD_LOCALE => $langId, self::FIELD_DOC_CONTENT => $content];
        $this->connection->executeStatement(
            \sprintf(
                'INSERT INTO %s (%s, %s, %s, %s) VALUES (:doc_id, :lang_id, :doc_content, CURRENT_TIMESTAMP(6))',
                self::CONTENT_TABLE_NAME,
                self::FIELD_DOC_ID,
                self::FIELD_LOCALE,
                self::FIELD_DOC_CONTENT,
                self::FIELD_UPDATED_AT,
            ),
            $row + ['doc_id' => $doc->getId()],
        );

        return $this->findForDoc($doc, (int) $this->connection->lastInsertId())
            ?? throw new \LogicException('The inserted document locale could not be read.');
    }

    public function saveLocale(Document $doc, int $id, string $langId, string $content): ?DocumentContent
    {
        return $this->connection->transactional(function () use ($doc, $id, $langId, $content): ?DocumentContent {
            $this->connection->executeStatement(
                \sprintf(
                    'UPDATE %s SET %s = :lang_id, %s = :doc_content, %s = CURRENT_TIMESTAMP(6)
                 WHERE %s = :doc_id AND %s = :id
                   AND (BINARY %s <> BINARY :previous_lang_id OR BINARY %s <> BINARY :previous_content)',
                    self::CONTENT_TABLE_NAME,
                    self::FIELD_LOCALE,
                    self::FIELD_DOC_CONTENT,
                    self::FIELD_UPDATED_AT,
                    self::FIELD_DOC_ID,
                    self::FIELD_ID,
                    self::FIELD_LOCALE,
                    self::FIELD_DOC_CONTENT,
                ),
                [
                    'lang_id' => $langId,
                    'doc_content' => $content,
                    'doc_id' => $doc->getId(),
                    'id' => $id,
                    'previous_lang_id' => $langId,
                    'previous_content' => $content,
                ],
            );

            return $this->findForDoc($doc, $id);
        });
    }

    public function deleteLocaleById(Document $doc, int $id): ?DocumentContent
    {
        return $this->connection->transactional(function () use ($doc, $id): ?DocumentContent {
            $row = $this->connection->fetchAssociative(
                \sprintf(
                    'SELECT %s FROM %s WHERE %s = :doc_id AND %s = :id FOR UPDATE',
                    self::CONTENT_COLUMNS,
                    self::CONTENT_TABLE_NAME,
                    self::FIELD_DOC_ID,
                    self::FIELD_ID,
                ),
                ['doc_id' => $doc->getId(), 'id' => $id],
            );
            if (false === $row) {
                return null;
            }

            $this->connection->executeStatement(
                \sprintf('DELETE FROM %s WHERE %s = :doc_id AND %s = :id', self::CONTENT_TABLE_NAME, self::FIELD_DOC_ID, self::FIELD_ID),
                ['doc_id' => $doc->getId(), 'id' => $id],
            );

            return $this->documentContentFactory->fromRow($doc, $row);
        });
    }
}
