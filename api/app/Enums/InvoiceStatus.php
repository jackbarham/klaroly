<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * Invoice lifecycle. Paid is never a status; it is computed from payments.
 */
enum InvoiceStatus: string
{
    use HasCheckConstraint;

    case Draft = 'draft';
    case Issued = 'issued';
    case Void = 'void';
}
