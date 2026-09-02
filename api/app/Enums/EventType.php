<?php

namespace App\Enums;

use App\Enums\Concerns\HasCheckConstraint;

/**
 * The kind of dated occasion on a booking. At most one main event per booking.
 */
enum EventType: string
{
    use HasCheckConstraint;

    case Main = 'main';
    case Trial = 'trial';
    case Consultation = 'consultation';
    case Shoot = 'shoot';
    case Setup = 'setup';
    case Delivery = 'delivery';
    case Collection = 'collection';
    case Other = 'other';
}
