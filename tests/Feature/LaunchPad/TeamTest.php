<?php

namespace Tests\Feature\LaunchPad;

use App\Models\SideHustle;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    private function createTeamFor(User $user): Team
    {
        $sideHustle = SideHustle::factory()->create(['student_id' => $user->id]);
        return $sideHustle->team()->create([]);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/teams/1')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Get team
    // -------------------------------------------------------------------------

    public function test_user_can_get_team_for_sidehustle(): void
    {
        $user = User::factory()->create();
        $team = $this->createTeamFor($user);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/teams/{$team->side_hustle_id}")
            ->assertOk()
            ->assertJsonPath('id', $team->id)
            ->assertJsonStructure(['members']);
    }

    // -------------------------------------------------------------------------
    // Add team member
    // -------------------------------------------------------------------------

    public function test_user_can_add_member_to_team(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $team   = $this->createTeamFor($owner);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/teams/{$team->id}/members", [
                'student_id' => $member->id,
                'role'       => 'Designer',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('role', 'Designer');

        $this->assertDatabaseHas('team_members', [
            'team_id'    => $team->id,
            'student_id' => $member->id,
            'role'       => 'Designer',
        ]);
    }

    public function test_add_member_returns_422_when_student_id_missing(): void
    {
        $user = User::factory()->create();
        $team = $this->createTeamFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/teams/{$team->id}/members", ['role' => 'Designer'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['student_id']);
    }

    // -------------------------------------------------------------------------
    // Remove team member
    // -------------------------------------------------------------------------

    public function test_user_can_remove_member_from_team(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $team   = $this->createTeamFor($owner);

        $teamMember = TeamMember::create([
            'team_id'    => $team->id,
            'student_id' => $member->id,
            'role'       => 'Developer',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/teams/{$team->id}/members/{$teamMember->id}")
            ->assertOk();

        $this->assertDatabaseMissing('team_members', ['id' => $teamMember->id]);
    }

    public function test_get_team_shows_all_members(): void
    {
        $owner = User::factory()->create();
        $team  = $this->createTeamFor($owner);

        TeamMember::create(['team_id' => $team->id, 'student_id' => $owner->id, 'role' => 'Founder']);
        TeamMember::create(['team_id' => $team->id, 'student_id' => User::factory()->create()->id, 'role' => 'Dev']);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/teams/{$team->side_hustle_id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('members'));
    }
}
