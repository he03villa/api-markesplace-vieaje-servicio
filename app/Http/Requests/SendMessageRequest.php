<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class SendMessageRequest extends FormRequest
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
        Log::info('Files recibidos:', array_map(fn($f) => [
            'name'     => $f->getClientOriginalName(),
            'mime'     => $f->getMimeType(),
            'client_mime' => $f->getClientMimeType(),
            'size'     => $f->getSize(),
        ], $this->file('files', [])));
        return [
            'receiver_id' => ['required', 'integer', 'exists:users,id', 'different:' . $this->user()->id],
            'body'        => ['nullable', 'string', 'max:1000'],
            'files'       => ['nullable', 'array', 'max:5'],
            'files.*'     => [
                'file',
                'max:20480',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,audio/mpeg,audio/ogg,audio/wav,audio/webm,audio/mp4,audio/aac,video/webm,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,text/plain',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'receiver_id.different' => 'No puedes enviarte mensajes a ti mismo.',
            'files.max'             => 'Máximo 5 archivos por mensaje.',
            'files.*.max'           => 'Cada archivo no puede superar 20 MB.',
            'files.*.mimetypes'     => 'Tipo de archivo no permitido.',
        ];
    }
 
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (! filled($this->input('body')) && ! $this->hasFile('files')) {
                $v->errors()->add('body', 'El mensaje debe tener texto o al menos un archivo adjunto.');
            }
        });
    }
}
