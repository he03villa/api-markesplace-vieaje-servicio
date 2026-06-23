<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Auth\AuthenticationException;
use App\Exceptions\BusinessLogicException;
use Throwable;

class Handler extends ExceptionHandler
{
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        if ($request->is('api/*')) {
            return $this->handleApiException($request, $exception);
        }

        return parent::render($request, $exception);
    }

    private function handleApiException($request, Throwable $exception)
    {
        if ($exception instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $exception->errors(),
            ], 422);
        }

        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Recurso no encontrado',
            ], 404);
        }

        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint no encontrado',
            ], 404);
        }

        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
            ], 401);
        }

        if ($exception instanceof AccessDeniedHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado',
            ], 403);
        }

        if ($exception instanceof BusinessLogicException) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 400);
        }

        if ($exception instanceof \Illuminate\Database\QueryException) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la base de datos',
                'error' => config('app.debug') ? $exception->getMessage() : null,
            ], 500);
        }

        return response()->json([
            'success' => false,
            'message' => 'Error interno del servidor',
            'error' => config('app.debug') ? $exception->getMessage() : null,
        ], 500);
    }
}
