<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SalonLead extends Model
{
    use HasUuids;

    protected $fillable = [
        'business_name',
        'owner_name',
        'phone_number',
        'email',
        'location',
        'source',
    ];
}
