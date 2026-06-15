<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DriverStatsRequest extends FormRequest
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
            'period' => ['sometimes', 'string', 'in:week,month,year'],
        ];
    }
 
    public function messages(): array
    {
        return [
            'period.in' => 'El período debe ser: week, month o year.',
        ];
    }

    public function period(): string
    {
        return $this->input('period', 'month');
    }
 
    /**
     * Rango de fechas según el período seleccionado.
     * Devuelve [Carbon $from, Carbon $to]
     */
    public function dateRange(): array
    {
        $now = now();
 
        return match ($this->period()) {
            'week'  => [$now->copy()->startOfWeek(),  $now->copy()->endOfWeek()],
            'year'  => [$now->copy()->startOfYear(),  $now->copy()->endOfYear()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()], // month
        };
    }
 
    /**
     * Rango del período anterior (para calcular el % de cambio).
     */
    public function previousDateRange(): array
    {
        $now = now();
 
        return match ($this->period()) {
            'week'  => [
                $now->copy()->subWeek()->startOfWeek(),
                $now->copy()->subWeek()->endOfWeek(),
            ],
            'year'  => [
                $now->copy()->subYear()->startOfYear(),
                $now->copy()->subYear()->endOfYear(),
            ],
            default => [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ],
        };
    }
}
