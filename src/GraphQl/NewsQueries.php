<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\GraphQl;

use GraphQL\Error\UserError;
use NuraBiz\SiteContent\Dto\NewsListQuery;
use NuraBiz\SiteContent\Repository\NewsRepository;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final readonly class NewsQueries
{
    public function __construct(private NewsRepository $news)
    {
    }

    public function list(int $limit, int $offset, ?bool $valid, ?int $clientType): array
    {
        if ($limit < 0 || $limit > NewsListQuery::MAX_LIMIT || $offset < 0) {
            throw new UserError(sprintf(
                'Limit must be between 0 and %d and offset must be non-negative.',
                NewsListQuery::MAX_LIMIT,
            ));
        }

        return array_map(self::formatDates(...), $this->news->list($limit, $offset, $valid, $clientType));
    }

    public function find(int $id, ?string $locale = null): ?array
    {
        $news = $this->news->find($id, $locale);

        return null === $news ? null : self::formatDates($news);
    }

    private static function formatDates(array $news): array
    {
        $timezone = new \DateTimeZone('UTC');
        foreach (['created_at', 'start_at', 'finish_at'] as $field) {
            if (null !== $news[$field]) {
                $news[$field] = new \DateTimeImmutable($news[$field], $timezone);
            }
        }

        foreach ($news['locales'] as &$content) {
            $content['created_at'] = new \DateTimeImmutable($content['created_at'], $timezone);
            $content['updated_at'] = new \DateTimeImmutable($content['updated_at'], $timezone);
        }
        unset($content);

        return $news;
    }
}
