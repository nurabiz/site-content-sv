<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DocContentDto
{
    public function __construct(
        #[Assert\Regex(pattern: '/\A[a-z]{2}\z/i')]
        public string $lang_id,
        #[Assert\Length(max: 65535, countUnit: Assert\Length::COUNT_BYTES)]
        public string $doc_content,
    ) {
    }
}
