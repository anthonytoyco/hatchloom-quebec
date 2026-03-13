<?php

namespace Tests\Feature\LaunchPad;

use App\Models\Sandbox;
use App\Models\SideHustle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LaunchPad — SideHustle tests.
 *
 * Required:
 *   TC-Q2-001 (HL-SideHustle-Create)
 *   TC-Q2-004 (HL-LaunchPad-Summary)
 *   TC-Q2-006 (HL-CreateFromSandbox)
 */
class SideHustleTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/sidehustles')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // TC-Q2-001  HL-SideHustle-Create
    // POST /api/sidehustles → 201, status=IN_THE_LAB, BMC + Team auto-created
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_create_sidehustle(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/sidehustles', [
                'student_id'  => $user->id,
                'title'       => 'Compound Butter Co.',
                'description' => 'Artisanal compound butters.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'IN_THE_LAB')
            ->assertJsonPath('title', 'Compound Butter Co.')
            ->assertJsonStructure(['id', 'bmc', 'team', 'positions']);

        $this->assertDatabaseHas('side_hustles', [
            'student_id' => $user->id,
            'title'      => 'Compound Butter Co.',
            'status'     => 'IN_THE_LAB',
        ]);
    }

    public function test_bmc_and_team_are_auto_created_on_sidehustle_creation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/sidehustles', [
                'student_id' => $user->id,
                'title'      => 'Test Venture',
            ]);

        $sideHustleId = $response->json('id');

        $this->assertDatabaseHas('business_model_canvases', ['side_hustle_id' => $sideHustleId]);
        $this->assertDatabaseHas('teams', ['side_hustle_id' => $sideHustleId]);
    }

    public function test_store_returns_422_when_required_fields_are_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/sidehustles', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['student_id', 'title']);
    }

    // -------------------------------------------------------------------------
    // Get SideHustle
    // -------------------------------------------------------------------------

    public function test_user_can_get_sidehustle_with_relations(): void
    {
        $user       = User::factory()->create();
        $sideHustle = SideHustle::factory()->create(['student_id' => $user->id]);
        $sideHustle->bmc()->create([]);
        $sideHustle->team()->create([]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/sidehustles/{$sideHustle->id}");

        $response->assertOk()
            ->assertJsonPath('id', $sideHustle->id)
            ->assertJsonStructure(['bmc', 'team', 'positions']);
    }

    // -------------------------------------------------------------------------
    // TC-Q2-006  HL-CreateFromSandbox
    // POST /api/sandboxes/{id}/launch → 201, SideHustle inherits sandbox data
    // -------------------------------------------------------------------------

    public function test_sandbox_can_be_promoted_to_sidehustle(): void
    {
        $user    = User::factory()->create();
        $sandbox = Sandbox::factory()->create([
            'student_id'  => $user->id,
            'title'       => 'Butter Lab',
            'description' => 'Compound butters.',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/sandboxes/{$sandbox->id}/launch");

        $response->assertStatus(201)
            ->assertJsonPath('sandbox_id', $sandbox->id)
            ->assertJsonPath('title', 'Butter Lab')
            ->assertJsonPath('status', 'IN_THE_LAB');

        $this->assertDatabaseHas('side_hustles', [
            'sandbox_id' => $sandbox->id,
            'student_id' => $user->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // TC-Q2-004  HL-LaunchPad-Summary
    // GET /api/launchpad/summary → correct counts for auth'd student
    // -------------------------------------------------------------------------

    public function test_launchpad_summary_returns_correct_counts(): void
    {
        $user = User::factory()->create();

        Sandbox::factory()->count(2)->create(['student_id' => $user->id]);

        SideHustle::factory()->create(['student_id' => $user->id, 'status' => 'IN_THE_LAB']);
        SideHustle::factory()->create(['student_id' => $user->id, 'status' => 'LIVE_VENTURE']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/launchpad/summary');

        $response->assertOk()
            ->assertJsonPath('sandbox_count', 2)
            ->assertJsonPath('in_the_lab_count', 1)
            ->assertJsonPath('live_venture_count', 1)
            ->assertJsonStructure(['side_hustles']);

        $this->assertCount(2, $response->json('side_hustles'));
    }

    public function test_launchpad_summary_only_returns_own_data(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Sandbox::factory()->count(3)->create(['student_id' => $userA->id]);
        Sandbox::factory()->count(1)->create(['student_id' => $userB->id]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/launchpad/summary');

        $response->assertOk()->assertJsonPath('sandbox_count', 3);
    }

    public function test_launchpad_summary_requires_auth(): void
    {
        $this->getJson('/api/launchpad/summary')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Update SideHustle
    // -------------------------------------------------------------------------

    public function test_user_can_update_sidehustle_status_to_live_venture(): void
    {
        $user       = User::factory()->create();
        $sideHustle = SideHustle::factory()->create(['student_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/sidehustles/{$sideHustle->id}", ['status' => 'LIVE_VENTURE'])
            ->assertOk()
            ->assertJsonPath('status', 'LIVE_VENTURE');
    }

    public function test_update_rejects_invalid_status(): void
    {
        $user       = User::factory()->create();
        $sideHustle = SideHustle::factory()->create(['student_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/sidehustles/{$sideHustle->id}", ['status' => 'INVALID'])
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Delete SideHustle
    // -------------------------------------------------------------------------

    public function test_user_can_delete_sidehustle(): void
    {
        $user       = User::factory()->create();
        $sideHustle = SideHustle::factory()->create(['student_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/sidehustles/{$sideHustle->id}")
            ->assertOk();

        $this->assertDatabaseMissing('side_hustles', ['id' => $sideHustle->id]);
    }

    // -------------------------------------------------------------------------
    // Ownership guards
    // -------------------------------------------------------------------------

    public function test_user_cannot_update_another_users_sidehustle(): void
    {
        $owner      = User::factory()->create();
        $other      = User::factory()->create();
        $sideHustle = SideHustle::factory()->create(['student_id' => $owner->id]);

        $this->actingAs($other, 'sanctum')
            ->putJson("/api/sidehustles/{$sideHustle->id}", ['title' => 'Hijacked Title'])
            ->assertStatus(403);
    }

    public function test_user_cannot_delete_another_users_sidehustle(): void
    {
        $owner      = User::factory()->create();
        $other      = User::factory()->create();
        $sideHustle = SideHustle::factory()->create(['student_id' => $owner->id]);

        $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/sidehustles/{$sideHustle->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('side_hustles', ['id' => $sideHustle->id]);
    }
}
