<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use NuraBiz\SiteContent\Dto\NewsDto;

final readonly class NewsRepository
{
    public function __construct(
        private Connection $connection,
        private LocalesRepository $locales,
    ) {
    }

    public function save(NewsDto $dto, ?int $id = null): ?array
    {
        return $this->connection->transactional(function () use ($dto, $id): ?array {
            $news = [
                'valid' => $dto->valid,
                'userid' => $dto->userid,
                'client_type' => $dto->client_type,
                'start_at' => $dto->start_at,
                'finish_at' => $dto->finish_at,
                'image' => $dto->image,
            ];
            $types = ['valid' => Types::BOOLEAN];

            if (null === $id) {
                $this->connection->executeStatement(
                    'INSERT INTO news (valid, userid, client_type, start_at, finish_at, image, created_at)
                     VALUES (:valid, :userid, :client_type, :start_at, :finish_at, :image, CURRENT_TIMESTAMP)',
                    $news,
                    $types,
                );
                $id = (int) $this->connection->lastInsertId();
                foreach ($this->locales->list() as $locale) {
                    $this->insertLocale($id, $locale, $dto->locales[$locale] ?? [], $dto->updated_by ?? 0);
                }
            } else {
                if (false === $this->connection->fetchOne('SELECT id FROM news WHERE id = ? FOR UPDATE', [$id])) {
                    return null;
                }

                $this->connection->update('news', $news, ['id' => $id], $types);
                $this->updateLocales($id, $dto);
            }

            return $this->load($id) ?? throw new \LogicException('The saved news could not be read.');
        });
    }

    public function find(int $id, ?string $locale = null): ?array
    {
        if (null !== $locale) {
            $locale = strtolower($locale);
            if (!$this->locales->contains($locale)) {
                return null;
            }
        }

        return $this->connection->transactional(fn (): ?array => $this->load($id, $locale));
    }

    public function list(int $limit, int $offset, ?bool $valid, ?int $clientType): array
    {
        return $this->connection->transactional(function () use ($limit, $offset, $valid, $clientType): array {
            $query = $this->connection->createQueryBuilder()
                ->select('id', 'valid', 'userid', 'client_type', 'created_at', 'start_at', 'finish_at', 'image')
                ->from('news')
                ->orderBy('start_at', 'DESC')
                ->addOrderBy('id', 'DESC')
                ->setMaxResults($limit)
                ->setFirstResult($offset);

            if (null !== $valid) {
                $query->andWhere('valid = :valid')->setParameter('valid', $valid, ParameterType::BOOLEAN);
            }
            if (null !== $clientType) {
                $query->andWhere('client_type = :client_type')
                    ->setParameter('client_type', $clientType, ParameterType::INTEGER);
            }

            $news = $query->fetchAllAssociative();
            if ([] === $news) {
                return [];
            }

            $contents = $this->connection->fetchAllAssociative(
                'SELECT news_id, locale, header, text, created_at, updated_at, updated_by
                 FROM news_content WHERE news_id IN (?) ORDER BY locale',
                [array_column($news, 'id')],
                [ArrayParameterType::INTEGER],
            );
            $byNewsId = [];
            foreach ($contents as $content) {
                $byNewsId[$content['news_id']][] = $content;
            }

            return array_map(
                static fn (array $row): array => self::withLocales($row, $byNewsId[$row['id']] ?? []),
                $news,
            );
        });
    }

    private function load(int $id, ?string $locale = null): ?array
    {
        $news = $this->connection->fetchAssociative(
            'SELECT id, valid, userid, client_type, created_at, start_at, finish_at, image FROM news WHERE id = ?',
            [$id],
        );
        if (false === $news) {
            return null;
        }

        $sql = 'SELECT news_id, locale, header, text, created_at, updated_at, updated_by
                FROM news_content WHERE news_id = ?';
        $parameters = [$id];
        if (null !== $locale) {
            $sql .= ' AND locale = ?';
            $parameters[] = $locale;
        }

        $contents = $this->connection->fetchAllAssociative($sql.' ORDER BY locale', $parameters);
        if (null !== $locale && [] === $contents) {
            return null;
        }

        return self::withLocales($news, $contents);
    }

    private static function withLocales(array $news, array $contents): array
    {
        $news['valid'] = null === $news['valid'] ? null : (bool) $news['valid'];
        $news['locales'] = $contents;

        return $news;
    }

    public function delete(int $id): bool
    {
        return 0 !== $this->connection->delete('news', ['id' => $id]);
    }

    private function updateLocales(int $id, NewsDto $dto): void
    {
        if ([] === $dto->locales) {
            return;
        }

        $stored = $this->connection->fetchAllAssociativeIndexed(
            'SELECT locale, header, text FROM news_content WHERE news_id = ? FOR UPDATE',
            [$id],
        );
        foreach ($dto->locales as $locale => $content) {
            if ([] === $content) {
                continue;
            }

            if (!isset($stored[$locale])) {
                $this->insertLocale($id, $locale, $content, $dto->updated_by ?? 0);
                continue;
            }

            $changes = array_filter(
                $content,
                static fn (?string $value, string $field): bool => $stored[$locale][$field] !== $value,
                ARRAY_FILTER_USE_BOTH,
            );
            if ([] === $changes) {
                continue;
            }

            $this->connection->executeStatement(
                'UPDATE news_content
                 SET header = :header, text = :text, updated_at = CURRENT_TIMESTAMP,
                     updated_by = COALESCE(:updated_by, updated_by)
                 WHERE news_id = :news_id AND locale = :locale',
                ($changes + $stored[$locale]) + [
                    'updated_by' => $dto->updated_by,
                    'news_id' => $id,
                    'locale' => $locale,
                ],
            );
        }
    }

    private function insertLocale(int $id, string $locale, array $content, int $updatedBy): void
    {
        $this->connection->insert('news_content', [
            'news_id' => $id,
            'locale' => $locale,
            'header' => $content['header'] ?? null,
            'text' => $content['text'] ?? null,
            'updated_by' => $updatedBy,
        ]);
    }
}
