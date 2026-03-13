<?php

namespace Tests\Feature\ConnectHub;

use App\Models\ClassifiedPost;
use App\Models\Position;
use App\Models\SideHustle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ConnectHub — Classifieds Module tests.
 *
 * Required:
 *   TC-Q3-002 (HL-Classified-Post-Success)
 *   TC-Q3-003 (HL-Classified-Post-Update)
 *   TC-Q3-004 (HL-Classifieds-Filter)
 * Additional: ownership guard, invalid lifecycle transition, auth guard.
 */
class ClassifiedPostTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Creates a SideHustle + Position owned by $user for test setup.
     */
    private function createPositionFor(User $user): Position
    {
        $sideHustle = SideHustle::create([
            'student_id'  => $user->id,
            'title'       => 'Test SideHustle',
            'description' => 'For testing.',
            'status'      => 'IN_THE_LAB',
        ]);

        return Position::create([
            'side_hustle_id' => $sideHustle->id,
            'title'          => 'Developer',
            'description'    => 'We need a dev.',
            'status'         => 'OPEN',
        ]);
    }

    // -------------------------------------------------------------------------
    // TC-Q3-002  HL-Classified-Post-Success
    // POST /api/classifieds with valid position_id, title, content → 201, status=OPEN
    // -------------------------------------------------------------------------

    public function test_user_can_create_classified_post_with_status_open(): void
    {
        $user     = User::factory()->create();
        $position = $this->createPositionFor($user);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/classifieds', [
                'position_id' => $position->id,
                'title'       => 'Looking for a developer',
                'content'     => 'Join our startup!',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['status' => 'OPEN'])
            ->assertJsonPath('position_id', $position->id);

        $this->assertDatabaseHas('classified_posts', [
            'position_id' => $position->id,
            'author_id'   => $user->id,
            'status'      => 'OPEN',
        ]);
    }

    // -------------------------------------------------------------------------
    // TC-Q3-003  HL-Classified-Post-Update
    // PATCH /api/classifieds/{id}/status with status=FILLED → 200, DB updated
    // -------------------------------------------------------------------------

    public function test_owner_can_update_classified_status_to_filled(): void
    {
        $user     = User::factory()->create();
        $position = $this->createPositionFor($user);

        $classified = ClassifiedPost::create([
            'position_id'    => $position->id,
            'side_hustle_id' => $position->side_hustle_id,
            'author_id'      => $user->id,
            'title'          => 'Developer needed',
            'content'        => 'Join us.',
            'status'         => 'OPEN',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/classifieds/{$classified->id}/status", [
                'status' => 'FILLED',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'FILLED']);

        $this->assertDatabaseHas('classified_posts', [
            'id'     => $classified->id,
            'status' => 'FILLED',
        ]);
    }

    public function test_owner_can_update_classified_status_to_closed(): void
    {
        $user     = User::factory()->create();
        $position = $this->createPositionFor($user);

        $classified = ClassifiedPost::create([
            'position_id'    => $position->id,
            'side_hustle_id' => $position->side_hustle_id,
            'author_id'      => $user->id,
            'title'          => 'Developer needed',
            'content'        => 'Join us.',
            'status'         => 'OPEN',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/classifieds/{$classified->id}/status", [
                'status' => 'CLOSED',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'CLOSED']);
    }

    // -------------------------------------------------------------------------
    // TC-Q3-004  HL-Classifieds-Filter
    // GET /api/classifieds?status=OPEN → only OPEN; ?status=FILLED → only FILLED
    // -------------------------------------------------------------------------

    public function test_classifieds_can_be_filtered_by_status(): void
    {
        $user     = User::factory()->create();
        $position = $this->createPositionFor($user);

        ClassifiedPost::create([
            'position_id'    => $position->id,
            'side_hustle_id' => $position->side_hustle_id,
            'author_id'      => $user->id,
            'title'          => 'Open post',
            'content'        => 'We are hiring.',
            'status'         => 'OPEN',
        ]);

        ClassifiedPost::create([
            'position_id'    => $position->id,
            'side_hustle_id' => $position->side_hustle_id,
            'author_id'      => $user->id,
            'title'          => 'Filled post',
            'content'        => 'Position filled.',
            'status'         => 'FILLED',
        ]);

        $openResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/classifieds?status=OPEN');

        $openResponse->assertOk();
        $this->assertTrue(collect($openResponse->json())->every(fn($p) => $p['status'] === 'OPEN'));
        $this->assertCount(1, $openResponse->json());

        $filledResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/classifieds?status=FILLED');

        $filledResponse->assertOk();
        $this->assertTrue(collect($filledResponse->json())->every(fn($p) => $p['status'] === 'FILLED'));
        $this->assertCount(1, $filledResponse->json());
    }

    // -------------------------------------------------------------------------
    // Ownership guard (Test ID 13, design doc p. 49)
    // Only the owner may change status — 403 for any other user
    // -------------------------------------------------------------------------

    public function test_non_owner_cannot_update_classified_status(): void
    {
        $owner    = User::factory()->create();
        $other    = User::factory()->create();
        $position = $this->createPositionFor($owner);

        $classified = ClassifiedPost::create([
            'position_id'    => $position->id,
            'side_hustle_id' => $position->side_hustle_id,
            'author_id'      => $owner->id,
            'title'          => 'Owner only',
            'content'        => 'Do not touch.',
            'status'         => 'OPEN',
        ]);

        $response = $this->actingAs($other, 'sanctum')
            ->patchJson("/api/classifieds/{$classified->id}/status", [
                'status' => 'FILLED',
            ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('classified_posts', [
            'id'     => $classified->id,
            'status' => 'OPEN',
        ]);
    }

    // -------------------------------------------------------------------------
    // Invalid lifecycle transition — FILLED → OPEN must return 422
    // (design doc p. 20, Test ID 13 notes p. 50)
    // -------------------------------------------------------------------------

    public function test_invalid_status_transition_returns_422(): void
    {
        $user     = User::factory()->create();
        $position = $this->createPositionFor($user);

        $classified = ClassifiedPost::create([
            'position_id'    => $position->id,
            'side_hustle_id' => $position->side_hustle_id,
            'author_id'      => $user->id,
            'title'          => 'Already filled',
            'content'        => 'No going back.',
            'status'         => 'FILLED',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/classifieds/{$classified->id}/status", [
                'status' => 'OPEN',
            ]);

        // OPEN is not in the allowed values list for PATCH, so this hits validation (422)
        $response->assertStatus(422);
    }

    public function test_cannot_transition_from_filled_to_closed(): void
    {
        $user     = User::factory()->create();
        $position = $this->createPositionFor($user);

        $classified = ClassifiedPost::create([
            'position_id'    => $position->id,
            'side_hustle_id' => $position->side_hustle_id,
            'author_id'      => $user->id,
            'title'          => 'Already filled',
            'content'        => 'No going back.',
            'status'         => 'FILLED',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/classifieds/{$classified->id}/status", [
                'status' => 'CLOSED',
            ]);

        // canTransitionTo() returns false → 422
        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Position Status Interface — ownership check on store
    // -------------------------------------------------------------------------

    public function test_user_cannot_create_classified_for_another_users_position(): void
    {
        $owner    = User::factory()->create();
        $attacker = User::factory()->create();
        $position = $this->createPositionFor($owner);

        $response = $this->actingAs($attacker, 'sanctum')
            ->postJson('/api/classifieds', [
                'position_id' => $position->id,
                'title'       => 'Unauthorized classified',
                'content'     => 'Should fail.',
            ]);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Single classified show
    // -------------------------------------------------------------------------

    public function test_user_can_view_a_single_classified_post(): void
    {
        $user     = User::factory()->create();
        $position = $this->createPositionFor($user);

        $classified = ClassifiedPost::create([
            'position_id'    => $position->id,
            'side_hustle_id' => $position->side_hustle_id,
            'author_id'      => $user->id,
            'title'          => 'View me',
            'content'        => 'Details here.',
            'status'         => 'OPEN',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/classifieds/{$classified->id}");

        $response->assertOk()
            ->assertJsonPath('id', $classified->id);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_store_returns_422_when_required_fields_are_missing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/classifieds', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['position_id', 'title', 'content']);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_to_classifieds_returns_401(): void
    {
        $response = $this->getJson('/api/classifieds');
        $response->assertStatus(401);
    }
}
