<?php

declare(strict_types=1);

namespace NuraBiz\SiteContent\GraphQl;

use NuraBiz\SiteContent\Repository\DocumentTypesRepository;

enum DocumentType: string
{
    case RULES = DocumentTypesRepository::TYPE_RULES;
    case SERVICES = DocumentTypesRepository::TYPE_SERVICES;
}
