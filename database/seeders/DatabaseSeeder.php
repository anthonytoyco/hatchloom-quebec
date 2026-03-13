<?php

namespace Database\Seeders;

use App\Models\Position;
use App\Models\Sandbox;
use App\Models\SideHustle;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Generic test user
        User::factory()->create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Demo student — Screen 240 (Compound Butter Co.)
        $student = User::factory()->create([
            'name'  => 'Demo Student',
            'email' => 'student@hatchloom.dev',
        ]);

        $sandbox = Sandbox::create([
            'student_id'  => $student->id,
            'title'       => 'Food Tech Lab',
            'description' => 'Experimenting with artisanal food products.',
        ]);

        $sideHustle = SideHustle::create([
            'student_id'         => $student->id,
            'sandbox_id'         => $sandbox->id,
            'title'              => 'Compound Butter Co.',
            'description'        => 'Artisanal compound butters for home chefs.',
            'status'             => 'IN_THE_LAB',
            'has_open_positions' => true,
        ]);

        $sideHustle->bmc()->create([
            'key_partners'           => 'Local farms, specialty grocery stores',
            'key_activities'         => 'Production, packaging, marketing',
            'key_resources'          => 'Commercial kitchen, ingredient suppliers',
            'value_propositions'     => 'Premium flavoured butters for home chefs',
            'customer_relationships' => 'Community events, social media engagement',
            'channels'               => 'Farmers markets, online store, local retailers',
            'customer_segments'      => 'Home cooks and food enthusiasts aged 25–45',
            'cost_structure'         => 'Ingredients, packaging, kitchen rental',
            'revenue_streams'        => 'Direct sales, farmers market, online store',
        ]);

        $team = $sideHustle->team()->create([]);
        $team->members()->create([
            'student_id' => $student->id,
            'role'       => 'Founder',
        ]);

        Position::create([
            'side_hustle_id' => $sideHustle->id,
            'title'          => 'Marketing Lead',
            'description'    => 'Help grow our brand on social media and at local events.',
            'status'         => 'OPEN',
        ]);
    }
}
