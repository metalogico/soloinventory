<?php

namespace Database\Factories;

use App\Models\Position;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Position> */
class PositionFactory extends Factory
{
    public function definition(): array
    {
        return ['section_id' => Section::factory(), 'name' => fake()->words(2, true)];
    }
}
