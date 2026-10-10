<?php

namespace Domain\Tests\Feature\API\Inamap;

use Domain\App\Models\Inamap\Inamap;
use Domain\App\Models\Inamap\Location;
use Domain\App\Models\Inamap\Path;
use Domain\App\Models\Inamap\RouteSession;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RouteSessionTest extends TestCase
{
    use DatabaseTransactions, InteractsWithInamap;

    private Inamap $inamap;
    private array $body;

    protected function setUp(): void
    {
        parent::setUp();

        [, $tenant] = $this->tenantUser('Tenant');
        $this->inamap = $this->makeInamap($tenant);
        $layout = $this->makeLayout($this->inamap);
        $owned = ['tenant_id' => $tenant->id, 'tenancy_id' => $tenant->tenancy_id, 'inamap_id' => $this->inamap->id, 'layout_id' => $layout->id];

        Path::create($owned + ['points' => [[0, 0], [20, 0]]]);
        $a = Location::create($owned + ['label' => 'A', 'x' => 0, 'y' => 0]);
        $b = Location::create($owned + ['label' => 'B', 'x' => 20, 'y' => 0]);

        $this->body = ['inamap' => $this->inamap->id, 'from' => "location:{$a->id}", 'to' => "location:{$b->id}"];
    }

    #[Test]
    public function an_open_inamap_issues_a_session_with_a_qr_and_hashes_the_token(): void
    {
        $data = $this->postJson('/api/inamap/route-sessions', $this->body)->assertCreated()->json('data');

        $this->assertStringContainsString("#t={$data['token']}", $data['view_url']);
        $this->assertStringStartsWith('<?xml', $data['qr_svg']);
        $this->assertSame(RouteSession::hashToken($data['token']), RouteSession::find($data['session_id'])->token_hash);

        $this->withHeader('X-Inamap-Token', $data['token'])
            ->getJson('/api/inamap/route-sessions/current')
            ->assertOk()
            ->assertJsonPath('data.route.distance', 20);

        $this->assertSame(1, RouteSession::find($data['session_id'])->open_count);
    }

    #[Test]
    public function a_token_required_inamap_rejects_anonymous_reads(): void
    {
        $this->inamap->update(['settings' => ['access_mode' => 'token_required']]);

        $this->getJson("/api/inamap/{$this->inamap->id}")->assertUnauthorized()->assertJsonPath('error.code', 'TOKEN_REQUIRED');
        $this->postJson('/api/inamap/route-sessions', $this->body)->assertForbidden();
    }

    #[Test]
    public function a_device_with_the_access_key_issues_tokens_that_open_the_inamap(): void
    {
        $this->inamap->update(['settings' => ['access_mode' => 'token_required', 'access_key_hash' => hash('sha256', 'imk_test')]]);

        $this->withHeader('X-Inamap-Access-Key', 'wrong')->postJson('/api/inamap/route-sessions', $this->body)
            ->assertForbidden()->assertJsonPath('error.code', 'ACCESS_KEY_INVALID');

        $token = $this->withHeader('X-Inamap-Access-Key', 'imk_test')->postJson('/api/inamap/route-sessions', $this->body)
            ->assertCreated()
            ->json('data.token');

        $this->withHeader('X-Inamap-Token', $token)->getJson("/api/inamap/{$this->inamap->id}")->assertOk();
    }

    #[Test]
    public function a_device_credential_authorizes_the_reads_a_kiosk_needs(): void
    {
        $this->inamap->update(['settings' => ['access_mode' => 'token_required', 'access_key_hash' => hash('sha256', 'imk_test')]]);

        $this->withHeader('X-Inamap-Access-Key', 'imk_test')->getJson("/api/inamap/{$this->inamap->id}")->assertOk();
        $this->withHeader('X-Inamap-Access-Key', 'imk_test')
            ->getJson('/api/inamap/route?' . http_build_query($this->body))
            ->assertOk();
        $this->withHeader('X-Inamap-Access-Key', 'wrong')->getJson("/api/inamap/{$this->inamap->id}")
            ->assertForbidden()->assertJsonPath('error.code', 'ACCESS_KEY_INVALID');
    }

    #[Test]
    public function expired_and_foreign_tokens_are_rejected(): void
    {
        $data = $this->postJson('/api/inamap/route-sessions', $this->body)->assertCreated()->json('data');
        [, $tenant] = $this->tenantUser('Tenant');
        $other = $this->makeInamap($tenant);

        $this->withHeader('X-Inamap-Token', $data['token'])->getJson("/api/inamap/{$other->id}")
            ->assertUnauthorized()->assertJsonPath('error.code', 'TOKEN_INVALID');

        RouteSession::whereKey($data['session_id'])->update(['expires_at' => now()->subMinute()]);

        $this->withHeader('X-Inamap-Token', $data['token'])->getJson('/api/inamap/route-sessions/current')
            ->assertUnauthorized()->assertJsonPath('error.code', 'TOKEN_EXPIRED');
    }
}
