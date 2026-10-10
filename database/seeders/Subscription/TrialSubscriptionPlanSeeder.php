<?php

namespace Domain\Database\Seeders\Subscription;

use App\Models\Subscription\SubscriptionPlan;
use Illuminate\Database\Seeder;

/**
 * Plan de prueba por defecto de INAMAP: el registro de prueba usa el plan
 * `is_default` + `is_active` cuando no se elige uno (SubscriptionPlan::getDefault()).
 *
 * Límites (README §3.7): 1 tenant y 1 inamap. Las claves max_inamaps,
 * max_layouts_per_inamap, max_locations y max_active_route_sessions las aplica
 * el dominio (InamapPlanLimits); max_tenants y max_users_per_tenant, el core.
 *
 * Precio: 0 hasta que se defina la oferta comercial; se edita desde
 * inamap-system (Planes). Idempotente: no sobrescribe un plan ya ajustado a mano
 * salvo los campos de abajo.
 */
class TrialSubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionPlan::updateOrCreate(
            ['slug' => 'trial'],
            [
                'name' => 'Prueba',
                'description' => 'Prueba de 30 días: 1 cliente y 1 inamap.',
                'tier' => 1,
                'prices' => ['CLP' => 0, 'USD' => 0],
                'billing_cycle' => 'monthly',
                'trial_days' => 30,
                'subscription_plan_type' => 'public',
                'is_active' => true,
                'is_default' => true,
                'addons' => [],
                'addon_prices' => [],
                'limits' => [
                    'max_tenants' => 1,
                    'max_users_per_tenant' => 5,
                    'max_inamaps' => 1,
                    'max_layouts_per_inamap' => 5,
                    'max_locations' => 200,
                    'max_active_route_sessions' => 200,
                ],
            ]
        );

        $this->command?->info('INAMAP: plan de prueba creado/actualizado');
    }
}
