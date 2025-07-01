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
            'status' => $this->whenLoaded('status', function (): ?array {
                $status = $this->resource->status;

                if ($status === null) {
                    return null;
                }

                return [
                    'id' => $status->id,
                    'name' => $status->name,
                    'color' => $status->color,
                    'is_closed' => $status->is_closed,
                ];
            }),
            'priority' => $this->whenLoaded('priority', function (): ?array {
                $priority = $this->resource->priority;

                if ($priority === null) {
                    return null;
                }

                return [
                    'id' => $priority->id,
                    'name' => $priority->name,
                    'level' => $priority->level,
                    'color' => $priority->color,
                ];
            }),
            'department' => $this->whenLoaded('department', function (): ?array {
                $department = $this->resource->department;

                if ($department === null) {
                    return null;
                }

                return [
                    'id' => $department->id,
                    'name' => $department->name,
                ];
            }),
            'assigned_to' => $this->whenLoaded('assignedTo', fn() => $this->resource->assignedTo ? [
                'id' => $this->resource->assignedTo->id,
                'name' => $this->resource->assignedTo->name,
                'email' => $this->resource->assignedTo->email
            ] : null),
            'created_by' => $this->whenLoaded('createdBy', function (): ?array {
                $creator = $this->resource->createdBy;

                if ($creator === null) {
                    return null;
                }

                return [
                    'id' => $creator->id,
                    'name' => $creator->name,
                    'email' => $creator->email,
                ];
            }),
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
