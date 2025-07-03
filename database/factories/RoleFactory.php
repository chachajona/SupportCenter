<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->unique()->slug,
            'display_name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence,
            'is_active' => true,
            'hierarchy_level' => $this->faker->numberBetween(1, 100),
        ];
    }
}
