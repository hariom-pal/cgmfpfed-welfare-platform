<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WorkflowStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkflowStatus>
 */
final class WorkflowStatusFactory extends Factory
{
    protected $model = WorkflowStatus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'code' => strtoupper('W'.$this->faker->unique()->bothify('###??')),
            'name' => $this->faker->unique()->words(3, true),
            'description' => $this->faker->sentence(),
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
        ];
    }
}
