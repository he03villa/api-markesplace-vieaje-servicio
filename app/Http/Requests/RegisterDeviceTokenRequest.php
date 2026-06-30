<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_token' => 'required|string',
            'platform' => 'sometimes|string|in:web,android,ios',
            'device_name' => 'sometimes|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'device_token.required' => 'El token del dispositivo es obligatorio.',
            'platform.in' => 'La plataforma debe ser web, android o ios.',
        ];
    }
}
