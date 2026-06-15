<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::prefix('chat')->middleware('auth:api')->group(function () {
 
    // Conversations
    Route::get('conversations',                        [ChatController::class, 'conversations']);
    Route::get('conversations/{conversation}',         [ChatController::class, 'showConversation']);
    Route::patch('conversations/{conversation}/read',  [ChatController::class, 'markAsRead']);
    Route::get('conversations/{userId}/users',         [ChatController::class, 'showConversationUsers']);
 
    // Messages
    Route::get('conversations/{conversation}/messages',[ChatController::class, 'messages']);
    Route::post('messages',                            [ChatController::class, 'sendMessage']);
    Route::delete('messages/{message}',                [ChatController::class, 'destroyMessage']);
 
    // Attachments
    Route::delete('attachments/{attachment}',          [ChatController::class, 'destroyAttachment']);
 
    // Typing
    Route::post('typing',                              [ChatController::class, 'typing']);

    // Users
});