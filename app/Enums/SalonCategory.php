<?php

namespace App\Enums;

enum SalonCategory: string
{
    case Hair = 'hair';
    case Nails = 'nails';
    case Skin = 'skin';
    case BrowsLashes = 'brows_lashes';
    case Barber = 'barber';
    case Spa = 'spa';
    case Makeup = 'makeup';
    case Other = 'other';
}
