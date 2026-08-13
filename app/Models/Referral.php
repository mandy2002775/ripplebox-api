<?php

namespace App\Models;

use App\Enums\ReferralStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Referral extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'referrer_client_id',
        'referred_client_id',
        'salon_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReferralStatus::class,
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'referrer_client_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'referred_client_id');
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function redemption(): HasOne
    {
        return $this->hasOne(Redemption::class);
    }
}
