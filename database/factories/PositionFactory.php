<?php

namespace Database\Factories;

use App\Models\SideHustle;
use Illuminate\Database\Eloquent\Factories\Factory;

class PositionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'side_hustle_id' => SideHustle::factory(),
            'title'          => $this->faker->jobTitle(),
            'description'    => $this->faker->sentence(),
            'status'         => 'OPEN',
        ];
    }
}
