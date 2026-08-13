<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';
}
