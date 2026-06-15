<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userId = $request->user()->id;
 
        return [
            'id'              => $this->id,
            'conversation_id' => $this->conversation_id,
            'body'            => $this->body,
            'read_at'         => $this->read_at?->toISOString(),
            'created_at'      => $this->created_at->toISOString(),
            'is_mine'         => $this->sender_id === $userId,
            'sender'          => [
                'id'     => $this->sender->id,
                'name'   => $this->sender->name,
                'avatar' => $this->sender->avatar_url ?? null,
            ],
            'attachments' => AttachmentResource::collection(
                $this->whenLoaded('attachments', $this->attachments, collect())
            ),
        ];
    }
}
