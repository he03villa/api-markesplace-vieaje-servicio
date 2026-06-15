<?php

namespace App\Services;

use App\Http\Requests\StoreReviewReplyRequest;
use App\Http\Requests\UpdateReviewReplyRequest;
use App\Models\Review;
use App\Models\ReviewReply;

class ReviewReplyService {
    
    public function store(StoreReviewReplyRequest $request, Review $review) {
        if ($review->reply()->exists()) {
            throw new \Exception('Ya existe una respuesta a esta reseña');
        }

        $reply = $review->reply()->create([
            'user_id' => $request->user()->id,
            'text'    => $request->text,
        ]);

        return [
            'message' => 'Respuesta publicada.',
            'data'    => $this->formatReply($reply->load('author')),
        ];
    }

    public function update(UpdateReviewReplyRequest $request, Review $review) {
        $reply = $review->reply;
        if (!$reply) {
            throw new \Exception('No hay respuesta para editar.');
        }

        $reply->update(['text' => $request->text]);

        return [
            'message' => 'Respuesta actualizada.',
            'data'    => $this->formatReply($reply->load('author')),
        ];
    }

    public function destroy(Review $review) {
        $reply = $review->reply;
        if (!$reply) {
            throw new \Exception('No hay respuesta para eliminar.');
        }

        $reply->delete();

        return [
            'message' => 'Respuesta eliminada.',
        ];
    }

    private function formatReply($reply): array
    {
        return [
            'id'     => $reply->id,
            'author' => $reply->author->name,
            'avatar' => $reply->author->avatar_url,
            'text'   => $reply->text,
            'date'   => $reply->created_at->toDateString(),
        ];
    }
}