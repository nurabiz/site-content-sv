<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\Dto;

use NuraBiz\SiteContent\Repository\LocalesRepository;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class NewsDto
{
    public function __construct(
        public bool $valid,
        #[Assert\Range(min: 1, max: 2147483647)]
        public int $userid,
        #[Assert\NotBlank]
        #[Assert\DateTime]
        public string $start_at,
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\DateTime]
        public ?string $finish_at = null,
        #[Assert\Range(min: 0, max: 2147483647)]
        public int $client_type = 0,
        #[Assert\Length(max: 100, countUnit: Assert\Length::COUNT_CODEPOINTS)]
        public ?string $image = null,
        #[Assert\All([
            new Assert\NotNull(),
            new Assert\Collection(fields: [
                'header' => new Assert\Optional([
                    new Assert\Type('string'),
                    new Assert\Length(max: 250, countUnit: Assert\Length::COUNT_CODEPOINTS),
                ]),
                'text' => new Assert\Optional([
                    new Assert\Type('string'),
                    new Assert\Length(max: 65535, countUnit: Assert\Length::COUNT_BYTES),
                ]),
            ]),
        ])]
        public array $locales = [],
        #[Assert\Range(min: 1, max: 2147483647)]
        public ?int $updated_by = null,
    ) {
    }

    #[Assert\Callback]
    public function validateLocales(ExecutionContextInterface $context): void
    {
        foreach (array_keys($this->locales) as $locale) {
            if (!\in_array($locale, LocalesRepository::LIST, true)) {
                $context->buildViolation('Unknown news locale.')
                    ->atPath('locales['.$locale.']')
                    ->addViolation();
            }
        }
    }
}
