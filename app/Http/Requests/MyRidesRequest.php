<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MyRidesRequest extends FormRequest
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
            // Tab activo en la vista
            'status'   => ['nullable', 'string', 'in:upcoming,completed,cancelled,in-progress,all'],
 
            // Búsqueda por texto (origen, destino)
            'search'   => ['nullable', 'string', 'max:100'],
 
            // Ordenamiento
            'sort'     => ['nullable', 'string', 'in:recent,date_asc,date_desc,price_high,price_low,passengers'],
 
            // Paginación
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function status(): ?string
    {
        $s = $this->input('status', 'all');
        return $s === 'all' ? null : $s;
    }
 
    public function sort(): string
    {
        return $this->input('sort', 'date_desc');
    }
 
    public function perPage(): int
    {
        return (int) $this->input('per_page', 10);
    }
}
