<?php

namespace App\Enums;

enum PlanType: string
{
    case Monthly = 'monthly';
    case Annual = 'annual';

    /**
     * Monthly-equivalent price, for revenue estimates — $49/mo or
     * $490/yr amortized to a monthly figure.
     */
    public function monthlyPrice(): float
    {
        return match ($this) {
            self::Monthly => 49.00,
            self::Annual => 490.00 / 12,
        };
    }
}
