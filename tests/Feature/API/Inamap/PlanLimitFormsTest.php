<?php

namespace Domain\Tests\Feature\API\Inamap;

use Domain\App\Services\Inamap\Plan\InamapPlanLimits;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The plan form comes from the core's config, which lists KitchnTabs limits
 * (products, push notifications) and had no way to set max_inamaps - so a System
 * admin could never change how many maps a plan allows.
 */
class PlanLimitFormsTest extends TestCase
{
    use DatabaseTransactions, InteractsWithInamap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInamapPermissions();
    }

    #[Test]
    public function the_system_admin_plan_form_offers_the_inamap_limits_and_not_the_other_domains(): void
    {
        [$admin] = $this->tenantUser('System');

        $ids = collect(
            $this->actingAs($admin)->getJson('/api/system/subscription-plan/limitFormats')
                ->assertOk()->json('data.formats') ?? [],
        )->pluck('id')->all();

        foreach (['max_tenants', 'max_users_per_tenant', 'max_inamaps', 'max_layouts_per_inamap', 'max_locations', 'max_active_route_sessions'] as $wanted) {
            $this->assertContains($wanted, $ids);
        }
        $this->assertNotContains('max_products_per_tenant', $ids);
        $this->assertNotContains('feature_push_notifications', $ids);
    }

    #[Test]
    public function every_limit_the_domain_enforces_has_a_field_in_the_form(): void
    {
        $offered = collect(config('subscription_plans.limit_formats'))->pluck('id')->all();

        $this->assertSame([], array_values(array_diff(InamapPlanLimits::KEYS, $offered)));
    }

    #[Test]
    public function the_plan_form_offers_no_marketplace_or_point_of_sale_addons(): void
    {
        [$admin] = $this->tenantUser('System');

        $this->actingAs($admin)->getJson('/api/system/subscription-plan/addonFormats')
            ->assertOk()->assertJsonPath('data.formats', []);
    }
}
