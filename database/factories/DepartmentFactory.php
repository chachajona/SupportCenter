<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
final class DepartmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\Department>
     */
    protected $model = Department::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Department',
            'description' => $this->faker->sentence(),
            'parent_id' => null,
            'manager_id' => null,
            'is_active' => true,
        ];
    }

    /**
     * Create a department with a manager.
     */
    public function withManager(User $manager = null): static
    {
        return $this->state(fn() => [
            'manager_id' => $manager?->id ?? User::factory(),
        ]);
    }

    /**
     * Create a child department.
     */
    public function child(Department $parent = null): static
    {
        return $this->state(fn() => [
            'parent_id' => $parent?->id ?? Department::factory(),
        ]);
    }

    /**
     * Create an inactive department.
     */
    public function inactive(): static
    {
        return $this->state(fn() => [
            'is_active' => false,
        ]);
    }
}
