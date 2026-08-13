<?php

namespace App\Enums;

enum RecipientType: string
{
    case Both = 'both';
    case Referrer = 'referrer';
    case NewClient = 'new_client';
}
