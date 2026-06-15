<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\deleteAccountRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UpdateHasNotificationRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Services\UserService;
use App\Traits\ApiResponseTrait;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private UserService $userService
    ) {}

    public function me()
    {
        $user = $this->userService->me();
        $user->load('reviews');
        $user->append('count_reviews');
        return $this->successResponse($user);
    }

    public function logout()
    {
        return $this->successResponse($this->userService->logout());
    }

    public function refresh()
    {
        return $this->successResponse($this->userService->refresh());
    }

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
            return $this->notFoundResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear la solicitud', 500);
        }
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {

        try {
            $this->userService->changePassword(
                auth('api')->id(),
                $request->current_password,
                $request->new_password
            );

            return $this->successResponse(null, 'Contraseña actualizada exitosamente');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Error al cambiar contraseña: ' . $e->getMessage());
            return $this->errorResponse('Error al cambiar la contraseña', 500);
        }
    }

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
        // Laravel valida automáticamente la firma
        if (!$request->hasValidSignature()) {
            return redirect(env('APP_DEEP_LINK') . '?status=invalid');
        }

        try {
            $this->userService->verifyEmail($id);
            // Deep link a la app — cuando configures Ionic será: tuapp://email-verified?status=success
            return redirect(env('APP_DEEP_LINK') . '?status=success');
        } catch (\InvalidArgumentException $e) {
            return redirect(env('APP_DEEP_LINK') . '?status=already_verified');
        }
    }

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
