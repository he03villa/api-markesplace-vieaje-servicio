<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryRequest extends FormRequest
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
            'completion_notes' => 'required|string|min:10|max:3000',
            'actual_hours'     => 'nullable|numeric|min:0|max:999',
            'evidence_images'  => 'required|array|min:1|max:5',
            'evidence_images.*'=> 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'evidence_docs'    => 'nullable|array|max:3',
            'evidence_docs.*'  => 'file|mimes:pdf,doc,docx|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'completion_notes.required' => 'El resumen del trabajo es obligatorio.',
            'completion_notes.min'      => 'El resumen debe tener al menos 10 caracteres.',
            'evidence_images.required'  => 'Debes subir al menos una foto de evidencia.',
            'evidence_images.min'       => 'Sube al menos una foto de evidencia.',
            'evidence_images.max'       => 'Maximo 5 imagenes permitidas.',
            'evidence_images.*.image'   => 'Cada archivo debe ser una imagen valida.',
            'evidence_images.*.max'     => 'Cada imagen no debe superar los 5MB.',
            'evidence_docs.*.mimes'     => 'Los documentos deben ser PDF, DOC o DOCX.',
            'evidence_docs.*.max'       => 'Cada documento no debe superar los 10MB.',
        ];
    }
}
