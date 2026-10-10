<?php

namespace Domain\Tests\Feature\API\Inamap;

use Domain\App\Models\Inamap\Location;
use Domain\App\Models\Inamap\Zone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LayoutGeometryTest extends TestCase
{
    use DatabaseTransactions, InteractsWithInamap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInamapPermissions();
    }

    #[Test]
    public function overlapping_zones_are_rejected_but_adjacent_ones_are_not(): void
    {
        [$user, $tenant] = $this->tenantUser('Tenant');
        $layout = $this->makeLayout($this->makeInamap($tenant));

        $this->actingAs($user)->postJson('/api/inamap/zones', [
            'layout_id' => $layout->id, 'label' => 'A', 'polygon' => $this->square(0, 0, 10),
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/inamap/zones', [
            'layout_id' => $layout->id, 'label' => 'B', 'polygon' => $this->square(10, 0, 10),
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/inamap/zones', [
            'layout_id' => $layout->id, 'label' => 'C', 'polygon' => $this->square(5, 5, 10),
        ])->assertUnprocessable()->assertJsonValidationErrors('polygon');
    }

    #[Test]
    public function overlapping_structures_only_warn(): void
    {
        [$user, $tenant] = $this->tenantUser('Tenant');
        $layout = $this->makeLayout($this->makeInamap($tenant));

        $this->actingAs($user)->postJson('/api/inamap/structures', [
            'layout_id' => $layout->id, 'label' => 'Pabellón 1', 'polygon' => $this->square(0, 0, 10),
        ])->assertCreated()->assertJsonCount(0, 'warnings');

        $this->actingAs($user)->postJson('/api/inamap/structures', [
            'layout_id' => $layout->id, 'label' => 'Pabellón 2', 'polygon' => $this->square(5, 5, 10),
        ])->assertCreated()->assertJsonPath('warnings.0.code', 'STRUCTURE_OVERLAP');
    }

    #[Test]
    public function self_intersecting_polygons_are_rejected(): void
    {
        [$user, $tenant] = $this->tenantUser('Tenant');
        $layout = $this->makeLayout($this->makeInamap($tenant));

        $this->actingAs($user)->postJson('/api/inamap/zones', [
            'layout_id' => $layout->id, 'label' => 'Moño', 'polygon' => [[0, 0], [10, 10], [10, 0], [0, 10]],
        ])->assertUnprocessable()->assertJsonValidationErrors('polygon');
    }

    #[Test]
    public function the_geometry_document_is_saved_atomically_with_client_ids(): void
    {
        [$user, $tenant] = $this->tenantUser('Tenant');
        $layout = $this->makeLayout($this->makeInamap($tenant));
        $stale = Zone::create([
            'tenant_id' => $tenant->id, 'tenancy_id' => $tenant->tenancy_id, 'inamap_id' => $layout->inamap_id,
            'layout_id' => $layout->id, 'label' => 'Vieja', 'polygon' => $this->square(50, 50, 5),
        ]);

        $response = $this->actingAs($user)->putJson("/api/inamap/layouts/{$layout->id}/geometry", [
            'zones'      => [['client_id' => 'z1', 'label' => 'Norte', 'polygon' => $this->square(0, 0, 20)]],
            'structures' => [['client_id' => 's1', 'label' => 'Edificio', 'polygon' => $this->square(2, 2, 5)]],
            'locations'  => [['client_id' => 'l1', 'label' => 'Recepción', 'x' => 3, 'y' => 3, 'structure_client_id' => 's1']],
            'paths'      => [['client_id' => 'p1', 'points' => [[0, 0], [20, 0]], 'direction' => 'both']],
        ])->assertOk();

        $idMap = $response->json('id_map');
        $this->assertSoftDeleted($stale);
        $this->assertSame($idMap['s1'], Location::find($idMap['l1'])->structure_id);
        $response->assertJsonCount(1, 'data.zones')->assertJsonCount(1, 'data.paths');
    }

    #[Test]
    public function an_invalid_geometry_document_changes_nothing(): void
    {
        [$user, $tenant] = $this->tenantUser('Tenant');
        $layout = $this->makeLayout($this->makeInamap($tenant));

        $this->actingAs($user)->putJson("/api/inamap/layouts/{$layout->id}/geometry", [
            'zones'     => [
                ['client_id' => 'a', 'label' => 'A', 'polygon' => $this->square(0, 0, 10)],
                ['client_id' => 'b', 'label' => 'B', 'polygon' => $this->square(5, 5, 10)],
            ],
            'locations' => [['client_id' => 'l1', 'label' => 'X', 'x' => 1, 'y' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['zones.0.polygon', 'zones.1.polygon']);

        $this->assertSame(0, Zone::where('layout_id', $layout->id)->count());
        $this->assertSame(0, Location::where('layout_id', $layout->id)->count());
    }

    #[Test]
    public function a_layout_of_another_tenant_cannot_receive_geometry(): void
    {
        [$user] = $this->tenantUser('Tenant');
        [, $other] = $this->tenantUser('Tenant');
        $layout = $this->makeLayout($this->makeInamap($other));

        $this->actingAs($user)->putJson("/api/inamap/layouts/{$layout->id}/geometry", ['zones' => []])->assertNotFound();
        $this->actingAs($user)->postJson('/api/inamap/zones', [
            'layout_id' => $layout->id, 'label' => 'Intrusa', 'polygon' => $this->square(0, 0, 5),
        ])->assertUnprocessable()->assertJsonValidationErrors('layout_id');
    }
}
