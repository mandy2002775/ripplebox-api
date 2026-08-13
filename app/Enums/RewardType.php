<?php

namespace App\Enums;

enum RewardType: string
{
    case GiftCard = 'gift_card';
    case FreeService = 'free_service';
    case Product = 'product';
    case VipPerk = 'vip_perk';
}
