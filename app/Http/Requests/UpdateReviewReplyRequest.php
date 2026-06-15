<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewReplyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $review = $this->route('review');
        return $this->user()->id === $review->reviewed_user_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'text' => 'required|string|min:10|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'text.required' => 'La respuesta no puede estar vacía.',
            'text.min'      => 'La respuesta debe tener al menos 10 caracteres.',
            'text.max'      => 'La respuesta no puede superar los 500 caracteres.',
        ];
    }
}
