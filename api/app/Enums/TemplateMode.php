<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * What happens with a rendered message. Only copy is used in the first build.
 */
enum TemplateMode: string
{
    use HasCheckConstraint;

    case Copy = 'copy';
    case Send = 'send';
    case Automate = 'automate';
}
