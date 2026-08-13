<?php

namespace App\Enums;

enum UserType: string
{
    case Client = 'client';
    case Salon = 'salon';
    case Admin = 'admin';
}
