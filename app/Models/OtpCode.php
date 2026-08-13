<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OtpCode extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'phone_number',
        'code',
        'expires_at',
        'consumed_at',
    ];

    protected $hidden = [
        'code',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
