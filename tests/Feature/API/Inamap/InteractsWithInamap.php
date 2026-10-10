<?php

namespace Domain\Tests\Feature\API\Inamap;

use App\Models\Tenancy;
use App\Models\Tenant;
use App\Models\User;
use Domain\App\Models\Inamap\Inamap;
use Domain\App\Models\Inamap\Layout;
use Domain\Database\Seeders\Extended\PermissionSeeder;
use Domain\Database\Seeders\Extended\RoleSeeder;
use Spatie\Permission\PermissionRegistrar;

trait InteractsWithInamap
{
    protected function seedInamapPermissions(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return array{0: User, 1: Tenant, 2: Tenancy} */
    protected function tenantUser(string $role = 'Tenant', ?Tenancy $tenancy = null, ?Tenant $tenant = null): array
    {
        $tenancy ??= Tenancy::factory()->create();
        $tenant ??= Tenant::factory()->create(['tenancy_id' => $tenancy->id]);

        $user = User::factory()->create(['tenant_id' => $tenant->id, 'tenancy_id' => $tenancy->id]);
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [$user->fresh(), $tenant, $tenancy];
    }

    protected function makeInamap(Tenant $tenant, array $attributes = []): Inamap
    {
        return Inamap::create(array_merge([
            'tenant_id'  => $tenant->id,
            'tenancy_id' => $tenant->tenancy_id,
            'name'       => 'Recinto',
            'slug'       => 'recinto-' . uniqid(),
            'unit'       => 'm',
            'status'     => Inamap::STATUS_PUBLISHED,
            'settings'   => [],
        ], $attributes));
    }

    protected function makeLayout(Inamap $inamap, array $attributes = []): Layout
    {
        return Layout::create(array_merge([
            'tenant_id'  => $inamap->tenant_id,
            'tenancy_id' => $inamap->tenancy_id,
            'inamap_id'  => $inamap->id,
            'name'       => 'Piso 1',
            'level'      => 1,
            'width'      => 100,
            'height'     => 100,
        ], $attributes));
    }

    protected function square(float $x, float $y, float $size): array
    {
        return [[$x, $y], [$x + $size, $y], [$x + $size, $y + $size], [$x, $y + $size]];
    }
}
