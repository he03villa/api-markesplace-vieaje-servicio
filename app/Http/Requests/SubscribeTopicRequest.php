<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'topic' => 'required|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'topic.required' => 'El tópico es obligatorio.',
        ];
    }
}
