<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DocContentUpdateDto
{
    public function __construct(
        #[Assert\Length(max: 65535, countUnit: Assert\Length::COUNT_BYTES)]
        public string $doc_content,
    ) {
    }
}
