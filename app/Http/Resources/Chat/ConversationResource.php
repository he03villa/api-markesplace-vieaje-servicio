<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userId  = $request->user()->id;
        $contact = $this->contactFor($userId);
 
        $lastMsgPreview = null;
        if ($this->relationLoaded('lastMessage') && $this->lastMessage) {
            $lm = $this->lastMessage;
            $lastMsgPreview = [
                'id'         => $lm->id,
                'body'       => $lm->body,
                'is_mine'    => $lm->sender_id === $userId,
                'created_at' => $lm->created_at->toISOString(),
                'preview'    => $lm->body
                    ?? ($lm->attachments->first()
                        ? ucfirst($lm->attachments->first()->type)
                        : null),
            ];
        }
 
        return [
            'id'              => $this->id,
            'contact'         => [
                'id'     => $contact->id,
                'name'   => $contact->name,
                'avatar' => $contact->avatar_url ?? null,
            ],
            'last_message'    => $lastMsgPreview,
            'unread_count'    => $this->unreadFor($userId),
            'last_message_at' => $this->last_message_at?->toISOString(),
        ];
    }
}
