<?php

namespace Domain\App\Models\Inamap;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Path extends InamapModel
{
    public const DIRECTIONS = ['forward', 'backward', 'both'];

    protected $table = 'inamap_paths';

    protected $casts = [
        'points'   => 'array',
        'metadata' => 'array',
    ];

    public function layout(): BelongsTo
    {
        return $this->belongsTo(Layout::class);
    }

    public function inamap(): BelongsTo
    {
        return $this->belongsTo(Inamap::class);
    }

    public function outgoingLinks(): HasMany
    {
        return $this->hasMany(PathLink::class, 'from_path_id');
    }

    public function incomingLinks(): HasMany
    {
        return $this->hasMany(PathLink::class, 'to_path_id');
    }
}
