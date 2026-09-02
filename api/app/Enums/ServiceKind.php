<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * What a rate card row is.
 */
enum ServiceKind: string
{
    use HasCheckConstraint;

    case Service = 'service';
    case Expense = 'expense';
    case Travel = 'travel';
}
