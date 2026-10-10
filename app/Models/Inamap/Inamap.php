<?php

namespace Domain\App\Models\Inamap;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Inamap extends InamapModel
{
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public const ACCESS_OPEN           = 'open';
    public const ACCESS_TOKEN_REQUIRED = 'token_required';

    protected $table = 'inamaps';

    protected $casts = [
        'settings'     => 'array',
        'is_active'    => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * `settings` puede contener access_key_hash: nunca se serializa en crudo.
     * Los Resources exponen publicSettings() en su lugar.
     */
    protected $hidden = ['settings'];

    public function layouts(): HasMany
    {
        return $this->hasMany(Layout::class)->orderBy('level')->orderBy('sort');
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

    public function pathLinks(): HasMany
    {
        return $this->hasMany(PathLink::class);
    }

    public function routeSessions(): HasMany
    {
        return $this->hasMany(RouteSession::class);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && $this->is_active;
    }

    public function accessMode(): string
    {
        $mode = $this->settings['access_mode'] ?? self::ACCESS_OPEN;

        return in_array($mode, config('inamap.access_modes'), true) ? $mode : self::ACCESS_OPEN;
    }

    public function requiresToken(): bool
    {
        return $this->accessMode() === self::ACCESS_TOKEN_REQUIRED;
    }

    public function sessionTtlMinutes(): int
    {
        $ttl = (int) ($this->settings['session_ttl_minutes'] ?? config('inamap.route_sessions.default_ttl_minutes'));

        return max(1, min($ttl, (int) config('inamap.route_sessions.max_ttl_minutes')));
    }

    public function accessKeyHash(): ?string
    {
        return $this->settings['access_key_hash'] ?? null;
    }

    /** Ajustes visibles hacia afuera (sin secretos). */
    public function publicSettings(): array
    {
        $settings = $this->settings ?? [];
        unset($settings['access_key_hash']);
        $settings['access_mode'] = $this->accessMode();
        $settings['session_ttl_minutes'] = $this->sessionTtlMinutes();
        $settings['has_access_key'] = $this->accessKeyHash() !== null;

        return $settings;
    }

    public function unitToMeters(): float
    {
        return (float) (config("inamap.units.{$this->unit}.to_meters") ?? 1.0);
    }
}
