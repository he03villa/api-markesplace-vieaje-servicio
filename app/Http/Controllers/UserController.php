<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\deleteAccountRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UpdateHasNotificationRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Notifications\PushNotification;
use App\Services\UserService;
use App\Traits\ApiResponseTrait;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenBlacklistedException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private UserService $userService
    ) {}

    #[OA\Get(
        path: '/api/auth/me',
        tags: ['Perfil'],
        summary: 'Obtener usuario autenticado',
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Datos del usuario autenticado',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 401, description: 'No autenticado',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function me()
    {
        $user = $this->userService->me();
        $user->load('reviews');
        $user->append('count_reviews');
        return $this->successResponse($user);
    }

    #[OA\Post(
        path: '/api/auth/logout',
        tags: ['Auth'],
        summary: 'Cerrar sesión',
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sesión cerrada exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 401, description: 'No autenticado',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function logout()
    {
        return $this->successResponse($this->userService->logout());
    }

    #[OA\Post(
        path: '/api/auth/refresh',
        tags: ['Auth'],
        summary: 'Refrescar token JWT',
        responses: [
            new OA\Response(response: 200, description: 'Token renovado',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'Operación exitosa'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/TokenResponse'),
                ])
            ),
            new OA\Response(response: 401, description: 'Token inválido o expirado',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function refresh()
    {
        try {
            return $this->successResponse($this->userService->refresh());
        } catch (TokenBlacklistedException $e) {
            return $this->unauthorizedResponse('Token ya utilizado, inicie sesión nuevamente');
        } catch (TokenExpiredException $e) {
            return $this->unauthorizedResponse('Token expirado, inicie sesión nuevamente');
        } catch (TokenInvalidException $e) {
            return $this->unauthorizedResponse('Token inválido');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Token no proporcionado o inválido');
        }
    }

    #[OA\Post(
        path: '/api/auth/register',
        tags: ['Auth'],
        summary: 'Registrar nuevo usuario',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RegisterRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Usuario registrado exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function register(Request $request)
    {
        try {
            $validate = $request->validate([
                'name' => 'required',
                'email' => 'required|email|unique:users',
                'password' => 'required|confirmed|min:8',
            ]);
            return $this->successResponse($this->userService->register($validate));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear la solicitud', 500);
        }
    }

    #[OA\Post(
        path: '/api/auth/login',
        tags: ['Auth'],
        summary: 'Iniciar sesión',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login exitoso, devuelve token JWT',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'Operación exitosa'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/TokenResponse'),
                ])
            ),
            new OA\Response(response: 401, description: 'Credenciales inválidas',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
        ]
    )]
    public function login(Request $request)
    {
        try {
            $validate = $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);
            return $this->successResponse($this->userService->login($validate));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (AuthenticationException $e) {
            return $this->unauthorizedResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear la solicitud', 500);
        }
    }

    #[OA\Post(
        path: '/api/auth/change-password',
        tags: ['Auth'],
        summary: 'Cambiar contraseña',
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'current_password', type: 'string', format: 'password'),
                new OA\Property(property: 'new_password', type: 'string', format: 'password'),
                new OA\Property(property: 'new_password_confirmation', type: 'string', format: 'password'),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Contraseña actualizada',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
        ]
    )]
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {

        try {
            $userId = auth('api')->id();
            $this->userService->changePassword(
                $userId,
                $request->current_password,
                $request->new_password
            );

            $user = \App\Models\User::find($userId);
            if ($user) {
                $user->notify(new PushNotification(
                    type: 'password_changed',
                    title: 'Contraseña actualizada',
                    body: 'Tu contraseña fue cambiada exitosamente. Si no realizaste este cambio, contacta al soporte.',
                    data: [],
                ));
            }

            return $this->successResponse(null, 'Contraseña actualizada exitosamente');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Error al cambiar contraseña: ' . $e->getMessage());
            return $this->errorResponse('Error al cambiar la contraseña', 500);
        }
    }

    #[OA\Post(
        path: '/api/email/verify/send',
        tags: ['Auth'],
        summary: 'Enviar correo de verificación',
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Correo enviado',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 422, description: 'Error al enviar',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function sendVerificationEmail(): JsonResponse
    {
        try {
            $this->userService->sendVerificationEmail(auth('api')->id());
            return $this->successResponse(null, 'Correo de verificación enviado');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Error al enviar verificación: ' . $e->getMessage());
            return $this->errorResponse('Error al enviar el correo', 500);
        }
    }

    public function verifyEmail(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $queryParams = $request->query();
        $signature = $queryParams['signature'] ?? null;
        unset($queryParams['signature']);

        $url = config('app.url') . '/api/email/verify/' . $id . '?' . http_build_query($queryParams);

        $expected = hash_hmac('sha256', $url, config('app.key'));

        if (!hash_equals($expected, $signature) || now()->getTimestamp() > (int) ($queryParams['expires'] ?? 0)) {
            return redirect(env('APP_DEEP_LINK') . '?status=invalid');
        }

        try {
            $this->userService->verifyEmail($id);

            $user = \App\Models\User::find($id);
            if ($user) {
                $user->notify(new PushNotification(
                    type: 'email_verified',
                    title: 'Correo verificado',
                    body: 'Tu correo electrónico fue verificado exitosamente.',
                    data: [],
                ));
            }

            return redirect(env('APP_DEEP_LINK') . '?status=success');
        } catch (\InvalidArgumentException $e) {
            return redirect(env('APP_DEEP_LINK') . '?status=already_verified');
        }
    }

    #[OA\Patch(
        path: '/api/auth/update-has-notification',
        tags: ['Auth'],
        summary: 'Actualizar preferencia de notificaciones',
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'has_notification', type: 'boolean', example: true),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Preferencia actualizada',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
        ]
    )]
    public function updateHasNotification(UpdateHasNotificationRequest $request): JsonResponse
    {
        try {
            $data = [
                'has_notification' => $request->has_notification
            ];
            $user = $this->userService->updateUser($request->user(), $data);
            return $this->successResponse($user, 'Notificaciones actualizadas');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar las notificaciones', 500);
        }
    }

    #[OA\Delete(
        path: '/api/auth/account',
        tags: ['Auth'],
        summary: 'Eliminar cuenta',
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'password', type: 'string', format: 'password'),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cuenta eliminada',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 422, description: 'Contraseña incorrecta',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function deleteAccount(deleteAccountRequest $request): JsonResponse
    {
        try {
            $this->userService->deleteAccount(auth('api')->id(), $request->password);

            return $this->successResponse(null, 'Cuenta eliminada exitosamente');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar la cuenta', 500);
        }
    }

    #[OA\Post(
        path: '/api/auth/forgot-password',
        tags: ['Auth'],
        summary: 'Solicitar código de recuperación',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Código enviado al correo',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 422, description: 'Email no encontrado',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $this->userService->sendResetPasswordOtp($request->email);
            return $this->successResponse(null, 'Código enviado a tu correo');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al enviar el correo', 500);
        }
    }

    #[OA\Post(
        path: '/api/auth/verify-otp',
        tags: ['Auth'],
        summary: 'Verificar código OTP',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'code', type: 'string', example: '123456'),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Código válido',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 422, description: 'Código inválido',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        try {
            $this->userService->verifyOtp($request->email, $request->code);
            return $this->successResponse(null, 'Código valido');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al enviar el correo', 500);
        }
    }

    #[OA\Post(
        path: '/api/auth/reset-password',
        tags: ['Auth'],
        summary: 'Restablecer contraseña con OTP',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'code', type: 'string', example: '123456'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Contraseña actualizada',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
        ]
    )]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $this->userService->resetPasswordWithOtp(
                $request->email,
                $request->code,
                $request->password
            );
            return $this->successResponse(null, 'Contraseña actualizada correctamente');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al enviar el correo', 500);
        }
    }
}
