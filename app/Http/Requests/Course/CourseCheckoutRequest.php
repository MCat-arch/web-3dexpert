<?php

namespace App\Http\Requests\Course;

use Illuminate\Foundation\Http\FormRequest;

class CourseCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('customer')->check();
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('payment_method') || blank($this->payment_method)) {
            $this->merge([
                'payment_method' => 'qris',
            ]);
        }
    }

    public function rules(): array
    {
        $course = $this->route('course');
        $isFree = $course && $course->isFree();

        return [
            'payment_method' => $isFree ? 'nullable|string' : 'required|string|in:qris,bca_va,bni_va,mandiri_va,gopay,shopeepay',
            'student_name' => 'nullable|string|max:150',
            'student_phone' => 'nullable|string|max:25',
        ];
    }
}
