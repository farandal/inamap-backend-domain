<?php

namespace Domain\App\Models\Inamap;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Categoría de ubicaciones. Sigue el modelo de categorías de catálogo del
 * framework: árbol por `category_id` (padre), imagen, `tree_index`, nombre con
 * ruta (`breadcrumbed_name`) y filas de alcance tenancy (tenant_id null).
 */
class Category extends InamapModel
{
    protected $table = 'inamap_categories';

    protected $casts = ['tree_index' => 'integer'];

    protected $appends = ['image_url'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('tree_index')->orderBy('name');
    }

    public function subcategoriesTree(): HasMany
    {
        return $this->subcategories()->with('subcategoriesTree');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    protected function breadcrumbedName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->category ? $this->category->breadcrumbed_name . " > {$this->name}" : $this->name,
        );
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

            return url('/api/storage/' . ltrim($this->image_path, '/'));
        });
    }

    /** Un tenant ve sus categorías y las de alcance tenancy. */
    protected function scopeToTenant(Builder $query, string $tenantId): Builder
    {
        $tenancyId = \App\Models\Tenant::whereKey($tenantId)->value('tenancy_id');

        return $query->where(function (Builder $q) use ($tenantId, $tenancyId) {
            $q->where('inamap_categories.tenant_id', $tenantId);
            if ($tenancyId) {
                $q->orWhere(fn (Builder $shared) => $shared
                    ->whereNull('inamap_categories.tenant_id')
                    ->where('inamap_categories.tenancy_id', $tenancyId));
            }
        });
    }

    public function canBeManagedBy(?\App\Models\User $user): bool
    {
        // Las de alcance tenancy solo las gestiona el TenancyAdmin (o SystemAdmin).
        if ($this->tenant_id === null && $user && !$user->isSystemAdmin()) {
            return $user->hasRole('TenancyAdmin') && (string) $user->tenancy_id === (string) $this->tenancy_id;
        }

        return parent::canBeManagedBy($user);
    }
}
