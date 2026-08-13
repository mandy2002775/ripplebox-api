<?php

namespace App\Models;

use App\Enums\PlanType;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'salon_id',
        'stripe_subscription_id',
        'plan_type',
        'status',
        'current_period_end',
    ];

    protected function casts(): array
    {
        return [
            'plan_type' => PlanType::class,
            'status' => SubscriptionStatus::class,
            'current_period_end' => 'datetime',
        ];
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }
}
