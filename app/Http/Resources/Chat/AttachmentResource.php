<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'type'             => $this->type,
            'url'              => $this->url,
            'original_name'    => $this->original_name,
            'mime_type'        => $this->mime_type,
            'size'             => $this->size,
            'human_size'       => $this->human_size,
            'width'            => $this->when($this->type === 'image', $this->width),
            'height'           => $this->when($this->type === 'image', $this->height),
            'duration_seconds' => $this->when($this->type === 'audio', $this->duration_seconds),
        ];
    }
}
