<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Department;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KnowledgeArticle>
 */
final class KnowledgeArticleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\KnowledgeArticle>
     */
    protected $model = KnowledgeArticle::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'content' => $this->faker->paragraphs(3, true),
            'summary' => $this->faker->sentence(),
            'category_id' => KnowledgeCategory::factory(),
            'department_id' => null,
            'author_id' => User::factory(),
            'status' => 'draft',
            'is_public' => false,
            'view_count' => $this->faker->numberBetween(0, 100),
            'tags' => $this->faker->words(3),
            'published_at' => null,
        ];
    }

    /**
     * Create a published article.
     */
    public function published(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Create an archived article.
     */
    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => 'archived',
        ]);
    }

    /**
     * Create a public article.
     */
    public function public(): static
    {
        return $this->state(fn () => [
            'is_public' => true,
        ]);
    }

    /**
     * Create an article for a specific department.
     */
    public function forDepartment(?Department $department = null): static
    {
        return $this->state(fn () => [
            'department_id' => $department?->id ?? Department::factory(),
        ]);
    }

    /**
     * Create an article with specific category.
     */
    public function inCategory(?KnowledgeCategory $category = null): static
    {
        return $this->state(fn () => [
            'category_id' => $category?->id ?? KnowledgeCategory::factory(),
        ]);
    }

    /**
     * Create an article by a specific author.
     */
    public function byAuthor(?User $author = null): static
    {
        return $this->state(fn () => [
            'author_id' => $author?->id ?? User::factory(),
        ]);
    }

    /**
     * Create a popular article with many views.
     */
    public function popular(): static
    {
        return $this->state(fn () => [
            'view_count' => $this->faker->numberBetween(500, 2000),
        ]);
    }
}
