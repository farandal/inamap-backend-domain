<?php

namespace Domain\App\Models\Inamap;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ruta calculada y congelada, expuesta con un token opaco y un QR (S4).
 * El token nunca se guarda en claro: solo su SHA-256 en token_hash.
 */
class RouteSession extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE  = 'active';
    public const STATUS_REVOKED = 'revoked';

    public const CREATED_BY = ['visitor', 'device', 'editor'];

    protected $table = 'inamap_route_sessions';

    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'route'           => 'array',
        'accessible'      => 'boolean',
        'expires_at'      => 'datetime',
        'first_opened_at' => 'datetime',
        'open_count'      => 'integer',
    ];

    public function inamap(): BelongsTo
    {
        return $this->belongsTo(Inamap::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE && !$this->isExpired();
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
