<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Department;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class KnowledgeBaseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function knowledge_categories_can_be_created(): void
    {
        $department = Department::factory()->create();

        $category = KnowledgeCategory::create([
            'name' => 'Test Category',
            'description' => 'Test description',
            'department_id' => $department->id,
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('knowledge_categories', [
            'name' => 'Test Category',
            'department_id' => $department->id,
        ]);

        $this->assertEquals('Test Category', $category->name);
        $this->assertEquals($department->id, $category->department_id);
    }

    #[Test]
    public function knowledge_articles_can_be_created(): void
    {
        $user = User::factory()->create();
        $department = Department::factory()->create();
        $category = KnowledgeCategory::factory()->create(['department_id' => $department->id]);

        $article = KnowledgeArticle::create([
            'title' => 'Test Article',
            'content' => 'This is test content',
            'summary' => 'Test summary',
            'category_id' => $category->id,
            'department_id' => $department->id,
            'author_id' => $user->id,
            'status' => 'draft',
            'is_public' => false,
            'tags' => ['test', 'example'],
        ]);

        $this->assertDatabaseHas('knowledge_articles', [
            'title' => 'Test Article',
            'author_id' => $user->id,
            'category_id' => $category->id,
        ]);

        $this->assertEquals('Test Article', $article->title);
        $this->assertEquals(['test', 'example'], $article->tags);
        $this->assertTrue($article->isDraft());
        $this->assertFalse($article->isPublished());
    }

    #[Test]
    public function articles_can_be_published(): void
    {
        $user = User::factory()->create();
        $category = KnowledgeCategory::factory()->create();

        $article = KnowledgeArticle::factory()->create([
            'author_id' => $user->id,
            'category_id' => $category->id,
            'status' => 'draft',
        ]);

        $this->assertFalse($article->isPublished());

        $article->publish();

        $this->assertTrue($article->isPublished());
        $this->assertEquals('published', $article->status);
        $this->assertNotNull($article->published_at);
    }

    #[Test]
    public function articles_can_be_searched(): void
    {
        $user = User::factory()->create();
        $category = KnowledgeCategory::factory()->create();

        $article1 = KnowledgeArticle::factory()->create([
            'title' => 'How to use Laravel',
            'content' => 'Laravel is a PHP framework',
            'author_id' => $user->id,
            'category_id' => $category->id,
            'status' => 'published',
        ]);

        $article2 = KnowledgeArticle::factory()->create([
            'title' => 'Introduction to React',
            'content' => 'React is a JavaScript library',
            'author_id' => $user->id,
            'category_id' => $category->id,
            'status' => 'published',
        ]);

        $laravelResults = KnowledgeArticle::search('Laravel')->get();
        $reactResults = KnowledgeArticle::search('React')->get();

        $this->assertCount(1, $laravelResults);
        $this->assertCount(1, $reactResults);
        $this->assertEquals($article1->id, $laravelResults->first()->id);
        $this->assertEquals($article2->id, $reactResults->first()->id);
    }

    #[Test]
    public function view_count_is_incremented(): void
    {
        $user = User::factory()->create();
        $category = KnowledgeCategory::factory()->create();

        $article = KnowledgeArticle::factory()->create([
            'author_id' => $user->id,
            'category_id' => $category->id,
            'view_count' => 0,
        ]);

        $this->assertEquals(0, $article->view_count);

        $article->incrementViewCount();
        $article->refresh();

        $this->assertEquals(1, $article->view_count);
    }

    #[Test]
    public function department_scoping_works(): void
    {
        $department1 = Department::factory()->create();
        $department2 = Department::factory()->create();

        $category1 = KnowledgeCategory::factory()->create(['department_id' => $department1->id]);
        $category2 = KnowledgeCategory::factory()->create(['department_id' => $department2->id]);

        $dept1Categories = KnowledgeCategory::forDepartment($department1->id)->get();
        $dept2Categories = KnowledgeCategory::forDepartment($department2->id)->get();

        $this->assertCount(1, $dept1Categories);
        $this->assertCount(1, $dept2Categories);
        $this->assertEquals($category1->id, $dept1Categories->first()->id);
        $this->assertEquals($category2->id, $dept2Categories->first()->id);
    }
}
