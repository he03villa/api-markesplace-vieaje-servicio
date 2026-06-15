<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('api')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'       => ['sometimes', 'string', 'max:50'],
            'title'      => ['sometimes', 'string', 'max:40'],
            'bio'        => ['sometimes', 'string', 'max:200'],
            'location'   => ['sometimes', 'string', 'max:100'],
            'phone'      => ['sometimes', 'string', 'max:20'],
            'avatar_url' => ['sometimes', 'url'],
            'avatar'     => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max'       => 'El nombre no puede superar los 50 caracteres.',
            'title.max'      => 'El título no puede superar los 40 caracteres.',
            'bio.max'        => 'La biografía no puede superar los 200 caracteres.',
            'avatar.image'   => 'El archivo debe ser una imagen.',
            'avatar.mimes'   => 'La imagen debe ser jpg, jpeg, png o webp.',
            'avatar.max'     => 'La imagen no puede superar los 2MB.',
            'avatar_url.url' => 'El avatar debe ser una URL válida.',
        ];
    }
}
