<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryItem> */
class InventoryItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'quantity' => 1,
            'section_id' => Section::factory(),
            'category_id' => Category::factory(),
        ];
    }
}
