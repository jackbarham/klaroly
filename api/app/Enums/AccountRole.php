<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * Membership role on an account. Permissions are toggles, not further roles.
 */
enum AccountRole: string
{
    use HasCheckConstraint;

    case Owner = 'owner';
    case Collaborator = 'collaborator';
}
