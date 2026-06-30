<?php

use App\Http\Controllers\FCMController;
use App\Http\Controllers\MyAssignmentsController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicationsController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RideRequestController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::group(['prefix' => 'auth'], function () {
    Route::post('/register', [UserController::class, 'register']);
    Route::post('/login', [UserController::class, 'login']);
    Route::post('/logout', [UserController::class, 'logout']);
    Route::post('/refresh', [UserController::class, 'refresh']);
    Route::post('/forgot-password', [UserController::class, 'forgotPassword']);
    Route::post('/verify-otp',      [UserController::class, 'verifyOtp']);
    Route::post('/reset-password',  [UserController::class, 'resetPassword']);
    Route::patch('/update-has-notification', [UserController::class, 'updateHasNotification']);
    Route::delete('/account', [UserController::class, 'deleteAccount']);
});

Route::get('email/verify/{id}', [UserController::class, 'verifyEmail'])->name('email.verify');

Route::middleware('auth:api')->group(function () {
    Route::get('auth/me', [UserController::class, 'me']);
    Route::post('auth/change-password', [UserController::class, 'changePassword']);
    Route::post('email/verify/send', [UserController::class, 'sendVerificationEmail']);

    // Publicaciones
    Route::get('/my-publications', [PublicationsController::class, 'index']);
    Route::get('/my-publications/stats', [PublicationsController::class, 'stats']);
    Route::get('/my-publications/summary', [PublicationsController::class, 'summary']);
    Route::get('/my-publications/explore', [PublicationsController::class, 'explore']);

    // Perfil de usuario
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::post('/', [ProfileController::class, 'update']);
    });
    
    // Búsqueda de usuarios (solo para admin o usuarios verificados)
    Route::get('/users/search', [UserProfileController::class, 'search'])
        ->middleware('can:search_users');
    
    // Solicitudes de servicio
    Route::prefix('service-requests')->group(function () {
        Route::get('/', [ServiceRequestController::class, 'index']);
        Route::post('/', [ServiceRequestController::class, 'store']);
        Route::get('/my-requests', [ServiceRequestController::class, 'myServiceRequests']);
        Route::get('/{id}', [ServiceRequestController::class, 'show']);
        Route::put('/{id}', [ServiceRequestController::class, 'update']);
        Route::patch('/{id}/status', [ServiceRequestController::class, 'transitionStatus']);
    });
    
    // Ofertas
    Route::prefix('offers')->group(function () {
        Route::post('/', [OfferController::class, 'store']);
        Route::post('/{id}/accept', [OfferController::class, 'accept']);
        Route::get('/my-offers', [OfferController::class, 'myOffers']);
    });

    Route::get('users/{user}/reviews',  [ReviewController::class, 'index']);
    Route::post('users/{user}/reviews', [ReviewController::class, 'store']);
    
    // Todas las asignaciones (combinado)
    Route::get('/my-assignments', [MyAssignmentsController::class, 'index']);
    
    // Filtradas por tipo
    Route::get('/my-assignments/services', [MyAssignmentsController::class, 'services']);
    Route::get('/my-assignments/rides/driver', [MyAssignmentsController::class, 'ridesAsDriver']);
    Route::get('/my-assignments/rides/passenger', [MyAssignmentsController::class, 'ridesAsPassenger']);

    // FCM Device Tokens
    Route::prefix('auth/device-token')->group(function () {
        Route::post('/', [FCMController::class, 'register']);
        Route::delete('/', [FCMController::class, 'unregister']);
        Route::get('/', [FCMController::class, 'listTokens']);
        Route::post('/subscribe', [FCMController::class, 'subscribeToTopic']);
        Route::post('/unsubscribe', [FCMController::class, 'unsubscribeFromTopic']);
    });

    // FCM Test Notification
    Route::post('/auth/notifications/test/{user}', [FCMController::class, 'testNotification']);
});

require __DIR__ . '/api_deliveries.php';
// Viajes compartidos
require __DIR__ . '/api_rides.php';
// Chats
require __DIR__ . '/api_chats.php';
// Reseñas
require __DIR__ . '/api_reviews.php';
