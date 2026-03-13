<?php

namespace Tests\Feature\LaunchPad;

use App\Models\SideHustle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LaunchPad — Business Model Canvas tests.
 *
 * Required:
 *   TC-Q2-002 (HL-BMC-Update)
 */
class BusinessModelCanvasTest extends TestCase
{
    use RefreshDatabase;

    private function createSideHustleWithBmc(User $user): SideHustle
    {
        $sideHustle = SideHustle::factory()->create(['student_id' => $user->id]);
        $sideHustle->bmc()->create([]);
        return $sideHustle;
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/sidehustles/1/bmc')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // TC-Q2-002  HL-BMC-Update
    // PUT /api/sidehustles/{id}/bmc → sections persist correctly
    // -------------------------------------------------------------------------

    public function test_user_can_update_bmc_sections(): void
    {
        $user       = User::factory()->create();
        $sideHustle = $this->createSideHustleWithBmc($user);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/sidehustles/{$sideHustle->id}/bmc", [
                'key_partners'       => 'Local farms',
                'value_propositions' => 'Premium artisanal butters',
            ]);

        $response->assertOk()
            ->assertJsonPath('key_partners', 'Local farms')
            ->assertJsonPath('value_propositions', 'Premium artisanal butters');

        $this->assertDatabaseHas('business_model_canvases', [
            'side_hustle_id'   => $sideHustle->id,
            'key_partners'     => 'Local farms',
            'value_propositions' => 'Premium artisanal butters',
        ]);
    }

    public function test_unset_bmc_sections_remain_null_after_partial_update(): void
    {
        $user       = User::factory()->create();
        $sideHustle = $this->createSideHustleWithBmc($user);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/sidehustles/{$sideHustle->id}/bmc", [
                'revenue_streams' => 'Direct sales',
            ]);

        $bmc = $sideHustle->bmc()->first();
        $this->assertNull($bmc->key_partners);
        $this->assertNull($bmc->channels);
        $this->assertEquals('Direct sales', $bmc->revenue_streams);
    }

    public function test_user_can_update_all_nine_bmc_sections(): void
    {
        $user       = User::factory()->create();
        $sideHustle = $this->createSideHustleWithBmc($user);

        $payload = [
            'key_partners'           => 'Farms',
            'key_activities'         => 'Production',
            'key_resources'          => 'Equipment',
            'value_propositions'     => 'Quality',
            'customer_relationships' => 'Personal',
            'channels'               => 'Online',
            'customer_segments'      => 'Home cooks',
            'cost_structure'         => 'Fixed costs',
            'revenue_streams'        => 'Direct sales',
        ];

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/sidehustles/{$sideHustle->id}/bmc", $payload)
            ->assertOk()
            ->assertJsonFragment($payload);
    }

    // -------------------------------------------------------------------------
    // Get BMC
    // -------------------------------------------------------------------------

    public function test_user_can_get_bmc_for_sidehustle(): void
    {
        $user       = User::factory()->create();
        $sideHustle = $this->createSideHustleWithBmc($user);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/sidehustles/{$sideHustle->id}/bmc")
            ->assertOk()
            ->assertJsonPath('side_hustle_id', $sideHustle->id);
    }

    public function test_get_bmc_returns_404_for_nonexistent_sidehustle(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/sidehustles/99999/bmc')
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Ownership guard
    // -------------------------------------------------------------------------

    public function test_non_owner_cannot_update_bmc(): void
    {
        $owner      = User::factory()->create();
        $other      = User::factory()->create();
        $sideHustle = $this->createSideHustleWithBmc($owner);

        $this->actingAs($other, 'sanctum')
            ->putJson("/api/sidehustles/{$sideHustle->id}/bmc", [
                'key_partners' => 'Should not save',
            ])
            ->assertStatus(403);
    }
}
