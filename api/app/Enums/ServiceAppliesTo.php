<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * Which event types a rate card row can be added to.
 */
enum ServiceAppliesTo: string
{
    use HasCheckConstraint;

    case Main = 'main';
    case Trial = 'trial';
    case Both = 'both';
}
