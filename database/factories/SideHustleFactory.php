<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SideHustleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id'         => User::factory(),
            'sandbox_id'         => null,
            'title'              => $this->faker->company(),
            'description'        => $this->faker->paragraph(),
            'status'             => 'IN_THE_LAB',
            'has_open_positions' => false,
        ];
    }
}
