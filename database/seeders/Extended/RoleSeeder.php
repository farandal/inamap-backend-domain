<?php

namespace Domain\Database\Seeders\Extended;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class RoleSeeder extends Seeder
{
    public function run()
    {
        app()['cache']->forget('spatie.permission.cache');

        $map = [
            Role::NAME_SYSTEM_ADMIN  => 'systemAdminPermissions.json',
            Role::NAME_TENANCY_ADMIN => 'tenantAdminPermissions.json',
            Role::NAME_TENANT_ADMIN  => 'tenantAdminPermissions.json',
            Role::NAME_NORMAL_USER   => 'normalUserPermissions.json',
        ];

        foreach ($map as $roleName => $filename) {
            $role = Role::where('name', $roleName)->where('guard_name', 'api')->first();

            if (!$role) {
                $this->command->warn("Role not found: {$roleName}");
                continue;
            }

            $this->assignDomainPermissions($role, $filename);
        }

        app()['cache']->forget('spatie.permission.cache');
    }

    private function assignDomainPermissions(Role $role, string $filename): void
    {
        $filePath = base_path("domain/database/data/rolePermissions/{$filename}");

        if (!File::exists($filePath)) {
            $this->command->warn("Domain role permissions file not found: {$filename}");
            Log::warning('Domain role permissions file not found', ['file' => $filename]);
            return;
        }

        try {
            $config = json_decode(File::get($filePath), true);

            if (!$config || !isset($config['permissions'])) {
                $this->command->error("Invalid domain role permissions configuration: {$filename}");
                return;
            }

            $permissions = Permission::whereIn('route_name', $config['permissions'])->get();

            if ($permissions->isEmpty()) {
                $this->command->warn("No domain permissions found for role: {$role->name} — run PermissionSeeder first");
                return;
            }

            $role->givePermissionTo($permissions);

            $this->command->info("✓ Added {$permissions->count()} domain permissions to role: {$role->name}");
            Log::info('Domain role permissions assigned', [
                'role'              => $role->name,
                'permissions_count' => $permissions->count(),
                'file'              => $filename,
            ]);

        } catch (\Exception $e) {
            $this->command->error("Error assigning domain permissions to {$role->name}: {$e->getMessage()}");
            Log::error('Error assigning domain role permissions', [
                'role'  => $role->name,
                'file'  => $filename,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
