<?php

namespace Domain\App\Models\Inamap;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PathLink extends InamapModel
{
    public const KINDS = ['vertex', 'link', 'vertical'];
    public const ENDS  = ['start', 'end'];

    protected $table = 'inamap_path_links';

    protected $casts = [
        'cost'          => 'float',
        'bidirectional' => 'boolean',
        'metadata'      => 'array',
    ];

    protected $appends = ['is_accessible'];

    public function fromPath(): BelongsTo
    {
        return $this->belongsTo(Path::class, 'from_path_id');
    }

    public function toPath(): BelongsTo
    {
        return $this->belongsTo(Path::class, 'to_path_id');
    }

    public function inamap(): BelongsTo
    {
        return $this->belongsTo(Inamap::class);
    }

    public function getIsAccessibleAttribute(): bool
    {
        if ($this->kind !== 'vertical') {
            return true;
        }

        return (bool) config("inamap.vertical_types.{$this->vertical_type}.accessible", false);
    }
}
