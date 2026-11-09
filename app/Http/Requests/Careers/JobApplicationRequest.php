<?php

namespace App\Http\Requests\Careers;

use Illuminate\Foundation\Http\FormRequest;

class JobApplicationRequest extends FormRequest
{
    /** CV, in kilobytes. */
    public const MAX_CV_KB = 10240;

    /** Portfolio, in kilobytes. */
    public const MAX_PORTFOLIO_KB = 30720;

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
             *
             * Each field is a single file: the form no longer accepts a bag of
             * supporting documents, so there is nothing to iterate over.
             */
            'cv' => [
                'required',
                'file',
                'max:'.self::MAX_CV_KB,
                'mimes:pdf',
                'mimetypes:application/pdf',
            ],

            'portfolio' => [
                'required',
                'file',
                'max:'.self::MAX_PORTFOLIO_KB,
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
            'cv' => __('careers.cv'),
            'portfolio' => __('careers.portfolio'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cv.required' => __('careers.error_cv_required'),
            'cv.file' => __('careers.error_cv_pdf_only'),
            'cv.mimes' => __('careers.error_cv_pdf_only'),
            'cv.mimetypes' => __('careers.error_cv_pdf_only'),
            'cv.max' => __('careers.error_cv_too_large', ['mb' => self::MAX_CV_KB / 1024]),
            'cv.uploaded' => __('careers.error_cv_too_large', ['mb' => self::MAX_CV_KB / 1024]),

            'portfolio.required' => __('careers.error_portfolio_required'),
            'portfolio.file' => __('careers.error_portfolio_pdf_only'),
            'portfolio.mimes' => __('careers.error_portfolio_pdf_only'),
            'portfolio.mimetypes' => __('careers.error_portfolio_pdf_only'),
            'portfolio.max' => __('careers.error_portfolio_too_large', ['mb' => self::MAX_PORTFOLIO_KB / 1024]),
            'portfolio.uploaded' => __('careers.error_portfolio_too_large', ['mb' => self::MAX_PORTFOLIO_KB / 1024]),
        ];
    }
}
