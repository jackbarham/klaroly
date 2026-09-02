<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * What a booking line is. Custom lines have no rate card row behind them.
 */
enum LineKind: string
{
    use HasCheckConstraint;

    case Service = 'service';
    case Expense = 'expense';
    case Travel = 'travel';
    case Custom = 'custom';
}
