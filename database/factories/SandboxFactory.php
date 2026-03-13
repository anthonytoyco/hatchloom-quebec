<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SandboxFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id'  => User::factory(),
            'title'       => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
        ];
    }
}
