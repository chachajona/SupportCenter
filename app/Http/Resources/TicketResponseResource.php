<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\TicketResponse $resource
 */
final class TicketResponseResource extends JsonResource
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
      'message' => $this->resource->message,
      'is_internal' => $this->resource->is_internal,
      'is_email' => $this->resource->is_email,
      'user' => $this->whenLoaded('user', fn() => [
        'id' => $this->resource->user->id,
        'name' => $this->resource->user->name,
        'email' => $this->resource->user->email
      ]),
      'created_at' => $this->resource->created_at->toISOString(),
    ];
  }
}
