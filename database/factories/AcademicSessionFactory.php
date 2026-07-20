<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicSession>
 */
class AcademicSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->numberBetween(2020, 2035);

        return [
            'uuid' => fake()->uuid(),
            'name' => sprintf('%d-%02d', $year, ($year + 1) % 100),
            'start_date' => "{$year}-04-01",
            'end_date' => ($year + 1).'-03-31',
            'is_active' => false,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
        ];
    }
}
