<?php

namespace Domain\App\Models\Inamap;

use Domain\App\Models\Inamap\Concerns\IsSearchable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Zone extends InamapModel
{
    use IsSearchable;

    protected $table = 'inamap_zones';

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
}
