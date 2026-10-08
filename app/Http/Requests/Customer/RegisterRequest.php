<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public route; the throttle middleware is the only gate. See the
        // rate-limiter note in AppServiceProvider: registration is NOT
        // throttled by IP alone (D17).
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:customers,email'],
            // Indonesian mobile numbers, stored normalised by prepareForValidation.
            'phone' => ['nullable', 'string', 'regex:/^[0-9]{9,15}$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => __('customer.name'),
            'email' => __('customer.email'),
            'phone' => __('customer.phone'),
            'password' => __('customer.password'),
        ];
    }

    /**
     * Normalises the phone number before validation so one person cannot hold
     * two accounts that differ only by punctuation, and so P7's progress-tracker
     * lookup can compare numbers directly rather than fuzzily.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $digits = preg_replace('/\D/', '', (string) $this->input('phone'));

            // +62 / 62 / 0 all mean the same leading national prefix.
            $digits = preg_replace('/^(62|0)/', '0', (string) $digits);

            $this->merge(['phone' => $digits]);
        }
    }
}
