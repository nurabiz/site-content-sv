<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DocDto
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public int $type_id,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255, countUnit: Assert\Length::COUNT_CODEPOINTS)]
        public string $slug,
        #[Assert\All([
            new Assert\NotNull(),
            new Assert\Type('string'),
            new Assert\Length(max: 65535, countUnit: Assert\Length::COUNT_BYTES),
        ])]
        public array $locales = [],
        #[Assert\Range(min: 1, max: 2147483647)]
        public ?int $updated_by = null,
    ) {
    }
}
