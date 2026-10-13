<?php

namespace Domain\Tests\Feature\API\Inamap;

use Domain\App\Models\Inamap\Inamap;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InamapCrudTest extends TestCase
{
    use DatabaseTransactions, InteractsWithInamap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInamapPermissions();
    }

    #[Test]
    public function zone_overlap_is_off_by_default_and_the_setting_is_saved_and_validated(): void
    {
        [$user, $tenant] = $this->tenantUser('Tenant');

        $id = $this->actingAs($user)->postJson('/api/inamaps', ['name' => 'Campus'])
            ->assertCreated()->assertJsonPath('data.settings.allow_zone_overlap', false)->json('data.id');

        $this->actingAs($user)->putJson("/api/inamaps/{$id}", ['settings' => ['allow_zone_overlap' => true]])
            ->assertOk()->assertJsonPath('data.settings.allow_zone_overlap', true);
        $this->assertTrue(Inamap::find($id)->allowsZoneOverlap());

        // Another setting later never switches it back off (omission does not erase).
        $this->actingAs($user)->putJson("/api/inamaps/{$id}", ['settings' => ['primary_color' => '#112233']])
            ->assertOk()->assertJsonPath('data.settings.allow_zone_overlap', true);

        $this->actingAs($user)->putJson("/api/inamaps/{$id}", ['settings' => ['allow_zone_overlap' => 'maybe']])
            ->assertUnprocessable();

        $this->actingAs($user)->putJson("/api/inamaps/{$id}", ['settings' => ['allow_zone_overlap' => false]])
            ->assertOk()->assertJsonPath('data.settings.allow_zone_overlap', false);
    }

    #[Test]
    public function a_tenant_admin_creates_an_inamap_in_their_own_tenant(): void
    {
        [$user, $tenant] = $this->tenantUser('Tenant');

        $response = $this->actingAs($user)->postJson('/api/inamaps', [
            'name'     => 'Campus Central',
            'settings' => ['access_mode' => 'open', 'access_key_hash' => 'injected'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.slug', 'campus-central')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.unit', 'm')
            ->assertJsonPath('data.settings.has_access_key', false);

        $this->assertArrayNotHasKey('access_key_hash', Inamap::find($response->json('data.id'))->settings);
    }

    #[Test]
    public function the_trial_plan_allows_a_single_inamap(): void
    {
        [$user] = $this->tenantUser('Tenant');

        $this->actingAs($user)->postJson('/api/inamaps', ['name' => 'Uno'])->assertCreated();
        $this->actingAs($user)->postJson('/api/inamaps', ['name' => 'Dos'])->assertForbidden();
    }

    #[Test]
    public function inamaps_of_another_tenant_are_invisible(): void
    {
        [$user] = $this->tenantUser('Tenant');
        [, $otherTenant] = $this->tenantUser('Tenant');
        $foreign = $this->makeInamap($otherTenant);

        $this->actingAs($user)->getJson("/api/inamaps/{$foreign->id}")->assertNotFound();
        $this->actingAs($user)->putJson("/api/inamaps/{$foreign->id}", ['name' => 'X'])->assertNotFound();
        $this->assertNotContains($foreign->id, collect($this->actingAs($user)->getJson('/api/inamaps')->json('data'))->pluck('id'));
    }

    #[Test]
    public function an_editor_cannot_create_or_publish_but_can_edit(): void
    {
        [$admin, $tenant, $tenancy] = $this->tenantUser('Tenant');
        [$editor] = $this->tenantUser('User', $tenancy, $tenant);
        $inamap = $this->makeInamap($tenant, ['status' => 'draft']);

        $this->actingAs($editor)->postJson('/api/inamaps', ['name' => 'Nuevo'])->assertForbidden();
        $this->actingAs($editor)->postJson("/api/inamaps/{$inamap->id}/publish")->assertForbidden();
        $this->actingAs($editor)->putJson("/api/inamaps/{$inamap->id}", ['name' => 'Renombrado'])->assertOk();

        $this->actingAs($admin)->postJson("/api/inamaps/{$inamap->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    #[Test]
    public function a_tenancy_admin_switched_into_a_tenant_creates_there(): void
    {
        [$admin, , $tenancy] = $this->tenantUser('TenancyAdmin');
        [, $target] = $this->tenantUser('Tenant', $tenancy);
        [, $outsider] = $this->tenantUser('Tenant');

        $this->actingAs($admin)->withHeader('X-Tenant-Id', $target->id)
            ->postJson('/api/inamaps', ['name' => 'Sede'])
            ->assertCreated()
            ->assertJsonPath('data.tenant_id', $target->id);

        $this->actingAs($admin)->withHeader('X-Tenant-Id', $outsider->id)
            ->postJson('/api/inamaps', ['name' => 'Ajeno'])
            ->assertUnprocessable();
    }

    #[Test]
    public function rotating_the_access_key_returns_it_once_and_stores_only_a_hash(): void
    {
        [$user, $tenant] = $this->tenantUser('Tenant');
        $inamap = $this->makeInamap($tenant);

        $key = $this->actingAs($user)->postJson("/api/inamaps/{$inamap->id}/access-key/rotate")
            ->assertOk()
            ->json('data.access_key');

        $this->assertSame(hash('sha256', $key), $inamap->fresh()->accessKeyHash());
        $this->actingAs($user)->getJson("/api/inamaps/{$inamap->id}")
            ->assertJsonPath('data.settings.has_access_key', true)
            ->assertJsonMissingPath('data.settings.access_key_hash');
    }
}
