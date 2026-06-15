<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExplorePublicationsRequest extends FormRequest
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
            'q'            => 'nullable|string|max:100',
            'category'     => 'nullable|string|in:service,ride',
            'sub_category' => 'nullable|string|max:50',
            'status'       => 'nullable|string|in:active,in_progress,completed',
            'sort'         => 'nullable|string|in:recent,distance,rating,price_asc,price_desc,relevance',
            'lat'          => 'nullable|numeric|required_if:sort,distance',
            'lng'          => 'nullable|numeric|required_if:sort,distance',
            'per_page'     => 'nullable|integer|min:5|max:50',
        ];
    }
}
