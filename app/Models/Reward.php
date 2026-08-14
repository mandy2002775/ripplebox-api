<?php

namespace App\Models;

use App\Enums\RecipientType;
use App\Enums\RewardType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reward extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'salon_id',
        'reward_type',
        'reward_value',
        'description',
        'recipient_type',
        'expiry_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'reward_type' => RewardType::class,
            'recipient_type' => RecipientType::class,
            'reward_value' => 'decimal:2',
            'expiry_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }
}
