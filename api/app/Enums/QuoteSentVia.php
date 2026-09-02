<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * How a quote reached the client.
 */
enum QuoteSentVia: string
{
    use HasCheckConstraint;

    case Copy = 'copy';
    case Email = 'email';
    case Pdf = 'pdf';
}
