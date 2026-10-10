<?php

namespace Domain\Database\Seeders\Extended;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()['cache']->forget('spatie.permission.cache');

        $filePath = dirname(__DIR__, 2) . '/data/permissions.json';

        if (!File::exists($filePath)) {
            $this->command?->warn("Domain permissions file not found: {$filePath}");
            return;
        }

        $permissions = json_decode(File::get($filePath), true);

        if (!is_array($permissions)) {
            $this->command?->error("Invalid domain permissions configuration: {$filePath}");
            Log::error('Invalid domain permissions configuration', ['file' => $filePath]);
            return;
        }

        $records = [];

        foreach ($permissions as $group) {
            if (!isset($group['group'], $group['level'], $group['labels']) || !is_array($group['labels'])) {
                continue;
            }

            foreach ($group['labels'] as $label) {
                if (!isset($label['route_name'])) {
                    continue;
                }

                $records[] = [
                    'name' => $label['name'] ?? $label['route_name'],
                    'route_name' => $label['route_name'],
                    'group' => $group['group'],
                    'level' => (int) $group['level'],
                    'guard_name' => 'api',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        Permission::insertOrIgnore($records);

        $this->command?->info('Domain permissions seeded successfully: ' . count($records));

        app()['cache']->forget('spatie.permission.cache');
    }
}