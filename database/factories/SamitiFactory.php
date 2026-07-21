<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Samiti;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Samiti>
 */
final class SamitiFactory extends Factory
{
    protected $model = Samiti::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'code' => strtoupper('S'.$this->faker->unique()->bothify('###??')),
            'name' => $this->faker->unique()->words(3, true),
            'description' => $this->faker->sentence(),
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
        ];
    }
}
