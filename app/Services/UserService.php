<?php

namespace App\Services;

use App\Mail\ResetPasswordOtpMail;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class UserService
{
    public function getUser(int $userId): User
    {
        return User::findOrFail($userId);
    }

    public function register($data)
    {
        return User::create($data);
    }


    public function login($credentials)
    {
        if (! $token = auth('api')->attempt($credentials)) {
            throw new AuthenticationException("ivalidas credenciales");
        }

        return $this->respondWithToken($token);
    }

    public function me()
    {
        return auth('api')->user();
    }

    public function logout()
    {
        auth('api')->logout();

        return ['message' => 'Successfully logged out'];
    }

    public function refresh()
    {
        $newToken = auth('api')->refresh();
        auth('api')->setToken($newToken);
        return $this->respondWithToken($newToken);
    }

    protected function respondWithToken($token)
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $this->me()
        ];
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): void
    {
        $user = $this->getUser($userId);

        // Verificar que la contraseña actual sea correcta
        if (!password_verify($currentPassword, $user->password)) {
            throw new \InvalidArgumentException('La contraseña actual es incorrecta');
        }

        // Verificar que la nueva contraseña sea diferente
        if (password_verify($newPassword, $user->password)) {
            throw new \InvalidArgumentException('La nueva contraseña debe ser diferente a la actual');
        }

        $user->update(['password' => $newPassword]); // El cast 'hashed' en el modelo la encripta automáticamente
    }

    public function sendVerificationEmail(int $userId): void
    {
        $user = $this->getUser($userId);

        if ($user->email_verified_at) {
            throw new \InvalidArgumentException('El correo ya está verificado');
        }

        URL::forceRootUrl('http://localhost');
        // URL firmada que expira en 60 minutos
        $signedUrl = URL::temporarySignedRoute(
            'email.verify',
            now()->addMinutes(60),
            ['id' => $user->id]
        );

        $signedUrlForEmail = str_replace('http://localhost', config('app.url'), $signedUrl);

        Mail::to($user->email)->send(new VerifyEmailMail($signedUrlForEmail));
    }

    public function verifyEmail(int $userId): void
    {
        $user = $this->getUser($userId);

        if ($user->email_verified_at) {
            throw new \InvalidArgumentException('El correo ya está verificado');
        }

        $user->update(['email_verified_at' => now()]);
    }

    public function updateUser(User $user, array $data): User
    {
        $user->update($data);
        return $user;
    }

    public function deleteAccount(int $userId, string $password): void
    {
        $user = $this->getUser($userId);

        if (!password_verify($password, $user->password)) {
            throw new \InvalidArgumentException('La contraseña es incorrecta');
        }

        // Anonimizar datos personales antes del soft delete
        $user->update([
            'name'              => 'Usuario eliminado',
            'email'             => 'deleted_' . $userId . '_' . time() . '@deleted.com',
            'password'          => bcrypt(\Str::random(32)),
            'has_notification'  => false,
        ]);

        // Limpiar UserAbout
        $user->about()?->update([
            'phone'       => null,
            'avatar'      => null,
            'bio'         => null,
            'address'     => null,
            'birth_date'  => null,
            'gender'      => null,
            'occupation'  => null,
            'education'   => null,
            'interests'   => null,
            'languages'   => null,
            'social_links' => null,
        ]);

        // Cerrar sesión e invalidar token JWT
        auth('api')->logout();

        // Soft delete
        $user->delete();
    }

    public function sendResetPasswordOtp(string $email): void
    {
        User::where('email', $email)->firstOrFail();

        // Eliminar OTPs anteriores
        DB::table('password_reset_otps')->where('email', $email)->delete();

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_otps')->insert([
            'email'      => $email,
            'code'       => hash('sha256', $code),
            'attempts'   => 0,
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Mail::to($email)->send(new ResetPasswordOtpMail($code));
    }

    public function verifyOtp(string $email, string $code): void
    {
        $record = DB::table('password_reset_otps')
            ->where('email', $email)
            ->first();

        if (!$record) {
            throw new \InvalidArgumentException('Código inválido o expirado');
        }

        // Máximo 5 intentos fallidos
        if ($record->attempts >= 5) {
            DB::table('password_reset_otps')->where('email', $email)->delete();
            throw new \InvalidArgumentException('Demasiados intentos, solicita un nuevo código');
        }

        // Verificar expiración
        if (now()->isAfter($record->expires_at)) {
            DB::table('password_reset_otps')->where('email', $email)->delete();
            throw new \InvalidArgumentException('El código ha expirado');
        }

        // Verificar código
        if (!hash_equals($record->code, hash('sha256', $code))) {
            DB::table('password_reset_otps')
                ->where('email', $email)
                ->increment('attempts');
            throw new \InvalidArgumentException('Código incorrecto');
        }
    }

    public function resetPasswordWithOtp(string $email, string $code, string $newPassword): void
    {
        // Reutiliza la verificación
        $this->verifyOtp($email, $code);

        $user = User::where('email', $email)->firstOrFail();
        $user->update(['password' => $newPassword]);

        // Limpiar OTP usado
        DB::table('password_reset_otps')->where('email', $email)->delete();
    }
}
