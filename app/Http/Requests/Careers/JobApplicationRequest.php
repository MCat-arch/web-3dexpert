<?php

namespace App\Http\Requests\Careers;

use Illuminate\Foundation\Http\FormRequest;

class JobApplicationRequest extends FormRequest
{
    /** Per file, in kilobytes. */
    public const MAX_FILE_KB = 5120;

    public const MAX_FILES = 5;

    public function authorize(): bool
    {
        // Route is already behind auth:customer + verified.customer.
        // Eligibility (vacancy open, no active application) is enforced by
        // SubmitJobApplication, which re-checks it inside the transaction.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cover_letter' => ['nullable', 'string', 'max:5000'],

            // Links only. Restricting schemes avoids javascript:/data: URLs
            // being rendered as clickable links in the staff panel later.
            'portfolio_url' => ['nullable', 'string', 'max:255', 'url:http,https'],

            'documents' => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES],

            /*
             * BOTH mimes and mimetypes, deliberately.
             *
             * `mimes` checks the extension. `mimetypes` checks the sniffed
             * content type. The legacy upload checked neither -- it took
             * uniqid().'_'.$_FILES['gambar_utama']['name'] straight into a
             * web-served directory, which is how an uploaded .php executed
             * (finding 3.1-5).
             *
             * Checking only the extension lets through a PHP file named
             * cv.pdf. Checking only the MIME lets through a real PDF named
             * cv.php, which matters the moment this disk is ever misconfigured
             * as public. Both, or neither is worth much.
             */
            'documents.*' => [
                'file',
                'max:'.self::MAX_FILE_KB,
                'mimes:pdf',
                'mimetypes:application/pdf',
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'cover_letter' => __('careers.cover_letter'),
            'portfolio_url' => __('careers.portfolio_url'),
            'documents' => __('careers.documents'),
            'documents.*' => __('careers.document'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'documents.*.mimes' => __('careers.error_pdf_only'),
            'documents.*.mimetypes' => __('careers.error_pdf_only'),
            'documents.*.max' => __('careers.error_too_large', ['mb' => self::MAX_FILE_KB / 1024]),
            'documents.max' => __('careers.error_too_many', ['count' => self::MAX_FILES]),
        ];
    }
}
