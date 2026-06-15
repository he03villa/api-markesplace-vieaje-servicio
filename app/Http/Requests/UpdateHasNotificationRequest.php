<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHasNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'has_notification' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'has_notification.required' => 'El campo "has_notification" es obligatorio.',
            'has_notification.boolean' => 'El campo "has_notification" debe ser un valor booleano.',
        ];
    }
}
