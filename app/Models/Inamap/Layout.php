<?php

namespace Domain\App\Models\Inamap;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Layout extends InamapModel
{
    protected $table = 'inamap_layouts';

    protected $casts = [
        'level'  => 'integer',
        'sort'   => 'integer',
        'width'  => 'float',
        'height' => 'float',
    ];

    protected $appends = ['image_url'];

    public function inamap(): BelongsTo
    {
        return $this->belongsTo(Inamap::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    public function structures(): HasMany
    {
        return $this->hasMany(Structure::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function paths(): HasMany
    {
        return $this->hasMany(Path::class);
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::make(get: function () {
            if (!$this->image_path) {
                return null;
            }

            if (config('app.media_disk') === 's3') {
                return Storage::disk('s3')->url($this->image_path);
            }

            // Servido por el FileController del core en /api/storage/{path}.
            return url('/api/storage/' . ltrim($this->image_path, '/'));
        });
    }
}
