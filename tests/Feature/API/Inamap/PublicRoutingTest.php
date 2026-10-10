<?php

namespace Domain\Tests\Feature\API\Inamap;

use App\Models\Tenant;
use Domain\App\Models\Inamap\Inamap;
use Domain\App\Models\Inamap\Location;
use Domain\App\Models\Inamap\Path;
use Domain\App\Models\Inamap\PathLink;
use Domain\App\Models\Inamap\Zone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Inamap de dos pisos: el piso 1 llega a una escalera (corta) y a un
 * ascensor (largo); ambos suben al piso 2, donde está la cafetería.
 */
class PublicRoutingTest extends TestCase
{
    use DatabaseTransactions, InteractsWithInamap;

    private Inamap $inamap;
    private Location $entrance;
    private Location $cafe;
    private Zone $hall;

    protected function setUp(): void
    {
        parent::setUp();

        [, $tenant] = $this->tenantUser('Tenant');
        $this->inamap = $this->makeInamap($tenant);
        $floor1 = $this->makeLayout($this->inamap, ['name' => 'Piso 1', 'level' => 1]);
        $floor2 = $this->makeLayout($this->inamap, ['name' => 'Piso 2', 'level' => 2]);

        $path = fn ($layout, $points, $direction = 'both') => Path::create($this->owned($tenant, $layout) + ['points' => $points, 'direction' => $direction]);
        $toStairs = $path($floor1, [[0, 0], [10, 0]]);
        $toElevator = $path($floor1, [[0, 0], [0, 30]]);
        $fromStairs = $path($floor2, [[10, 0], [10, 10]]);
        $fromElevator = $path($floor2, [[0, 30], [10, 10]]);

        $link = fn ($from, $to, $type) => PathLink::create([
            'tenant_id' => $tenant->id, 'tenancy_id' => $tenant->tenancy_id, 'inamap_id' => $this->inamap->id,
            'from_path_id' => $from->id, 'from_end' => 'end', 'to_path_id' => $to->id, 'to_end' => 'start',
            'kind' => 'vertical', 'vertical_type' => $type,
        ]);
        $link($toStairs, $fromStairs, 'stairs');
        $link($toElevator, $fromElevator, 'elevator');

        $this->entrance = Location::create($this->owned($tenant, $floor1) + ['label' => 'Acceso Principal', 'code' => 'A1', 'x' => 0, 'y' => 0]);
        $this->cafe = Location::create($this->owned($tenant, $floor2) + ['label' => 'Cafetería', 'code' => 'C2', 'x' => 10, 'y' => 10, 'terms' => ['café', 'comida']]);
        $this->hall = Zone::create($this->owned($tenant, $floor1) + ['label' => 'Hall', 'polygon' => $this->square(-2, -2, 4)]);
    }

    private function owned(Tenant $tenant, $layout): array
    {
        return ['tenant_id' => $tenant->id, 'tenancy_id' => $tenant->tenancy_id, 'inamap_id' => $this->inamap->id, 'layout_id' => $layout->id];
    }

    private function routeUrl(array $extra = []): string
    {
        return '/api/inamap/route?' . http_build_query(array_merge([
            'inamap' => $this->inamap->id,
            'from'   => "location:{$this->entrance->id}",
            'to'     => "location:{$this->cafe->id}",
        ], $extra));
    }

    #[Test]
    public function the_public_payload_contains_everything_needed_to_draw_the_map(): void
    {
        $this->getJson("/api/inamap/{$this->inamap->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.layouts')
            ->assertJsonCount(2, 'data.locations')
            ->assertJsonCount(1, 'data.zones')
            ->assertJsonMissingPath('data.paths');
    }

    #[Test]
    public function an_unpublished_inamap_is_not_available(): void
    {
        $this->inamap->update(['status' => 'draft']);

        $this->getJson("/api/inamap/{$this->inamap->id}")->assertNotFound()->assertJsonPath('error.code', 'INAMAP_NOT_PUBLISHED');
    }

    #[Test]
    public function the_shortest_route_uses_the_stairs(): void
    {
        $this->getJson($this->routeUrl())
            ->assertOk()
            ->assertJsonCount(2, 'data.steps')
            ->assertJsonPath('data.steps.0.transition.vertical_type', 'stairs')
            ->assertJsonPath('data.steps.0.layout_name', 'Piso 1');
    }

    #[Test]
    public function the_accessible_route_uses_the_elevator(): void
    {
        $this->getJson($this->routeUrl(['accessible' => 1]))
            ->assertOk()
            ->assertJsonPath('data.accessible', true)
            ->assertJsonPath('data.steps.0.transition.vertical_type', 'elevator');
    }

    #[Test]
    public function walking_speed_comes_from_tenant_settings(): void
    {
        $tenant = Tenant::find($this->inamap->tenant_id);
        $tenant->settings = array_merge($tenant->settings ?? [], ['inamap_walking_speed_mps' => 2.0]);
        $tenant->save();

        $this->getJson($this->routeUrl())->assertOk()->assertJsonPath('data.walking_speed_mps', 2);
    }

    #[Test]
    public function a_zone_can_be_the_origin(): void
    {
        $this->getJson($this->routeUrl(['from' => "zone:{$this->hall->id}"]))->assertOk();
    }

    #[Test]
    public function an_endpoint_from_another_inamap_is_rejected(): void
    {
        [, $tenant] = $this->tenantUser('Tenant');
        $other = $this->makeInamap($tenant);
        $foreign = Location::create(array_merge($this->owned($tenant, $this->makeLayout($other)), ['inamap_id' => $other->id, 'label' => 'X', 'x' => 1, 'y' => 1]));

        $this->getJson($this->routeUrl(['to' => "location:{$foreign->id}"]))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ENDPOINT_NOT_FOUND');
    }

    #[Test]
    public function fuzzy_search_tolerates_accents_and_typos(): void
    {
        $search = fn ($q) => collect($this->getJson('/api/inamap/search?' . http_build_query(['inamap' => $this->inamap->id, 'q' => $q]))->assertOk()->json('data'))->pluck('id');

        $this->assertContains($this->cafe->id, $search('cafeteria'));
        $this->assertContains($this->cafe->id, $search('cafetria'));
        $this->assertContains($this->cafe->id, $search('COMIDA'));
        $this->assertContains($this->entrance->id, $search('a1'));
    }
}
