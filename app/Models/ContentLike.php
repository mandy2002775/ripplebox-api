<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentLike extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'content_post_id',
        'client_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function contentPost(): BelongsTo
    {
        return $this->belongsTo(ContentPost::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
