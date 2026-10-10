<?php

namespace Domain\App\Models\Inamap;

use Domain\App\Models\Inamap\Concerns\IsSearchable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends InamapModel
{
    use IsSearchable;

    protected $table = 'inamap_locations';

    protected $casts = [
        'x'        => 'float',
        'y'        => 'float',
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

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    protected function searchableParts(): array
    {
        $category = $this->category_id ? Category::withTrashed()->find($this->category_id)?->name : null;

        return [$this->label, $this->code, $category, ...array_values((array) ($this->terms ?? []))];
    }
}
