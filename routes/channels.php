<?php

use App\Models\Conversation;
use App\Models\RideRequest;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

Broadcast::routes(['middleware' => ['auth:api'], 'prefix' => 'api']);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal privado para un servicio específico
Broadcast::channel('service.{serviceId}', function ($user, $serviceId) {
    $sr = \App\Models\ServiceRequest::find($serviceId);

    if (!$sr) return false;

    $isClient = $sr->user_id === $user->id;
    $isWorker = $sr->acceptedOffer?->user_id === $user->id;

    return $isClient || $isWorker;
});

// Canal del viaje — conductor y pasajeros confirmados pueden escuchar
Broadcast::channel('ride.{rideId}', function (User $user, int $rideId) {
    $ride = RideRequest::find($rideId);

    if (!$ride) return false;

    $isDriver    = $ride->driver_id === $user->id;
    $isPassenger = $ride->passengers()->where('user_id', $user->id)->exists();

    return $isDriver || $isPassenger;
});

// Canal personal del usuario — solo él mismo puede escuchar
Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});

Broadcast::channel('conversation.{conversationId}', function ($user, int $conversationId) {
    Log::info('channel conversation', ['user' => $user->id, 'conversation' => $conversationId]);
    return Conversation::where('id', $conversationId)
        ->where(fn ($q) => $q
            ->where('user_a_id', $user->id)
            ->orWhere('user_b_id', $user->id)
        )
        ->exists();
});