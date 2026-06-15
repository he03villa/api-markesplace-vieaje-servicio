<?php

use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewReplyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api'])->group(function () {
    Route::prefix('reviews')->group(function () {

        Route::post('/', [ReviewController::class, 'store']);
        Route::get('/users/{userId}', [ReviewController::class, 'userReviews']);
        
        // Acciones sobre una review
        Route::post('/{review}/like',    [ReviewController::class, 'like']);
        Route::post('/{review}/helpful', [ReviewController::class, 'helpful']);
        Route::post('/{review}/report',  [ReviewController::class, 'report']);

        // Reply (solo el proveedor reseñado)
        Route::post('/{review}/reply',   [ReviewReplyController::class, 'store']);
        Route::put('/{review}/reply',    [ReviewReplyController::class, 'update']);
        Route::delete('/{review}/reply', [ReviewReplyController::class, 'destroy']);
    });
});