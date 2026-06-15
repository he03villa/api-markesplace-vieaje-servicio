<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->id !== (int) $this->route('user')->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:10|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required'  => 'La calificación es obligatoria.',
            'rating.between'   => 'La calificación debe estar entre 1 y 5.',
            'comment.required' => 'El comentario es obligatorio.',
            'comment.min'      => 'El comentario debe tener al menos 10 caracteres.',
        ];
    }
}
