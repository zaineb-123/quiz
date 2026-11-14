<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->teacher(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'academic_year' => '2026-2027',
            'join_enabled' => true,
        ];
    }

    public function joinDisabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'join_enabled' => false,
        ]);
    }
}
