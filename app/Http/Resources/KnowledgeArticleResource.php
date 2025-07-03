<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\KnowledgeArticle $resource
 */
final class KnowledgeArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'content' => $this->resource->content,
            'summary' => $this->resource->summary,
            'status' => $this->resource->status,
            'is_public' => $this->resource->is_public,
            'view_count' => $this->resource->view_count,
            'tags' => $this->resource->tags,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->resource->category->id,
                'name' => $this->resource->category->name,
                'description' => $this->resource->category->description ?? null,
            ]),
            'department' => $this->whenLoaded('department', fn () => $this->resource->department ? [
                'id' => $this->resource->department->id,
                'name' => $this->resource->department->name,
            ] : null),
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->resource->author->id,
                'name' => $this->resource->author->name,
                'email' => $this->resource->author->email,
            ]),
            'published_at' => $this->resource->published_at?->toISOString(),
            'created_at' => $this->resource->created_at->toISOString(),
            'updated_at' => $this->resource->updated_at->toISOString(),
        ];
    }
}
