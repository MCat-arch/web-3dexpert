<?php

namespace App\Http\Requests\Event;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the attendee details entered in the registration dialog.
 *
 * Email is deliberately NOT accepted from the request: it is the account
 * email and the dialog renders it read-only. The action always writes
 * $customer->email to attendee_email.
 */
class EnrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        // Keep the existing contract for unpublished events: 404, not a
        // validation redirect. This runs before the rules.
        abort_unless($event instanceof Event && $event->is_published, 404);

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'attendee_name' => ['required', 'string', 'min:2', 'max:100'],
            'attendee_phone' => [
                'required',
                'string',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $digits = preg_replace('/\D/', '', (string) $value);

                    if (! preg_match('/^[0-9+\s\-()]+$/', (string) $value)
                        || strlen((string) $digits) < 9
                        || strlen((string) $digits) > 15) {
                        $fail(__('events.error_phone'));
                    }
                },
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'attendee_name.required' => __('events.error_name_required'),
            'attendee_name.string' => __('events.error_name'),
            'attendee_name.min' => __('events.error_name'),
            'attendee_name.max' => __('events.error_name'),
            'attendee_phone.required' => __('events.error_phone_required'),
            'attendee_phone.string' => __('events.error_phone'),
            'attendee_phone.max' => __('events.error_phone'),
        ];
    }

    /** @return array{name: string, phone: string} */
    public function attendee(): array
    {
        return [
            'name' => trim((string) $this->validated('attendee_name')),
            'phone' => trim((string) $this->validated('attendee_phone')),
        ];
    }
}
