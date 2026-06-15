<?php

use App\Http\Controllers\RideRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::prefix('rides')->group(function () {
        Route::get('/', [RideRequestController::class, 'index']);
        Route::post('/', [RideRequestController::class, 'store']);
        Route::get('/my-rides', [RideRequestController::class, 'myRidesPublication']);
        Route::get('/stats', [RideRequestController::class, 'stats']);
        Route::get('/{id}', [RideRequestController::class, 'show']);
        Route::post('/{id}/join', [RideRequestController::class, 'joinRide']);
        Route::post('/{id}/passengers/{passengerId}/confirm', [RideRequestController::class, 'confirmPassenger']);
        Route::post('/{id}/start', [RideRequestController::class, 'start']);
        Route::post('/{id}/pickup/{passengerId}', [RideRequestController::class, 'pickupPassenger']);
        Route::post('/{id}/dropoff/{passengerId}', [RideRequestController::class, 'dropoffPassenger']);
        Route::post('/{id}/complete', [RideRequestController::class, 'complete']);
        Route::post('/{id}/cancel', [RideRequestController::class, 'cancel']);
        Route::post('/{id}/rate', [RideRequestController::class, 'rate']);
    });
});

