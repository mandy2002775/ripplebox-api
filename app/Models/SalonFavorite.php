<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalonFavorite extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'salon_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }
}
