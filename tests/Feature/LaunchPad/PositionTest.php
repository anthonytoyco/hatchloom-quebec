<?php

namespace Tests\Feature\LaunchPad;

use App\Models\Position;
use App\Models\SideHustle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LaunchPad — Position tests.
 *
 * Required:
 *   TC-Q2-003 (HL-Create-Open-Position)
 *   TC-Q2-005 (HL-Position-Status-Sync)
 *   TC-Q2-007 (HL-Unauthenticated-Rejected)
 */
class PositionTest extends TestCase
{
    use RefreshDatabase;

    private function createSideHustleFor(User $user): SideHustle
    {
        return SideHustle::factory()->create(['student_id' => $user->id]);
    }

    // -------------------------------------------------------------------------
    // TC-Q2-007  HL-Unauthenticated-Rejected
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/positions', [])->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // TC-Q2-003  HL-Create-Open-Position
    // POST /api/positions, 201, status defaults to OPEN, has_open_positions synced
    // -------------------------------------------------------------------------

    public function test_user_can_create_open_position(): void
    {
        $user       = User::factory()->create();
        $sideHustle = $this->createSideHustleFor($user);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/positions', [
                'side_hustle_id' => $sideHustle->id,
                'title'          => 'Marketing Lead',
                'description'    => 'Help grow our brand.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'OPEN')
            ->assertJsonPath('side_hustle_id', $sideHustle->id);

        $this->assertDatabaseHas('positions', [
            'side_hustle_id' => $sideHustle->id,
            'title'          => 'Marketing Lead',
            'status'         => 'OPEN',
        ]);
    }

    public function test_has_open_positions_flag_is_set_true_after_creating_open_position(): void
    {
        $user       = User::factory()->create();
        $sideHustle = $this->createSideHustleFor($user);

        $this->assertFalse((bool) $sideHustle->fresh()->has_open_positions);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/positions', [
                'side_hustle_id' => $sideHustle->id,
                'title'          => 'Developer',
            ]);

        $this->assertTrue((bool) $sideHustle->fresh()->has_open_positions);
    }

    public function test_store_returns_422_when_required_fields_are_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/positions', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['side_hustle_id', 'title']);
    }

    public function test_store_rejects_invalid_status_value(): void
    {
        $user       = User::factory()->create();
        $sideHustle = $this->createSideHustleFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/positions', [
                'side_hustle_id' => $sideHustle->id,
                'title'          => 'Designer',
                'status'         => 'BANANA',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    // -------------------------------------------------------------------------
    // TC-Q2-005  HL-Position-Status-Sync
    // Updating position to FILLED/CLOSED syncs has_open_positions flag
    // -------------------------------------------------------------------------

    public function test_has_open_positions_becomes_false_when_last_open_position_is_filled(): void
    {
        $user       = User::factory()->create();
        $sideHustle = $this->createSideHustleFor($user);
        $position   = Position::factory()->create([
            'side_hustle_id' => $sideHustle->id,
            'status'         => 'OPEN',
        ]);
        // Manually set the flag as the controller would
        $sideHustle->update(['has_open_positions' => true]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/positions/{$position->id}", ['status' => 'FILLED']);

        $this->assertFalse((bool) $sideHustle->fresh()->has_open_positions);
    }

    public function test_has_open_positions_stays_true_when_other_open_positions_remain(): void
    {
        $user       = User::factory()->create();
        $sideHustle = $this->createSideHustleFor($user);

        $positionA = Position::factory()->create(['side_hustle_id' => $sideHustle->id, 'status' => 'OPEN']);
        $positionB = Position::factory()->create(['side_hustle_id' => $sideHustle->id, 'status' => 'OPEN']);
        $sideHustle->update(['has_open_positions' => true]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/positions/{$positionA->id}", ['status' => 'FILLED']);

        // positionB is still OPEN, so flag should remain true
        $this->assertTrue((bool) $sideHustle->fresh()->has_open_positions);
    }

    // -------------------------------------------------------------------------
    // Update position
    // -------------------------------------------------------------------------

    public function test_user_can_update_position_title_and_description(): void
    {
        $user       = User::factory()->create();
        $sideHustle = $this->createSideHustleFor($user);
        $position   = Position::factory()->create(['side_hustle_id' => $sideHustle->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/positions/{$position->id}", [
                'title'       => 'Updated Role',
                'description' => 'Updated description.',
            ])
            ->assertOk()
            ->assertJsonPath('title', 'Updated Role');
    }

    public function test_update_rejects_invalid_status(): void
    {
        $user       = User::factory()->create();
        $sideHustle = $this->createSideHustleFor($user);
        $position   = Position::factory()->create(['side_hustle_id' => $sideHustle->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/positions/{$position->id}", ['status' => 'INVALID'])
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Delete position
    // -------------------------------------------------------------------------

    public function test_user_can_delete_position(): void
    {
        $user       = User::factory()->create();
        $sideHustle = $this->createSideHustleFor($user);
        $position   = Position::factory()->create(['side_hustle_id' => $sideHustle->id, 'status' => 'OPEN']);
        $sideHustle->update(['has_open_positions' => true]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/positions/{$position->id}")
            ->assertOk();

        $this->assertDatabaseMissing('positions', ['id' => $position->id]);
        $this->assertFalse((bool) $sideHustle->fresh()->has_open_positions);
    }

    // -------------------------------------------------------------------------
    // List positions
    // -------------------------------------------------------------------------

    public function test_user_can_list_positions_for_sidehustle(): void
    {
        $user       = User::factory()->create();
        $sideHustle = $this->createSideHustleFor($user);

        Position::factory()->count(3)->create(['side_hustle_id' => $sideHustle->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/positions/{$sideHustle->id}")
            ->assertOk()
            ->assertJsonCount(3);
    }

    public function test_non_owner_cannot_create_position_for_others_sidehustle(): void
    {
        $owner      = User::factory()->create();
        $other      = User::factory()->create();
        $sideHustle = $this->createSideHustleFor($owner);

        $this->actingAs($other, 'sanctum')
            ->postJson('/api/positions', [
                'side_hustle_id' => $sideHustle->id,
                'title'          => 'Hijacked Position',
            ])
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Ownership guards
    // -------------------------------------------------------------------------

    public function test_non_owner_cannot_update_position(): void
    {
        $owner      = User::factory()->create();
        $other      = User::factory()->create();
        $sideHustle = $this->createSideHustleFor($owner);
        $position   = Position::factory()->create(['side_hustle_id' => $sideHustle->id]);

        $this->actingAs($other, 'sanctum')
            ->putJson("/api/positions/{$position->id}", ['title' => 'Hijacked'])
            ->assertStatus(403);
    }

    public function test_non_owner_cannot_delete_position(): void
    {
        $owner      = User::factory()->create();
        $other      = User::factory()->create();
        $sideHustle = $this->createSideHustleFor($owner);
        $position   = Position::factory()->create(['side_hustle_id' => $sideHustle->id]);

        $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/positions/{$position->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('positions', ['id' => $position->id]);
    }

    // -------------------------------------------------------------------------
    // Status transition guards (design doc p.19 / Test ID 13)
    // Only OPEN positions can transition; FILLED and CLOSED are terminal
    // -------------------------------------------------------------------------

    public function test_cannot_transition_from_filled_to_open(): void
    {
        $user       = User::factory()->create();
        $sideHustle = $this->createSideHustleFor($user);
        $position   = Position::factory()->create([
            'side_hustle_id' => $sideHustle->id,
            'status'         => 'FILLED',
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/positions/{$position->id}", ['status' => 'OPEN'])
            ->assertStatus(422);
    }

    public function test_cannot_transition_from_closed_to_open(): void
    {
        $user       = User::factory()->create();
        $sideHustle = $this->createSideHustleFor($user);
        $position   = Position::factory()->create([
            'side_hustle_id' => $sideHustle->id,
            'status'         => 'CLOSED',
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/positions/{$position->id}", ['status' => 'OPEN'])
            ->assertStatus(422);
    }

    public function test_cannot_transition_from_filled_to_closed(): void
    {
        $user       = User::factory()->create();
        $sideHustle = $this->createSideHustleFor($user);
        $position   = Position::factory()->create([
            'side_hustle_id' => $sideHustle->id,
            'status'         => 'FILLED',
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/positions/{$position->id}", ['status' => 'CLOSED'])
            ->assertStatus(422);
    }
}
