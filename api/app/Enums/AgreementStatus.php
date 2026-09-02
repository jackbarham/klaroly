<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * Agreement lifecycle. A signed agreement is never edited.
 */
enum AgreementStatus: string
{
    use HasCheckConstraint;

    case Draft = 'draft';
    case Sent = 'sent';
    case Signed = 'signed';
    case Superseded = 'superseded';
    case Void = 'void';
}
