<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Department;
use App\Models\KnowledgeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KnowledgeCategory>
 */
final class KnowledgeCategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\KnowledgeCategory>
     */
    protected $model = KnowledgeCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'department_id' => null,
            'sort_order' => $this->faker->numberBetween(0, 100),
            'is_active' => true,
        ];
    }

    /**
     * Create a category for a specific department.
     */
    public function forDepartment(?Department $department = null): static
    {
        return $this->state(fn () => [
            'department_id' => $department?->id ?? Department::factory(),
        ]);
    }

    /**
     * Create an inactive category.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a general (no department) category.
     */
    public function general(): static
    {
        return $this->state(fn () => [
            'department_id' => null,
        ]);
    }
}
