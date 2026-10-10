<?php

namespace Domain\App\Models\Inamap;

use Domain\App\Models\Inamap\Concerns\IsSearchable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Structure extends InamapModel
{
    use IsSearchable;

    protected $table = 'inamap_structures';

    protected $casts = [
        'polygon'  => 'array',
        'terms'    => 'array',
        'metadata' => 'array',
    ];

    protected $hidden = ['search_text'];

    public function layout(): BelongsTo
    {
        return $this->belongsTo(Layout::class);
    }

    public function inamap(): BelongsTo
    {
        return $this->belongsTo(Inamap::class);
    }

    public function locations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Location::class);
    }
}
