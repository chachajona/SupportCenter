<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Ticket $resource
 */
final class TicketResource extends JsonResource
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
      'number' => $this->resource->number,
      'subject' => $this->resource->subject,
      'description' => $this->resource->description,
      'status' => $this->whenLoaded('status', fn() => [
        'id' => $this->resource->status->id,
        'name' => $this->resource->status->name,
        'color' => $this->resource->status->color,
        'is_closed' => $this->resource->status->is_closed
      ]),
      'priority' => $this->whenLoaded('priority', fn() => [
        'id' => $this->resource->priority->id,
        'name' => $this->resource->priority->name,
        'level' => $this->resource->priority->level,
        'color' => $this->resource->priority->color
      ]),
      'department' => $this->whenLoaded('department', fn() => [
        'id' => $this->resource->department->id,
        'name' => $this->resource->department->name
      ]),
      'assigned_to' => $this->whenLoaded('assignedTo', fn() => $this->resource->assignedTo ? [
        'id' => $this->resource->assignedTo->id,
        'name' => $this->resource->assignedTo->name,
        'email' => $this->resource->assignedTo->email
      ] : null),
      'created_by' => $this->whenLoaded('createdBy', fn() => [
        'id' => $this->resource->createdBy->id,
        'name' => $this->resource->createdBy->name,
        'email' => $this->resource->createdBy->email
      ]),
      'updated_by' => $this->whenLoaded('updatedBy', fn() => $this->resource->updatedBy ? [
        'id' => $this->resource->updatedBy->id,
        'name' => $this->resource->updatedBy->name,
        'email' => $this->resource->updatedBy->email
      ] : null),
      'due_at' => $this->resource->due_at?->toISOString(),
      'resolved_at' => $this->resource->resolved_at?->toISOString(),
      'created_at' => $this->resource->created_at->toISOString(),
      'updated_at' => $this->resource->updated_at->toISOString(),
      'responses' => $this->whenLoaded(
        'responses',
        fn() =>
        \App\Http\Resources\TicketResponseResource::collection($this->resource->responses)
      ),
      'is_overdue' => $this->resource->isOverdue(),
      'is_closed' => $this->resource->isClosed(),
    ];
  }
}
