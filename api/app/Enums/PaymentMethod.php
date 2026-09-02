<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * How a payment was made.
 */
enum PaymentMethod: string
{
    use HasCheckConstraint;

    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Card = 'card';
    case Stripe = 'stripe';
    case Other = 'other';
}
