<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveDeliveryRequest extends FormRequest
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
            'action'   => 'required|in:approve,reject,revision',
            'feedback' => 'required_if:action,reject,revision|string|max:2000',
            'rating'   => 'required_if:action,approve|integer|min:1|max:5',
            'comment'  => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'action.required'      => 'La accion es obligatoria.',
            'action.in'              => 'La accion debe ser aprobar, rechazar o solicitar revision.',
            'feedback.required_if'   => 'Debes indicar el motivo del rechazo o las correcciones necesarias.',
            'rating.required_if'     => 'Debes calificar al worker al aprobar.',
            'rating.min'             => 'La calificacion minima es 1 estrella.',
            'rating.max'             => 'La calificacion maxima es 5 estrellas.',
        ];
    }
}
