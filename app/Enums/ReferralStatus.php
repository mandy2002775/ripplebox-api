<?php

namespace App\Enums;

enum ReferralStatus: string
{
    case Pending = 'pending';
    case Engaged = 'engaged';
    case Redeemed = 'redeemed';
}
