<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Message      $message,
        public Conversation $conversation,
    ) {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("conversation.{$this->conversation->id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id'              => $this->message->id,
            'conversation_id' => $this->conversation->id,
            'sender_id'       => $this->message->sender_id,
            'body'            => $this->message->body,
            'read_at'         => null,
            'created_at'      => $this->message->created_at->toISOString(),
            'sender' => [
                'id'     => $this->message->sender->id,
                'name'   => $this->message->sender->name,
                'avatar' => $this->message->sender->avatar_url ?? null,
            ],
            'attachments' => $this->message->attachments->map(fn($a) => [
                'id'               => $a->id,
                'type'             => $a->type,
                'url'              => $a->url,
                'original_name'    => $a->original_name,
                'mime_type'        => $a->mime_type,
                'size'             => $a->size,
                'human_size'       => $a->human_size,
                'width'            => $a->width,
                'height'           => $a->height,
                'duration_seconds' => $a->duration_seconds,
            ])->values()->all(),
            'conversation_preview' => [
                'last_message_at' => $this->conversation->last_message_at->toISOString(),
                'unread_a'        => $this->conversation->unread_a,
                'unread_b'        => $this->conversation->unread_b,
                'preview'         => $this->message->body
                    ?? ($this->message->attachments->first()
                        ? ucfirst($this->message->attachments->first()->type)
                        : null),
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
