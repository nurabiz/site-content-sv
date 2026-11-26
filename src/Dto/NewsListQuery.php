<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class NewsListQuery
{
    public const int MAX_LIMIT = 100;

    public function __construct(
        #[Assert\Range(min: 0, max: self::MAX_LIMIT)]
        public int $limit = 20,
        #[Assert\PositiveOrZero]
        public int $offset = 0,
        public ?bool $valid = null,
        public ?int $client_type = null,
    ) {
    }
}
