<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeliveryController;

Route::middleware(['auth:api'])->group(function () {

    // Worker: Entregar trabajo con evidencia
    Route::post('/service-requests/{serviceRequest}/deliver', [DeliveryController::class, 'submit']);

    // Ver entrega de una solicitud (participantes)
    Route::get('/service-requests/{serviceRequest}/delivery', [DeliveryController::class, 'show']);

    // Cliente: Responder a la entrega (aprobar/rechazar/revision)
    Route::post('/deliveries/{delivery}/respond', [DeliveryController::class, 'respond']);

    // Worker: Mis entregas
    Route::get('/my-deliveries', [DeliveryController::class, 'myDeliveries']);

    // Cliente: Entregas pendientes de aprobacion
    Route::get('/pending-approvals', [DeliveryController::class, 'pendingApprovals']);

});
