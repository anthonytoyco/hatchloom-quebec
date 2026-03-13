<?php

namespace Tests\Feature\LaunchPad;

use App\Models\Sandbox;
use App\Models\SideHustle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SandboxTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/sandboxes')->assertStatus(401);
        $this->postJson('/api/sandboxes', [])->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Create sandbox
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_create_sandbox(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/sandboxes', [
                'student_id'  => $user->id,
                'title'       => 'Food Tech Lab',
                'description' => 'Testing artisanal food ideas.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('title', 'Food Tech Lab')
            ->assertJsonPath('student_id', $user->id);

        $this->assertDatabaseHas('sandboxes', [
            'student_id' => $user->id,
            'title'      => 'Food Tech Lab',
        ]);
    }

    public function test_store_returns_422_when_required_fields_are_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/sandboxes', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['student_id', 'title']);
    }

    public function test_store_returns_422_when_student_id_does_not_exist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/sandboxes', [
                'student_id' => 99999,
                'title'      => 'Ghost Sandbox',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['student_id']);
    }

    // -------------------------------------------------------------------------
    // Get sandbox
    // -------------------------------------------------------------------------

    public function test_user_can_get_single_sandbox(): void
    {
        $user    = User::factory()->create();
        $sandbox = Sandbox::factory()->create(['student_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/sandboxes/{$sandbox->id}")
            ->assertOk()
            ->assertJsonPath('id', $sandbox->id);
    }

    public function test_get_sandbox_returns_404_for_nonexistent_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/sandboxes/99999')
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // List sandboxes
    // -------------------------------------------------------------------------

    public function test_index_filters_by_student_id(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Sandbox::factory()->count(2)->create(['student_id' => $userA->id]);
        Sandbox::factory()->count(1)->create(['student_id' => $userB->id]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson("/api/sandboxes?student_id={$userA->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json());
        $this->assertTrue(collect($response->json())->every(fn($s) => $s['student_id'] === $userA->id));
    }

    // -------------------------------------------------------------------------
    // Update sandbox
    // -------------------------------------------------------------------------

    public function test_user_can_update_sandbox(): void
    {
        $user    = User::factory()->create();
        $sandbox = Sandbox::factory()->create(['student_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/sandboxes/{$sandbox->id}", ['title' => 'Updated Title'])
            ->assertOk()
            ->assertJsonPath('title', 'Updated Title');
    }

    // -------------------------------------------------------------------------
    // Delete sandbox
    // -------------------------------------------------------------------------

    public function test_user_can_delete_sandbox(): void
    {
        $user    = User::factory()->create();
        $sandbox = Sandbox::factory()->create(['student_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/sandboxes/{$sandbox->id}")
            ->assertOk();

        $this->assertDatabaseMissing('sandboxes', ['id' => $sandbox->id]);
    }
}
