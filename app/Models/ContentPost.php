<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentPost extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'salon_id',
        'image_path',
        'image_mime',
        'caption',
    ];

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(ContentLike::class);
    }
}
