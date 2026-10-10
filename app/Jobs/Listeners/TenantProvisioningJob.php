<?php

namespace Domain\App\Jobs\Listeners;

use App\Models\Tenant;
use Domain\App\Models\Inamap\Category;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * El core despacha este job al crearse un tenant (si la clase existe):
 * siembra las categorías por defecto de INAMAP. Idempotente.
 */
class TenantProvisioningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $tenantId)
    {
    }

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (!$tenant || Category::where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        foreach (config('inamap.default_categories', []) as $index => $category) {
            Category::create(array_merge($category, [
                'tenant_id'  => $tenant->id,
                'tenancy_id' => $tenant->tenancy_id,
                'tree_index' => $index,
            ]));
        }
    }
}
