<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('customer')->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'submitted_text' => ['nullable', 'string', 'max:10000'],
            'file' => [
                'nullable',
                'file',
                'max:20480',
                'mimes:pdf,zip,jpg,jpeg,png',
                'mimetypes:application/pdf,application/zip,application/x-zip-compressed,image/jpeg,image/png',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (blank((string) $this->input('submitted_text')) && ! $this->hasFile('file')) {
                    $validator->errors()->add('file', 'Isi teks atau unggah berkas tugas.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'Berkas tugas maksimal 20 MB.',
            'file.mimes' => 'Berkas harus PDF, ZIP, JPG, atau PNG.',
            'file.mimetypes' => 'Tipe berkas tidak didukung.',
        ];
    }
}
