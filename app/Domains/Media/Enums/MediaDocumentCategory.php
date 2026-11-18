<?php

declare(strict_types=1);

namespace App\Domains\Media\Enums;

enum MediaDocumentCategory: string
{
    case IDENTITY = 'identity';
    case LEGAL = 'legal';
    case ADMINISTRATIVE = 'administrative';
    case CERTIFICATION = 'certification';
    case OTHER = 'other';
}
